<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tecnina_bot_auth
{
    public function availability()
    {
        if (! filter_var($_ENV['API_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return ['ok' => false, 'status' => 503, 'reason' => 'api_disabled'];
        }

        $token = (string) ($_ENV['MAPOS_BOT_TOKEN'] ?? '');
        if (strlen($token) < 32) {
            return ['ok' => false, 'status' => 503, 'reason' => 'integration_not_configured'];
        }

        return ['ok' => true, 'status' => 200, 'reason' => 'available'];
    }

    public function authorize($authorization)
    {
        $availability = $this->availability();
        if (! $availability['ok']) {
            return $availability;
        }

        if (! is_string($authorization) || strpos($authorization, 'Bearer ') !== 0) {
            return ['ok' => false, 'status' => 401, 'reason' => 'unauthorized'];
        }

        $supplied = substr($authorization, 7);
        $expected = (string) $_ENV['MAPOS_BOT_TOKEN'];
        if ($supplied === '' || ! hash_equals($expected, $supplied)) {
            return ['ok' => false, 'status' => 403, 'reason' => 'forbidden'];
        }

        return ['ok' => true, 'status' => 200, 'reason' => 'authorized'];
    }
}
