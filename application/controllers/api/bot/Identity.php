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
        return $this->response(['status' => true, 'hash' => $result['hash'], 'algorithm_runtime' => $result['algorithm_runtime']], self::HTTP_OK);
    }

    public function lookup_post()
    {
        if (! $this->authorize()) { return; }
        $input = $this->post();
        if (! is_array($input) || array_keys($input) !== ['canonical_phone']) { return $this->response(['status' => false, 'reason' => 'invalid_payload'], self::HTTP_UNPROCESSABLE_ENTITY); }
        $result = $this->tecnina_identity_authority->lookupCanonicalPhone($input['canonical_phone']);
        if (! $result['ok']) { return $this->response(['status' => false, 'reason' => $result['reason']], self::HTTP_UNPROCESSABLE_ENTITY); }
        return $this->response(array_merge(['status' => true], $result), self::HTTP_OK);
    }

    public function email_verification_post()
    {
        if (! $this->authorize()) { return; }
        $input = $this->post();
        if (! is_array($input)) { return $this->response(['status' => false, 'reason' => 'invalid_payload'], self::HTTP_UNPROCESSABLE_ENTITY); }
        $result = $this->tecnina_identity_authority->issueEmailVerification($input);
        return $this->response($result, $result['ok'] ? self::HTTP_OK : self::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function password_reset_post()
    {
        if (! $this->authorize()) { return; }
        $input = $this->post();
        if (! is_array($input) || ! isset($input['client_id'], $input['phone'])) { return $this->response(['status' => false, 'reason' => 'invalid_payload'], self::HTTP_UNPROCESSABLE_ENTITY); }
        return $this->response($this->tecnina_identity_authority->issuePasswordReset($input), self::HTTP_OK);
    }

    public function email_verification_verify_post() { if (! $this->authorize()) return; $result=$this->tecnina_identity_authority->verifyEmail($this->post()); return $this->response($result,$result['ok']?self::HTTP_OK:self::HTTP_CONFLICT); }

    private function authorize()
    {
        $auth = $this->tecnina_bot_auth->authorize($this->input->get_request_header('Authorization', true));
        if (! $auth['ok']) { $this->response(['status' => false, 'reason' => $auth['reason']], $auth['status']); return false; }
        return true;
    }
}
