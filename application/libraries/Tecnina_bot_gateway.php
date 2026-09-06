<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Internal, server-to-server client for the TecNina Bot Gateway.
 * The browser never receives the Gateway URL token.
 */
class Tecnina_bot_gateway
{
    private $baseUrl;
    private $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) ($_ENV['TECNINA_BOT_BASE_URL'] ?? ''), '/');
        $this->token = (string) ($_ENV['MAPOS_BOT_TOKEN'] ?? '');
    }

    public function available()
    {
        return filter_var($this->baseUrl, FILTER_VALIDATE_URL) !== false && strlen($this->token) >= 32 && function_exists('curl_init');
    }

    public function request($method, $path, $payload = null)
    {
        if (! $this->available()) {
            return ['ok' => false, 'status' => 503, 'reason' => 'gateway_not_configured', 'data' => null];
        }

        if (! is_string($path) || strpos($path, '/') !== 0 || strpos($path, '//') === 0) {
            return ['ok' => false, 'status' => 400, 'reason' => 'invalid_path', 'data' => null];
        }

        $ch = curl_init($this->baseUrl . $path);
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token,
        ];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($payload !== null) {
            $encoded = json_encode($payload);
            if ($encoded === false) {
                curl_close($ch);
                return ['ok' => false, 'status' => 400, 'reason' => 'invalid_payload', 'data' => null];
            }
            $options[CURLOPT_POSTFIELDS] = $encoded;
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            return ['ok' => false, 'status' => 503, 'reason' => 'gateway_unavailable', 'data' => null];
        }
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return ['ok' => false, 'status' => $status ?: 502, 'reason' => 'invalid_gateway_response', 'data' => null];
        }
        if ($status < 200 || $status >= 300) {
            // Only documented, non-sensitive contract errors may cross this boundary.
            $safeReasons = [
                'intake_not_found',
                'intake_review_conflict',
                'existing_client_required',
                'client_name_required',
                'incomplete_intake',
                'invalid_client_action',
                'invalid_operator',
                'idempotency_conflict',
                'approval_in_progress',
                'ambiguous_client',
                'client_match_changed',
                'duplicate_client_requires_decision',
                'approval_unavailable',
                'mapos_unavailable',
            ];
            $detail = isset($decoded['detail']) && is_string($decoded['detail'])
                ? $decoded['detail']
                : '';
            $reason = in_array($detail, $safeReasons, true) ? $detail : 'gateway_request_failed';

            return ['ok' => false, 'status' => $status, 'reason' => $reason, 'data' => null];
        }

        return ['ok' => true, 'status' => $status, 'reason' => 'ok', 'data' => $decoded];
    }
}
