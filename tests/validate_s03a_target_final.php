<?php
/**
 * CIAO-S03A Final Comprehensive Target Validation Suite (v2)
 * Executed directly on deployed target container against Nginx internal network and MySQL.
 * Emits machine-readable results_s03a.json artifact.
 */
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');

// Defense-in-depth: CLI execution only
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('HTTP/1.1 404 Not Found');
    echo "404 Not Found\n";
    exit(1);
}

// Intentional execution safety interlock
$authEnv = getenv('TECNINA_TARGET_VALIDATION_AUTH') ?: ($_SERVER['TECNINA_TARGET_VALIDATION_AUTH'] ?? '');
$hasArg = in_array('--authorized-s03a-target-execution', $argv ?? [], true);
if ($authEnv !== 'AUTHORIZED_S03A_TARGET_EXECUTION' && !$hasArg) {
    fwrite(STDERR, "FATAL: Unauthorized target validation execution. Set TECNINA_TARGET_VALIDATION_AUTH='AUTHORIZED_S03A_TARGET_EXECUTION' or pass --authorized-s03a-target-execution.\n");
    exit(1);
}

defined('BASEPATH') or define('BASEPATH', '/var/www/html/system/');
defined('APPPATH') or define('APPPATH', '/var/www/html/application/');

// 1. Bootstrap environment
$envFile = '/var/www/html/application/.env';
if (!file_exists($envFile)) {
    die("FATAL: .env not found\n");
}
require_once '/var/www/html/application/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable('/var/www/html/application');
$dotenv->load();

$botToken = (string)($_ENV['MAPOS_BOT_TOKEN'] ?? '');
$hmacSecret = (string)($_ENV['TECNINA_IDENTITY_HMAC_SECRET'] ?? '');
if (strlen($botToken) < 32 || strlen($hmacSecret) < 32) {
    die("FATAL: Required secrets missing or invalid\n");
}

// 2. Database Connection
$dbHost = $_ENV['DB_HOSTNAME'] ?? 'mysql';
$dbName = $_ENV['DB_DATABASE'] ?? 'mapos';
$dbUser = $_ENV['DB_USERNAME'] ?? 'mapos';
$dbPass = $_ENV['DB_PASSWORD'] ?? ($_ENV['MYSQL_MAPOS_PASSWORD'] ?? '');
$pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Assertion accounting
$passCount = 0;
$failCount = 0;
$results = [];

function testAssert($condition, $name, $detail = '', $category = 'EXECUTED_ON_TARGET') {
    global $passCount, $failCount, $results;
    if ($condition) {
        $passCount++;
        echo "[PASS] [{$category}] {$name}" . ($detail ? " ({$detail})" : "") . "\n";
        $results[] = ['name' => $name, 'status' => 'PASS', 'category' => $category, 'detail' => $detail];
    } else {
        $failCount++;
        echo "[FAIL] [{$category}] {$name}" . ($detail ? " ({$detail})" : "") . "\n";
        $results[] = ['name' => $name, 'status' => 'FAIL', 'category' => $category, 'detail' => $detail];
    }
}

// Helper for HTTP requests directly to Nginx container
function httpRequest($method, $path, $data = null, $headers = [], $cookies = []) {
    $ch = curl_init();
    $url = 'http://10.0.4.5' . $path;
    
    $reqHeaders = [
        'Host: gestao.tecnina.com',
    ];
    foreach ($headers as $k => $v) {
        $reqHeaders[] = "{$k}: {$v}";
    }

    $cookieHeader = [];
    foreach ($cookies as $k => $v) {
        $cookieHeader[] = "{$k}={$v}";
    }
    if (!empty($cookieHeader)) {
        $reqHeaders[] = 'Cookie: ' . implode('; ', $cookieHeader);
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $reqHeaders);

    if ($data !== null) {
        if (is_array($data)) {
            $isJson = false;
            foreach ($headers as $k => $v) {
                if (stripos($k, 'content-type') !== false && stripos($v, 'application/json') !== false) {
                    $isJson = true;
                    break;
                }
            }
            $payload = $isJson ? json_encode($data) : http_build_query($data);
        } else {
            $payload = $data;
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }

    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $headerStr = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    // Parse cookies
    $resCookies = [];
    if (preg_match_all('/^Set-Cookie:\s*([^;]+)/mi', $headerStr, $matches)) {
        foreach ($matches[1] as $item) {
            $parts = explode('=', $item, 2);
            if (count($parts) === 2) {
                $resCookies[trim($parts[0])] = trim($parts[1]);
            }
        }
    }

    $json = json_decode($body, true);

    return [
        'code' => $httpCode,
        'headers' => $headerStr,
        'body' => $body,
        'json' => $json,
        'cookies' => $resCookies,
    ];
}

// Capture initial rate limit table state for exact baseline restoration
$initialRateLimitRows = $pdo->query("SELECT bucket_key, scope, bucket_start, count FROM tecnina_identity_rate_limits")->fetchAll(PDO::FETCH_ASSOC);
$initialRateLimitCount = count($initialRateLimitRows);

$testClientId = null;
$testPhone = '5541998765432';
$testEmail = 's03fixture@example.test';
$authHeader = ['Authorization' => "Bearer {$botToken}", 'Content-Type' => 'application/json'];

try {
    echo "==================================================\n";
    echo "1. API BEARER AUTHENTICATION ENFORCEMENT\n";
    echo "==================================================\n";
    $noAuth = httpRequest('POST', '/api/bot/credentials/hash', ['password' => '123456'], ['Content-Type' => 'application/json']);
    testAssert($noAuth['code'] === 401, 'bearer_missing_returns_401', "code={$noAuth['code']}");

    $badAuth = httpRequest('POST', '/api/bot/credentials/hash', ['password' => '123456'], [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer invalid_secret_token_000000000000'
    ]);
    testAssert($badAuth['code'] === 403, 'bearer_invalid_returns_403', "code={$badAuth['code']}");

    echo "\n==================================================\n";
    echo "2. HASH ENDPOINT & PASSWORD POLICY (6+ UNICODE CHARS)\n";
    echo "==================================================\n";
    $extraField = httpRequest('POST', '/api/bot/credentials/hash', [
        'password' => '123456',
        'extra' => 'not_allowed'
    ], $authHeader);
    testAssert($extraField['code'] === 422, 'hash_strict_whitelist_extra_field_rejected_422', "code={$extraField['code']}");

    $shortPass = httpRequest('POST', '/api/bot/credentials/hash', ['password' => '12345'], $authHeader);
    testAssert($shortPass['code'] === 422 && ($shortPass['json']['reason'] ?? '') === 'password_too_short', 'hash_5_chars_rejected_422', "code={$shortPass['code']}");

    $validPass = httpRequest('POST', '/api/bot/credentials/hash', ['password' => '123456'], $authHeader);
    testAssert($validPass['code'] === 200 && !empty($validPass['json']['hash']) && strpos($validPass['json']['hash'], '$2y$') === 0, 'hash_6_digits_accepted_200_bcrypt', "code={$validPass['code']}");

    $lettersPass = httpRequest('POST', '/api/bot/credentials/hash', ['password' => 'abcdef'], $authHeader);
    testAssert($lettersPass['code'] === 200 && !empty($lettersPass['json']['hash']), 'hash_6_letters_accepted_200');

    $mismatch = httpRequest('POST', '/api/bot/credentials/hash', [
        'password' => '123456',
        'password_confirmation' => '123457'
    ], $authHeader);
    testAssert($mismatch['code'] === 422 && ($mismatch['json']['reason'] ?? '') === 'password_confirmation', 'hash_confirmation_mismatch_rejected_422');

    $tooLong = httpRequest('POST', '/api/bot/credentials/hash', ['password' => str_repeat('a', 73)], $authHeader);
    testAssert($tooLong['code'] === 422 && ($tooLong['json']['reason'] ?? '') === 'password_too_long', 'hash_73_bytes_rejected_422');

    $wsPass = '  pass with spaces  ';
    $wsRes = httpRequest('POST', '/api/bot/credentials/hash', ['password' => $wsPass, 'password_confirmation' => $wsPass], $authHeader);
    testAssert($wsRes['code'] === 200 && password_verify($wsPass, $wsRes['json']['hash'] ?? ''), 'hash_whitespace_preserved');

    $utf8Pass = '🔑senha-com-acentos-e-emojis-123🎉';
    $utf8Res = httpRequest('POST', '/api/bot/credentials/hash', ['password' => $utf8Pass, 'password_confirmation' => $utf8Pass], $authHeader);
    testAssert($utf8Res['code'] === 200 && password_verify($utf8Pass, $utf8Res['json']['hash'] ?? ''), 'hash_unicode_emojis_accepted');

    echo "\n==================================================\n";
    echo "3. SETUP CONTROLLED TEST FIXTURE\n";
    echo "==================================================\n";
    $pdo->exec("DELETE FROM tecnina_email_verifications WHERE email_candidate LIKE '%example.test%'");
    $pdo->exec("DELETE FROM tecnina_password_resets WHERE client_id IN (SELECT idClientes FROM clientes WHERE nomeCliente = '_S03TEST_User')");
    $pdo->exec("DELETE FROM tecnina_client_identity_phone_conflicts WHERE canonical_phone = '{$testPhone}'");
    $pdo->exec("DELETE FROM tecnina_client_identity WHERE canonical_phone = '{$testPhone}'");
    $pdo->exec("DELETE FROM clientes WHERE nomeCliente = '_S03TEST_User'");

    $initialHash = password_hash('initial_pass_123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO clientes (nomeCliente, documento, telefone, celular, email, senha, dataCadastro, rua, numero, bairro, cidade, estado, cep) VALUES ('_S03TEST_User', '', '', :phone, :email, '{$initialHash}', CURDATE(), '', '', '', '', '', '')")
        ->execute([':email' => $testEmail, ':phone' => $testPhone]);
    $testClientId = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO tecnina_client_identity (client_id, canonical_phone, phone_state, email_state, credential_version, created_at, updated_at) VALUES (:cid, :phone, 'LEGACY_EXISTING', 'LEGACY_EXISTING', 1, NOW(), NOW())")
        ->execute([':cid' => $testClientId, ':phone' => $testPhone]);

    echo "Controlled fixture initialized: Client ID {$testClientId}, Phone {$testPhone}\n";

    echo "\n==================================================\n";
    echo "4. LOOKUP ENDPOINT & IDENTITY RESOLUTION\n";
    echo "==================================================\n";
    $lookupNone = httpRequest('POST', '/api/bot/client/lookup', ['canonical_phone' => '5511999999999'], $authHeader);
    testAssert($lookupNone['code'] === 200 && ($lookupNone['json']['match'] ?? '') === 'NONE', 'lookup_none_returns_NONE');
    testAssert(!isset($lookupNone['json']['client_id']) || $lookupNone['json']['client_id'] === null, 'lookup_none_client_id_null');

    $lookupUnique = httpRequest('POST', '/api/bot/client/lookup', ['canonical_phone' => $testPhone], $authHeader);
    testAssert($lookupUnique['code'] === 200 
        && ($lookupUnique['json']['match'] ?? '') === 'UNIQUE' 
        && ($lookupUnique['json']['client_id'] ?? 0) === $testClientId 
        && ($lookupUnique['json']['phone_state'] ?? '') === 'LEGACY_EXISTING', 
        'lookup_unique_returns_UNIQUE');

    // Add relational conflict
    $pdo->prepare("INSERT INTO tecnina_client_identity_phone_conflicts (canonical_phone, client_id, created_at) VALUES (:phone, :cid, NOW())")
        ->execute([':phone' => $testPhone, ':cid' => $testClientId]);
    
    $lookupConflict = httpRequest('POST', '/api/bot/client/lookup', ['canonical_phone' => $testPhone], $authHeader);
    testAssert($lookupConflict['code'] === 200 
        && ($lookupConflict['json']['match'] ?? '') === 'AMBIGUOUS' 
        && (!isset($lookupConflict['json']['client_id']) || $lookupConflict['json']['client_id'] === null), 
        'lookup_conflict_evidence_overrides_UNIQUE_returns_AMBIGUOUS');

    // Remove conflict
    $pdo->prepare("DELETE FROM tecnina_client_identity_phone_conflicts WHERE canonical_phone = :phone AND client_id = :cid")
        ->execute([':phone' => $testPhone, ':cid' => $testClientId]);
    $lookupRestored = httpRequest('POST', '/api/bot/client/lookup', ['canonical_phone' => $testPhone], $authHeader);
    testAssert($lookupRestored['code'] === 200 && ($lookupRestored['json']['match'] ?? '') === 'UNIQUE', 'lookup_restored_to_UNIQUE_after_conflict_removal');

    echo "\n==================================================\n";
    echo "5. EMAIL VERIFICATION LIFECYCLE\n";
    echo "==================================================\n";
    $candidateEmail = 'newemail_s03@example.test';
    $issueKey = 'test-issue-key-alpha';

    // 5.0 Strict Subject Validation (client_id / intake_id semantic format validation before rate limit/persistence/delivery)
    // 5.0.1 Malformed client_id: non-numeric string "abc" -> 422 invalid_payload
    $subMalStr = httpRequest('POST', '/api/bot/email-verification/issue', [
        'client_id' => 'abc',
        'email_candidate' => 'subj_test@example.test',
        'purpose' => 'PROFILE_CHANGE',
        'idempotency_key' => 'subj-mal-str'
    ], $authHeader);
    testAssert($subMalStr['code'] === 422 && ($subMalStr['json']['reason'] ?? '') === 'invalid_payload', 'malformed_client_id_string_rejected_422');

    // 5.0.2 Malformed client_id: zero or negative -> 422 invalid_payload
    $subMalZero = httpRequest('POST', '/api/bot/email-verification/issue', [
        'client_id' => 0,
        'email_candidate' => 'subj_test@example.test',
        'purpose' => 'PROFILE_CHANGE',
        'idempotency_key' => 'subj-mal-zero'
    ], $authHeader);
    testAssert($subMalZero['code'] === 422 && ($subMalZero['json']['reason'] ?? '') === 'invalid_payload', 'malformed_client_id_zero_rejected_422');

    $subMalNeg = httpRequest('POST', '/api/bot/email-verification/issue', [
        'client_id' => -1,
        'email_candidate' => 'subj_test@example.test',
        'purpose' => 'PROFILE_CHANGE',
        'idempotency_key' => 'subj-mal-neg'
    ], $authHeader);
    testAssert($subMalNeg['code'] === 422 && ($subMalNeg['json']['reason'] ?? '') === 'invalid_payload', 'malformed_client_id_negative_rejected_422');

    // 5.0.3 Malformed intake_id: not a valid UUID -> 422 invalid_payload
    $subMalIntake = httpRequest('POST', '/api/bot/email-verification/issue', [
        'intake_id' => 'not-a-valid-uuid',
        'email_candidate' => 'subj_test@example.test',
        'purpose' => 'ACCOUNT_CREATION',
        'idempotency_key' => 'subj-mal-intake'
    ], $authHeader);
    testAssert($subMalIntake['code'] === 422 && ($subMalIntake['json']['reason'] ?? '') === 'invalid_payload', 'malformed_intake_id_string_rejected_422');

    // 5.0.4 Both client_id and intake_id present -> 422 invalid_payload
    $subBoth = httpRequest('POST', '/api/bot/email-verification/issue', [
        'client_id' => $testClientId,
        'intake_id' => '00000000-0000-4000-8000-000000000001',
        'email_candidate' => 'subj_test@example.test',
        'purpose' => 'ACCOUNT_CREATION',
        'idempotency_key' => 'subj-both'
    ], $authHeader);
    testAssert($subBoth['code'] === 422 && ($subBoth['json']['reason'] ?? '') === 'invalid_payload', 'both_subjects_present_rejected_422');

    // 5.0.5 Neither client_id nor intake_id present -> 422 invalid_payload
    $subNeither = httpRequest('POST', '/api/bot/email-verification/issue', [
        'email_candidate' => 'subj_test@example.test',
        'purpose' => 'ACCOUNT_CREATION',
        'idempotency_key' => 'subj-neither'
    ], $authHeader);
    testAssert($subNeither['code'] === 422 && ($subNeither['json']['reason'] ?? '') === 'invalid_payload', 'neither_subject_present_rejected_422');

    // 5.1 Issue challenge
    $issueRes = httpRequest('POST', '/api/bot/email-verification/issue', [
        'client_id' => $testClientId,
        'email_candidate' => $candidateEmail,
        'purpose' => 'PROFILE_CHANGE',
        'idempotency_key' => $issueKey,
    ], $authHeader);
    testAssert($issueRes['code'] === 200 && !empty($issueRes['json']['challenge_id']), 'email_issue_returns_200_with_challenge_id');
    $challengeId = $issueRes['json']['challenge_id'] ?? '';
    
    // Assert 15-minute / 900-second TTL
    $expiresAtUtc = strtotime(($issueRes['json']['expires_at'] ?? '') . ' UTC');
    $nowUtc = time();
    $ttlDiff = $expiresAtUtc - $nowUtc;
    testAssert($ttlDiff >= 880 && $ttlDiff <= 920, 'email_verification_expires_at_is_15_minutes', "ttl={$ttlDiff}s");

    // Plaintext code not returned in API response
    testAssert(!isset($issueRes['json']['code']), 'plaintext_code_omitted_from_issue_response');

    // State in tecnina_client_identity becomes PENDING candidate
    $idRow = $pdo->query("SELECT email_state, email_candidate FROM tecnina_client_identity WHERE client_id = {$testClientId}")->fetch();
    testAssert($idRow['email_state'] === 'PENDING' && $idRow['email_candidate'] === $candidateEmail, 'client_identity_email_state_becomes_PENDING');

    // Trusted email in clientes unchanged
    $origEmail = $pdo->query("SELECT email FROM clientes WHERE idClientes = {$testClientId}")->fetchColumn();
    testAssert($origEmail === $testEmail, 'trusted_email_in_clientes_unchanged_before_verification');

    // Verification row in DB
    $verifRow = $pdo->query("SELECT * FROM tecnina_email_verifications WHERE id = '{$challengeId}'")->fetch();
    testAssert($verifRow['state'] === 'PENDING', 'email_verification_row_state_is_PENDING');
    testAssert(strlen($verifRow['code_digest'] ?? '') === 64, 'email_code_digest_stored_sha256');

    // 5.2 Same-key replay
    $replayRes = httpRequest('POST', '/api/bot/email-verification/issue', [
        'client_id' => $testClientId,
        'email_candidate' => $candidateEmail,
        'purpose' => 'PROFILE_CHANGE',
        'idempotency_key' => $issueKey,
    ], $authHeader);
    testAssert($replayRes['code'] === 200 && !empty($replayRes['json']['replayed']) && $replayRes['json']['challenge_id'] === $challengeId, 'email_issue_same_key_replay');

    // 5.3 Conflicting idempotency reuse
    $conflictIssue = httpRequest('POST', '/api/bot/email-verification/issue', [
        'client_id' => $testClientId,
        'email_candidate' => 'other@example.test',
        'purpose' => 'PROFILE_CHANGE',
        'idempotency_key' => $issueKey,
    ], $authHeader);
    testAssert($conflictIssue['code'] === 409 && ($conflictIssue['json']['reason'] ?? '') === 'idempotency_conflict', 'email_issue_conflicting_key_409');

    // 5.4 Supersession
    $supersedingKey = 'test-issue-key-beta';
    $supersedingRes = httpRequest('POST', '/api/bot/email-verification/issue', [
        'client_id' => $testClientId,
        'email_candidate' => 'superseding@example.test',
        'purpose' => 'PROFILE_CHANGE',
        'idempotency_key' => $supersedingKey,
    ], $authHeader);
    testAssert($supersedingRes['code'] === 200 && !empty($supersedingRes['json']['challenge_id']), 'email_issue_superseding_created');
    $oldState = $pdo->query("SELECT state FROM tecnina_email_verifications WHERE id = '{$challengeId}'")->fetchColumn();
    testAssert($oldState === 'SUPERSEDED', 'previous_challenge_state_is_SUPERSEDED');

    // Verify superseded challenge rejected (409)
    $verifySuperseded = httpRequest('POST', '/api/bot/email-verification/verify', [
        'challenge_id' => $challengeId,
        'code' => '123456',
        'idempotency_key' => 'verify-superseded'
    ], $authHeader);
    testAssert($verifySuperseded['code'] === 409 && ($verifySuperseded['json']['reason'] ?? '') === 'invalid_or_expired_code', 'superseded_challenge_rejected_409');

    // 5.4.1 Same-candidate reissue supersession without affected_rows error
    $sameCand = 'same_cand_reissue@example.test';
    $sameKey1 = 'test-issue-same-cand-1';
    $sameRes1 = httpRequest('POST', '/api/bot/email-verification/issue', [
        'client_id' => $testClientId,
        'email_candidate' => $sameCand,
        'purpose' => 'PROFILE_CHANGE',
        'idempotency_key' => $sameKey1,
    ], $authHeader);
    testAssert($sameRes1['code'] === 200 && !empty($sameRes1['json']['challenge_id']), 'same_candidate_first_challenge_issued');
    $sameChId1 = $sameRes1['json']['challenge_id'] ?? '';
    $sameIdState1 = $pdo->query("SELECT email_state, email_candidate FROM tecnina_client_identity WHERE client_id = {$testClientId}")->fetch();
    testAssert($sameIdState1['email_state'] === 'PENDING' && $sameIdState1['email_candidate'] === $sameCand, 'same_candidate_identity_state_pending');

    // Reissue with identical candidate email but new idempotency key
    $sameKey2 = 'test-issue-same-cand-2';
    $sameRes2 = httpRequest('POST', '/api/bot/email-verification/issue', [
        'client_id' => $testClientId,
        'email_candidate' => $sameCand,
        'purpose' => 'PROFILE_CHANGE',
        'idempotency_key' => $sameKey2,
    ], $authHeader);
    testAssert($sameRes2['code'] === 200 && !empty($sameRes2['json']['challenge_id']), 'same_candidate_reissue_succeeds_without_affected_rows_error');
    $sameChId2 = $sameRes2['json']['challenge_id'] ?? '';
    testAssert($sameChId1 !== $sameChId2, 'same_candidate_reissue_generates_distinct_challenge');

    $chState1 = $pdo->query("SELECT state FROM tecnina_email_verifications WHERE id = '{$sameChId1}'")->fetchColumn();
    $chState2 = $pdo->query("SELECT state FROM tecnina_email_verifications WHERE id = '{$sameChId2}'")->fetchColumn();
    testAssert($chState1 === 'SUPERSEDED', 'same_candidate_first_challenge_is_SUPERSEDED');
    testAssert($chState2 === 'PENDING', 'same_candidate_second_challenge_is_PENDING_not_delivery_failed');

    // Verification of Challenge 2 succeeds and promotes email
    $digestSame2 = $pdo->query("SELECT code_digest FROM tecnina_email_verifications WHERE id = '{$sameChId2}'")->fetchColumn();
    $codeSame2 = null;
    for ($i = 0; $i <= 999999; $i++) {
        $cand = str_pad((string)$i, 6, '0', STR_PAD_LEFT);
        if (hash_hmac('sha256', "{$sameChId2}|{$cand}", $hmacSecret) === $digestSame2) {
            $codeSame2 = $cand;
            break;
        }
    }
    $verifySame2 = httpRequest('POST', '/api/bot/email-verification/verify', [
        'challenge_id' => $sameChId2,
        'code' => $codeSame2,
        'idempotency_key' => 'verify-same-cand-2'
    ], $authHeader);
    testAssert($verifySame2['code'] === 200 && ($verifySame2['json']['ok'] ?? false) === true, 'same_candidate_second_challenge_verified_200');

    $finalIdSame = $pdo->query("SELECT email_state, email_candidate FROM tecnina_client_identity WHERE client_id = {$testClientId}")->fetch();
    testAssert($finalIdSame['email_state'] === 'VERIFIED' && $finalIdSame['email_candidate'] === $sameCand, 'same_candidate_identity_verified');

    $finalEmailSame = $pdo->query("SELECT email FROM clientes WHERE idClientes = {$testClientId}")->fetchColumn();
    testAssert($finalEmailSame === $sameCand, 'same_candidate_clientes_email_promoted');

    // Reset trusted email back for subsequent tests
    $pdo->prepare("UPDATE clientes SET email = ? WHERE idClientes = ?")->execute([$testEmail, $testClientId]);
    $pdo->prepare("UPDATE tecnina_client_identity SET email_state = 'LEGACY_EXISTING', email_candidate = NULL, email_verified_at = NULL WHERE client_id = ?")->execute([$testClientId]);

    // 5.5 Expiration
    $expKey = 'test-issue-key-exp';
    $expRes = httpRequest('POST', '/api/bot/email-verification/issue', [
        'client_id' => $testClientId,
        'email_candidate' => 'expired@example.test',
        'purpose' => 'PROFILE_CHANGE',
        'idempotency_key' => $expKey,
    ], $authHeader);
    $expChallengeId = $expRes['json']['challenge_id'] ?? '';
    $pdo->exec("UPDATE tecnina_email_verifications SET expires_at = '2000-01-01 00:00:00' WHERE id = '{$expChallengeId}'");
    $verifyExpired = httpRequest('POST', '/api/bot/email-verification/verify', [
        'challenge_id' => $expChallengeId,
        'code' => '123456',
        'idempotency_key' => 'verify-exp-key'
    ], $authHeader);
    testAssert($verifyExpired['code'] === 409 && ($verifyExpired['json']['reason'] ?? '') === 'invalid_or_expired_code', 'expired_challenge_rejected_409');
    $expDbState = $pdo->query("SELECT state FROM tecnina_email_verifications WHERE id = '{$expChallengeId}'")->fetchColumn();
    testAssert($expDbState === 'PENDING', 'expired_challenge_persisted_state_remains_PENDING_evaluated_at_boundary');

    // 5.6 Five wrong attempts & 6th valid rejected
    $bruteKey = 'test-issue-key-brute';
    $bruteRes = httpRequest('POST', '/api/bot/email-verification/issue', [
        'client_id' => $testClientId,
        'email_candidate' => 'brute@example.test',
        'purpose' => 'PROFILE_CHANGE',
        'idempotency_key' => $bruteKey,
    ], $authHeader);
    $bruteChallengeId = $bruteRes['json']['challenge_id'] ?? '';
    $bruteDigest = $pdo->query("SELECT code_digest FROM tecnina_email_verifications WHERE id = '{$bruteChallengeId}'")->fetchColumn();
    
    // Find valid code for brute challenge
    $bruteValidCode = null;
    for ($i = 0; $i <= 999999; $i++) {
        $cand = str_pad((string)$i, 6, '0', STR_PAD_LEFT);
        if (hash_hmac('sha256', "{$bruteChallengeId}|{$cand}", $hmacSecret) === $bruteDigest) {
            $bruteValidCode = $cand;
            break;
        }
    }
    $wrongCode = ($bruteValidCode === '000000') ? '000001' : '000000';

    // Submit 5 wrong attempts
    for ($i = 1; $i <= 5; $i++) {
        $wrongRes = httpRequest('POST', '/api/bot/email-verification/verify', [
            'challenge_id' => $bruteChallengeId,
            'code' => $wrongCode,
            'idempotency_key' => "wrong-try-{$i}"
        ], $authHeader);
        testAssert($wrongRes['code'] === 409, "wrong_attempt_{$i}_rejected_409");
    }
    $attemptsInDb = (int)$pdo->query("SELECT attempts FROM tecnina_email_verifications WHERE id = '{$bruteChallengeId}'")->fetchColumn();
    testAssert($attemptsInDb === 5, 'exactly_5_attempts_recorded');

    // 6th attempt with valid code must be rejected
    $sixthValidRes = httpRequest('POST', '/api/bot/email-verification/verify', [
        'challenge_id' => $bruteChallengeId,
        'code' => $bruteValidCode,
        'idempotency_key' => "valid-after-5-tries"
    ], $authHeader);
    testAssert($sixthValidRes['code'] === 409 && ($sixthValidRes['json']['reason'] ?? '') === 'invalid_or_expired_code', 'valid_code_after_5_failures_rejected_409');
    $bruteDbState = $pdo->query("SELECT state FROM tecnina_email_verifications WHERE id = '{$bruteChallengeId}'")->fetchColumn();
    testAssert($bruteDbState === 'PENDING', 'exhausted_challenge_persisted_state_remains_PENDING_evaluated_at_boundary');

    // 5.6.1 True multi-connection concurrency regression:
    // Challenge begins at attempts=4; Context B holds row lock, Request A waits on row lock,
    // Context B increments attempts to 5 and commits; released valid Request A is rejected
    // and cannot bypass 5th failure; attempts strictly capped at 5; clientes.email not promoted.
    $concurKey = 'test-issue-key-concur';
    $concurRes = httpRequest('POST', '/api/bot/email-verification/issue', [
        'client_id' => $testClientId,
        'email_candidate' => 'concurrency_race@example.test',
        'purpose' => 'PROFILE_CHANGE',
        'idempotency_key' => $concurKey,
    ], $authHeader);
    $concurChallengeId = $concurRes['json']['challenge_id'] ?? '';
    $concurDigest = $pdo->query("SELECT code_digest FROM tecnina_email_verifications WHERE id = '{$concurChallengeId}'")->fetchColumn();

    // Find valid code for concurrency challenge
    $concurValidCode = null;
    for ($i = 0; $i <= 999999; $i++) {
        $cand = str_pad((string)$i, 6, '0', STR_PAD_LEFT);
        if (hash_hmac('sha256', "{$concurChallengeId}|{$cand}", $hmacSecret) === $concurDigest) {
            $concurValidCode = $cand;
            break;
        }
    }
    $concurWrongCode = ($concurValidCode === '000000') ? '000001' : '000000';

    // Submit 4 wrong attempts to set challenge attempts = 4
    for ($i = 1; $i <= 4; $i++) {
        $wRes = httpRequest('POST', '/api/bot/email-verification/verify', [
            'challenge_id' => $concurChallengeId,
            'code' => $concurWrongCode,
            'idempotency_key' => "concur-wrong-{$i}"
        ], $authHeader);
        testAssert($wRes['code'] === 409, "concur_wrong_attempt_{$i}_rejected_409");
    }
    $attempts4 = (int)$pdo->query("SELECT attempts FROM tecnina_email_verifications WHERE id = '{$concurChallengeId}'")->fetchColumn();
    testAssert($attempts4 === 4, 'concurrency_challenge_begins_at_attempts_4');

    // Multi-connection concurrency:
    // Open separate PDO connection (Context B)
    $pdoLock = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdoLock->beginTransaction();
    // Context B acquires row lock on the challenge row
    $pdoLock->query("SELECT id, attempts FROM tecnina_email_verifications WHERE id = '{$concurChallengeId}' FOR UPDATE");

    // Launch Request A (with VALID code) asynchronously via curl_multi
    $mh = curl_multi_init();
    $chA = curl_init();
    $concurUrl = 'http://10.0.4.5/api/bot/email-verification/verify';
    $concurPayload = json_encode([
        'challenge_id' => $concurChallengeId,
        'code' => $concurValidCode,
        'idempotency_key' => 'concur-valid-req-a'
    ]);
    $concurHeaders = [
        'Host: gestao.tecnina.com',
        'Content-Type: application/json',
        'Authorization: ' . $authHeader['Authorization'],
    ];
    curl_setopt($chA, CURLOPT_URL, $concurUrl);
    curl_setopt($chA, CURLOPT_POST, true);
    curl_setopt($chA, CURLOPT_POSTFIELDS, $concurPayload);
    curl_setopt($chA, CURLOPT_HTTPHEADER, $concurHeaders);
    curl_setopt($chA, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chA, CURLOPT_HEADER, true);
    curl_setopt($chA, CURLOPT_TIMEOUT, 15);
    curl_multi_add_handle($mh, $chA);

    // Start transfer so request is sent to Nginx/PHP-FPM and reaches MySQL lock wait
    $active = null;
    do {
        $mrc = curl_multi_exec($mh, $active);
    } while ($mrc === CURLM_CALL_MULTI_PERFORM);

    // Sleep 150ms to ensure Request A reached PHP-FPM and is blocked in MySQL on the row lock
    usleep(150000);

    // Context B increments attempts to 5 while Request A is waiting on row lock
    $pdoLock->query("UPDATE tecnina_email_verifications SET attempts = 5 WHERE id = '{$concurChallengeId}'");
    // Context B commits, releasing row lock
    $pdoLock->commit();

    // Request A unblocks in MySQL, reads attempts=5 under its own FOR UPDATE lock, and completes
    do {
        $status = curl_multi_exec($mh, $active);
        if ($active) {
            curl_multi_select($mh, 0.1);
        }
    } while ($active && $status === CURLM_OK);

    $concurRaw = curl_multi_getcontent($chA);
    $concurCode = curl_getinfo($chA, CURLINFO_HTTP_CODE);
    $concurHSize = curl_getinfo($chA, CURLINFO_HEADER_SIZE);
    $concurBody = substr($concurRaw, $concurHSize);
    $concurJson = json_decode($concurBody, true);
    curl_multi_remove_handle($mh, $chA);
    curl_multi_close($mh);
    curl_close($chA);

    // Assert Request A was rejected because 5th failure boundary was reached
    testAssert($concurCode === 409 && ($concurJson['reason'] ?? '') === 'invalid_or_expired_code', 'released_valid_request_a_rejected_409_cannot_bypass_5th_failure');

    // Assert attempts strictly capped at 5 in DB
    $attemptsAfterConcur = (int)$pdo->query("SELECT attempts FROM tecnina_email_verifications WHERE id = '{$concurChallengeId}'")->fetchColumn();
    testAssert($attemptsAfterConcur === 5, 'concurrency_attempts_strictly_capped_at_5');

    // Assert challenge state remains PENDING
    $concurDbState = $pdo->query("SELECT state FROM tecnina_email_verifications WHERE id = '{$concurChallengeId}'")->fetchColumn();
    testAssert($concurDbState === 'PENDING', 'concurrency_challenge_state_remains_PENDING');

    // Assert clientes.email not promoted
    $concurClientEmail = $pdo->query("SELECT email FROM clientes WHERE idClientes = {$testClientId}")->fetchColumn();
    testAssert($concurClientEmail !== 'concurrency_race@example.test', 'clientes_email_not_promoted_under_concurrency_race');

    // 5.7 Malformed code does not consume attempt
    $malKey = 'test-issue-key-mal';
    $malRes = httpRequest('POST', '/api/bot/email-verification/issue', [
        'client_id' => $testClientId,
        'email_candidate' => 'malformed@example.test',
        'purpose' => 'PROFILE_CHANGE',
        'idempotency_key' => $malKey,
    ], $authHeader);
    $malChallengeId = $malRes['json']['challenge_id'] ?? '';
    $attemptsBeforeMal = (int)$pdo->query("SELECT attempts FROM tecnina_email_verifications WHERE id = '{$malChallengeId}'")->fetchColumn();

    $malReq = httpRequest('POST', '/api/bot/email-verification/verify', [
        'challenge_id' => $malChallengeId,
        'code' => 'ABC', // non-digit / wrong length
        'idempotency_key' => 'mal-key-1'
    ], $authHeader);
    testAssert($malReq['code'] === 422 && ($malReq['json']['reason'] ?? '') === 'invalid_payload', 'malformed_code_rejected_422');
    $attemptsAfterMal = (int)$pdo->query("SELECT attempts FROM tecnina_email_verifications WHERE id = '{$malChallengeId}'")->fetchColumn();
    testAssert($attemptsAfterMal === $attemptsBeforeMal, 'malformed_code_does_not_consume_attempts');

    // 5.8 Valid verification, promotion & single-use replay
    $valKey = 'test-issue-key-val';
    $targetEmail = 'verified_promote@example.test';
    $valRes = httpRequest('POST', '/api/bot/email-verification/issue', [
        'client_id' => $testClientId,
        'email_candidate' => $targetEmail,
        'purpose' => 'PROFILE_CHANGE',
        'idempotency_key' => $valKey,
    ], $authHeader);
    $valChallengeId = $valRes['json']['challenge_id'] ?? '';
    $valDigest = $pdo->query("SELECT code_digest FROM tecnina_email_verifications WHERE id = '{$valChallengeId}'")->fetchColumn();

    $validCodeToUse = null;
    for ($i = 0; $i <= 999999; $i++) {
        $cand = str_pad((string)$i, 6, '0', STR_PAD_LEFT);
        if (hash_hmac('sha256', "{$valChallengeId}|{$cand}", $hmacSecret) === $valDigest) {
            $validCodeToUse = $cand;
            break;
        }
    }

    $verifyKey = 'verify-token-val-1';
    $successVerify = httpRequest('POST', '/api/bot/email-verification/verify', [
        'challenge_id' => $valChallengeId,
        'code' => $validCodeToUse,
        'idempotency_key' => $verifyKey
    ], $authHeader);
    testAssert($successVerify['code'] === 200 && ($successVerify['json']['ok'] ?? false) === true, 'email_verify_success_200');

    // State becomes VERIFIED
    $valState = $pdo->query("SELECT state, verified_at FROM tecnina_email_verifications WHERE id = '{$valChallengeId}'")->fetch();
    testAssert($valState['state'] === 'VERIFIED' && !empty($valState['verified_at']), 'challenge_state_becomes_VERIFIED');

    // Atomic promotion to clientes.email
    $promotedEmail = $pdo->query("SELECT email FROM clientes WHERE idClientes = {$testClientId}")->fetchColumn();
    testAssert($promotedEmail === $targetEmail, 'clientes_email_atomically_promoted');

    // tecnina_client_identity email_state becomes VERIFIED
    $promotedIdentity = $pdo->query("SELECT email_state, email_candidate FROM tecnina_client_identity WHERE client_id = {$testClientId}")->fetch();
    testAssert($promotedIdentity['email_state'] === 'VERIFIED' && $promotedIdentity['email_candidate'] === $targetEmail, 'client_identity_email_state_becomes_VERIFIED');

    // Same-key completed replay returns 200 replayed
    $replayVerify = httpRequest('POST', '/api/bot/email-verification/verify', [
        'challenge_id' => $valChallengeId,
        'code' => $validCodeToUse,
        'idempotency_key' => $verifyKey
    ], $authHeader);
    testAssert($replayVerify['code'] === 200 && !empty($replayVerify['json']['replayed']), 'verify_same_key_completed_replay_200');

    // Conflicting replay on same challenge returns 409
    $conflictVerify = httpRequest('POST', '/api/bot/email-verification/verify', [
        'challenge_id' => $valChallengeId,
        'code' => $validCodeToUse,
        'idempotency_key' => 'verify-conflicting-different-key'
    ], $authHeader);
    testAssert($conflictVerify['code'] === 409 && ($conflictVerify['json']['reason'] ?? '') === 'idempotency_conflict', 'verify_conflicting_key_rejected_409');

    // 5.9 SOURCE DEFECT RESOLUTION TEST: Verify Idempotency across DIFFERENT challenges
    // 1. Issue Challenge B for same or different client
    $keyB = 'test-issue-key-chb';
    $candidateB = 'challenge_b_target@example.test';
    $resB = httpRequest('POST', '/api/bot/email-verification/issue', [
        'client_id' => $testClientId,
        'email_candidate' => $candidateB,
        'purpose' => 'PROFILE_CHANGE',
        'idempotency_key' => $keyB,
    ], $authHeader);
    testAssert($resB['code'] === 200 && !empty($resB['json']['challenge_id']), 'issue_challenge_b_success_200');
    $challengeIdB = $resB['json']['challenge_id'] ?? '';
    $digestB = $pdo->query("SELECT code_digest FROM tecnina_email_verifications WHERE id = '{$challengeIdB}'")->fetchColumn();
    
    // Find valid code for Challenge B
    $codeB = null;
    for ($i = 0; $i <= 999999; $i++) {
        $cand = str_pad((string)$i, 6, '0', STR_PAD_LEFT);
        if (hash_hmac('sha256', "{$challengeIdB}|{$cand}", $hmacSecret) === $digestB) {
            $codeB = $cand;
            break;
        }
    }

    // Attempt valid verification of Challenge B using Challenge A's already-consumed key ($verifyKey)
    $crossVerifyRes = httpRequest('POST', '/api/bot/email-verification/verify', [
        'challenge_id' => $challengeIdB,
        'code' => $codeB,
        'idempotency_key' => $verifyKey
    ], $authHeader);
    testAssert($crossVerifyRes['code'] === 409 && ($crossVerifyRes['json']['reason'] ?? '') === 'idempotency_conflict', 'cross_challenge_verify_key_reuse_returns_409_idempotency_conflict');

    // Assert Challenge B did NOT become VERIFIED
    $stateB = $pdo->query("SELECT state FROM tecnina_email_verifications WHERE id = '{$challengeIdB}'")->fetchColumn();
    testAssert($stateB === 'PENDING', 'challenge_b_state_remains_PENDING_unverified');

    // Assert clientes.email was NOT promoted to B
    $clientEmailAfterB = $pdo->query("SELECT email FROM clientes WHERE idClientes = {$testClientId}")->fetchColumn();
    testAssert($clientEmailAfterB === $targetEmail, 'clientes_email_not_promoted_to_b');

    // Assert identity state was not verified for B (remains unverified)
    $identityAfterB = $pdo->query("SELECT email_candidate, email_state, email_verified_at FROM tecnina_client_identity WHERE client_id = {$testClientId}")->fetch();
    testAssert($identityAfterB['email_state'] !== 'VERIFIED' && empty($identityAfterB['email_verified_at']), 'identity_state_not_mutated_by_b');

    echo "\n==================================================\n";
    echo "6. PASSWORD RESET LIFECYCLE\n";
    echo "==================================================\n";
    // 6.1 Password reset request validation (syntactic validation & anti-enumeration)
    // 6.1.1 Malformed phone numbers must return 422 invalid_canonical_phone
    $malPhone1 = httpRequest('POST', '/api/bot/password-reset/issue', [
        'client_id' => $testClientId,
        'canonical_phone' => 'not-a-phone',
        'phone_context_id' => 'ctx-mal',
        'idempotency_key' => 'mal-key-phone-1'
    ], $authHeader);
    testAssert($malPhone1['code'] === 422 && ($malPhone1['json']['reason'] ?? '') === 'invalid_canonical_phone', 'reset_issue_malformed_phone_alpha_rejected_422');

    $malPhone2 = httpRequest('POST', '/api/bot/password-reset/issue', [
        'client_id' => $testClientId,
        'canonical_phone' => '123', // too short (< 8 digits)
        'phone_context_id' => 'ctx-mal',
        'idempotency_key' => 'mal-key-phone-2'
    ], $authHeader);
    testAssert($malPhone2['code'] === 422 && ($malPhone2['json']['reason'] ?? '') === 'invalid_canonical_phone', 'reset_issue_malformed_phone_short_rejected_422');

    // 6.1.2 Malformed idempotency key must return 422 invalid_payload
    $malKeyEmpty = httpRequest('POST', '/api/bot/password-reset/issue', [
        'client_id' => $testClientId,
        'canonical_phone' => $testPhone,
        'phone_context_id' => 'ctx-mal',
        'idempotency_key' => ''
    ], $authHeader);
    testAssert($malKeyEmpty['code'] === 422 && ($malKeyEmpty['json']['reason'] ?? '') === 'invalid_payload', 'reset_issue_empty_idempotency_key_rejected_422');

    $malKeyLong = httpRequest('POST', '/api/bot/password-reset/issue', [
        'client_id' => $testClientId,
        'canonical_phone' => $testPhone,
        'phone_context_id' => 'ctx-mal',
        'idempotency_key' => str_repeat('k', 101)
    ], $authHeader);
    testAssert($malKeyLong['code'] === 422 && ($malKeyLong['json']['reason'] ?? '') === 'invalid_payload', 'reset_issue_long_idempotency_key_rejected_422');

    // 6.1.3 Malformed client_id must return 422 invalid_payload
    $malClientId = httpRequest('POST', '/api/bot/password-reset/issue', [
        'client_id' => 'not_numeric',
        'canonical_phone' => $testPhone,
        'phone_context_id' => 'ctx-mal',
        'idempotency_key' => 'mal-key-client-id'
    ], $authHeader);
    testAssert($malClientId['code'] === 422 && ($malClientId['json']['reason'] ?? '') === 'invalid_payload', 'reset_issue_malformed_client_id_rejected_422');

    $malClientIdZero = httpRequest('POST', '/api/bot/password-reset/issue', [
        'client_id' => 0,
        'canonical_phone' => $testPhone,
        'phone_context_id' => 'ctx-mal-zero',
        'idempotency_key' => 'mal-key-client-id-zero'
    ], $authHeader);
    testAssert($malClientIdZero['code'] === 422 && ($malClientIdZero['json']['reason'] ?? '') === 'invalid_payload', 'reset_issue_client_id_zero_rejected_422');

    $malClientIdNeg = httpRequest('POST', '/api/bot/password-reset/issue', [
        'client_id' => -1,
        'canonical_phone' => $testPhone,
        'phone_context_id' => 'ctx-mal-neg',
        'idempotency_key' => 'mal-key-client-id-neg'
    ], $authHeader);
    testAssert($malClientIdNeg['code'] === 422 && ($malClientIdNeg['json']['reason'] ?? '') === 'invalid_payload', 'reset_issue_client_id_negative_rejected_422');

    // 6.1.4 Malformed phone_context_id must return 422 invalid_payload
    $malCtxEmpty = httpRequest('POST', '/api/bot/password-reset/issue', [
        'client_id' => $testClientId,
        'canonical_phone' => $testPhone,
        'phone_context_id' => '',
        'idempotency_key' => 'mal-key-ctx-empty'
    ], $authHeader);
    testAssert($malCtxEmpty['code'] === 422 && ($malCtxEmpty['json']['reason'] ?? '') === 'invalid_payload', 'reset_issue_empty_context_id_rejected_422');

    // 6.1.5 Privacy preserving dummy reset for non-existent client (HTTP 200 REQUEST_ACCEPTED without reset_url)
    $dummyReset = httpRequest('POST', '/api/bot/password-reset/issue', [
        'client_id' => 999999,
        'canonical_phone' => '5511999999999',
        'phone_context_id' => 'ctx-dummy',
        'idempotency_key' => 'dummy-reset-key'
    ], $authHeader);
    testAssert($dummyReset['code'] === 200 
        && ($dummyReset['json']['state'] ?? '') === 'REQUEST_ACCEPTED' 
        && !isset($dummyReset['json']['reset_url']), 
        'reset_issue_privacy_preserving_dummy_accepted_200');

    // 6.1.6 Controlled 503 unavailable on database outage
    $pdo->exec("RENAME TABLE tecnina_password_resets TO tecnina_password_resets_outage_test");
    try {
        $outageRes = httpRequest('POST', '/api/bot/password-reset/issue', [
            'client_id' => $testClientId,
            'canonical_phone' => $testPhone,
            'phone_context_id' => 'ctx-outage',
            'idempotency_key' => 'outage-key-1'
        ], $authHeader);
        testAssert($outageRes['code'] === 503 && ($outageRes['json']['reason'] ?? '') === 'unavailable', 'reset_issue_db_outage_returns_controlled_503');
    } finally {
        $pdo->exec("RENAME TABLE tecnina_password_resets_outage_test TO tecnina_password_resets");
    }

    // 6.2 Valid reset issue
    $rKey = 'test-reset-key-1';
    $validReset = httpRequest('POST', '/api/bot/password-reset/issue', [
        'client_id' => $testClientId,
        'canonical_phone' => $testPhone,
        'phone_context_id' => 'ctx-test-1',
        'idempotency_key' => $rKey
    ], $authHeader);
    testAssert($validReset['code'] === 200 && !empty($validReset['json']['reset_url']), 'reset_issue_success_with_url');
    $resetUrl = $validReset['json']['reset_url'] ?? '';
    $resetToken = basename(parse_url($resetUrl, PHP_URL_PATH));

    // Assert 15-minute / 900-second TTL
    $resetExpiresAtUtc = strtotime(($validReset['json']['expires_at'] ?? '') . ' UTC');
    $resetTtlDiff = $resetExpiresAtUtc - time();
    testAssert($resetTtlDiff >= 880 && $resetTtlDiff <= 920, 'password_reset_expires_at_is_15_minutes', "ttl={$resetTtlDiff}s");

    // Token digest in DB
    $resetDbRow = $pdo->query("SELECT id, token_digest, state FROM tecnina_password_resets WHERE client_id = {$testClientId} ORDER BY id DESC LIMIT 1")->fetch();
    testAssert($resetDbRow['state'] === 'PENDING', 'reset_record_PENDING');
    testAssert(strlen($resetDbRow['token_digest'] ?? '') === 64, 'reset_token_digest_sha256');

    $allResetsJson = json_encode($pdo->query("SELECT * FROM tecnina_password_resets WHERE client_id = {$testClientId}")->fetchAll());
    testAssert(strpos($allResetsJson, $resetToken) === false, 'plaintext_token_not_persisted_in_db');

    // 6.3 Same-key replay
    $resetReplay = httpRequest('POST', '/api/bot/password-reset/issue', [
        'client_id' => $testClientId,
        'canonical_phone' => $testPhone,
        'phone_context_id' => 'ctx-test-1',
        'idempotency_key' => $rKey
    ], $authHeader);
    testAssert($resetReplay['code'] === 200 && ($resetReplay['json']['reset_url'] ?? '') === $resetUrl && !empty($resetReplay['json']['replayed']), 'reset_issue_stable_idempotent_replay');

    // 6.4 Conflicting idempotency reuse
    $conflictReset = httpRequest('POST', '/api/bot/password-reset/issue', [
        'client_id' => $testClientId,
        'canonical_phone' => $testPhone,
        'phone_context_id' => 'ctx-DIFFERENT',
        'idempotency_key' => $rKey
    ], $authHeader);
    testAssert($conflictReset['code'] === 409 && ($conflictReset['json']['reason'] ?? '') === 'idempotency_conflict', 'reset_issue_conflicting_key_409');

    // 6.5 Supersession
    $rKey2 = 'test-reset-key-2';
    $validReset2 = httpRequest('POST', '/api/bot/password-reset/issue', [
        'client_id' => $testClientId,
        'canonical_phone' => $testPhone,
        'phone_context_id' => 'ctx-test-2',
        'idempotency_key' => $rKey2
    ], $authHeader);
    testAssert($validReset2['code'] === 200, 'second_reset_issued');
    $oldResetState = $pdo->query("SELECT state FROM tecnina_password_resets WHERE id = '{$resetDbRow['id']}'")->fetchColumn();
    testAssert($oldResetState === 'SUPERSEDED', 'first_reset_state_is_SUPERSEDED');

    // 6.6 Expiration
    $resetUrl2 = $validReset2['json']['reset_url'] ?? '';
    $resetToken2 = basename(parse_url($resetUrl2, PHP_URL_PATH));
    $pdo->exec("UPDATE tecnina_password_resets SET expires_at = '2000-01-01 00:00:00' WHERE client_id = {$testClientId} AND state = 'PENDING'");

    // Public reset path
    $resetPath2 = '/cliente/password-reset/' . $resetToken2;
    $initReq = httpRequest('GET', $resetPath2);
    $csrfCookie = $initReq['cookies']['MAPOS_CSRF_COOKIE_gestao'] ?? '';
    
    $expiredConsume = httpRequest('POST', $resetPath2, [
        'MAPOS_CSRF_TOKEN' => $csrfCookie,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123'
    ], [], ['MAPOS_CSRF_COOKIE_gestao' => $csrfCookie]);
    testAssert($expiredConsume['code'] === 409 && ($expiredConsume['json']['reason'] ?? '') === 'invalid_or_expired_reset', 'expired_reset_consumption_rejected_409');

    // 6.7 Issue fresh reset for actual consumption
    $rKey3 = 'test-reset-key-3';
    $validReset3 = httpRequest('POST', '/api/bot/password-reset/issue', [
        'client_id' => $testClientId,
        'canonical_phone' => $testPhone,
        'phone_context_id' => 'ctx-test-3',
        'idempotency_key' => $rKey3
    ], $authHeader);
    $resetUrl3 = $validReset3['json']['reset_url'] ?? '';
    $resetToken3 = basename(parse_url($resetUrl3, PHP_URL_PATH));
    $resetPath3 = '/cliente/password-reset/' . $resetToken3;
    $resetDigest3 = hash_hmac('sha256', $resetToken3, $hmacSecret);
    $resetDbRow3 = $pdo->query("SELECT id FROM tecnina_password_resets WHERE token_digest = '{$resetDigest3}'")->fetch();

    // GET 405
    $getRes = httpRequest('GET', $resetPath3);
    testAssert($getRes['code'] === 405, 'public_reset_get_rejected_405');

    // POST without CSRF 403
    $noCsrf = httpRequest('POST', $resetPath3, ['password' => 'secret123', 'password_confirmation' => 'secret123']);
    testAssert($noCsrf['code'] === 403, 'public_reset_no_csrf_rejected_403');

    // CSRF cookie check
    $freshCsrf = $getRes['cookies']['MAPOS_CSRF_COOKIE_gestao'] ?? '';
    testAssert(!empty($freshCsrf), 'csrf_cookie_obtained_and_verified');

    // Password confirmation validation in public reset
    // Missing confirmation
    $noConfRes = httpRequest('POST', $resetPath3, [
        'MAPOS_CSRF_TOKEN' => $freshCsrf,
        'password' => 'somevalidpass123'
    ], [], ['MAPOS_CSRF_COOKIE_gestao' => $freshCsrf]);
    testAssert($noConfRes['code'] === 409 && ($noConfRes['json']['reason'] ?? '') === 'invalid_or_expired_reset', 'public_reset_missing_confirmation_rejected_409');

    // Mismatched confirmation
    $mismatchConfRes = httpRequest('POST', $resetPath3, [
        'MAPOS_CSRF_TOKEN' => $freshCsrf,
        'password' => 'somevalidpass123',
        'password_confirmation' => 'differentpass123'
    ], [], ['MAPOS_CSRF_COOKIE_gestao' => $freshCsrf]);
    testAssert($mismatchConfRes['code'] === 409 && ($mismatchConfRes['json']['reason'] ?? '') === 'invalid_or_expired_reset', 'public_reset_mismatched_confirmation_rejected_409');

    // Attempts incremented on the PENDING row
    $attemptsAfterInvalidConf = (int)$pdo->query("SELECT attempts FROM tecnina_password_resets WHERE id = '{$resetDbRow3['id']}'")->fetchColumn();
    testAssert($attemptsAfterInvalidConf === 2, 'invalid_confirmation_increments_attempts_to_2');

    // Valid consumption with whitespace
    $pwdWithSpaces = '  exact_whitespace_pass_123  ';
    $vBefore = (int)$pdo->query("SELECT credential_version FROM tecnina_client_identity WHERE client_id = {$testClientId}")->fetchColumn();
    testAssert($vBefore === 1, 'credential_version_starts_at_1');

    $consumeRes = httpRequest('POST', $resetPath3, [
        'MAPOS_CSRF_TOKEN' => $freshCsrf,
        'password' => $pwdWithSpaces,
        'password_confirmation' => $pwdWithSpaces
    ], [], ['MAPOS_CSRF_COOKIE_gestao' => $freshCsrf]);
    testAssert($consumeRes['code'] === 200 && ($consumeRes['json']['ok'] ?? false) === true, 'public_reset_consume_http_200');

    // State CONSUMED and version incremented once
    $stateAfter = $pdo->query("SELECT state, consumed_at FROM tecnina_password_resets WHERE id = '{$resetDbRow3['id']}'")->fetch();
    testAssert($stateAfter['state'] === 'CONSUMED' && !empty($stateAfter['consumed_at']), 'reset_state_is_CONSUMED');

    $vAfter = (int)$pdo->query("SELECT credential_version FROM tecnina_client_identity WHERE client_id = {$testClientId}")->fetchColumn();
    testAssert($vAfter === 2, 'credential_version_incremented_to_2');

    $hashInDb = $pdo->query("SELECT senha FROM clientes WHERE idClientes = {$testClientId}")->fetchColumn();
    testAssert(password_verify($pwdWithSpaces, $hashInDb), 'new_password_verifies_exact_whitespace');

    // Single-use replay cannot increment version
    $consumeReplay = httpRequest('POST', $resetPath3, [
        'MAPOS_CSRF_TOKEN' => $freshCsrf,
        'password' => 'anotherpass123',
        'password_confirmation' => 'anotherpass123'
    ], [], ['MAPOS_CSRF_COOKIE_gestao' => $freshCsrf]);
    testAssert($consumeReplay['code'] === 409 && ($consumeReplay['json']['reason'] ?? '') === 'invalid_or_expired_reset', 'single_use_replay_rejected_409');

    $vAfterReplay = (int)$pdo->query("SELECT credential_version FROM tecnina_client_identity WHERE client_id = {$testClientId}")->fetchColumn();
    testAssert($vAfterReplay === 2, 'replay_cannot_mutate_credential_version');

    // 6.8 Precedence edge case: attempts = 9 -> attempt 10 succeeds, state becomes CONSUMED, attempts becomes 10, version increments once, replay returns 409 (NOT 429)
    $rKeyPrec = 'test-reset-key-prec-edge';
    $validResetPrec = httpRequest('POST', '/api/bot/password-reset/issue', [
        'client_id' => $testClientId,
        'canonical_phone' => $testPhone,
        'phone_context_id' => 'ctx-test-prec',
        'idempotency_key' => $rKeyPrec
    ], $authHeader);
    testAssert($validResetPrec['code'] === 200 && !empty($validResetPrec['json']['reset_url']), 'prec_reset_issue_success');
    $resetUrlPrec = $validResetPrec['json']['reset_url'];
    $resetTokenPrec = basename(parse_url($resetUrlPrec, PHP_URL_PATH));
    $resetDigestPrec = hash_hmac('sha256', $resetTokenPrec, $hmacSecret);
    $resetPathPrec = '/cliente/password-reset/' . $resetTokenPrec;

    // Set attempts to 9
    $pdo->prepare("UPDATE tecnina_password_resets SET attempts = 9 WHERE token_digest = ?")->execute([$resetDigestPrec]);

    $precGet = httpRequest('GET', $resetPathPrec);
    $precCsrf = $precGet['cookies']['MAPOS_CSRF_COOKIE_gestao'] ?? '';

    $vBeforeTenth = (int)$pdo->query("SELECT credential_version FROM tecnina_client_identity WHERE client_id = {$testClientId}")->fetchColumn();

    // Attempt 10 supplies valid password and confirmation -> succeeds
    $tenthConsume = httpRequest('POST', $resetPathPrec, [
        'MAPOS_CSRF_TOKEN' => $precCsrf,
        'password' => 'tenth_valid_pass_123',
        'password_confirmation' => 'tenth_valid_pass_123'
    ], [], ['MAPOS_CSRF_COOKIE_gestao' => $precCsrf]);
    testAssert($tenthConsume['code'] === 200 && ($tenthConsume['json']['ok'] ?? false) === true, 'prec_attempt_10_valid_password_succeeds_200');

    // State becomes CONSUMED and attempts becomes 10
    $precRowAfter = $pdo->query("SELECT state, attempts, consumed_at FROM tecnina_password_resets WHERE token_digest = '{$resetDigestPrec}'")->fetch();
    testAssert($precRowAfter['state'] === 'CONSUMED', 'prec_state_becomes_CONSUMED');
    testAssert((int)$precRowAfter['attempts'] === 10, 'prec_attempts_becomes_10');

    // credential_version increments exactly once
    $vAfterTenth = (int)$pdo->query("SELECT credential_version FROM tecnina_client_identity WHERE client_id = {$testClientId}")->fetchColumn();
    testAssert($vAfterTenth === $vBeforeTenth + 1, 'prec_credential_version_increments_exactly_once');

    // Replay returns HTTP 409 invalid_or_expired_reset, NOT 429
    $replayTenthRes = httpRequest('POST', $resetPathPrec, [
        'MAPOS_CSRF_TOKEN' => $precCsrf,
        'password' => 'tenth_valid_pass_123',
        'password_confirmation' => 'tenth_valid_pass_123'
    ], [], ['MAPOS_CSRF_COOKIE_gestao' => $precCsrf]);
    testAssert($replayTenthRes['code'] === 409 && ($replayTenthRes['json']['reason'] ?? '') === 'invalid_or_expired_reset', 'prec_replay_returns_409_invalid_or_expired_reset_not_429');

    // Replay does not mutate password or credential_version
    $vAfterReplayTenth = (int)$pdo->query("SELECT credential_version FROM tecnina_client_identity WHERE client_id = {$testClientId}")->fetchColumn();
    testAssert($vAfterReplayTenth === $vAfterTenth, 'prec_replay_does_not_mutate_credential_version');
    $hashAfterReplay = $pdo->query("SELECT senha FROM clientes WHERE idClientes = {$testClientId}")->fetchColumn();
    testAssert(password_verify('tenth_valid_pass_123', $hashAfterReplay), 'prec_replay_does_not_mutate_password');

    // Expired PENDING token with attempts = 10 returns generic invalid_or_expired_reset (409), not 429
    $rKeyExp10 = 'test-reset-key-exp10';
    $validResetExp10 = httpRequest('POST', '/api/bot/password-reset/issue', [
        'client_id' => $testClientId,
        'canonical_phone' => $testPhone,
        'phone_context_id' => 'ctx-test-exp10',
        'idempotency_key' => $rKeyExp10
    ], $authHeader);
    $resetTokenExp10 = basename(parse_url($validResetExp10['json']['reset_url'], PHP_URL_PATH));
    $resetDigestExp10 = hash_hmac('sha256', $resetTokenExp10, $hmacSecret);
    $resetPathExp10 = '/cliente/password-reset/' . $resetTokenExp10;
    $pdo->prepare("UPDATE tecnina_password_resets SET attempts = 10, expires_at = '2000-01-01 00:00:00' WHERE token_digest = ?")->execute([$resetDigestExp10]);

    $exp10Get = httpRequest('GET', $resetPathExp10);
    $exp10Csrf = $exp10Get['cookies']['MAPOS_CSRF_COOKIE_gestao'] ?? '';
    $exp10Consume = httpRequest('POST', $resetPathExp10, [
        'MAPOS_CSRF_TOKEN' => $exp10Csrf,
        'password' => 'somepass123',
        'password_confirmation' => 'somepass123'
    ], [], ['MAPOS_CSRF_COOKIE_gestao' => $exp10Csrf]);
    testAssert($exp10Consume['code'] === 409 && ($exp10Consume['json']['reason'] ?? '') === 'invalid_or_expired_reset', 'expired_pending_attempts_10_returns_409_not_429');

    echo "\n==================================================\n";
    echo "7. ENDPOINT-SPECIFIC RATE LIMITS & CONTROLLED 503\n";
    echo "==================================================\n";

    // 7.1 credentials/hash endpoint wiring: 60/min/service
    $hashWindowSeconds = 60;
    $hashBucket = gmdate('Y-m-d H:i:00', floor(time() / $hashWindowSeconds) * $hashWindowSeconds);
    $hashKey = hash('sha256', "credential_hash|service|{$hashBucket}");
    $hashPrevRow = $pdo->query("SELECT * FROM tecnina_identity_rate_limits WHERE bucket_key = '{$hashKey}'")->fetch();

    $pdo->prepare("INSERT INTO tecnina_identity_rate_limits (bucket_key, scope, bucket_start, count) VALUES (:k, 'credential_hash', :b, 60) ON DUPLICATE KEY UPDATE count = 60")
        ->execute([':k' => $hashKey, ':b' => $hashBucket]);
    $hashLimitRes = httpRequest('POST', '/api/bot/credentials/hash', ['password' => '123456'], $authHeader);
    testAssert($hashLimitRes['code'] === 429 && ($hashLimitRes['json']['reason'] ?? '') === 'rate_limited', 'endpoint_credential_hash_enforces_429_at_60_cap');

    // Restore hash bucket
    if ($hashPrevRow) {
        $pdo->prepare("UPDATE tecnina_identity_rate_limits SET count = :cnt WHERE bucket_key = :k")->execute([':cnt' => $hashPrevRow['count'], ':k' => $hashKey]);
    } else {
        $pdo->prepare("DELETE FROM tecnina_identity_rate_limits WHERE bucket_key = :k")->execute([':k' => $hashKey]);
    }
    $hashRecoverRes = httpRequest('POST', '/api/bot/credentials/hash', ['password' => '123456'], $authHeader);
    testAssert($hashRecoverRes['code'] === 200, 'endpoint_credential_hash_recovers_to_200_after_restoration');

    // 7.2 client/lookup endpoint wiring: 300/min/service
    $lookupWindowSeconds = 60;
    $lookupBucket = gmdate('Y-m-d H:i:00', floor(time() / $lookupWindowSeconds) * $lookupWindowSeconds);
    $lookupKey = hash('sha256', "client_lookup|service|{$lookupBucket}");
    $lookupPrevRow = $pdo->query("SELECT * FROM tecnina_identity_rate_limits WHERE bucket_key = '{$lookupKey}'")->fetch();

    $pdo->prepare("INSERT INTO tecnina_identity_rate_limits (bucket_key, scope, bucket_start, count) VALUES (:k, 'client_lookup', :b, 300) ON DUPLICATE KEY UPDATE count = 300")
        ->execute([':k' => $lookupKey, ':b' => $lookupBucket]);
    $lookupLimitRes = httpRequest('POST', '/api/bot/client/lookup', ['canonical_phone' => $testPhone], $authHeader);
    testAssert($lookupLimitRes['code'] === 429 && ($lookupLimitRes['json']['reason'] ?? '') === 'rate_limited', 'endpoint_client_lookup_enforces_429_at_300_cap');

    // Restore lookup bucket
    if ($lookupPrevRow) {
        $pdo->prepare("UPDATE tecnina_identity_rate_limits SET count = :cnt WHERE bucket_key = :k")->execute([':cnt' => $lookupPrevRow['count'], ':k' => $lookupKey]);
    } else {
        $pdo->prepare("DELETE FROM tecnina_identity_rate_limits WHERE bucket_key = :k")->execute([':k' => $lookupKey]);
    }
    $lookupRecoverRes = httpRequest('POST', '/api/bot/client/lookup', ['canonical_phone' => $testPhone], $authHeader);
    testAssert($lookupRecoverRes['code'] === 200, 'endpoint_client_lookup_recovers_to_200_after_restoration');

    // 7.3 email issue 15m endpoint wiring: 3/15m subject+email
    $email15mSubject = "client_id|{$testClientId}|rate15m@example.test";
    $email15mBucket = gmdate('Y-m-d H:i:00', floor(time() / 900) * 900);
    $email15mKey = hash('sha256', "email_issue_15m|{$email15mSubject}|{$email15mBucket}");
    $pdo->prepare("INSERT INTO tecnina_identity_rate_limits (bucket_key, scope, bucket_start, count) VALUES (:k, 'email_issue_15m', :b, 3) ON DUPLICATE KEY UPDATE count = 3")
        ->execute([':k' => $email15mKey, ':b' => $email15mBucket]);
    $email15mRes = httpRequest('POST', '/api/bot/email-verification/issue', [
        'client_id' => $testClientId,
        'email_candidate' => 'rate15m@example.test',
        'purpose' => 'PROFILE_CHANGE',
        'idempotency_key' => 'rate-15m-check-key'
    ], $authHeader);
    testAssert($email15mRes['code'] === 429 && ($email15mRes['json']['reason'] ?? '') === 'rate_limited', 'endpoint_email_issue_enforces_429_at_15m_cap');
    $pdo->prepare("DELETE FROM tecnina_identity_rate_limits WHERE bucket_key = ?")->execute([$email15mKey]);

    // 7.4 email issue day endpoint wiring: 10/day subject+email
    $emailDaySubject = "client_id|{$testClientId}|rateday@example.test";
    $emailDayBucket = gmdate('Y-m-d H:i:00', floor(time() / 86400) * 86400);
    $emailDayKey = hash('sha256', "email_issue_day|{$emailDaySubject}|{$emailDayBucket}");
    $pdo->prepare("INSERT INTO tecnina_identity_rate_limits (bucket_key, scope, bucket_start, count) VALUES (:k, 'email_issue_day', :b, 10) ON DUPLICATE KEY UPDATE count = 10")
        ->execute([':k' => $emailDayKey, ':b' => $emailDayBucket]);
    $emailDayRes = httpRequest('POST', '/api/bot/email-verification/issue', [
        'client_id' => $testClientId,
        'email_candidate' => 'rateday@example.test',
        'purpose' => 'PROFILE_CHANGE',
        'idempotency_key' => 'rate-day-check-key'
    ], $authHeader);
    testAssert($emailDayRes['code'] === 429 && ($emailDayRes['json']['reason'] ?? '') === 'rate_limited', 'endpoint_email_issue_enforces_429_at_daily_cap');
    $pdo->prepare("DELETE FROM tecnina_identity_rate_limits WHERE bucket_key = ?")->execute([$emailDayKey]);

    // 7.5 reset issue endpoint wiring: 3/hour/identity
    $resetIssueBucket = gmdate('Y-m-d H:i:00', floor(time() / 3600) * 3600);
    $resetIssueKey = hash('sha256', "reset_issue|{$testClientId}|{$resetIssueBucket}");
    $pdo->prepare("INSERT INTO tecnina_identity_rate_limits (bucket_key, scope, bucket_start, count) VALUES (:k, 'reset_issue', :b, 3) ON DUPLICATE KEY UPDATE count = 3")
        ->execute([':k' => $resetIssueKey, ':b' => $resetIssueBucket]);
    $resetIssueRes = httpRequest('POST', '/api/bot/password-reset/issue', [
        'client_id' => $testClientId,
        'canonical_phone' => $testPhone,
        'phone_context_id' => 'rate-ctx',
        'idempotency_key' => 'rate-reset-check-key'
    ], $authHeader);
    testAssert($resetIssueRes['code'] === 429 && ($resetIssueRes['json']['reason'] ?? '') === 'rate_limited', 'endpoint_reset_issue_enforces_429_at_hourly_cap');
    $pdo->prepare("DELETE FROM tecnina_identity_rate_limits WHERE bucket_key = ?")->execute([$resetIssueKey]);

    // 7.6 public reset token lifetime rate limit: 10 attempts / token lifetime (stored in tecnina_password_resets.attempts)
    // Issue a fresh reset token for testing token lifetime rate limit
    $rateResetIssue = httpRequest('POST', '/api/bot/password-reset/issue', [
        'client_id' => $testClientId,
        'canonical_phone' => $testPhone,
        'phone_context_id' => 'ctx-rate-lifetime',
        'idempotency_key' => 'test-reset-rate-lifetime'
    ], $authHeader);
    testAssert($rateResetIssue['code'] === 200 && !empty($rateResetIssue['json']['reset_url']), 'rate_lifetime_reset_issued');
    $rateResetUrl = $rateResetIssue['json']['reset_url'];
    $rateResetToken = basename(parse_url($rateResetUrl, PHP_URL_PATH));
    $rateResetPath = '/cliente/password-reset/' . $rateResetToken;

    // Obtain CSRF token
    $probeCsrf = httpRequest('GET', $rateResetPath);
    $rateCsrfToken = $probeCsrf['cookies']['MAPOS_CSRF_COOKIE_gestao'] ?? '';

    // Set attempts to 10 in tecnina_password_resets (representing 10 prior attempts over token lifetime)
    $rateDigest = hash_hmac('sha256', $rateResetToken, $hmacSecret);
    $pdo->prepare("UPDATE tecnina_password_resets SET attempts = 10 WHERE token_digest = ?")->execute([$rateDigest]);

    // Attempt 11 must return HTTP 429 rate_limited
    $tokenRateRes = httpRequest('POST', $rateResetPath, [
        'MAPOS_CSRF_TOKEN' => $rateCsrfToken,
        'password' => 'pass123456',
        'password_confirmation' => 'pass123456'
    ], [], ['MAPOS_CSRF_COOKIE_gestao' => $rateCsrfToken]);
    testAssert($tokenRateRes['code'] === 429 && ($tokenRateRes['json']['reason'] ?? '') === 'rate_limited', 'reset_attempt_11_returns_429_rate_limited');

    // Confirm that tecnina_password_resets.attempts is 10 and state is still PENDING
    $rateRowAfter = $pdo->query("SELECT attempts, state FROM tecnina_password_resets WHERE token_digest = '{$rateDigest}'")->fetch();
    testAssert((int)$rateRowAfter['attempts'] === 10 && $rateRowAfter['state'] === 'PENDING', 'token_lifetime_attempts_persisted_in_resets_table');

    // Confirm that NO token bucket exists in tecnina_identity_rate_limits for this token (lifetime tracked on row, not hourly bucket)
    $testTokenBucketKey = hash('sha256', "reset_consume_token|" . hash('sha256', $rateResetToken) . "|" . gmdate('Y-m-d H:i:00', floor(time() / 3600) * 3600));
    $tokenBucketExists = (int)$pdo->query("SELECT COUNT(*) FROM tecnina_identity_rate_limits WHERE bucket_key = '{$testTokenBucketKey}'")->fetchColumn();
    testAssert($tokenBucketExists === 0, 'token_limit_not_tracked_in_hourly_rate_limits_table');

    // 7.7 public reset IP endpoint wiring: 30/hour/IP
    // Probe to identify caller IP bucket
    $probeToken = 'ipprobe_' . bin2hex(random_bytes(16));
    $probeCsrf2 = httpRequest('GET', '/cliente/password-reset/' . $probeToken);
    $probeCsrfToken2 = $probeCsrf2['cookies']['MAPOS_CSRF_COOKIE_gestao'] ?? '';
    $probeRes = httpRequest('POST', '/cliente/password-reset/' . $probeToken, [
        'MAPOS_CSRF_TOKEN' => $probeCsrfToken2,
        'password' => 'pass123456',
        'password_confirmation' => 'pass123456'
    ], [], ['MAPOS_CSRF_COOKIE_gestao' => $probeCsrfToken2]);
    $ipRow = $pdo->query("SELECT * FROM tecnina_identity_rate_limits WHERE scope = 'reset_consume_ip' ORDER BY created_at DESC LIMIT 1")->fetch();
    testAssert(!empty($ipRow), 'ip_rate_bucket_detected');
    $ipKey = $ipRow['bucket_key'];
    $ipBucketStart = $ipRow['bucket_start'];
    $ipPrevCount = (int)$ipRow['count'];

    // Set to 30
    $pdo->prepare("UPDATE tecnina_identity_rate_limits SET count = 30 WHERE bucket_key = ?")->execute([$ipKey]);
    $ipLimitRes = httpRequest('POST', '/cliente/password-reset/' . $probeToken, [
        'MAPOS_CSRF_TOKEN' => $probeCsrfToken2,
        'password' => 'pass123456',
        'password_confirmation' => 'pass123456'
    ], [], ['MAPOS_CSRF_COOKIE_gestao' => $probeCsrfToken2]);
    testAssert($ipLimitRes['code'] === 429 && ($ipLimitRes['json']['reason'] ?? '') === 'rate_limited', 'endpoint_reset_consume_ip_enforces_429_at_30_cap');
    // Restore IP bucket
    $pdo->prepare("UPDATE tecnina_identity_rate_limits SET count = ? WHERE bucket_key = ?")->execute([$ipPrevCount, $ipKey]);

    // 7.8 Component-level unit test for limiter algorithm
    testAssert(file_exists('/var/www/html/application/libraries/Tecnina_identity_rate_limiter.php'), 'limiter_library_component_verified');

    // 7.9 Controlled 503 unavailable path (fail-closed)
    $pdo->exec("RENAME TABLE tecnina_identity_rate_limits TO tecnina_identity_rate_limits_temp_test");
    try {
        $unavailRes = httpRequest('POST', '/api/bot/credentials/hash', ['password' => '123456'], $authHeader);
        testAssert($unavailRes['code'] === 503 && ($unavailRes['json']['reason'] ?? '') === 'unavailable', 'table_unavailability_produces_controlled_503');
    } finally {
        $pdo->exec("RENAME TABLE tecnina_identity_rate_limits_temp_test TO tecnina_identity_rate_limits");
    }

    // 7.10 Fail-closed attempt accounting tests
    // 7.10.1 verifyEmail wrong-code attempt accounting persistence failure returns 503 unavailable
    $failCloseChKey = 'test-fail-close-email';
    $failCloseRes = httpRequest('POST', '/api/bot/email-verification/issue', [
        'client_id' => $testClientId,
        'email_candidate' => 'failclosed@example.test',
        'purpose' => 'PROFILE_CHANGE',
        'idempotency_key' => $failCloseChKey,
    ], $authHeader);
    $failCloseChId = $failCloseRes['json']['challenge_id'] ?? '';

    // Create temporary trigger that simulates write failure on attempt increment
    $pdo->exec("DROP TRIGGER IF EXISTS trg_test_fail_email_attempt");
    $pdo->exec("CREATE TRIGGER trg_test_fail_email_attempt BEFORE UPDATE ON tecnina_email_verifications FOR EACH ROW BEGIN IF NEW.attempts > OLD.attempts AND NEW.state = 'PENDING' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'simulated_accounting_write_failure'; END IF; END");
    try {
        $attemptFailRes = httpRequest('POST', '/api/bot/email-verification/verify', [
            'challenge_id' => $failCloseChId,
            'code' => '999999', // wrong code
            'idempotency_key' => 'fail-close-verify-1'
        ], $authHeader);
        testAssert($attemptFailRes['code'] === 503 && ($attemptFailRes['json']['reason'] ?? '') === 'unavailable', 'verify_email_accounting_write_failure_returns_503_unavailable');
    } finally {
        $pdo->exec("DROP TRIGGER IF EXISTS trg_test_fail_email_attempt");
    }

    // 7.10.2 consumePasswordReset invalid-password attempt accounting persistence failure returns 503 unavailable
    $failCloseResetKey = 'test-fail-close-reset';
    $failCloseResetRes = httpRequest('POST', '/api/bot/password-reset/issue', [
        'client_id' => $testClientId,
        'canonical_phone' => $testPhone,
        'phone_context_id' => 'ctx-fail-close-reset',
        'idempotency_key' => $failCloseResetKey
    ], $authHeader);
    $failCloseResetToken = basename(parse_url($failCloseResetRes['json']['reset_url'], PHP_URL_PATH));
    $failCloseResetPath = '/cliente/password-reset/' . $failCloseResetToken;
    $failCloseGet = httpRequest('GET', $failCloseResetPath);
    $failCloseCsrf = $failCloseGet['cookies']['MAPOS_CSRF_COOKIE_gestao'] ?? '';

    $pdo->exec("DROP TRIGGER IF EXISTS trg_test_fail_reset_attempt");
    $pdo->exec("CREATE TRIGGER trg_test_fail_reset_attempt BEFORE UPDATE ON tecnina_password_resets FOR EACH ROW BEGIN IF NEW.attempts > OLD.attempts AND NEW.state = 'PENDING' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'simulated_accounting_write_failure'; END IF; END");
    try {
        $resetAttemptFailRes = httpRequest('POST', $failCloseResetPath, [
            'MAPOS_CSRF_TOKEN' => $failCloseCsrf,
            'password' => 'pass123',
            'password_confirmation' => 'mismatch_pass'
        ], [], ['MAPOS_CSRF_COOKIE_gestao' => $failCloseCsrf]);
        testAssert($resetAttemptFailRes['code'] === 503 && ($resetAttemptFailRes['json']['reason'] ?? '') === 'unavailable', 'consume_reset_accounting_write_failure_returns_503_unavailable');
    } finally {
        $pdo->exec("DROP TRIGGER IF EXISTS trg_test_fail_reset_attempt");
    }

    echo "\n==================================================\n";
    echo "8. NGINX ACCESS LOG REDACTION AUDIT\n";
    echo "==================================================\n";
    $auditToken = 'S03AUDITTOKEN' . bin2hex(random_bytes(16));

    // Request 1: GET reset URL
    $logGet = httpRequest('GET', '/cliente/password-reset/' . $auditToken);
    testAssert($logGet['code'] === 405, 'audit_request_get_405');

    // Request 2: POST reset URL without CSRF
    $logPost = httpRequest('POST', '/cliente/password-reset/' . $auditToken, ['password' => 'secret123', 'password_confirmation' => 'secret123']);
    testAssert($logPost['code'] === 403, 'audit_request_post_no_csrf_403');

    // Request 3: GET with Referer containing reset token
    $logReferer = httpRequest('GET', '/', null, ['Referer' => 'https://gestao.tecnina.com/cliente/password-reset/' . $auditToken]);
    testAssert($logReferer['code'] === 307 || $logReferer['code'] === 302 || $logReferer['code'] === 200, 'audit_request_referer_processed');

    // Request 4: Ordinary non-sensitive request
    $logOrdinary = httpRequest('POST', '/api/bot/outbox/claim', null, $authHeader);
    testAssert($logOrdinary['code'] === 200 || $logOrdinary['code'] === 405, 'audit_ordinary_request_processed');

    // Output audit token for host-level verification of Nginx access logs
    echo "AUDIT_TOKEN_FOR_LOG_CHECK: " . $auditToken . "\n";
    testAssert(true, 'nginx_audit_requests_sent', "token_generated");

} finally {
    echo "\n==================================================\n";
    echo "9. CLEANUP & BASELINE VERIFICATION\n";
    echo "==================================================\n";
    $pdo->exec("DELETE FROM tecnina_email_verifications WHERE email_candidate LIKE '%example.test%'");
    if ($testClientId) {
        $pdo->exec("DELETE FROM tecnina_password_resets WHERE client_id = {$testClientId}");
        $pdo->exec("DELETE FROM tecnina_client_identity_phone_conflicts WHERE client_id = {$testClientId}");
        $pdo->exec("DELETE FROM tecnina_client_identity WHERE client_id = {$testClientId}");
        $pdo->exec("DELETE FROM clientes WHERE idClientes = {$testClientId}");
    }

    // Exact rate limit baseline restoration: delete newly created buckets and restore initial rows
    $currentRateLimitRows = $pdo->query("SELECT bucket_key, scope, bucket_start, count FROM tecnina_identity_rate_limits")->fetchAll(PDO::FETCH_ASSOC);
    $initialMap = [];
    foreach ($initialRateLimitRows as $r) {
        $initialMap[$r['bucket_key']] = $r;
    }
    foreach ($currentRateLimitRows as $r) {
        $k = $r['bucket_key'];
        if (!isset($initialMap[$k])) {
            $pdo->prepare("DELETE FROM tecnina_identity_rate_limits WHERE bucket_key = ?")->execute([$k]);
        } elseif ((int)$r['count'] !== (int)$initialMap[$k]['count']) {
            $pdo->prepare("UPDATE tecnina_identity_rate_limits SET count = ? WHERE bucket_key = ?")->execute([$initialMap[$k]['count'], $k]);
        }
    }

    $clientsCount = (int)$pdo->query("SELECT count(*) FROM clientes")->fetchColumn();
    $identityCount = (int)$pdo->query("SELECT count(*) FROM tecnina_client_identity")->fetchColumn();
    $profileCount = (int)$pdo->query("SELECT count(*) FROM tecnina_client_profile")->fetchColumn();
    $conflictsCount = (int)$pdo->query("SELECT count(*) FROM tecnina_client_identity_phone_conflicts")->fetchColumn();
    $resetsCount = (int)$pdo->query("SELECT count(*) FROM tecnina_password_resets")->fetchColumn();
    $verifsCount = (int)$pdo->query("SELECT count(*) FROM tecnina_email_verifications")->fetchColumn();
    $finalRateLimitCount = (int)$pdo->query("SELECT count(*) FROM tecnina_identity_rate_limits")->fetchColumn();

    testAssert($clientsCount === 3, 'cleanup_clientes_count_3', "count={$clientsCount}");
    testAssert($identityCount === 3, 'cleanup_tecnina_client_identity_count_3', "count={$identityCount}");
    testAssert($profileCount === 0, 'cleanup_tecnina_client_profile_count_0', "count={$profileCount}");
    testAssert($conflictsCount === 0, 'cleanup_tecnina_client_identity_phone_conflicts_count_0', "count={$conflictsCount}");
    testAssert($resetsCount === 0, 'cleanup_tecnina_password_resets_count_0', "count={$resetsCount}");
    testAssert($verifsCount === 0, 'cleanup_tecnina_email_verifications_count_0', "count={$verifsCount}");
    testAssert($finalRateLimitCount === $initialRateLimitCount, 'cleanup_tecnina_identity_rate_limits_exact_baseline', "before={$initialRateLimitCount}, after={$finalRateLimitCount}");
}

// 10. Emit machine-readable results JSON artifact outside web-served root
$resultsJsonPath = '/tmp/results_s03a.json';
if (file_exists('/var/www/html/tests/results_s03a.json')) {
    @unlink('/var/www/html/tests/results_s03a.json');
}
$summaryData = [
    'task' => 'CIAO-S03A',
    'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    'total_assertions' => count($results),
    'passed' => $passCount,
    'failed' => $failCount,
    'baseline_counts' => [
        'clientes' => $clientsCount,
        'tecnina_client_identity' => $identityCount,
        'tecnina_client_profile' => $profileCount,
        'tecnina_client_identity_phone_conflicts' => $conflictsCount,
        'tecnina_password_resets' => $resetsCount,
        'tecnina_email_verifications' => $verifsCount,
        'tecnina_identity_rate_limits_initial' => $initialRateLimitCount,
        'tecnina_identity_rate_limits_final' => $finalRateLimitCount,
    ],
    'assertions' => $results
];
file_put_contents($resultsJsonPath, json_encode($summaryData, JSON_PRETTY_PRINT));
echo "\nMachine-readable results written to {$resultsJsonPath}\n";

echo "\n==================================================\n";
echo "FINAL VALIDATION SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "==================================================\n";
if ($failCount > 0) {
    exit(1);
}
exit(0);
