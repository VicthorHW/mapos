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
        if (! $this->rate('credential_hash', 'service', 60, 60)) { return; }
        $input = $this->post();
        if (! is_array($input) || array_diff(array_keys($input), ['password', 'password_confirmation']) !== [] || ! array_key_exists('password', $input)) {
            return $this->response(['status' => false, 'reason' => 'invalid_payload'], self::HTTP_UNPROCESSABLE_ENTITY);
        }
        $result = $this->tecnina_identity_authority->passwordHash($input['password'], $input['password_confirmation'] ?? null);
        if (! $result['ok']) { return $this->respondResult($result); }
        return $this->response(['status' => true, 'hash' => $result['hash'], 'algorithm_runtime' => $result['algorithm_runtime']], self::HTTP_OK);
    }

    public function lookup_post()
    {
        if (! $this->authorize()) { return; }
        if (! $this->rate('client_lookup', 'service', 300, 60)) { return; }
        $input = $this->post();
        if (! is_array($input) || array_keys($input) !== ['canonical_phone']) { return $this->response(['status' => false, 'reason' => 'invalid_payload'], self::HTTP_UNPROCESSABLE_ENTITY); }
        $result = $this->tecnina_identity_authority->lookupCanonicalPhone($input['canonical_phone']);
        if (! $result['ok']) { return $this->respondResult($result); }
        return $this->response(array_merge(['status' => true], $result), self::HTTP_OK);
    }

    public function email_verification_post()
    {
        if (! $this->authorize()) { return; }
        $input = $this->post();
        if (! is_array($input) || array_diff(array_keys($input), ['client_id','intake_id','email_candidate','purpose','idempotency_key']) !== []) { return $this->response(['status' => false, 'reason' => 'invalid_payload'], self::HTTP_UNPROCESSABLE_ENTITY); }
        $result = $this->tecnina_identity_authority->issueEmailVerification($input);
        return $this->respondResult($result);
    }

    public function password_reset_post()
    {
        if (! $this->authorize()) { return; }
        $proof_payload = $this->verify_context_proof('PASSWORD_RESET_ISSUE');
        if (!$proof_payload) { return; }
        $input = $this->post();
        if (! is_array($input) || array_diff(array_keys($input), ['client_id','canonical_phone','phone_context_id','idempotency_key']) !== [] || ! isset($input['client_id'], $input['canonical_phone'], $input['phone_context_id'], $input['idempotency_key'])) { return $this->response(['status' => false, 'reason' => 'invalid_payload'], self::HTTP_UNPROCESSABLE_ENTITY); }
        if (!$this->tecnina_context_proof->bind_password_reset($proof_payload, $input['client_id'], $input['phone_context_id'], $input['canonical_phone'])) {
            return $this->response(['status' => false, 'reason' => 'invalid_context_proof'], self::HTTP_FORBIDDEN);
        }

        return $this->respondResult($this->tecnina_identity_authority->issuePasswordReset($input));
    }

    public function email_verification_verify_post() {
        if (! $this->authorize()) return;
        $proof_payload = $this->verify_context_proof('EMAIL_VERIFICATION_VERIFY');
        if (!$proof_payload) return;
        $input=$this->post();
        if(!is_array($input)||array_diff(array_keys($input),['challenge_id','code','idempotency_key'])!==[])return $this->response(['status'=>false,'reason'=>'invalid_payload'],self::HTTP_UNPROCESSABLE_ENTITY);
        $this->db->select('intake_id, purpose');
        $this->db->where('id', $input['challenge_id']);
        $row = $this->db->get('tecnina_email_verifications')->row_array();
        if (!$row) { return $this->response(['status' => false, 'reason' => 'invalid_context_proof'], self::HTTP_FORBIDDEN); }
        if (!$this->tecnina_context_proof->bind_email_verification($proof_payload, $input['challenge_id'], $row['intake_id'], $row['purpose'])) {
            return $this->response(['status' => false, 'reason' => 'invalid_context_proof'], self::HTTP_FORBIDDEN);
        }

        return $this->respondResult($this->tecnina_identity_authority->verifyEmail($input));
    }

    private function verify_context_proof($expected_operation)
    {
        $proof = $this->input->get_request_header('X-Tecnina-Context-Proof', true);
        $this->load->library('tecnina_context_proof', ['secret' => getenv('TECNINA_CONTEXT_PROOF_HMAC_SECRET') ?: $this->config->item('tecnina_context_proof_hmac_secret')]);
        try { $result = $this->tecnina_context_proof->verify_proof($proof, $expected_operation); }
        catch (RuntimeException $e) { $this->response(['status' => false, 'reason' => 'context_authority_unavailable'], self::HTTP_SERVICE_UNAVAILABLE); return false; }
        if (!$result['status']) {
            $this->response(['status' => false, 'reason' => $result['reason']], $result['code']);
            return false;
        }
        return $result['payload'];
    }

    private function rate($scope,$subject,$limit,$seconds){$allowed=$this->tecnina_identity_rate_limiter->allow($scope,$subject,$limit,$seconds);if($allowed===true)return true;$this->response(['status'=>false,'reason'=>$allowed===null?'unavailable':'rate_limited'],$allowed===null?self::HTTP_SERVICE_UNAVAILABLE:self::HTTP_TOO_MANY_REQUESTS);return false;}

    private function respondResult($result)
    {
        if ($result['ok']) return $this->response($result, self::HTTP_OK);
        $status = $result['reason'] === 'rate_limited' ? self::HTTP_TOO_MANY_REQUESTS : ($result['reason'] === 'unavailable' || $result['reason'] === 'delivery_unavailable' ? self::HTTP_SERVICE_UNAVAILABLE : ($result['reason'] === 'idempotency_conflict' || $result['reason'] === 'invalid_or_expired_code' ? self::HTTP_CONFLICT : self::HTTP_UNPROCESSABLE_ENTITY));
        return $this->response(['status'=>false,'reason'=>$result['reason']], $status);
    }

    private function authorize()
    {
        $auth = $this->tecnina_bot_auth->authorize($this->input->get_request_header('Authorization', true));
        if (! $auth['ok']) { $this->response(['status' => false, 'reason' => $auth['reason']], $auth['status']); return false; }
        return true;
    }
}
