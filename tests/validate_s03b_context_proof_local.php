<?php

define('BASEPATH', __DIR__ . '/../system/');

require_once __DIR__ . '/../application/libraries/Tecnina_context_proof.php';

function testAssertProof($condition, $message) {
    if ($condition) {
        echo "[PASS] $message\n";
    } else {
        echo "[FAIL] $message\n";
        exit(1);
    }
}

function genProof($secret, $payload, $operation) {
    $b64 = strtr(base64_encode(json_encode($payload)), '+/', '-_');
    $b64 = rtrim($b64, '=');
    $domain_key = hash_hmac('sha256', "context-proof/" . strtolower($operation) . "/v1", $secret, true);
    $sig = hash_hmac('sha256', $b64, $domain_key);
    return "v1.$b64.$sig";
}

$secret = "12345678901234567890123456789012";
$helper = new Tecnina_context_proof(['secret' => $secret]);

$now = time();
$evPayload = [
    'v' => '1',
    'operation' => 'EMAIL_VERIFICATION_VERIFY',
    'issued_at' => $now,
    'expires_at' => $now + 300,
    'nonce' => 'nonce123',
    'challenge_id' => '123',
    'phone_context_id' => 'draft-123',
    'purpose' => 'ACCOUNT_CREATION'
];

$res = $helper->verify_proof(genProof($secret, $evPayload, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === true, "Valid proof");
testAssertProof($helper->bind_email_verification($res['payload'], '123', 'draft-123', 'ACCOUNT_CREATION'), "Email Verify Bind: Valid bound context");
testAssertProof($helper->bind_email_verification($res['payload'], '999', 'draft-123', 'ACCOUNT_CREATION') === false, "Email Verify Bind: Wrong challenge mismatch");
testAssertProof($helper->bind_email_verification($res['payload'], '123', 'draft-999', 'ACCOUNT_CREATION') === false, "Email Verify Bind: Wrong context mismatch");
testAssertProof($helper->bind_email_verification($res['payload'], '123', 'draft-123', 'WRONG_PURPOSE') === false, "Email Verify Bind: Wrong purpose mismatch");

$res = $helper->verify_proof("", 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Missing proof -> 403");

$res = $helper->verify_proof("v1.bad", 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Malformed structure -> 403");

$res = $helper->verify_proof("v2." . explode('.', genProof($secret, $evPayload, 'EMAIL_VERIFICATION_VERIFY'))[1] . ".sig", 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Unsupported version -> 403");

$res = $helper->verify_proof("v1.!!!.sig", 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Invalid base64 -> 403");

$invalidJsonB64 = strtr(base64_encode("{bad json}"), '+/', '-_');
$res = $helper->verify_proof("v1.$invalidJsonB64.sig", 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Invalid JSON -> 403");

$res = $helper->verify_proof(genProof('wrong-secret-12345678901234567890', $evPayload, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Bad signature -> 403");

$p = $evPayload; unset($p['operation']);
$res = $helper->verify_proof(genProof($secret, $p, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Missing operation -> 403");

$p = $evPayload; $p['operation'] = 'WRONG';
$res = $helper->verify_proof(genProof($secret, $p, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Wrong operation -> 403");

$p = $evPayload; unset($p['issued_at']);
$res = $helper->verify_proof(genProof($secret, $p, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Missing issued_at -> 403");

$p = $evPayload; $p['issued_at'] = "string";
$res = $helper->verify_proof(genProof($secret, $p, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Non-integer issued_at -> 403");

$p = $evPayload; unset($p['expires_at']);
$res = $helper->verify_proof(genProof($secret, $p, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Missing expires_at -> 403");

$p = $evPayload; $p['expires_at'] = "string";
$res = $helper->verify_proof(genProof($secret, $p, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Non-integer expires_at -> 403");

$p = $evPayload; $p['expires_at'] = $p['issued_at'] - 10;
$res = $helper->verify_proof(genProof($secret, $p, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Expires <= issued -> 403");

$res = $helper->verify_proof(genProof($secret, $evPayload, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now + 400);
testAssertProof($res['status'] === false && $res['code'] === 403, "Expired -> 403");

$res = $helper->verify_proof(genProof($secret, $evPayload, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now - 400);
testAssertProof($res['status'] === false && $res['code'] === 403, "Issued too far in future -> 403");

$p = $evPayload; $p['expires_at'] = $p['issued_at'] + 1000;
$res = $helper->verify_proof(genProof($secret, $p, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "TTL > 900 -> 403");

$p = $evPayload; unset($p['nonce']);
$res = $helper->verify_proof(genProof($secret, $p, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Missing nonce -> 403");

$p = $evPayload; $p['nonce'] = str_repeat('a', 65);
$res = $helper->verify_proof(genProof($secret, $p, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Oversized nonce (>64) -> 403");

$p = $evPayload; $p['issued_at'] = 123456.78;
$res = $helper->verify_proof(genProof($secret, $p, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Float issued_at -> 403");

$p = $evPayload; $p['issued_at'] = (string)$now;
$res = $helper->verify_proof(genProof($secret, $p, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Numeric string issued_at -> 403");

$p = $evPayload; $p['expires_at'] = 123456.78;
$res = $helper->verify_proof(genProof($secret, $p, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Float expires_at -> 403");

$p = $evPayload; $p['expires_at'] = (string)($now + 300);
$res = $helper->verify_proof(genProof($secret, $p, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Numeric string expires_at -> 403");

$p = $evPayload; $p['nonce'] = ["malformed"];
$res = $helper->verify_proof(genProof($secret, $p, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Malformed nonce -> 403");

$helper_bad_secret = new Tecnina_context_proof(['secret' => 'short']);
$res = $helper_bad_secret->verify_proof(genProof($secret, $evPayload, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 503, "Secret < 32 bytes -> 503");

$helper_no_secret = new Tecnina_context_proof(['secret' => '']);
$res = $helper_no_secret->verify_proof(genProof($secret, $evPayload, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 503, "Missing secret -> 503");

try { $helper_bad_secret->compute_fingerprint('canonical-phone', '5541999990000'); testAssertProof(false, "Weak fingerprint secret must fail closed"); }
catch (RuntimeException $e) { testAssertProof(true, "Weak fingerprint secret throws RuntimeException"); }

// Bind type validation tests
testAssertProof($helper->bind_email_verification(['challenge_id' => 123, 'phone_context_id' => 'draft-123', 'purpose' => 'ACCOUNT_CREATION'], '123', 'draft-123', 'ACCOUNT_CREATION') === false, "Email Verify Bind: Non-string challenge_id rejected");
testAssertProof($helper->bind_email_verification(['challenge_id' => '123', 'phone_context_id' => ['draft-123'], 'purpose' => 'ACCOUNT_CREATION'], '123', 'draft-123', 'ACCOUNT_CREATION') === false, "Email Verify Bind: Non-string phone_context_id rejected");
testAssertProof($helper->bind_email_verification(['challenge_id' => '123', 'phone_context_id' => 'draft-123', 'purpose' => 1], '123', 'draft-123', 'ACCOUNT_CREATION') === false, "Email Verify Bind: Non-string purpose rejected");

$prPayload = [
    'v' => '1',
    'operation' => 'PASSWORD_RESET_ISSUE',
    'issued_at' => $now,
    'expires_at' => $now + 300,
    'nonce' => 'nonce123',
    'client_id' => 42,
    'phone_context_id' => 'draft-123',
    'canonical_phone_fp' => $helper->compute_fingerprint('canonical-phone', '5541999990000')
];

$res = $helper->verify_proof(genProof($secret, $prPayload, 'PASSWORD_RESET_ISSUE'), 'PASSWORD_RESET_ISSUE', $now);
testAssertProof($res['status'] === true, "PR: Valid bound context");
testAssertProof($helper->bind_password_reset($res['payload'], 42, 'draft-123', '5541999990000'), "PR Bind: Valid bound context");
testAssertProof($helper->bind_password_reset($res['payload'], 999, 'draft-123', '5541999990000') === false, "PR Bind: Wrong client_id");
testAssertProof($helper->bind_password_reset(array_merge($res['payload'], ['client_id' => '42']), 42, 'draft-123', '5541999990000') === false, "PR Bind: String client_id rejected");
testAssertProof($helper->bind_password_reset(array_merge($res['payload'], ['canonical_phone_fp' => 'not-hex-64-bytes']), 42, 'draft-123', '5541999990000') === false, "PR Bind: Invalid canonical_phone_fp rejected");
testAssertProof($helper->bind_password_reset($res['payload'], 42, 'draft-999', '5541999990000') === false, "PR Bind: Wrong phone_context_id");
testAssertProof($helper->bind_password_reset($res['payload'], 42, 'draft-123', '5541000000000') === false, "PR Bind: Wrong canonical_phone fingerprint");

$p = $prPayload; $p['operation'] = 'WRONG';
$res = $helper->verify_proof(genProof($secret, $p, 'PASSWORD_RESET_ISSUE'), 'PASSWORD_RESET_ISSUE', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "PR: Wrong operation -> 403");

$p = $prPayload; unset($p['issued_at']);
$res = $helper->verify_proof(genProof($secret, $p, 'PASSWORD_RESET_ISSUE'), 'PASSWORD_RESET_ISSUE', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "PR: Malformed/expired proof -> 403");

echo "All MapOS Local Tests passed.\n";
exit(0);
