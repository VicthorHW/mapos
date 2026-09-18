<?php

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
    'operation' => 'EMAIL_VERIFICATION_VERIFY',
    'issued_at' => $now,
    'expires_at' => $now + 300,
    'nonce' => 'nonce123'
];

$res = $helper->verify_proof(genProof($secret, $evPayload, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === true, "Valid proof");

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

$p = $evPayload; $p['nonce'] = ["malformed"];
$res = $helper->verify_proof(genProof($secret, $p, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 403, "Malformed nonce -> 403");

$helper_bad_secret = new Tecnina_context_proof(['secret' => 'short']);
$res = $helper_bad_secret->verify_proof(genProof($secret, $evPayload, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 503, "Secret < 32 bytes -> 503");

$helper_no_secret = new Tecnina_context_proof(['secret' => '']);
$res = $helper_no_secret->verify_proof(genProof($secret, $evPayload, 'EMAIL_VERIFICATION_VERIFY'), 'EMAIL_VERIFICATION_VERIFY', $now);
testAssertProof($res['status'] === false && $res['code'] === 503, "Missing secret -> 503");

echo "All MapOS Local Tests passed.\n";
