<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tecnina_context_proof {

    private $secret;

    public function __construct($params = [])
    {
        $this->secret = isset($params['secret']) ? $params['secret'] : (getenv('TECNINA_CONTEXT_PROOF_HMAC_SECRET') ?: '');
    }

    public function set_secret($secret)
    {
        $this->secret = $secret;
    }

    private function get_domain_key($domain_string)
    {
        return hash_hmac('sha256', $domain_string, $this->secret, true);
    }

    public function verify_proof($proof, $expected_operation, $now = null)
    {
        if (empty($this->secret) || strlen($this->secret) < 32) {
            return ['status' => false, 'reason' => 'context_authority_unavailable', 'code' => 503];
        }
        if (empty($proof) || strpos($proof, 'v1.') !== 0) {
            return ['status' => false, 'reason' => 'invalid_context_proof', 'code' => 403];
        }

        $parts = explode('.', $proof);
        if (count($parts) !== 3) {
            return ['status' => false, 'reason' => 'invalid_context_proof', 'code' => 403];
        }

        $b64_payload = $parts[1];
        $signature = $parts[2];

        $domain_string = "context-proof/" . strtolower($expected_operation) . "/v1";
        $domain_key = $this->get_domain_key($domain_string);

        $expected_signature = hash_hmac('sha256', $b64_payload, $domain_key);
        if (!hash_equals($expected_signature, $signature)) {
            return ['status' => false, 'reason' => 'invalid_context_proof', 'code' => 403];
        }

        $padded_b64 = str_pad($b64_payload, strlen($b64_payload) + (4 - strlen($b64_payload) % 4) % 4, '=', STR_PAD_RIGHT);
        $json_str = base64_decode(strtr($padded_b64, '-_', '+/'), true);
        if ($json_str === false) {
            return ['status' => false, 'reason' => 'invalid_context_proof', 'code' => 403];
        }

        $payload = json_decode($json_str, true);
        if (!is_array($payload)) {
            return ['status' => false, 'reason' => 'invalid_context_proof', 'code' => 403];
        }

        // Strict Type Validation
        if (!isset($payload['operation']) || !is_string($payload['operation']) || $payload['operation'] === '') {
            return ['status' => false, 'reason' => 'invalid_context_proof', 'code' => 403];
        }
        if ($payload['operation'] !== $expected_operation) {
            return ['status' => false, 'reason' => 'invalid_context_proof', 'code' => 403];
        }
        if (!isset($payload['issued_at']) || !is_int($payload['issued_at'])) {
            return ['status' => false, 'reason' => 'invalid_context_proof', 'code' => 403];
        }
        if (!isset($payload['expires_at']) || !is_int($payload['expires_at'])) {
            return ['status' => false, 'reason' => 'invalid_context_proof', 'code' => 403];
        }
        if (!isset($payload['nonce']) || !is_string($payload['nonce']) || $payload['nonce'] === '' || strlen($payload['nonce']) > 64) {
            return ['status' => false, 'reason' => 'invalid_context_proof', 'code' => 403];
        }

        if ($now === null) {
            $now = time();
        }

        if ($payload['expires_at'] <= $payload['issued_at'] || $now > $payload['expires_at'] || $payload['issued_at'] > $now + 60 || ($payload['expires_at'] - $payload['issued_at']) > 900) {
            return ['status' => false, 'reason' => 'invalid_context_proof', 'code' => 403];
        }

        return ['status' => true, 'payload' => $payload];
    }

    public function compute_fingerprint($domain, $data)
    {
        if (empty($this->secret) || strlen($this->secret) < 32) {
            return null; // Should throw or handle
        }
        $domain_key = $this->get_domain_key("fingerprint/" . strtolower($domain) . "/v1");
        return hash_hmac('sha256', $data, $domain_key);
    }
}
