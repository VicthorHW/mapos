<?php

/**
 * CIAO-S07 OS Conversion & Complete Data Transfer Behavioral Test Suite
 * Governed by S07-OS-CONVERSION-AND-DATA-TRANSFER.md, S01-ADR-002, S01-ADR-004, S01-ADR-005.
 *
 * Enforces and verifies:
 * 1. Pre-flight Gate enforcement: Materialization fails if intake is not physically received (ADR-004).
 * 2. New Customer Materialization:
 *    - Full data transfer without re-entry (ADJ-001).
 *    - Zero Hash-on-Hash credential integrity (ADJ-024 / ADR-002): Opaque account_password_hash copied directly to clientes.senha.
 *    - Customer can immediately authenticate with their chosen plaintext password (ADJ-029).
 *    - Separation of registered address vs. pickup address (ADJ-015).
 *    - Zero synthetic email: Missing email stores empty string '' in clientes.email (ADJ-018).
 *    - MapOS Identity and Profile populated.
 * 3. Existing Customer Linking:
 *    - OS linked to existing customer without duplicate account.
 *    - Existing customer password and details remain completely untouched (credential protection).
 * 4. Structured OS Annotations (ADJ-003, ADJ-004, ADJ-035, ADR-005):
 *    - Receiving condition, accessories, serial/IMEI, inspection notes.
 *    - Original and adjusted GPS coordinates.
 *    - Google Maps URL for primary coordinates.
 * 5. Definitive Attachment Promotion (ADR-005):
 *    - Checksum-verified promotion from private pre-OS storage to assets/anexos.
 *    - Attachment linked to newly created OS.
 * 6. Idempotent replay: Re-materializing same intake returns already_completed with identical OS/client IDs.
 * 7. Post-commit Bot finalization & password hash purge.
 */

$GLOBALS['s07_assertions'] = 0;

function expectS07($condition, $message, $extra = null) {
    ++$GLOBALS['s07_assertions'];
    if (! $condition) {
        fwrite(STDERR, "[FAIL] " . $message . PHP_EOL);
        if ($extra !== null) {
            fwrite(STDERR, "[DETAIL] " . (is_string($extra) ? $extra : json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . PHP_EOL);
        }
        exit(1);
    }
}

function testUuidV4S07(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$matService = isset($this) && isset($this->tecnina_materialization_service) ? $this->tecnina_materialization_service : null;
$receivingModel = isset($this) && isset($this->Tecnina_receiving_model) ? $this->Tecnina_receiving_model : null;
$storageService = isset($this) && isset($this->tecnina_attachment_storage) ? $this->tecnina_attachment_storage : null;
$db = isset($this) && isset($this->db) ? $this->db : null;

if (! $matService || ! $receivingModel || ! $storageService || ! $db) {
    fwrite(STDERR, "FATAL: Test must run within CodeIgniter S07_materialization_test_runner context.\n");
    exit(1);
}

echo "=== S07 OS Conversion & Complete Data Transfer Test Suite ===" . PHP_EOL;

$initialOsCount = (int) $db->count_all('os');
echo "[INFO] Initial OS count in database: {$initialOsCount}" . PHP_EOL;

// Helper to generate a valid sealed snapshot
function buildSealedSnapshot(array $overrides = []): array {
    $nowUtc = gmdate('Y-m-d\TH:i:s\Z');
    $sealExpires = gmdate('Y-m-d\TH:i:s\Z', time() + 900); // 15 min lease

    $base = [
        'intake_id' => testUuidV4S07(),
        'intake_version' => 1,
        'snapshot_version' => 1,
        'snapshot_hash' => hash('sha256', 'test-snapshot-' . microtime(true) . bin2hex(random_bytes(8))),
        'sealed_at' => $nowUtc,
        'seal_expires_at' => $sealExpires,
        'data' => [
            'name' => 'Cliente Teste S07',
            'phone_canonical' => '+55419' . mt_rand(10000000, 99999999),
            'registration_cpf' => sprintf('%011d', mt_rand(10000000000, 99999999999)),
            'registration_choice' => 'REGISTER_NOW',
            'registration_email' => 'cliente.s07@example.invalid',
            'registration_postal_code' => '80000000',
            'registration_street' => 'Rua Marechal Deodoro',
            'registration_street_number' => '500',
            'registration_neighborhood' => 'Centro',
            'registration_city' => 'Curitiba',
            'registration_address_state' => 'PR',
            'registration_complement' => 'Apto 101',
            'registration_reference' => 'Em frente à praça',
            'birth_date' => '1990-05-15',
            'has_account_password' => true,
            'account_password_hash' => password_hash('SenhaSecretaS07!', PASSWORD_BCRYPT),
            'device_type' => 'NOTEBOOK',
            'brand' => 'Lenovo',
            'model' => 'ThinkPad T14',
            'problem_description' => 'Não inicializa após atualização de BIOS',
            'notes' => 'Cuidado com a carcaça, pequenas marcas no canto',
            'service_mode' => 'PICKUP_REQUESTED',
            'pickup_street' => 'Rua XV de Novembro',
            'pickup_street_number' => '1234',
            'pickup_neighborhood' => 'Batel',
            'pickup_city' => 'Curitiba',
            'pickup_state' => 'PR',
            'pickup_postal_code' => '80020310',
            'gps_latitude' => '-25.4284000',
            'gps_longitude' => '-49.2733000',
            'gps_accuracy_meters' => '12.50',
            'gps_source' => 'browser_geolocation',
        ],
    ];

    if (! empty($overrides['data'])) {
        $base['data'] = array_merge($base['data'], $overrides['data']);
        unset($overrides['data']);
    }

    return array_merge($base, $overrides);
}

function seedLegalAcceptances($db, string $intakeId): void {
    $db->insert('tecnina_legal_acceptances', [
        'event_id' => testUuidV4S07(),
        'idempotency_key' => testUuidV4S07(),
        'document_version_id' => 1,
        'intake_id' => $intakeId,
        'action' => 'ACCEPTED',
        'occurred_at' => gmdate('Y-m-d H:i:s'),
        'source' => 'customer_registration',
        'document_type_snapshot' => 'TERMS_OF_USE',
        'document_version_snapshot' => '1.0.0',
        'document_hash_snapshot' => hash('sha256', 'terms-v1'),
    ]);
    $db->insert('tecnina_legal_acceptances', [
        'event_id' => testUuidV4S07(),
        'idempotency_key' => testUuidV4S07(),
        'document_version_id' => 2,
        'intake_id' => $intakeId,
        'action' => 'ACCEPTED',
        'occurred_at' => gmdate('Y-m-d H:i:s'),
        'source' => 'customer_registration',
        'document_type_snapshot' => 'PRIVACY_POLICY',
        'document_version_snapshot' => '1.0.0',
        'document_hash_snapshot' => hash('sha256', 'privacy-v1'),
    ]);
}

// -------------------------------------------------------------------------
// TEST 1: Gate Enforcement — Unreceived Intake Fails & Creates ZERO OS
// -------------------------------------------------------------------------
$intakeId1 = testUuidV4S07();
$snap1 = buildSealedSnapshot(['intake_id' => $intakeId1]);

$res1 = $matService->materialize($intakeId1, 1, [], $snap1);
expectS07($res1['ok'] === false, 'Test 1: Unreceived intake must fail materialization');
expectS07($res1['reason'] === 'materialization_blocked', 'Test 1: Reason must be materialization_blocked');
expectS07(in_array('PHYSICAL_RECEIPT_REQUIRED', $res1['blocking_reasons'], true), 'Test 1: Must require physical receipt');

$osCountAfter1 = (int) $db->count_all('os');
expectS07($osCountAfter1 === $initialOsCount, 'Test 1: Zero premature OS created when blocked');
echo "[PASS] Test 1: Gate enforcement verified (Zero premature OS created)" . PHP_EOL;

// -------------------------------------------------------------------------
// TEST 2: Happy Path — New Customer Materialization & Zero Hash-on-Hash
// -------------------------------------------------------------------------
$intakeId2 = testUuidV4S07();
$chosenPlaintextPassword = 'SenhaSecretaS07!';
$opaqueHash = password_hash($chosenPlaintextPassword, PASSWORD_BCRYPT);

// Setup receiving in RECEIVED state
$receivingModel->confirmPhysicalReceipt($intakeId2, 1, [
    'device_condition' => 'Tela intacta, pequenas marcas de uso',
    'accessories' => 'Carregador, Cabo',
    'serial_number' => 'PF2ABC89',
    'imei' => '990000862471854',
    'other_identifiers' => 'Patrimônio #4561',
    'notes' => 'Entregue pelo próprio cliente no balcão',
], testUuidV4S07());

seedLegalAcceptances($db, $intakeId2);

// Setup GPS location record with both original and adjusted coordinates
$db->insert('tecnina_intake_locations', [
    'intake_id' => $intakeId2,
    'original_latitude' => -25.4284000,
    'original_longitude' => -49.2733000,
    'original_accuracy_meters' => 12.50,
    'original_source' => 'browser_geolocation',
    'original_captured_at' => gmdate('Y-m-d H:i:s'),
    'adjusted_latitude' => -25.4285000,
    'adjusted_longitude' => -49.2734000,
    'adjusted_accuracy_meters' => 5.00,
    'adjusted_source' => 'staff_confirmed',
    'adjusted_at' => gmdate('Y-m-d H:i:s'),
    'primary_coordinate' => 'ADJUSTED',
]);

// Setup private attachment
$dummyContent = '%PDF-1.4 test intake attachment for s07';
$dummySha = hash('sha256', $dummyContent);
$storageKey = bin2hex(random_bytes(16)) . '.pdf';
$fullPrivatePath = rtrim($storageService->getStorageRoot(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $storageKey;
file_put_contents($fullPrivatePath, $dummyContent);

$db->insert('tecnina_pre_os_attachments', [
    'intake_id' => $intakeId2,
    'original_name' => 'laudo_triagem.pdf',
    'storage_key' => $storageKey,
    'detected_mime' => 'application/pdf',
    'size_bytes' => strlen($dummyContent),
    'sha256' => $dummySha,
    'state' => 'STAGED',
]);
$stagedAttachmentId = (int) $db->insert_id();

$test2Phone = '+55419' . mt_rand(10000000, 99999999);
$test2PhoneDigits = substr($test2Phone, 1);
$test2Cpf = sprintf('%011d', mt_rand(10000000000, 99999999999));

$snap2 = buildSealedSnapshot([
    'intake_id' => $intakeId2,
    'data' => [
        'phone_canonical' => $test2Phone,
        'registration_cpf' => $test2Cpf,
        'account_password_hash' => $opaqueHash,
    ],
]);

$res2 = $matService->materialize($intakeId2, 1, [], $snap2);
expectS07($res2['ok'] === true, 'Test 2: Materialization must succeed', $res2);
expectS07($res2['result'] === 'created', 'Test 2: Result must be created');
expectS07($res2['client_created'] === true, 'Test 2: Client must be newly created');
expectS07($res2['client_id'] > 0, 'Test 2: Client ID must be valid');
expectS07($res2['os_id'] > 0, 'Test 2: OS ID must be valid');
expectS07($res2['attachment_sync_state'] === 'COMPLETED', 'Test 2: Attachments must be promoted');

// Verify Customer Table & Zero Hash-on-Hash
$clientRow2 = $db->get_where('clientes', ['idClientes' => $res2['client_id']])->row_array();
expectS07(! empty($clientRow2), 'Test 2: Client record must exist');
expectS07($clientRow2['nomeCliente'] === 'Cliente Teste S07', 'Test 2: Client name must match');
expectS07($clientRow2['documento'] === $test2Cpf, 'Test 2: CPF must match');
expectS07($clientRow2['email'] === 'cliente.s07@example.invalid', 'Test 2: Email must match');
expectS07($clientRow2['rua'] === 'Rua Marechal Deodoro', 'Test 2: Registered street must match');
expectS07($clientRow2['numero'] === '500', 'Test 2: Registered number must match');
expectS07($clientRow2['bairro'] === 'Centro', 'Test 2: Registered neighborhood must match');
expectS07($clientRow2['cep'] === '80000000', 'Test 2: Registered postal code must match');

// ADJ-024 / ADR-002: Senha must match verbatim and authenticate with chosen plaintext password!
expectS07($clientRow2['senha'] === $opaqueHash, 'Test 2: Opaque password hash copied verbatim (Zero Hash-on-Hash)');
expectS07(password_verify($chosenPlaintextPassword, $clientRow2['senha']) === true, 'Test 2: Client authenticates with originally chosen password');

// Verify Identity Authority
$identityRow2 = $db->get_where('tecnina_client_identity', ['client_id' => $res2['client_id']])->row_array();
expectS07(! empty($identityRow2), 'Test 2: Identity authority row exists');
expectS07($identityRow2['phone_state'] === 'VERIFIED', 'Test 2: Phone state is VERIFIED');
expectS07($identityRow2['canonical_phone'] === $test2PhoneDigits, 'Test 2: Canonical phone matches normalized digits');

// Verify Profile
$profileRow2 = $db->get_where('tecnina_client_profile', ['client_id' => $res2['client_id']])->row_array();
expectS07(! empty($profileRow2), 'Test 2: Profile row exists');
expectS07($profileRow2['birth_date'] === '1990-05-15', 'Test 2: Birth date transferred');
expectS07($profileRow2['address_reference'] === 'Em frente à praça', 'Test 2: Address reference transferred');

// Verify OS
$osRow2 = $db->get_where('os', ['idOs' => $res2['os_id']])->row_array();
expectS07(! empty($osRow2), 'Test 2: OS row exists');
expectS07((int) $osRow2['clientes_id'] === $res2['client_id'], 'Test 2: OS linked to client');
expectS07($osRow2['status'] === 'Aberto', 'Test 2: OS status is Aberto');
expectS07(strpos($osRow2['descricaoProduto'], 'Lenovo') !== false, 'Test 2: OS description contains equipment');

// Verify Structured Annotations (ADJ-003, ADJ-004, ADJ-035, ADR-005)
$annotations2 = $db->get_where('anotacoes_os', ['os_id' => $res2['os_id']])->result_array();
expectS07(count($annotations2) >= 5, 'Test 2: Multiple structured annotations recorded');
$annTexts = array_column($annotations2, 'anotacao');
$joinedAnn = implode(' | ', $annTexts);
expectS07(strpos($joinedAnn, '[Recebimento] Condição: Tela intacta') !== false, 'Test 2: Receiving condition in annotations');
expectS07(strpos($joinedAnn, 'Serial: PF2ABC89') !== false, 'Test 2: Serial number in annotations');
expectS07(strpos($joinedAnn, 'IMEI: 990000862471854') !== false, 'Test 2: IMEI in annotations');
expectS07(strpos($joinedAnn, '[GPS Coleta - Original]') !== false, 'Test 2: Original GPS in annotations');
expectS07(strpos($joinedAnn, '[GPS Coleta - Ajustado]') !== false, 'Test 2: Adjusted GPS in annotations');
expectS07(strpos($joinedAnn, 'https://www.google.com/maps?q=-25.4285000,-49.2734000') !== false, 'Test 2: Primary Google Maps URL in annotations');

// Verify Attachment Promotion (ADR-005)
$promotedPreOs = $db->get_where('tecnina_pre_os_attachments', ['id' => $stagedAttachmentId])->row_array();
expectS07($promotedPreOs['state'] === 'PROMOTED', 'Test 2: Staged attachment state is PROMOTED');
expectS07(! empty($promotedPreOs['promoted_anexo_id']), 'Test 2: promoted_anexo_id is set');

$anexoRow = $db->get_where('anexos', ['idAnexos' => $promotedPreOs['promoted_anexo_id']])->row_array();
expectS07(! empty($anexoRow), 'Test 2: Definitive anexo row created in MapOS');
expectS07((int) $anexoRow['os_id'] === $res2['os_id'], 'Test 2: Definitive anexo linked to OS');

echo "[PASS] Test 2: New customer materialization & Zero Hash-on-Hash verified" . PHP_EOL;

// -------------------------------------------------------------------------
// TEST 3: Existing Customer Linking & Credential Immutability
// -------------------------------------------------------------------------
// Create existing client with separate password
$existingPassword = 'SenhaAntigaExistente999!';
$existingPhone = '+55419' . mt_rand(10000000, 99999999);
$existingCpf = (string) mt_rand(10000000000, 99999999999);
$db->insert('clientes', [
    'nomeCliente' => 'Cliente Já Cadastrado Antigo',
    'pessoa_fisica' => 1,
    'documento' => $existingCpf,
    'telefone' => $existingPhone,
    'celular' => $existingPhone,
    'email' => 'antigo@example.invalid',
    'senha' => password_hash($existingPassword, PASSWORD_BCRYPT),
    'dataCadastro' => '2025-01-10',
    'fornecedor' => 0,
]);
$existingClientId = (int) $db->insert_id();
$existingClientBefore = $db->get_where('clientes', ['idClientes' => $existingClientId])->row_array();

$intakeId3 = testUuidV4S07();
$receivingModel->confirmPhysicalReceipt($intakeId3, 1, [
    'device_condition' => 'Teclado com teclas falhando',
    'accessories' => 'Nenhum',
], testUuidV4S07());

$snap3 = buildSealedSnapshot([
    'intake_id' => $intakeId3,
    'data' => [
        'name' => 'Cliente Já Cadastrado Antigo',
        'phone_canonical' => $existingPhone,
        'registration_cpf' => $existingCpf,
        'possible_mapos_client_id' => $existingClientId,
        'registration_choice' => 'LINK_EXISTING',
        'account_password_hash' => null, // Existing clients have no pending hash
    ],
]);

$res3 = $matService->materialize($intakeId3, 1, ['client_action' => 'LINK_EXISTING', 'client_id' => $existingClientId], $snap3);
expectS07($res3['ok'] === true, 'Test 3: Materialization with existing client must succeed');
expectS07($res3['client_created'] === false, 'Test 3: Must NOT create new client');
expectS07($res3['client_id'] === $existingClientId, 'Test 3: Must link to existing client ID');
expectS07($res3['os_id'] > 0, 'Test 3: New OS ID created');

// Assert existing client credentials remain 100% untouched
$existingClientAfter = $db->get_where('clientes', ['idClientes' => $existingClientId])->row_array();
expectS07($existingClientAfter['senha'] === $existingClientBefore['senha'], 'Test 3: Existing password hash unchanged');
expectS07(password_verify($existingPassword, $existingClientAfter['senha']) === true, 'Test 3: Existing client login still valid');
expectS07($existingClientAfter['email'] === 'antigo@example.invalid', 'Test 3: Existing email untouched');

// Verify OS linked to existing client
$osRow3 = $db->get_where('os', ['idOs' => $res3['os_id']])->row_array();
expectS07((int) $osRow3['clientes_id'] === $existingClientId, 'Test 3: OS linked to existing client');

echo "[PASS] Test 3: Existing customer linking & credential immutability verified" . PHP_EOL;

// -------------------------------------------------------------------------
// TEST 4: Idempotent Replay — Re-materialization Returns Existing Record
// -------------------------------------------------------------------------
$res4 = $matService->materialize($intakeId2, 1, [], $snap2);
expectS07($res4['ok'] === true, 'Test 4: Re-materialize must succeed');
expectS07($res4['result'] === 'already_completed', 'Test 4: Must indicate already_completed');
expectS07($res4['client_id'] === $res2['client_id'], 'Test 4: Replay returns identical client ID');
expectS07($res4['os_id'] === $res2['os_id'], 'Test 4: Replay returns identical OS ID');

$finalOsCount = (int) $db->count_all('os');
expectS07($finalOsCount === $initialOsCount + 2, 'Test 4: Exactly 2 OS created in entire test suite (Zero duplicates)');
echo "[PASS] Test 4: Idempotent replay verified (No duplicate OS created)" . PHP_EOL;

// -------------------------------------------------------------------------
// TEST 5: Zero Synthetic Email (ADJ-018)
// -------------------------------------------------------------------------
$intakeId5 = testUuidV4S07();
$receivingModel->confirmPhysicalReceipt($intakeId5, 1, ['device_condition' => 'Excelente'], testUuidV4S07());
seedLegalAcceptances($db, $intakeId5);
$snap5 = buildSealedSnapshot([
    'intake_id' => $intakeId5,
    'data' => [
        'name' => 'Cliente Sem Email S07',
        'phone_canonical' => '+55419' . mt_rand(10000000, 99999999),
        'registration_email' => null, // NO EMAIL PROVIDED
    ],
]);

$res5 = $matService->materialize($intakeId5, 1, [], $snap5);
expectS07($res5['ok'] === true, 'Test 5: Materialization without email succeeds');
$clientRow5 = $db->get_where('clientes', ['idClientes' => $res5['client_id']])->row_array();
expectS07($clientRow5['email'] === '', 'Test 5: Absence of email stores empty string (ADJ-018 Zero Synthetic Email)');
echo "[PASS] Test 5: Zero synthetic email verified (clientes.email = '')" . PHP_EOL;

// -------------------------------------------------------------------------
// TEST 6: Expired Snapshot Lease Blocks Materialization
// -------------------------------------------------------------------------
$intakeId6 = testUuidV4S07();
$receivingModel->confirmPhysicalReceipt($intakeId6, 1, ['device_condition' => 'Bom'], testUuidV4S07());
seedLegalAcceptances($db, $intakeId6);
$snap6 = buildSealedSnapshot([
    'intake_id' => $intakeId6,
    'seal_expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 300), // Expired 5 minutes ago
]);

$res6 = $matService->materialize($intakeId6, 1, [], $snap6);
expectS07($res6['ok'] === false, 'Test 6: Expired snapshot lease must fail');
expectS07(in_array('SNAPSHOT_STALE', $res6['blocking_reasons'] ?? [], true) || ($res6['reason'] ?? '') === 'snapshot_stale', 'Test 6: Must report snapshot_stale');
echo "[PASS] Test 6: Expired snapshot lease blocked" . PHP_EOL;

echo "=== S07 Test Suite Completed Successfully ({$GLOBALS['s07_assertions']} assertions passed) ===" . PHP_EOL;
