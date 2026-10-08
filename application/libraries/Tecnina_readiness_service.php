<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Authoritative OS Materialization Readiness Domain Service for MapOS (CIAO-S06B).
 * Enforces the closed vocabulary and multidimensional contract defined in
 * architecture/S01-MATERIALIZATION-READINESS.md.
 */
class Tecnina_readiness_service
{
    public const CONTRACT_VERSION = 'S01-2026-09';

    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->database();
        $this->CI->load->library('Tecnina_bot_gateway');
        $this->CI->load->library('Tecnina_phone');
    }

    /**
     * Authoritative evaluation of pre-attendance readiness for OS materialization.
     *
     * @param string $intakeId
     * @param array|null $injectedSnapshot Optional pre-fetched or simulated snapshot for testing/isolation
     * @return array Closed-vocabulary readiness report
     */
    public function evaluateReadiness(string $intakeId, ?array $injectedSnapshot = null): array
    {
        $blockingReasons = [];
        $pendingItems = [];
        $now = gmdate('Y-m-d H:i:s');
        $nowTimestamp = time();

        // -------------------------------------------------------------
        // 1. Dimension: Physical Receiving
        // -------------------------------------------------------------
        $physicalReceiving = 'MISSING';
        $receivingRow = $this->CI->db
            ->select('id, state, received_at, received_by')
            ->from('tecnina_physical_receiving')
            ->where('intake_id', $intakeId)
            ->limit(1)
            ->get()
            ->row_array();

        if ($receivingRow) {
            $state = strtoupper(trim((string) ($receivingRow['state'] ?? '')));
            if ($state === 'RECEIVED') {
                $physicalReceiving = 'RECEIVED';
            } elseif ($state === 'CANCELLED') {
                $physicalReceiving = 'CANCELLED';
            } elseif ($state === 'PENDING_DELIVERY') {
                $physicalReceiving = 'PENDING_DELIVERY';
            } else {
                $physicalReceiving = 'MISSING';
            }
        }

        if ($physicalReceiving !== 'RECEIVED') {
            $blockingReasons[] = 'PHYSICAL_RECEIPT_REQUIRED';
        }

        // -------------------------------------------------------------
        // 2. Dimension: Authoritative Intake Snapshot & Lease
        // -------------------------------------------------------------
        $intakeSnapshot = 'UNAVAILABLE';
        $snapshotPayload = null;
        $snapshotHash = null;
        $snapshotVersion = null;
        $intakeVersion = null;
        $sealExpiresAt = null;

        if ($injectedSnapshot !== null) {
            $snapshotPayload = $injectedSnapshot;
        } else {
            $gatewayResp = $this->CI->tecnina_bot_gateway->fetchMaterializationSnapshot($intakeId);
            if ($gatewayResp['ok'] && isset($gatewayResp['data'])) {
                $snapshotPayload = $gatewayResp['data'];
            } elseif (($gatewayResp['status'] ?? 0) === 409 || ($gatewayResp['reason'] ?? '') === 'snapshot_stale') {
                $intakeSnapshot = 'STALE';
            } else {
                $intakeSnapshot = 'UNAVAILABLE';
            }
        }

        if ($snapshotPayload !== null) {
            $snapshotHash = $snapshotPayload['snapshot_hash'] ?? null;
            $snapshotVersion = isset($snapshotPayload['snapshot_version']) ? (int) $snapshotPayload['snapshot_version'] : null;
            $intakeVersion = isset($snapshotPayload['intake_version']) ? (int) $snapshotPayload['intake_version'] : null;
            $sealExpiresAt = $snapshotPayload['seal_expires_at'] ?? null;

            if ($sealExpiresAt !== null) {
                $expiryTimestamp = strtotime($sealExpiresAt);
                if ($expiryTimestamp && $nowTimestamp >= $expiryTimestamp) {
                    $intakeSnapshot = 'STALE';
                } else {
                    $intakeSnapshot = 'READY';
                }
            } else {
                $intakeSnapshot = 'READY';
            }
        }

        if ($intakeSnapshot === 'STALE') {
            $pendingItems[] = 'SNAPSHOT_STALE';
        } elseif ($intakeSnapshot === 'UNAVAILABLE') {
            $pendingItems[] = 'SNAPSHOT_UNAVAILABLE';
        }

        $snapData = is_array($snapshotPayload['data'] ?? null) ? $snapshotPayload['data'] : ($snapshotPayload ?? []);

        // -------------------------------------------------------------
        // 3. Dimension: Identity Resolution
        // -------------------------------------------------------------
        $identityResolution = 'MISSING';
        $matchedClientId = null;
        $isExistingAccount = false;

        $phoneCanonical = trim((string) ($snapData['phone_canonical'] ?? ''));
        $cpf = preg_replace('/\D+/', '', (string) ($snapData['registration_cpf'] ?? ''));
        $clientName = trim((string) ($snapData['name'] ?? ''));

        $matchingIds = [];

        // 3a. Search existing client by phone
        if ($phoneCanonical !== '') {
            $clientRows = $this->CI->db
                ->select('idClientes, celular, telefone, documento')
                ->from('clientes')
                ->get()
                ->result_array();

            foreach ($clientRows as $row) {
                if ($this->CI->tecnina_phone->matchesCandidate($phoneCanonical, $row['celular'], $row['telefone'])) {
                    $matchingIds[(int) $row['idClientes']] = true;
                }
            }
        }

        // 3b. Search existing client by CPF
        if ($cpf !== '' && strlen($cpf) === 11) {
            $cpfRows = $this->CI->db
                ->select('idClientes')
                ->from('clientes')
                ->where('documento', $cpf)
                ->get()
                ->result_array();

            foreach ($cpfRows as $row) {
                $matchingIds[(int) $row['idClientes']] = true;
            }
        }

        $matchCount = count($matchingIds);
        if ($matchCount === 1) {
            $identityResolution = 'READY';
            $matchedClientId = (int) array_keys($matchingIds)[0];
            $isExistingAccount = true;
        } elseif ($matchCount > 1) {
            $identityResolution = 'AMBIGUOUS';
            $pendingItems[] = 'CLIENT_IDENTITY_AMBIGUOUS';
        } else {
            // No existing client in MapOS: check if minimum new customer details exist
            if (mb_strlen($clientName) >= 2 && strlen($phoneCanonical) >= 8) {
                $identityResolution = 'READY';
                $isExistingAccount = false;
            } else {
                $identityResolution = 'MISSING';
                $pendingItems[] = 'CLIENT_IDENTITY_MISSING';
            }
        }

        // -------------------------------------------------------------
        // 4. Dimensions: Registration & Credential Branches
        // -------------------------------------------------------------
        $registration = 'DEFERRED_NOT_COMPLETED';
        $credential = 'MISSING';

        if ($isExistingAccount) {
            $registration = 'EXISTING_ACCOUNT';
            $credential = 'EXISTING_ACCOUNT';
        } else {
            $choice = strtoupper(trim((string) ($snapData['registration_choice'] ?? '')));
            if ($choice === 'REGISTER_NOW') {
                $registration = 'READY_TO_MATERIALIZE';

                $hasPassword = !empty($snapData['has_account_password']) || !empty($snapData['account_password_hash']);
                if ($hasPassword) {
                    $credential = 'PRESENT';
                } else {
                    $credential = 'MISSING';
                    $pendingItems[] = 'CREDENTIAL_MISSING';
                }
            } elseif ($choice === 'DEFER_REGISTRATION') {
                $registration = 'DEFERRED_NOT_COMPLETED';
                $credential = 'MISSING';
                $pendingItems[] = 'REGISTRATION_PENDING';
            } else {
                $registration = 'DEFERRED_NOT_COMPLETED';
                $credential = 'MISSING';
                $pendingItems[] = 'REGISTRATION_PENDING';
            }
        }

        // -------------------------------------------------------------
        // 5. Dimension: Legal Manifestations
        // -------------------------------------------------------------
        $legal = 'MISSING';
        if ($isExistingAccount) {
            // Per S01-MATERIALIZATION-READINESS: Para conta existente, legal=SATISFIED
            // significa que não há manifestação obrigatória corrente; não fabrica evento por nova OS.
            $legal = 'SATISFIED';
        } else {
            // New account requires audit-grade terms & privacy acceptance in tecnina_legal_acceptances
            $legalRows = $this->CI->db
                ->select('document_type_snapshot, action')
                ->from('tecnina_legal_acceptances')
                ->where('intake_id', $intakeId)
                ->get()
                ->result_array();

            $hasTerms = false;
            $hasPrivacy = false;
            foreach ($legalRows as $lRow) {
                if ($lRow['document_type_snapshot'] === 'TERMS_OF_USE' && $lRow['action'] === 'ACCEPTED') {
                    $hasTerms = true;
                }
                if ($lRow['document_type_snapshot'] === 'PRIVACY_POLICY' && in_array($lRow['action'], ['ACCEPTED', 'ACKNOWLEDGED'], true)) {
                    $hasPrivacy = true;
                }
            }

            if ($hasTerms && $hasPrivacy) {
                $legal = 'SATISFIED';
            } else {
                $legal = 'MISSING';
                $pendingItems[] = 'LEGAL_ACCEPTANCE_PENDING';
            }
        }

        // -------------------------------------------------------------
        // 6. Dimension: Equipment Minimums
        // -------------------------------------------------------------
        $deviceType = trim((string) ($snapData['device_type'] ?? ''));
        $brand = trim((string) ($snapData['brand'] ?? ''));
        $model = trim((string) ($snapData['model'] ?? ''));
        $problem = trim((string) ($snapData['problem_description'] ?? ''));

        $equipmentValid = ($deviceType !== '') && ($brand !== '' || $model !== '') && ($problem !== '');
        if (!$equipmentValid) {
            $pendingItems[] = 'EQUIPMENT_DATA_INCOMPLETE';
        }

        // -------------------------------------------------------------
        // 7. Dimension: Attachments
        // -------------------------------------------------------------
        $attachments = 'READY';
        $attachmentRows = $this->CI->db
            ->select('id, state')
            ->from('tecnina_pre_os_attachments')
            ->where('intake_id', $intakeId)
            ->get()
            ->result_array();

        $hasPending = false;
        foreach ($attachmentRows as $att) {
            $attState = strtoupper(trim((string) ($att['state'] ?? '')));
            if (in_array($attState, ['FAILED', 'ERROR'], true)) {
                $attachments = 'BLOCKING_ERROR';
                $pendingItems[] = 'ATTACHMENTS_ERROR';
                break;
            }
            if ($attState === 'PROMOTION_PENDING') {
                $hasPending = true;
            }
        }
        if ($attachments !== 'BLOCKING_ERROR' && $hasPending) {
            $attachments = 'POST_COMMIT_PENDING_ALLOWED';
        }

        // -------------------------------------------------------------
        // 8. Overall Readiness Synthesis
        // -------------------------------------------------------------
        // Per 07-10-2026 adjustment: Only physical receiving is mandatory and blocks OS opening.
        // Other items (legal manifestations, registration choice, credentials, etc.) are tracked as pending_items without blocking OS opening.
        if ($physicalReceiving !== 'RECEIVED') {
            $blockingReasons[] = 'PHYSICAL_RECEIVING_PENDING';
        }

        $ready = ($physicalReceiving === 'RECEIVED');

        $result = [
            'ready' => $ready,
            'physical_receiving' => $physicalReceiving,
            'identity_resolution' => $identityResolution,
            'registration' => $registration,
            'credential' => $credential,
            'legal' => $legal,
            'intake_snapshot' => $intakeSnapshot,
            'attachments' => $attachments,
            'equipment_minimums' => $equipmentValid,
            'blocking_reasons' => array_values(array_unique($blockingReasons)),
            'pending_items' => array_values(array_unique($pendingItems)),
            'intake_version' => $intakeVersion,
            'snapshot_version' => $snapshotVersion,
            'snapshot_hash' => $snapshotHash,
            'matched_client_id' => $matchedClientId,
            'contract_version' => self::CONTRACT_VERSION,
            'evaluated_at' => $now,
        ];

        // -------------------------------------------------------------
        // 9. Persist Audit Record in tecnina_intake_approvals
        // -------------------------------------------------------------
        $this->persistReadinessAudit($intakeId, $result, $sealExpiresAt);

        return $result;
    }

    /**
     * Persist the latest readiness evaluation audit snapshot.
     */
    protected function persistReadinessAudit(string $intakeId, array $result, ?string $sealExpiresAt): void
    {
        $existing = $this->CI->db
            ->select('id')
            ->from('tecnina_intake_approvals')
            ->where('intake_id', $intakeId)
            ->limit(1)
            ->get()
            ->row_array();

        if ($existing) {
            $data = [
                'readiness_result' => json_encode($result),
                'readiness_contract_version' => self::CONTRACT_VERSION,
                'snapshot_hash' => $result['snapshot_hash'],
                'snapshot_version' => $result['snapshot_version'],
                'snapshot_fetched_at' => $result['evaluated_at'],
                'seal_expires_at' => $sealExpiresAt ? gmdate('Y-m-d H:i:s', strtotime($sealExpiresAt)) : null,
            ];

            $this->CI->db->where('id', (int) $existing['id'])->update('tecnina_intake_approvals', $data);
        }
    }
}
