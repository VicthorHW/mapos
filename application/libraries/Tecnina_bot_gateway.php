<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Internal, server-to-server client for the TecNina Bot Gateway.
 * The browser never receives the Gateway URL token.
 */
class Tecnina_bot_gateway
{
    public const DEFAULT_TIMEOUT_SECONDS = 8;
    public const DEFAULT_SCENARIO_TIMEOUT_SECONDS = 45;
    public const MIN_SCENARIO_TIMEOUT_SECONDS = 15;
    public const MAX_SCENARIO_TIMEOUT_SECONDS = 90;
    public const DEFAULT_CONNECT_TIMEOUT_SECONDS = 3;

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

    public function scenarioTimeoutSeconds(): int
    {
        $raw = $_ENV['TECNINA_BOT_SCENARIO_TIMEOUT_SECONDS'] ?? getenv('TECNINA_BOT_SCENARIO_TIMEOUT_SECONDS');
        if ($raw === null || $raw === false) {
            return self::DEFAULT_SCENARIO_TIMEOUT_SECONDS;
        }

        $rawStr = trim((string) $raw);
        if ($rawStr === '' || ! preg_match('/^-?\d+$/', $rawStr)) {
            return self::DEFAULT_SCENARIO_TIMEOUT_SECONDS;
        }

        $val = (int) $rawStr;
        if ($val < self::MIN_SCENARIO_TIMEOUT_SECONDS) {
            return self::MIN_SCENARIO_TIMEOUT_SECONDS;
        }
        if ($val > self::MAX_SCENARIO_TIMEOUT_SECONDS) {
            return self::MAX_SCENARIO_TIMEOUT_SECONDS;
        }

        return $val;
    }

    public function resolveTimeout($timeoutSeconds = null): int
    {
        if ($timeoutSeconds === null) {
            return self::DEFAULT_TIMEOUT_SECONDS;
        }

        if (is_int($timeoutSeconds)) {
            $val = $timeoutSeconds;
        } elseif (is_string($timeoutSeconds) && preg_match('/^-?\d+$/', trim($timeoutSeconds))) {
            $val = (int) trim($timeoutSeconds);
        } else {
            return self::DEFAULT_TIMEOUT_SECONDS;
        }

        if ($val < 1) {
            return self::DEFAULT_TIMEOUT_SECONDS;
        }

        return min(self::MAX_SCENARIO_TIMEOUT_SECONDS, $val);
    }

    public function buildCurlOptions($method, array $headers, $payload = null, $timeoutSeconds = null): array
    {
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_CONNECTTIMEOUT => self::DEFAULT_CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => $this->resolveTimeout($timeoutSeconds),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($payload !== null) {
            $encoded = json_encode($payload);
            if ($encoded !== false) {
                $options[CURLOPT_POSTFIELDS] = $encoded;
            }
        }
        return $options;
    }

    public function request($method, $path, $payload = null, $timeoutSeconds = null)
    {
        if (! $this->available()) {
            return ['ok' => false, 'status' => 503, 'reason' => 'gateway_not_configured', 'data' => null];
        }

        if (! is_string($path) || strpos($path, '/') !== 0 || strpos($path, '//') === 0) {
            return ['ok' => false, 'status' => 400, 'reason' => 'invalid_path', 'data' => null];
        }

        $effectiveTimeout = $this->resolveTimeout($timeoutSeconds);

        $ch = curl_init($this->baseUrl . $path);
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token,
        ];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_CONNECTTIMEOUT => self::DEFAULT_CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => $effectiveTimeout,
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
        if ($status === 204 && trim((string) $body) === '') {
            return ['ok' => true, 'status' => 204, 'reason' => 'ok', 'data' => null];
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
                'zone_key_already_exists',
                'route_key_already_exists',
                'capacity_rule_key_already_exists',
                'equipment_type_key_already_exists',
                'zone_not_found',
                'route_not_found',
                'appointment_not_found',
                'stale_appointment_version',
                'appointment_schedule_incomplete',
                'route_unavailable',
                'zone_unavailable',
                'route_does_not_serve_zone',
                'operation_not_allowed_in_zone',
                'operation_not_allowed_on_route',
                'equipment_not_supported_on_route',
                'transport_profile_mismatch',
                'pricing_requires_review',
                'route_schedule_mismatch',
                'minimum_notice_not_met',
                'confirmed_location_required',
                'capacity_rule_missing',
                'window_capacity_exceeded',
                'daily_capacity_exceeded',
                'blackout_capacity_exceeded',
                'route_blackout',
                'location_purpose_mismatch',
                'location_request_closed',
                'flow_not_found',
                'flow_version_not_found',
                'flow_version_missing',
                'draft_not_found',
                'stale_flow_revision',
                'invalid_os_id',
                'mapos_context_unavailable',
                'invalid_dropoff_address',
                'invalid_dropoff_timezone',
                'invalid_dropoff_period',
                'overlapping_dropoff_periods',
                'enabled_day_requires_period',
                'seven_unique_weekdays_required',
                'simulation_not_found',
                'simulation_fixture_incomplete',
                'simulation_runtime_state',
                'simulation_manager_unavailable',
                'simulation_execution_failed',
                'simulation_operational_config_unavailable',
            ];
            $detail = isset($decoded['detail']) && is_string($decoded['detail'])
                ? $decoded['detail']
                : '';
            $reason = 'gateway_request_failed';
            if (in_array($detail, $safeReasons, true)) {
                $reason = $detail;
            } else {
                foreach ($safeReasons as $safe) {
                    if (strpos($detail, $safe . ':') === 0) {
                        $reason = $detail;
                        break;
                    }
                }
            }

            return ['ok' => false, 'status' => $status, 'reason' => $reason, 'data' => null];
        }

        return ['ok' => true, 'status' => $status, 'reason' => 'ok', 'data' => $decoded];
    }
}
