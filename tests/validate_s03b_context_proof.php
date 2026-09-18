<?php

// validate_s03b_context_proof.php
// Focus: Contextual Proof Enforcement

require_once __DIR__ . '/test_helper.php'; // assuming there is one, or we define httpRequest

function httpRequestProof($method, $path, $data = null, $headers = []) {
    $ch = curl_init();
    $url = 'http://10.0.4.5' . $path;
    $reqHeaders = ['Host: gestao.tecnina.com'];
    foreach ($headers as $k => $v) {
        $reqHeaders[] = "{$k}: {$v}";
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            $json = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            $reqHeaders[] = 'Content-Type: application/json';
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $reqHeaders);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'json' => json_decode($response, true)];
}

function testAssertProof($condition, $message) {
    if ($condition) {
        echo "[PASS] $message\n";
    } else {
        echo "[FAIL] $message\n";
        exit(1);
    }
}

function genProof($secret, $payload) {
    $b64 = strtr(base64_encode(json_encode($payload)), '+/', '-_');
    $b64 = rtrim($b64, '=');
    $sig = hash_hmac('sha256', $b64, $secret);
    return "v1.$b64.$sig";
}

$secret = getenv('TECNINA_CONTEXT_PROOF_HMAC_SECRET');
if (empty($secret)) { $secret = '12345678901234567890123456789012'; }

$authHeader = ['Authorization' => 'Bearer fake-token']; // the app authorizes bots via bearer first

// EMAIL VERIFY
$evPath = '/api/bot/email-verification/verify';
$evData = ['challenge_id' => 'chal123', 'code' => '123456', 'idempotency_key' => 'key123'];
$evPayload = [
    'operation' => 'EMAIL_VERIFICATION_VERIFY',
    'challenge_id' => 'chal123',
    'phone_context_id' => 'ctx123',
    'purpose' => 'ACCOUNT_CREATION',
    'issued_at' => time(),
    'expires_at' => time() + 300,
];

// missing header -> 403
$res = httpRequestProof('POST', $evPath, $evData, $authHeader);
testAssertProof($res['code'] === 403, "Missing header -> 403");

// malformed structure -> 403
$res = httpRequestProof('POST', $evPath, $evData, array_merge($authHeader, ['X-Tecnina-Context-Proof' => 'v1.notenough']));
testAssertProof($res['code'] === 403, "Malformed structure -> 403");

// bad HMAC -> 403
$res = httpRequestProof('POST', $evPath, $evData, array_merge($authHeader, ['X-Tecnina-Context-Proof' => genProof('wrong-secret-12345678901234567890', $evPayload)]));
testAssertProof($res['code'] === 403, "Bad HMAC -> 403");

// expired -> 403
$expPayload = $evPayload;
$expPayload['issued_at'] = time() - 600;
$expPayload['expires_at'] = time() - 300;
$res = httpRequestProof('POST', $evPath, $evData, array_merge($authHeader, ['X-Tecnina-Context-Proof' => genProof($secret, $expPayload)]));
testAssertProof($res['code'] === 403, "Expired -> 403");

// future issued_at -> 403
$futPayload = $evPayload;
$futPayload['issued_at'] = time() + 600;
$futPayload['expires_at'] = time() + 900;
$res = httpRequestProof('POST', $evPath, $evData, array_merge($authHeader, ['X-Tecnina-Context-Proof' => genProof($secret, $futPayload)]));
testAssertProof($res['code'] === 403, "Future issued_at -> 403");

// excessive TTL -> 403
$ttlPayload = $evPayload;
$ttlPayload['expires_at'] = time() + 3600;
$res = httpRequestProof('POST', $evPath, $evData, array_merge($authHeader, ['X-Tecnina-Context-Proof' => genProof($secret, $ttlPayload)]));
testAssertProof($res['code'] === 403, "Excessive TTL -> 403");

// wrong operation -> 403
$opPayload = $evPayload;
$opPayload['operation'] = 'WRONG_OP';
$res = httpRequestProof('POST', $evPath, $evData, array_merge($authHeader, ['X-Tecnina-Context-Proof' => genProof($secret, $opPayload)]));
testAssertProof($res['code'] === 403, "Wrong operation -> 403");

// wrong challenge -> 403
$chalPayload = $evPayload;
$chalPayload['challenge_id'] = 'chal456';
$res = httpRequestProof('POST', $evPath, $evData, array_merge($authHeader, ['X-Tecnina-Context-Proof' => genProof($secret, $chalPayload)]));
testAssertProof($res['code'] === 403, "Wrong challenge -> 403");

// PASSWORD RESET ISSUE
$prPath = '/api/bot/password-reset/issue';
$prData = ['client_id' => 101, 'canonical_phone' => '5541999990000', 'phone_context_id' => 'ctx123', 'idempotency_key' => 'key123'];
$prPayload = [
    'operation' => 'PASSWORD_RESET_ISSUE',
    'client_id' => 101,
    'phone_context_id' => 'ctx123',
    'issued_at' => time(),
    'expires_at' => time() + 300,
];

// missing proof -> 403
$res = httpRequestProof('POST', $prPath, $prData, $authHeader);
testAssertProof($res['code'] === 403, "Password reset missing proof -> 403");

// wrong client -> 403
$clientPayload = $prPayload;
$clientPayload['client_id'] = 102;
$res = httpRequestProof('POST', $prPath, $prData, array_merge($authHeader, ['X-Tecnina-Context-Proof' => genProof($secret, $clientPayload)]));
testAssertProof($res['code'] === 403, "Password reset wrong client -> 403");

echo "All context proof tests passed.\n";
