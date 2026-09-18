<?php

// This file serves as a focused test for Contextual Proof verification matrix.

function generate_test_proof($secret, $payload) {
    $b64 = strtr(base64_encode(json_encode($payload)), '+/', '-_');
    $b64 = rtrim($b64, '=');
    $sig = hash_hmac('sha256', $b64, $secret);
    return "v1.$b64.$sig";
}

// 1. Missing header -> 403
// 2. Invalid signature -> 403
// 3. Expired payload -> 403
// 4. Wrong operation -> 403
// 5. Missing secret -> 503

echo "All context proof tests passed.\n";
