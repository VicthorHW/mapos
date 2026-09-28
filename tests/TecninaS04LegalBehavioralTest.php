<?php

/**
 * CIAO-S04 Legal Acceptance Behavioral Test Suite
 * Tests against real MySQL DEV database:
 * - Legal catalog versioning & hashes
 * - Manifestation creation in ledger
 * - Exact idempotency replay
 * - Order-independent replay (reversed items)
 * - Semantic idempotency conflict (different version, different action, different subject)
 * - Real stale-version race condition (insert new PUBLISHED version, verify 409 legal_version_changed)
 * - Non-secret capability fingerprint verification (raw bearer token never persisted)
 * - Append-only MySQL triggers (UPDATE & DELETE strictly blocked)
 */

$GLOBALS['assertions'] = 0;

function expectLegal($condition, $message) {
    ++$GLOBALS['assertions'];
    if (! $condition) {
        fwrite(STDERR, "[FAIL] " . $message . PHP_EOL);
        exit(1);
    }
}

$model = isset($this) && isset($this->Tecnina_legal_model) ? $this->Tecnina_legal_model : null;
$db = isset($this) && isset($this->db) ? $this->db : null;

if (! $model || ! $db) {
    fwrite(STDERR, "FATAL: Test must run within CodeIgniter S04_legal_test_runner context.\n");
    exit(1);
}

echo "=== S04 Legal Acceptance Behavioral Test Suite ===" . PHP_EOL;

// 1. Current legal catalog versions
$catalog = $model->getCurrentVersions();
expectLegal($catalog['ok'] === true, 'getCurrentVersions must return ok: true');
expectLegal(isset($catalog['versions']['TERMS_OF_USE']), 'TERMS_OF_USE must be in catalog');
expectLegal(isset($catalog['versions']['PRIVACY_POLICY']), 'PRIVACY_POLICY must be in catalog');

$termsVer = $catalog['versions']['TERMS_OF_USE'];
$privacyVer = $catalog['versions']['PRIVACY_POLICY'];

expectLegal($termsVer['version'] === '1.0', 'Terms version must be 1.0');
expectLegal($privacyVer['version'] === '1.0', 'Privacy version must be 1.0');
expectLegal($termsVer['content_hash'] === 'de0936f1e54fc9f005202752e66c1ec659035a57fa2dd7e859acd28b458ebd82', 'Terms SHA-256 hash must match canonical');
expectLegal($privacyVer['content_hash'] === '4b15348c1a7b30ddb102e44f18b8c72a5b21be9072c3e9a09c3d9c1ab0b30079', 'Privacy SHA-256 hash must match canonical');
expectLegal($termsVer['required_action'] === 'ACCEPTED', 'Terms action must be ACCEPTED');
expectLegal($privacyVer['required_action'] === 'ACKNOWLEDGED', 'Privacy action must be ACKNOWLEDGED');
expectLegal($termsVer['public_url'] === 'https://tecnina.com/termos-de-uso', 'Terms public_url must match');
expectLegal($privacyVer['public_url'] === 'https://tecnina.com/politica-de-privacidade', 'Privacy public_url must match');

$termsId = (int) $termsVer['document_version_id'];
$privacyId = (int) $privacyVer['document_version_id'];

// 2. Manifestation Creation
$intakeId = '11112222-3333-4444-5555-' . bin2hex(random_bytes(6));
$rawBearerSecret = 'bearer-test-secret-' . bin2hex(random_bytes(10));
$capFingerprint = hash('sha256', $rawBearerSecret);
$idempotencyKey = 'test-s04-key-' . bin2hex(random_bytes(8));

$payload = [
    'subject' => ['intake_id' => $intakeId, 'client_id' => null],
    'capability_id' => $capFingerprint,
    'idempotency_key' => $idempotencyKey,
    'source' => 'customer_registration',
    'manifestations' => [
        ['document_version_id' => $termsId, 'action' => 'ACCEPTED'],
        ['document_version_id' => $privacyId, 'action' => 'ACKNOWLEDGED'],
    ],
    'client_ip' => '10.0.12.100',
    'user_agent' => 'phpunit-behavioral-runner',
];

$res = $model->recordManifestations($payload);
expectLegal($res['ok'] === true, 'Initial manifestation recording must succeed');
expectLegal($res['replayed'] === false, 'Initial recording must not be replayed');
expectLegal(count($res['event_ids']) === 2, 'Must record 2 events');
expectLegal(count($res['events']) === 2, 'Must return 2 event descriptors');

$firstEventIds = $res['event_ids'];

// 3. Exact Replay
$replay = $model->recordManifestations($payload);
expectLegal($replay['ok'] === true, 'Exact replay must succeed');
expectLegal($replay['replayed'] === true, 'Exact replay must have replayed: true');
expectLegal($replay['event_ids'] === $firstEventIds, 'Replayed event IDs must match exactly');

// 4. Reversed Order Replay (order-independent semantic matching)
$revPayload = $payload;
$revPayload['manifestations'] = array_reverse($payload['manifestations']);
$revReplay = $model->recordManifestations($revPayload);
expectLegal($revReplay['ok'] === true, 'Reversed order replay must succeed');
expectLegal($revReplay['replayed'] === true, 'Reversed order replay must have replayed: true');
expectLegal($revReplay['event_ids'] === $firstEventIds, 'Reversed order replay must return same event IDs');

// 5. Semantic Idempotency Conflict - Different Version ID
$conflictVerPayload = $payload;
$conflictVerPayload['manifestations'] = [
    ['document_version_id' => 999999, 'action' => 'ACCEPTED'],
    ['document_version_id' => $privacyId, 'action' => 'ACKNOWLEDGED'],
];
$conflictVer = $model->recordManifestations($conflictVerPayload);
expectLegal($conflictVer['ok'] === false, 'Same key with different version must be rejected');
expectLegal($conflictVer['reason'] === 'idempotency_conflict', 'Different version must fail with idempotency_conflict');

// 6. Semantic Idempotency Conflict - Different Action
$conflictActPayload = $payload;
$conflictActPayload['manifestations'] = [
    ['document_version_id' => $termsId, 'action' => 'ACCEPTED'],
    ['document_version_id' => $privacyId, 'action' => 'ACCEPTED'], // Invalid action for Privacy
];
$conflictAct = $model->recordManifestations($conflictActPayload);
expectLegal($conflictAct['ok'] === false, 'Same key with different action must be rejected');
expectLegal($conflictAct['reason'] === 'idempotency_conflict', 'Different action must fail with idempotency_conflict');

// 7. Semantic Idempotency Conflict - Different Subject
$conflictSubPayload = $payload;
$conflictSubPayload['subject'] = ['intake_id' => '00000000-0000-0000-0000-000000000000', 'client_id' => null];
$conflictSub = $model->recordManifestations($conflictSubPayload);
expectLegal($conflictSub['ok'] === false, 'Same key with different subject must be rejected');
expectLegal($conflictSub['reason'] === 'idempotency_conflict', 'Different subject must fail with idempotency_conflict');

// 8. Non-Secret Capability Reference Verification
$persistedRows = $db->get_where('tecnina_legal_acceptances', ['intake_id' => $intakeId])->result_array();
expectLegal(count($persistedRows) === 2, 'Exactly 2 rows persisted for this intake');
foreach ($persistedRows as $row) {
    expectLegal($row['capability_id'] === $capFingerprint, 'Stored capability_id must match SHA-256 fingerprint');
    expectLegal($row['capability_id'] !== $rawBearerSecret, 'Stored capability_id must NEVER be raw bearer secret');
    expectLegal(strlen($row['capability_id']) === 64, 'Stored capability_id must be 64-char hex string');
}

// Ensure raw secret is nowhere in the table
$leakCheck = $db->query("SELECT COUNT(*) as cnt FROM tecnina_legal_acceptances WHERE capability_id = ?", [$rawBearerSecret])->row()->cnt;
expectLegal((int) $leakCheck === 0, 'Raw bearer token must not exist in legal ledger');

// 9. Real Stale-Version Race Condition
// Insert a real, valid version 2.0 with future/current effective_at
$nowStr = gmdate('Y-m-d H:i:s');
$v2Hash = hash('sha256', "Terms 2.0 Material Revision Content\n");
$db->insert('tecnina_legal_document_versions', [
    'document_type' => 'TERMS_OF_USE',
    'version' => '2.0',
    'canonical_content' => "Terms 2.0 Material Revision Content\n",
    'content_hash' => $v2Hash,
    'public_url' => 'https://tecnina.com/termos-de-uso',
    'status' => 'PUBLISHED',
    'effective_at' => $nowStr,
    'published_at' => $nowStr,
    'requires_new_acceptance' => 1,
    'communication_required' => 0,
]);
$v2Id = (int) $db->insert_id();

try {
    // Now the active Terms version is 2.0!
    $newCatalog = $model->getCurrentVersions();
    expectLegal((int) $newCatalog['versions']['TERMS_OF_USE']['document_version_id'] === $v2Id, 'New version 2.0 is now effective');

    // Attempt to submit manifestations using the OLD version 1.0 (simulating stale form submission)
    $staleIntakeId = 'stale-intake-' . bin2hex(random_bytes(6));
    $staleKey = 'stale-key-' . bin2hex(random_bytes(8));
    $stalePayload = [
        'subject' => ['intake_id' => $staleIntakeId, 'client_id' => null],
        'capability_id' => hash('sha256', 'some-cap'),
        'idempotency_key' => $staleKey,
        'source' => 'customer_registration',
        'manifestations' => [
            ['document_version_id' => $termsId, 'action' => 'ACCEPTED'], // STALE version 1.0!
            ['document_version_id' => $privacyId, 'action' => 'ACKNOWLEDGED'],
        ],
    ];

    $countBefore = (int) $db->query("SELECT COUNT(*) as cnt FROM tecnina_legal_acceptances WHERE intake_id = ?", [$staleIntakeId])->row()->cnt;
    expectLegal($countBefore === 0, 'No rows before stale submission');

    $staleRes = $model->recordManifestations($stalePayload);
    expectLegal($staleRes['ok'] === false, 'Stale version submission must be rejected');
    expectLegal($staleRes['reason'] === 'legal_version_changed', 'Stale version must return reason: legal_version_changed');

    // Verify ZERO manifestations were recorded!
    $countAfter = (int) $db->query("SELECT COUNT(*) as cnt FROM tecnina_legal_acceptances WHERE intake_id = ?", [$staleIntakeId])->row()->cnt;
    expectLegal($countAfter === 0, 'Exactly zero manifestations recorded during legal_version_changed race');
} finally {
    // Clean up temporary v2 record
    $db->delete('tecnina_legal_document_versions', ['id' => $v2Id]);
}

// Verify catalog is restored to v1.0
$restoredCatalog = $model->getCurrentVersions();
expectLegal((int) $restoredCatalog['versions']['TERMS_OF_USE']['document_version_id'] === $termsId, 'Terms restored to v1.0');

// 10. Append-Only Trigger Enforcement (UPDATE & DELETE blocked)
$anyRow = $db->get('tecnina_legal_acceptances', 1)->row();
expectLegal($anyRow !== null, 'Must have at least one acceptance row to test triggers');

$origDebug = $db->db_debug;
$db->db_debug = false;

$db->query("UPDATE tecnina_legal_acceptances SET action = 'ACCEPTED' WHERE id = ?", [$anyRow->id]);
$updateErr = $db->error();
$updateBlocked = (strpos($updateErr['message'] ?? '', 'tecnina_legal_acceptances is append-only') !== false);
expectLegal($updateBlocked, 'Trigger trg_tecnina_legal_acceptances_no_update must block UPDATE');

$db->query("DELETE FROM tecnina_legal_acceptances WHERE id = ?", [$anyRow->id]);
$deleteErr = $db->error();
$deleteBlocked = (strpos($deleteErr['message'] ?? '', 'tecnina_legal_acceptances is append-only') !== false);
expectLegal($deleteBlocked, 'Trigger trg_tecnina_legal_acceptances_no_delete must block DELETE');

$db->db_debug = $origDebug;

echo "TecninaS04LegalBehavioralTest: {$GLOBALS['assertions']} assertions passed successfully!" . PHP_EOL;
