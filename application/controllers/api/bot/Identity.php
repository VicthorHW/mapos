<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . '/libraries/REST_Controller.php';

/** Private Bot-facing identity authority; no plaintext credential is persisted or echoed. */
class Identity extends REST_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('Tecnina_bot_auth');
        $this->load->library('Tecnina_identity_authority');
    }

    public function hash_post()
    {
        if (! $this->authorize()) { return; }
        $input = $this->post();
        if (! is_array($input) || array_diff(array_keys($input), ['password', 'password_confirmation']) !== [] || ! array_key_exists('password', $input)) {
            return $this->response(['status' => false, 'reason' => 'invalid_payload'], self::HTTP_UNPROCESSABLE_ENTITY);
        }
        $result = $this->tecnina_identity_authority->passwordHash($input['password'], $input['password_confirmation'] ?? null);
        if (! $result['ok']) { return $this->response(['status' => false, 'reason' => $result['reason']], self::HTTP_UNPROCESSABLE_ENTITY); }
        return $this->response(['status' => true, 'password_hash' => $result['hash'], 'algorithm' => $result['algorithm']], self::HTTP_OK);
    }

    public function lookup_post()
    {
        if (! $this->authorize()) { return; }
        $input = $this->post();
        if (! is_array($input) || array_keys($input) !== ['phone']) { return $this->response(['status' => false, 'reason' => 'invalid_payload'], self::HTTP_UNPROCESSABLE_ENTITY); }
        $result = $this->tecnina_identity_authority->lookupPhone($input['phone']);
        if (! $result['ok']) { return $this->response(['status' => false, 'reason' => $result['reason']], self::HTTP_UNPROCESSABLE_ENTITY); }
        return $this->response(array_merge(['status' => true], $result), self::HTTP_OK);
    }

    public function email_verification_post()
    {
        if (! $this->authorize()) { return; }
        $input = $this->post();
        if (! is_array($input) || ! isset($input['email'], $input['purpose'])) { return $this->response(['status' => false, 'reason' => 'invalid_payload'], self::HTTP_UNPROCESSABLE_ENTITY); }
        $result = $this->tecnina_identity_authority->issueEmailVerification($input['email'], $input['purpose'], $input['client_id'] ?? null, $input['intake_id'] ?? null, $input['idempotency_key'] ?? null);
        return $this->response($result, $result['ok'] ? self::HTTP_OK : self::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function password_reset_post()
    {
        if (! $this->authorize()) { return; }
        $input = $this->post();
        if (! is_array($input) || ! isset($input['client_id'], $input['phone'])) { return $this->response(['status' => false, 'reason' => 'invalid_payload'], self::HTTP_UNPROCESSABLE_ENTITY); }
        $lookup = $this->tecnina_identity_authority->lookupPhone($input['phone']);
        if (! $lookup['ok'] || $lookup['match'] !== 'UNIQUE' || $lookup['client_id'] !== (int) $input['client_id']) { return $this->response(['status' => false, 'reason' => 'identity_not_unique'], self::HTTP_UNPROCESSABLE_ENTITY); }
        return $this->response($this->tecnina_identity_authority->issuePasswordReset((int) $input['client_id'], preg_replace('/\D+/', '', $input['phone']), $input['idempotency_key'] ?? null), self::HTTP_OK);
    }

    private function authorize()
    {
        $auth = $this->tecnina_bot_auth->authorize($this->input->get_request_header('Authorization', true));
        if (! $auth['ok']) { $this->response(['status' => false, 'reason' => $auth['reason']], $auth['status']); return false; }
        return true;
    }
}
