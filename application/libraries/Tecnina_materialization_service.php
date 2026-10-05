<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Authoritative OS Materialization Service for MapOS (CIAO-S07).
 *
 * Governed by:
 * - CIAO-S07 / stages/S07-OS-CONVERSION-AND-DATA-TRANSFER.md
 * - S01-ADR-002 (Pending Registration Credential & Zero Hash-on-Hash)
 * - S01-ADR-004 (Physical Receiving OS Gate)
 * - S01-ADR-005 (Attachments, GPS & Structured OS Annotations)
 * - S01-DATA-MODEL & S01-MATERIALIZATION-READINESS
 */
class Tecnina_materialization_service
{
    public const CONTRACT_VERSION = 'S01-2026-09';

    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->database();
        $this->CI->load->library('Tecnina_bot_gateway');
        $this->CI->load->library('Tecnina_readiness_service');
        $this->CI->load->library('Tecnina_attachment_storage');
        $this->CI->load->model('Tecnina_receiving_model');
        $this->CI->load->library('Tecnina_phone');
    }

    /**
     * Convert a ready, physically received pre-attendance into MapOS client and OS records.
     *
     * @param string $intakeId UUIDv4 of the pre-attendance
     * @param int $operatorId MapOS authenticated staff ID
     * @param array $options Optional client resolution parameters (client_action, client_id, force_create_new)
     * @param array|null $injectedSnapshot Optional pre-fetched or simulated snapshot for testing/isolation
     * @return array Result of materialization
     */
    public function materialize(string $intakeId, int $operatorId, array $options = [], ?array $injectedSnapshot = null): array
    {
        if (! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $intakeId)) {
            return ['ok' => false, 'reason' => 'invalid_intake_id'];
        }

        if ($operatorId <= 0) {
            return ['ok' => false, 'reason' => 'invalid_operator'];
        }

        // Verify operator is active in MapOS usuarios
        $operator = $this->CI->db->select('idUsuarios, nome')
            ->from('usuarios')
            ->where('idUsuarios', $operatorId)
            ->where('situacao', 1)
            ->limit(1)
            ->get()
            ->row_array();
        if (! $operator) {
            return ['ok' => false, 'reason' => 'invalid_operator'];
        }

        // Check if already materialized (Idempotency)
        $existingApproval = $this->CI->db->select('*')
            ->from('tecnina_intake_approvals')
            ->where('intake_id', $intakeId)
            ->limit(1)
            ->get()
            ->row_array();

        if ($existingApproval && $existingApproval['state'] === 'COMPLETED') {
            return [
                'ok' => true,
                'result' => 'already_completed',
                'materialization_id' => (int) $existingApproval['id'],
                'client_id' => (int) $existingApproval['client_id'],
                'os_id' => (int) $existingApproval['os_id'],
                'client_created' => (bool) $existingApproval['client_created'],
                'snapshot_version' => (int) $existingApproval['snapshot_version'],
                'snapshot_hash' => $existingApproval['snapshot_hash'],
                'attachment_sync_state' => $existingApproval['attachment_sync_state'],
                'bot_sync_state' => $existingApproval['bot_sync_state'],
            ];
        }

        // 1. Evaluate Readiness Gate (S06B)
        $readiness = $this->CI->tecnina_readiness_service->evaluateReadiness($intakeId, $injectedSnapshot);
        if (! $readiness['ready']) {
            return [
                'ok' => false,
                'reason' => 'materialization_blocked',
                'blocking_reasons' => $readiness['blocking_reasons'],
                'readiness' => $readiness,
            ];
        }

        // 2. Obtain Authoritative Sealed Snapshot
        if ($injectedSnapshot !== null) {
            $snapshot = $injectedSnapshot;
        } else {
            $snapResp = $this->CI->tecnina_bot_gateway->fetchMaterializationSnapshot($intakeId);
            if (! $snapResp['ok'] || ! isset($snapResp['data'])) {
                return ['ok' => false, 'reason' => $snapResp['reason'] ?? 'snapshot_unavailable'];
            }
            $snapshot = $snapResp['data'];
        }

        $snapshotVersion = (int) ($snapshot['snapshot_version'] ?? 0);
        $snapshotHash = (string) ($snapshot['snapshot_hash'] ?? '');
        $intakeVersion = (int) ($snapshot['intake_version'] ?? 0);
        $sealExpiresAt = $snapshot['seal_expires_at'] ?? null;

        if (empty($snapshotHash) || $snapshotVersion < 1) {
            return ['ok' => false, 'reason' => 'invalid_snapshot'];
        }

        if ($sealExpiresAt !== null && time() >= strtotime($sealExpiresAt)) {
            return ['ok' => false, 'reason' => 'snapshot_stale'];
        }

        $snapData = is_array($snapshot['data'] ?? null) ? $snapshot['data'] : $snapshot;

        // 3. Confirm Physical Receiving State (ADR-004)
        $receiving = $this->CI->Tecnina_receiving_model->getReceiving($intakeId);
        if (! $receiving || $receiving['state'] !== 'RECEIVED') {
            return ['ok' => false, 'reason' => 'physical_receipt_required'];
        }

        // 4. Begin Local Atomic Transaction
        $this->CI->db->trans_begin();
        $nowUtc = gmdate('Y-m-d H:i:s');

        try {
            // Determine Client Resolution (Create New vs Link Existing)
            $clientAction = $options['client_action'] ?? null;
            $matchedClientId = null;

            if ($clientAction === null) {
                if (! empty($snapData['possible_mapos_client_id'])) {
                    $matchedClientId = (int) $snapData['possible_mapos_client_id'];
                    $clientAction = 'LINK_EXISTING';
                } elseif (($snapData['registration_choice'] ?? '') === 'LINK_EXISTING' && ! empty($readiness['matched_client_id'])) {
                    $matchedClientId = (int) $readiness['matched_client_id'];
                    $clientAction = 'LINK_EXISTING';
                } else {
                    $clientAction = 'CREATE_NEW';
                }
            }

            $clientCreated = false;
            $clientId = null;

            if ($clientAction === 'LINK_EXISTING') {
                $clientId = (int) ($options['client_id'] ?? $matchedClientId ?? 0);
                if ($clientId <= 0) {
                    throw new RuntimeException('existing_client_required');
                }

                $existingClient = $this->CI->db->get_where('clientes', ['idClientes' => $clientId])->row_array();
                if (! $existingClient) {
                    throw new RuntimeException('existing_client_not_found');
                }

                // Existing client credentials and details remain strictly untouched!
                $clientCreated = false;
            } else {
                // CREATE NEW CUSTOMER
                $clientName = trim((string) ($snapData['name'] ?? ''));
                if (empty($clientName)) {
                    throw new RuntimeException('client_name_required');
                }

                $phoneCanonical = $snapData['phone_canonical'] ?? '';
                $storagePhone = $this->CI->tecnina_phone->storageValueFromCanonical($phoneCanonical) ?: $phoneCanonical;

                // ADJ-018: Zero synthetic email. If absent, store empty string ''.
                $rawEmail = trim((string) ($snapData['registration_email'] ?? ''));
                $email = $rawEmail !== '' ? $rawEmail : '';

                // ADJ-024 / ADR-002: Zero Hash-on-Hash credential integrity!
                // Copy approved opaque pending password hash DIRECTLY into clientes.senha.
                // Absolutely NO password_hash() call over the existing hash!
                $accountHash = $snapData['account_password_hash'] ?? null;
                if (! empty($accountHash)) {
                    $finalPasswordHash = $accountHash; // VERBATIM COPY
                } else {
                    // Deferred registration or absent password: generate cryptographically secure hash
                    $finalPasswordHash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
                }

                // Registered address (ADJ-015: distinct from pickup address)
                $newClientData = [
                    'nomeCliente' => mb_substr($clientName, 0, 255),
                    'pessoa_fisica' => 1,
                    'documento' => mb_substr(trim((string) ($snapData['registration_cpf'] ?? '')), 0, 20),
                    'telefone' => $storagePhone,
                    'celular' => $storagePhone,
                    'email' => mb_substr($email, 0, 100),
                    'senha' => $finalPasswordHash,
                    'rua' => isset($snapData['registration_street']) ? mb_substr(trim((string) $snapData['registration_street']), 0, 160) : null,
                    'numero' => isset($snapData['registration_street_number']) ? mb_substr(trim((string) $snapData['registration_street_number']), 0, 32) : null,
                    'bairro' => isset($snapData['registration_neighborhood']) ? mb_substr(trim((string) $snapData['registration_neighborhood']), 0, 120) : null,
                    'cidade' => isset($snapData['registration_city']) ? mb_substr(trim((string) $snapData['registration_city']), 0, 80) : null,
                    'estado' => isset($snapData['registration_address_state']) ? mb_substr(trim((string) $snapData['registration_address_state']), 0, 20) : null,
                    'cep' => isset($snapData['registration_postal_code']) ? mb_substr(trim((string) $snapData['registration_postal_code']), 0, 20) : null,
                    'complemento' => isset($snapData['registration_complement']) ? mb_substr(trim((string) $snapData['registration_complement']), 0, 160) : null,
                    'dataCadastro' => date('Y-m-d'),
                    'fornecedor' => 0,
                ];

                $this->CI->db->insert('clientes', $newClientData);
                $clientId = (int) $this->CI->db->insert_id();
                if ($clientId <= 0) {
                    throw new RuntimeException('client_insert_failed');
                }
                $clientCreated = true;

                // MapOS Identity Authority (tecnina_client_identity)
                $identityPhone = $this->CI->tecnina_phone->normalizeCanonicalIdentity($phoneCanonical)
                    ?: preg_replace('/\D+/', '', (string) $phoneCanonical);
                $identityPhone = $identityPhone !== '' ? $identityPhone : null;

                $this->CI->db->insert('tecnina_client_identity', [
                    'client_id' => $clientId,
                    'canonical_phone' => $identityPhone,
                    'phone_state' => 'VERIFIED',
                    'phone_confirmed_at' => $nowUtc,
                    'email_candidate' => $email !== '' ? $email : null,
                    'email_state' => $email !== '' ? 'PENDING' : 'NONE',
                    'credential_version' => 1,
                ]);

                // MapOS Client Profile (tecnina_client_profile)
                $birthDate = ! empty($snapData['birth_date']) ? $snapData['birth_date'] : null;
                $addressRef = ! empty($snapData['registration_reference']) ? mb_substr(trim((string) $snapData['registration_reference']), 0, 255) : null;
                if ($birthDate !== null || $addressRef !== null) {
                    $this->CI->db->insert('tecnina_client_profile', [
                        'client_id' => $clientId,
                        'birth_date' => $birthDate,
                        'address_reference' => $addressRef,
                    ]);
                }
            }

            // OS CREATION
            $deviceParts = array_filter([
                $snapData['device_type'] ?? null,
                $snapData['brand'] ?? null,
                $snapData['model'] ?? null,
            ]);
            $deviceDescription = ! empty($deviceParts) ? implode(' ', $deviceParts) : 'Equipamento não especificado';
            $problemDescription = ! empty($snapData['problem_description']) ? $snapData['problem_description'] : 'Defeito informado no pré-atendimento';

            $dataInicial = date('Y-m-d');
            $dataFinal = date('Y-m-d', strtotime('+7 days'));

            $osRecord = [
                'dataInicial' => $dataInicial,
                'dataFinal' => $dataFinal,
                'garantia' => '0',
                'descricaoProduto' => mb_substr($deviceDescription, 0, 255),
                'defeito' => $problemDescription,
                'status' => 'Aberto',
                'observacoes' => isset($snapData['notes']) ? mb_substr(trim((string) $snapData['notes']), 0, 1000) : null,
                'laudoTecnico' => null,
                'credencial_tipo' => 'nao_informada',
                'clientes_id' => $clientId,
                'usuarios_id' => $operatorId,
                'faturado' => 0,
            ];

            $this->CI->db->insert('os', $osRecord);
            $osId = (int) $this->CI->db->insert_id();
            if ($osId <= 0) {
                throw new RuntimeException('os_insert_failed');
            }

            // STRUCTURED OS ANNOTATIONS (anotacoes_os)
            $annotations = [];

            // 1. Origin attribution
            $annotations[] = '[Pré-atendimento] Intake #' . $intakeId . ' convertido por operador #' . $operatorId . '.';

            // 2. Receiving condition & accessories (ADJ-003, ADJ-004)
            $cond = ! empty($receiving['device_condition']) ? $receiving['device_condition'] : 'Não especificada';
            $acc = ! empty($receiving['accessories']) ? $receiving['accessories'] : 'Nenhum';
            $annotations[] = '[Recebimento] Condição: ' . $cond . ' | Acessórios: ' . mb_substr($acc, 0, 150);

            // 3. Serial & IMEI
            $serial = ! empty($receiving['serial_number']) ? $receiving['serial_number'] : 'N/I';
            $imei = ! empty($receiving['imei']) ? $receiving['imei'] : 'N/I';
            $annotations[] = '[Recebimento] Serial: ' . $serial . ' | IMEI: ' . $imei;

            // 4. Other identifiers (if present)
            if (! empty($receiving['other_identifiers'])) {
                $annotations[] = '[Recebimento] Outros IDs: ' . mb_substr($receiving['other_identifiers'], 0, 200);
            }

            // 5. Receiving notes (if present)
            if (! empty($receiving['notes'])) {
                $annotations[] = '[Recebimento] Notas: ' . mb_substr($receiving['notes'], 0, 210);
            }

            // 6. Pickup address (if service_mode == PICKUP_REQUESTED)
            if (($snapData['service_mode'] ?? '') === 'PICKUP_REQUESTED') {
                $pStreet = $snapData['pickup_street'] ?? '';
                $pNum = $snapData['pickup_street_number'] ?? '';
                $pNeigh = $snapData['pickup_neighborhood'] ?? '';
                $pCity = $snapData['pickup_city'] ?? '';
                $pCep = $snapData['pickup_postal_code'] ?? '';
                $annotations[] = '[Coleta] Endereço: ' . mb_substr("{$pStreet}, {$pNum} — {$pNeigh} — {$pCity} CEP {$pCep}", 0, 230);
            }

            // 7. Pickup GPS & Google Maps link (ADJ-035, ADR-005)
            $locRow = $this->CI->db->get_where('tecnina_intake_locations', ['intake_id' => $intakeId])->row_array();
            $origLat = $locRow['original_latitude'] ?? $snapData['gps_latitude'] ?? null;
            $origLon = $locRow['original_longitude'] ?? $snapData['gps_longitude'] ?? null;
            $origAcc = $locRow['original_accuracy_meters'] ?? $snapData['gps_accuracy_meters'] ?? null;
            $adjLat = $locRow['adjusted_latitude'] ?? null;
            $adjLon = $locRow['adjusted_longitude'] ?? null;
            $primaryCoord = $locRow['primary_coordinate'] ?? 'ORIGINAL';

            if ($origLat !== null && $origLon !== null) {
                $accStr = $origAcc !== null ? " (precisão: {$origAcc}m)" : '';
                $annotations[] = "[GPS Coleta - Original] Lat: {$origLat}, Lon: {$origLon}{$accStr}";
            }
            if ($adjLat !== null && $adjLon !== null) {
                $annotations[] = "[GPS Coleta - Ajustado] Lat: {$adjLat}, Lon: {$adjLon}";
            }

            $primLat = ($primaryCoord === 'ADJUSTED' && $adjLat !== null) ? $adjLat : $origLat;
            $primLon = ($primaryCoord === 'ADJUSTED' && $adjLon !== null) ? $adjLon : $origLon;
            if ($primLat !== null && $primLon !== null) {
                $annotations[] = "[GPS Coleta] https://www.google.com/maps?q={$primLat},{$primLon}";
            }

            // Insert annotations (max 255 chars each per anotacoes_os schema)
            foreach ($annotations as $annText) {
                $this->CI->db->insert('anotacoes_os', [
                    'anotacao' => mb_substr(trim($annText), 0, 255),
                    'data_hora' => $nowUtc,
                    'os_id' => $osId,
                ]);
            }

            // INTAKE APPROVAL RECORD (tecnina_intake_approvals)
            $requestHash = hash('sha256', $intakeId . ':' . $snapshotHash . ':' . $osId . ':' . $clientId);
            $approvalData = [
                'intake_id' => $intakeId,
                'request_hash' => $requestHash,
                'state' => 'COMPLETED',
                'client_id' => $clientId,
                'os_id' => $osId,
                'client_created' => $clientCreated ? 1 : 0,
                'completed_at' => $nowUtc,
                'intake_version' => $intakeVersion,
                'snapshot_version' => $snapshotVersion,
                'snapshot_hash' => $snapshotHash,
                'snapshot_fetched_at' => $nowUtc,
                'seal_expires_at' => $sealExpiresAt ? gmdate('Y-m-d H:i:s', strtotime($sealExpiresAt)) : null,
                'readiness_result' => json_encode($readiness),
                'readiness_contract_version' => self::CONTRACT_VERSION,
                'attachment_sync_state' => 'PENDING',
                'bot_sync_state' => 'PENDING',
                'bot_finalize_attempts' => 0,
                'last_error_code' => null,
            ];

            if ($existingApproval) {
                $this->CI->db->where('intake_id', $intakeId)->update('tecnina_intake_approvals', $approvalData);
                $approvalId = (int) $existingApproval['id'];
            } else {
                $this->CI->db->insert('tecnina_intake_approvals', $approvalData);
                $approvalId = (int) $this->CI->db->insert_id();
            }

            if ($this->CI->db->trans_status() === false) {
                throw new RuntimeException('materialization_db_transaction_failed');
            }

            // Commit atomic transaction
            $this->CI->db->trans_commit();
        } catch (Throwable $e) {
            $this->CI->db->trans_rollback();
            log_message('error', 'Tecnina Materialization Failed for intake ' . $intakeId . ': ' . $e->getMessage());
            return [
                'ok' => false,
                'reason' => 'materialization_failed',
                'detail' => $e->getMessage(),
            ];
        }

        // 5. Post-Commit Attachment Promotion (ADR-005)
        $attachmentSyncState = 'COMPLETED';
        $stagedAttachments = $this->CI->db
            ->select('id')
            ->from('tecnina_pre_os_attachments')
            ->where('intake_id', $intakeId)
            ->where_in('state', ['STAGED', 'PROMOTION_PENDING'])
            ->get()
            ->result_array();

        if (! empty($stagedAttachments)) {
            $promotedCount = 0;
            foreach ($stagedAttachments as $att) {
                $promotedId = $this->CI->tecnina_attachment_storage->promoteAttachment((int) $att['id'], $osId);
                if ($promotedId !== null) {
                    $promotedCount++;
                }
            }
            $attachmentSyncState = ($promotedCount === count($stagedAttachments)) ? 'COMPLETED' : 'PARTIAL';
        }

        $this->CI->db->where('id', $approvalId)->update('tecnina_intake_approvals', [
            'attachment_sync_state' => $attachmentSyncState,
        ]);

        // 6. Post-Commit Bot Finalization (ADR-002 Password Hash Purge)
        $botSyncState = 'PENDING';
        try {
            $finalizePayload = [
                'materialization_id' => (string) $approvalId,
                'client_id' => $clientId,
                'os_id' => $osId,
                'snapshot_version' => $snapshotVersion,
                'snapshot_hash' => $snapshotHash,
            ];
            $finResp = $this->CI->tecnina_bot_gateway->finalizeMaterialization($intakeId, $finalizePayload);
            if ($finResp['ok']) {
                $botSyncState = 'COMPLETED';
            } else {
                $botSyncState = 'FAILED_RETRYABLE';
            }
        } catch (Throwable $e) {
            $botSyncState = 'FAILED_RETRYABLE';
            log_message('error', 'Tecnina Bot Finalization Error for intake ' . $intakeId . ': ' . $e->getMessage());
        }

        $this->CI->db->where('id', $approvalId)->update('tecnina_intake_approvals', [
            'bot_sync_state' => $botSyncState,
            'bot_finalize_attempts' => 1,
        ]);

        return [
            'ok' => true,
            'result' => 'created',
            'materialization_id' => $approvalId,
            'client_id' => $clientId,
            'client_created' => (bool) $clientCreated,
            'os_id' => $osId,
            'snapshot_version' => $snapshotVersion,
            'snapshot_hash' => $snapshotHash,
            'attachment_sync_state' => $attachmentSyncState,
            'bot_sync_state' => $botSyncState,
        ];
    }
}
