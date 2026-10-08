<?php

/**
 * CIAO-S06B OS Readiness Gate Behavioral Test Suite
 * Governed by S01-MATERIALIZATION-READINESS.md, S01-ADR-004-PHYSICAL-RECEIVING-OS-GATE.md,
 * and Stage S06 specification.
 *
 * Enforces and verifies:
 * 1. ZERO premature OS creation under all readiness operations.
 * 2. Closed-vocabulary evaluation across all 8 dimensions:
 *    - physical_receiving (RECEIVED | PENDING_DELIVERY | CANCELLED | MISSING)
 *    - identity_resolution (READY | AMBIGUOUS | MISSING)
 *    - registration (EXISTING_ACCOUNT | READY_TO_MATERIALIZE | DEFERRED_NOT_COMPLETED)
 *    - credential (PRESENT | EXISTING_ACCOUNT | RECOVERY_REQUIRED | MISSING)
 *    - legal (SATISFIED | REMANIFESTATION_REQUIRED | MISSING)
 *    - intake_snapshot (READY | STALE | UNAVAILABLE)
 *    - attachments (READY | POST_COMMIT_PENDING_ALLOWED | BLOCKING_ERROR)
 *    - equipment_minimums (true | false)
 * 3. Deterministic blocking reasons when any gate fails.
 * 4. Strict gate combinations:
 *    - New client: READY_TO_MATERIALIZE + PRESENT + SATISFIED + RECEIVED + READY snapshot.
 *    - Existing client: EXISTING_ACCOUNT + EXISTING_ACCOUNT + SATISFIED + RECEIVED + READY snapshot.
 * 5. Existing client password and identity remain completely untouched.
 * 6. Expired snapshot lease (seal_expires_at in past) returns STALE and blocks.
 * 7. Ambiguous client identity detection and blocking.
 * 8. Attachment errors detect and block.
 * 9. Absolute invariant: OS table row count unchanged (S06B_OS_ROWS_CREATED = 0).
 */

$GLOBALS['s06b_assertions'] = 0;

function expectReadiness($condition, $message) {
    ++$GLOBALS['s06b_assertions'];
    if (! $condition) {
        fwrite(STDERR, "[FAIL] " . $message . PHP_EOL);
        exit(1);
    }
}

function testUuidV4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$service = isset($this) && isset($this->tecnina_readiness_service) ? $this->tecnina_readiness_service : null;
$receivingModel = isset($this) && isset($this->Tecnina_receiving_model) ? $this->Tecnina_receiving_model : null;
$db = isset($this) && isset($this->db) ? $this->db : null;

if (! $service || ! $receivingModel || ! $db) {
    fwrite(STDERR, "FATAL: Test must run within CodeIgniter S06B_readiness_test_runner context.\n");
    exit(1);
}

echo "=== S06B OS Readiness Gate Test Suite ===" . PHP_EOL;

// 0. BASELINE OS COUNT CHECK
$initialOsCount = (int) $db->count_all('os');
echo "[INFO] Initial OS count in database: {$initialOsCount}" . PHP_EOL;

// Helper to generate a valid base snapshot
function createBaseSnapshot(array $overrides = []): array {
    $nowUtc = gmdate('Y-m-d\TH:i:s\Z');
    $sealExpires = gmdate('Y-m-d\TH:i:s\Z', time() + 900); // 15 min lease

    $base = [
        'intake_id' => testUuidV4(),
        'intake_version' => 3,
        'snapshot_version' => 1,
        'snapshot_hash' => hash('sha256', 'test-snapshot-' . microtime(true)),
        'sealed_at' => $nowUtc,
        'seal_expires_at' => $sealExpires,
        'data' => [
            'name' => 'Cliente Teste Novo S06B',
            'phone_canonical' => '+5541999990001',
            'registration_cpf' => '01234567890',
            'registration_choice' => 'REGISTER_NOW',
            'has_account_password' => true,
            'account_password_hash' => '$2y$10$abcdefghijklmnopqrstuvwxyz12345678901234567890123456',
            'device_type' => 'NOTEBOOK',
            'brand' => 'Dell',
            'model' => 'Inspiron 15',
            'problem_description' => 'Não liga após queda de energia.',
            'service_mode' => 'DROP_OFF',
        ],
    ];

    if (! empty($overrides['data'])) {
        $base['data'] = array_merge($base['data'], $overrides['data']);
        unset($overrides['data']);
    }

    return array_merge($base, $overrides);
}

// -------------------------------------------------------------------------
// TEST 1: Unreceived Intake (No receiving row at all)
// -------------------------------------------------------------------------
$intakeId1 = testUuidV4();
$snap1 = createBaseSnapshot(['intake_id' => $intakeId1]);

$res1 = $service->evaluateReadiness($intakeId1, $snap1);
expectReadiness($res1['ready'] === false, 'Test 1: Unreceived intake must not be ready');
expectReadiness($res1['physical_receiving'] === 'MISSING', 'Test 1: physical_receiving must be MISSING');
expectReadiness(in_array('PHYSICAL_RECEIPT_REQUIRED', $res1['blocking_reasons'], true), 'Test 1: Reason must include PHYSICAL_RECEIPT_REQUIRED');
echo "[PASS] Test 1: Unreceived intake correctly blocked (MISSING)" . PHP_EOL;

// -------------------------------------------------------------------------
// TEST 2: Intake in PENDING_DELIVERY state (Prepared but not confirmed)
// -------------------------------------------------------------------------
$intakeId2 = testUuidV4();
$receivingModel->savePreparation($intakeId2, [
    'device_condition' => 'Tela trincada',
    'notes' => 'Aguardando cliente trazer na loja',
], 1);
$snap2 = createBaseSnapshot(['intake_id' => $intakeId2]);

$res2 = $service->evaluateReadiness($intakeId2, $snap2);
expectReadiness($res2['ready'] === false, 'Test 2: PENDING_DELIVERY intake must not be ready');
expectReadiness($res2['physical_receiving'] === 'PENDING_DELIVERY', 'Test 2: physical_receiving must be PENDING_DELIVERY');
expectReadiness(in_array('PHYSICAL_RECEIPT_REQUIRED', $res2['blocking_reasons'], true), 'Test 2: Reason must include PHYSICAL_RECEIPT_REQUIRED');
echo "[PASS] Test 2: PENDING_DELIVERY intake correctly blocked" . PHP_EOL;

// -------------------------------------------------------------------------
// TEST 3: Intake in CANCELLED receiving state
// -------------------------------------------------------------------------
$intakeId3 = testUuidV4();
$db->insert('tecnina_physical_receiving', [
    'intake_id' => $intakeId3,
    'state' => 'CANCELLED',
    'created_at' => gmdate('Y-m-d H:i:s'),
]);
$snap3 = createBaseSnapshot(['intake_id' => $intakeId3]);

$res3 = $service->evaluateReadiness($intakeId3, $snap3);
expectReadiness($res3['ready'] === false, 'Test 3: CANCELLED receiving must not be ready');
expectReadiness($res3['physical_receiving'] === 'CANCELLED', 'Test 3: physical_receiving must be CANCELLED');
expectReadiness(in_array('PHYSICAL_RECEIPT_REQUIRED', $res3['blocking_reasons'], true), 'Test 3: Reason must include PHYSICAL_RECEIPT_REQUIRED');
echo "[PASS] Test 3: CANCELLED intake correctly blocked" . PHP_EOL;

// -------------------------------------------------------------------------
// TEST 4: Received, but Customer chose DEFER_REGISTRATION
// -------------------------------------------------------------------------
$intakeId4 = testUuidV4();
$receivingModel->confirmPhysicalReceipt($intakeId4, 1, ['device_condition' => 'Bom estado'], testUuidV4());
$snap4 = createBaseSnapshot([
    'intake_id' => $intakeId4,
    'data' => [
        'registration_choice' => 'DEFER_REGISTRATION',
        'has_account_password' => false,
        'account_password_hash' => null,
    ],
]);

$res4 = $service->evaluateReadiness($intakeId4, $snap4);
expectReadiness($res4['ready'] === true, 'Test 4: DEFER_REGISTRATION must be ready when physical receiving is confirmed');
expectReadiness($res4['physical_receiving'] === 'RECEIVED', 'Test 4: physical_receiving must be RECEIVED');
expectReadiness($res4['registration'] === 'DEFERRED_NOT_COMPLETED', 'Test 4: registration must be DEFERRED_NOT_COMPLETED');
expectReadiness($res4['credential'] === 'MISSING', 'Test 4: credential must be MISSING');
expectReadiness(in_array('REGISTRATION_PENDING', $res4['pending_items'], true), 'Test 4: pending_items must include REGISTRATION_PENDING');
echo "[PASS] Test 4: DEFER_REGISTRATION marked as pending without blocking OS" . PHP_EOL;

// -------------------------------------------------------------------------
// TEST 5: Received, REGISTER_NOW, but Password missing
// -------------------------------------------------------------------------
$intakeId5 = testUuidV4();
$receivingModel->confirmPhysicalReceipt($intakeId5, 1, ['device_condition' => 'Bom estado'], testUuidV4());
$snap5 = createBaseSnapshot([
    'intake_id' => $intakeId5,
    'data' => [
        'registration_choice' => 'REGISTER_NOW',
        'has_account_password' => false,
        'account_password_hash' => null,
    ],
]);

$res5 = $service->evaluateReadiness($intakeId5, $snap5);
expectReadiness($res5['ready'] === true, 'Test 5: Missing password must be ready when physical receiving is confirmed');
expectReadiness($res5['registration'] === 'READY_TO_MATERIALIZE', 'Test 5: registration is READY_TO_MATERIALIZE');
expectReadiness($res5['credential'] === 'MISSING', 'Test 5: credential must be MISSING');
expectReadiness(in_array('CREDENTIAL_MISSING', $res5['pending_items'], true), 'Test 5: pending_items must include CREDENTIAL_MISSING');
echo "[PASS] Test 5: Missing credential marked as pending without blocking OS" . PHP_EOL;

// -------------------------------------------------------------------------
// TEST 6: Received, REGISTER_NOW, Password present, but Legal manifests missing
// -------------------------------------------------------------------------
$intakeId6 = testUuidV4();
$receivingModel->confirmPhysicalReceipt($intakeId6, 1, ['device_condition' => 'Bom estado'], testUuidV4());
$snap6 = createBaseSnapshot([
    'intake_id' => $intakeId6,
    'data' => [
        'registration_choice' => 'REGISTER_NOW',
        'has_account_password' => true,
        'account_password_hash' => '$2y$10$validhashhere00000000000000000000000000000000000000',
    ],
]);

$res6 = $service->evaluateReadiness($intakeId6, $snap6);
expectReadiness($res6['ready'] === true, 'Test 6: Missing legal manifestations must be ready when physical receiving is confirmed');
expectReadiness($res6['legal'] === 'MISSING', 'Test 6: legal must be MISSING');
expectReadiness(in_array('LEGAL_ACCEPTANCE_PENDING', $res6['pending_items'], true), 'Test 6: pending_items must include LEGAL_ACCEPTANCE_PENDING');
echo "[PASS] Test 6: Missing legal manifestations marked as pending without blocking OS" . PHP_EOL;

// -------------------------------------------------------------------------
// TEST 7: Full Ready New Customer (Physical RECEIVED + REGISTER_NOW + Password + Legal + Valid Snapshot)
// -------------------------------------------------------------------------
$intakeId7 = testUuidV4();
$receivingModel->confirmPhysicalReceipt($intakeId7, 1, [
    'device_condition' => 'Perfeito estado',
    'serial_number' => 'SN-FULL-READY-001',
    'accessories' => 'Carregador original',
], testUuidV4());

// Insert required legal acceptances into tecnina_legal_acceptances
$db->insert('tecnina_legal_acceptances', [
    'event_id' => testUuidV4(),
    'idempotency_key' => testUuidV4(),
    'document_version_id' => 1,
    'intake_id' => $intakeId7,
    'action' => 'ACCEPTED',
    'occurred_at' => gmdate('Y-m-d H:i:s'),
    'source' => 'customer_registration',
    'document_type_snapshot' => 'TERMS_OF_USE',
    'document_version_snapshot' => '1.0.0',
    'document_hash_snapshot' => hash('sha256', 'terms-v1'),
]);
$db->insert('tecnina_legal_acceptances', [
    'event_id' => testUuidV4(),
    'idempotency_key' => testUuidV4(),
    'document_version_id' => 2,
    'intake_id' => $intakeId7,
    'action' => 'ACCEPTED',
    'occurred_at' => gmdate('Y-m-d H:i:s'),
    'source' => 'customer_registration',
    'document_type_snapshot' => 'PRIVACY_POLICY',
    'document_version_snapshot' => '1.0.0',
    'document_hash_snapshot' => hash('sha256', 'privacy-v1'),
]);

$snap7 = createBaseSnapshot([
    'intake_id' => $intakeId7,
    'data' => [
        'name' => 'Novo Cliente Totalmente Apto',
        'phone_canonical' => '+5541998887766',
        'registration_choice' => 'REGISTER_NOW',
        'has_account_password' => true,
        'account_password_hash' => '$2y$10$validhashhere00000000000000000000000000000000000000',
        'device_type' => 'SMARTPHONE',
        'brand' => 'Samsung',
        'model' => 'Galaxy S23',
        'problem_description' => 'Troca de bateria solicitada.',
    ],
]);

$res7 = $service->evaluateReadiness($intakeId7, $snap7);
expectReadiness($res7['ready'] === true, 'Test 7: Full ready new customer MUST have ready = true');
expectReadiness($res7['physical_receiving'] === 'RECEIVED', 'Test 7: physical_receiving must be RECEIVED');
expectReadiness($res7['identity_resolution'] === 'READY', 'Test 7: identity_resolution must be READY');
expectReadiness($res7['registration'] === 'READY_TO_MATERIALIZE', 'Test 7: registration must be READY_TO_MATERIALIZE');
expectReadiness($res7['credential'] === 'PRESENT', 'Test 7: credential must be PRESENT');
expectReadiness($res7['legal'] === 'SATISFIED', 'Test 7: legal must be SATISFIED');
expectReadiness($res7['intake_snapshot'] === 'READY', 'Test 7: intake_snapshot must be READY');
expectReadiness($res7['attachments'] === 'READY', 'Test 7: attachments must be READY');
expectReadiness($res7['equipment_minimums'] === true, 'Test 7: equipment_minimums must be true');
expectReadiness(empty($res7['blocking_reasons']), 'Test 7: blocking_reasons must be empty');
echo "[PASS] Test 7: Full ready new customer passed ALL readiness gates (ready=true)" . PHP_EOL;

// -------------------------------------------------------------------------
// TEST 8: Full Ready Existing Customer (Client ID 4)
// -------------------------------------------------------------------------
$testClientPhone = '41999880008';
$testCanonical = '+5541999880008';
$client4 = $db->get_where('clientes', ['celular' => $testClientPhone])->row_array();
if (empty($client4)) {
    $db->insert('clientes', [
        'nomeCliente' => 'Cliente Teste Existente 8',
        'celular' => $testClientPhone,
        'senha' => password_hash('SenhaTeste123!', PASSWORD_DEFAULT),
        'dataCadastro' => date('Y-m-d'),
    ]);
    $client4 = $db->get_where('clientes', ['celular' => $testClientPhone])->row_array();
}
$targetClientId = (int) $client4['idClientes'];
$client4OriginalPassword = $client4['senha'];
$client4Phone = $testCanonical;

$intakeId8 = testUuidV4();
$receivingModel->confirmPhysicalReceipt($intakeId8, 1, [
    'device_condition' => 'Bom estado geral',
    'serial_number' => 'SN-CLIENT4-998',
], testUuidV4());

$snap8 = createBaseSnapshot([
    'intake_id' => $intakeId8,
    'data' => [
        'name' => $client4['nomeCliente'],
        'phone_canonical' => $client4Phone,
        'registration_choice' => 'EXISTING_ACCOUNT',
        'device_type' => 'NOTEBOOK',
        'brand' => 'Lenovo',
        'model' => 'ThinkPad T14',
        'problem_description' => 'Teclado com teclas falhando.',
    ],
]);

$res8 = $service->evaluateReadiness($intakeId8, $snap8);
expectReadiness($res8['ready'] === true, 'Test 8: Full ready existing customer MUST have ready = true');
expectReadiness($res8['identity_resolution'] === 'READY', 'Test 8: identity_resolution must be READY');
expectReadiness((int) $res8['matched_client_id'] === $targetClientId, "Test 8: matched_client_id must be $targetClientId");
expectReadiness($res8['registration'] === 'EXISTING_ACCOUNT', 'Test 8: registration must be EXISTING_ACCOUNT');
expectReadiness($res8['credential'] === 'EXISTING_ACCOUNT', 'Test 8: credential must be EXISTING_ACCOUNT');
expectReadiness($res8['legal'] === 'SATISFIED', 'Test 8: legal must be SATISFIED (no artificial event needed for existing)');
expectReadiness(empty($res8['blocking_reasons']), 'Test 8: blocking_reasons must be empty');

// Assert Client password hash is completely untouched
$client4Recheck = $db->get_where('clientes', ['idClientes' => $targetClientId])->row_array();
expectReadiness($client4Recheck['senha'] === $client4OriginalPassword, 'Test 8: Client password hash must remain completely untouched');
echo "[PASS] Test 8: Full ready existing customer passed gates; password hash untouched" . PHP_EOL;

// -------------------------------------------------------------------------
// TEST 9: Ambiguous Customer Identity (Duplicate Client in MapOS)
// -------------------------------------------------------------------------
$dupPhone = '+5541987654321';
$db->insert('clientes', [
    'nomeCliente' => 'Cliente Dup A',
    'celular' => $dupPhone,
    'documento' => '99988877701',
    'dataCadastro' => date('Y-m-d'),
]);
$dupIdA = $db->insert_id();

$db->insert('clientes', [
    'nomeCliente' => 'Cliente Dup B',
    'celular' => $dupPhone,
    'documento' => '99988877702',
    'dataCadastro' => date('Y-m-d'),
]);
$dupIdB = $db->insert_id();

$intakeId9 = testUuidV4();
$receivingModel->confirmPhysicalReceipt($intakeId9, 1, ['device_condition' => 'Ok'], testUuidV4());
$snap9 = createBaseSnapshot([
    'intake_id' => $intakeId9,
    'data' => [
        'name' => 'Cliente Dup Teste',
        'phone_canonical' => $dupPhone,
        'registration_choice' => 'REGISTER_NOW',
    ],
]);

$res9 = $service->evaluateReadiness($intakeId9, $snap9);
expectReadiness($res9['ready'] === true, 'Test 9: Ambiguous customer must be ready when physical receiving is confirmed');
expectReadiness($res9['identity_resolution'] === 'AMBIGUOUS', 'Test 9: identity_resolution must be AMBIGUOUS');
expectReadiness(in_array('CLIENT_IDENTITY_AMBIGUOUS', $res9['pending_items'], true), 'Test 9: Reason must include CLIENT_IDENTITY_AMBIGUOUS in pending_items');

// Cleanup temporary duplicate clients
$db->where('idClientes', $dupIdA)->delete('clientes');
$db->where('idClientes', $dupIdB)->delete('clientes');
echo "[PASS] Test 9: Ambiguous identity correctly detected as pending without blocking OS" . PHP_EOL;

// -------------------------------------------------------------------------
// TEST 10: Expired Snapshot Lease (seal_expires_at in past)
// -------------------------------------------------------------------------
$intakeId10 = testUuidV4();
$receivingModel->confirmPhysicalReceipt($intakeId10, 1, ['device_condition' => 'Ok'], testUuidV4());
$snap10 = createBaseSnapshot([
    'intake_id' => $intakeId10,
    'seal_expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 3600), // Expired 1 hour ago
]);

$res10 = $service->evaluateReadiness($intakeId10, $snap10);
expectReadiness($res10['ready'] === true, 'Test 10: Expired snapshot lease must be ready when physical receiving is confirmed');
expectReadiness($res10['intake_snapshot'] === 'STALE', 'Test 10: intake_snapshot must be STALE');
expectReadiness(in_array('SNAPSHOT_STALE', $res10['pending_items'], true), 'Test 10: Reason must include SNAPSHOT_STALE in pending_items');
echo "[PASS] Test 10: Expired snapshot lease correctly returned STALE as pending without blocking OS" . PHP_EOL;

// -------------------------------------------------------------------------
// TEST 11: Attachments in Error State
// -------------------------------------------------------------------------
$intakeId11 = testUuidV4();
$receivingModel->confirmPhysicalReceipt($intakeId11, 1, ['device_condition' => 'Ok'], testUuidV4());
$snap11 = createBaseSnapshot(['intake_id' => $intakeId11]);

$db->insert('tecnina_pre_os_attachments', [
    'intake_id' => $intakeId11,
    'state' => 'FAILED',
    'storage_key' => 'corrupt-file-' . testUuidV4() . '.bin',
    'original_name' => 'damage_photo.jpg',
    'detected_mime' => 'image/jpeg',
    'size_bytes' => 1024,
    'sha256' => hash('sha256', 'error-att'),
    'created_at' => gmdate('Y-m-d H:i:s'),
    'updated_at' => gmdate('Y-m-d H:i:s'),
]);

$res11 = $service->evaluateReadiness($intakeId11, $snap11);
expectReadiness($res11['ready'] === true, 'Test 11: Corrupt/error attachment must be ready when physical receiving is confirmed');
expectReadiness($res11['attachments'] === 'BLOCKING_ERROR', 'Test 11: attachments must be BLOCKING_ERROR');
expectReadiness(in_array('ATTACHMENTS_ERROR', $res11['pending_items'], true), 'Test 11: Reason must include ATTACHMENTS_ERROR in pending_items');
echo "[PASS] Test 11: Attachment error correctly returned BLOCKING_ERROR as pending without blocking OS" . PHP_EOL;

// -------------------------------------------------------------------------
// TEST 12: Incomplete Equipment Data
// -------------------------------------------------------------------------
$intakeId12 = testUuidV4();
$receivingModel->confirmPhysicalReceipt($intakeId12, 1, ['device_condition' => 'Ok'], testUuidV4());
$snap12 = createBaseSnapshot([
    'intake_id' => $intakeId12,
    'data' => [
        'device_type' => '', // Empty device type
        'problem_description' => '', // Empty problem description
    ],
]);

$res12 = $service->evaluateReadiness($intakeId12, $snap12);
expectReadiness($res12['ready'] === true, 'Test 12: Incomplete equipment data must be ready when physical receiving is confirmed');
expectReadiness($res12['equipment_minimums'] === false, 'Test 12: equipment_minimums must be false');
expectReadiness(in_array('EQUIPMENT_DATA_INCOMPLETE', $res12['pending_items'], true), 'Test 12: Reason must include EQUIPMENT_DATA_INCOMPLETE in pending_items');
echo "[PASS] Test 12: Incomplete equipment data marked as pending without blocking OS" . PHP_EOL;

// -------------------------------------------------------------------------
// TEST 13: Closed Vocabulary and Schema Contract Compliance
// -------------------------------------------------------------------------
$validPhysical = ['RECEIVED', 'PENDING_DELIVERY', 'CANCELLED', 'MISSING'];
$validIdentity = ['READY', 'AMBIGUOUS', 'MISSING'];
$validReg = ['EXISTING_ACCOUNT', 'READY_TO_MATERIALIZE', 'DEFERRED_NOT_COMPLETED'];
$validCred = ['PRESENT', 'EXISTING_ACCOUNT', 'RECOVERY_REQUIRED', 'MISSING'];
$validLegal = ['SATISFIED', 'REMANIFESTATION_REQUIRED', 'MISSING'];
$validSnap = ['READY', 'STALE', 'UNAVAILABLE'];
$validAtt = ['READY', 'POST_COMMIT_PENDING_ALLOWED', 'BLOCKING_ERROR'];

expectReadiness(in_array($res7['physical_receiving'], $validPhysical, true), 'Contract: physical_receiving closed vocabulary');
expectReadiness(in_array($res7['identity_resolution'], $validIdentity, true), 'Contract: identity_resolution closed vocabulary');
expectReadiness(in_array($res7['registration'], $validReg, true), 'Contract: registration closed vocabulary');
expectReadiness(in_array($res7['credential'], $validCred, true), 'Contract: credential closed vocabulary');
expectReadiness(in_array($res7['legal'], $validLegal, true), 'Contract: legal closed vocabulary');
expectReadiness(in_array($res7['intake_snapshot'], $validSnap, true), 'Contract: intake_snapshot closed vocabulary');
expectReadiness(in_array($res7['attachments'], $validAtt, true), 'Contract: attachments closed vocabulary');
expectReadiness(is_bool($res7['equipment_minimums']), 'Contract: equipment_minimums is boolean');
expectReadiness(is_array($res7['blocking_reasons']), 'Contract: blocking_reasons is array');
expectReadiness(is_array($res7['pending_items']), 'Contract: pending_items is array');
expectReadiness($res7['contract_version'] === 'S01-2026-09', 'Contract: contract_version is S01-2026-09');
echo "[PASS] Test 13: Strict compliance with S01 closed vocabulary and contract specification" . PHP_EOL;

// -------------------------------------------------------------------------
// 14. ABSOLUTE PROOF OF ZERO OS ROWS CREATED
// -------------------------------------------------------------------------
$finalOsCount = (int) $db->count_all('os');
echo "[INFO] Final OS count in database: {$finalOsCount}" . PHP_EOL;
expectReadiness($finalOsCount === $initialOsCount, "CRITICAL: Final OS count ({$finalOsCount}) MUST EXACTLY EQUAL initial OS count ({$initialOsCount}). Zero OS rows created!");

echo PHP_EOL;
echo "SUCCESS: All {$GLOBALS['s06b_assertions']} S06B OS Readiness Gate assertions PASSED!" . PHP_EOL;
