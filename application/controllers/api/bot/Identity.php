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
        if (! $this->verify_context_proof('PASSWORD_RESET')) { return; }
        $input = $this->post();
        if (! is_array($input) || array_diff(array_keys($input), ['client_id','canonical_phone','phone_context_id','idempotency_key']) !== [] || ! isset($input['client_id'], $input['canonical_phone'], $input['phone_context_id'], $input['idempotency_key'])) { return $this->response(['status' => false, 'reason' => 'invalid_payload'], self::HTTP_UNPROCESSABLE_ENTITY); }
        return $this->respondResult($this->tecnina_identity_authority->issuePasswordReset($input));
    }

    public function email_verification_verify_post() { if (! $this->authorize()) return; if (!$this->verify_context_proof("EMAIL_VERIFICATION_VERIFY")) return; $input=$this->post();if(!is_array($input)||array_diff(array_keys($input),['challenge_id','code','idempotency_key'])!==[])return $this->response(['status'=>false,'reason'=>'invalid_payload'],self::HTTP_UNPROCESSABLE_ENTITY);return $this->respondResult($this->tecnina_identity_authority->verifyEmail($input)); }

    private function verify_context_proof($expected_operation)
    {
        $proof = $this->input->get_request_header('X-Tecnina-Context-Proof', true);
        $secret = getenv('TECNINA_CONTEXT_PROOF_HMAC_SECRET') ?: $this->config->item('tecnina_context_proof_hmac_secret');
        if (empty($secret)) {
            $this->response(['status' => false, 'reason' => 'context_authority_unavailable'], self::HTTP_SERVICE_UNAVAILABLE);
            return false;
        }
        if (empty($proof) || strpos($proof, 'v1.') !== 0) {
            $this->response(['status' => false, 'reason' => 'invalid_context_proof'], self::HTTP_FORBIDDEN);
            return false;
        }
        $parts = explode('.', $proof);
        if (count($parts) !== 3) {
            $this->response(['status' => false, 'reason' => 'invalid_context_proof'], self::HTTP_FORBIDDEN);
            return false;
        }
        $b64_payload = $parts[1];
        $signature = $parts[2];
        $expected_signature = hash_hmac('sha256', $b64_payload, $secret);
        if (!hash_equals($expected_signature, $signature)) {
            $this->response(['status' => false, 'reason' => 'invalid_context_proof'], self::HTTP_FORBIDDEN);
            return false;
        }
        $padded_b64 = str_pad($b64_payload, strlen($b64_payload) + (4 - strlen($b64_payload) % 4) % 4, '=', STR_PAD_RIGHT);
        $json_str = base64_decode(strtr($padded_b64, '-_', '+/'), true);
        if ($json_str === false) {
            $this->response(['status' => false, 'reason' => 'invalid_context_proof'], self::HTTP_FORBIDDEN);
            return false;
        }
        $payload = json_decode($json_str, true);
        if (!is_array($payload) || !isset($payload['operation'], $payload['expires_at'])) {
            $this->response(['status' => false, 'reason' => 'invalid_context_proof'], self::HTTP_FORBIDDEN);
            return false;
        }
        if ($payload['operation'] !== $expected_operation) {
            $this->response(['status' => false, 'reason' => 'invalid_context_proof'], self::HTTP_FORBIDDEN);
            return false;
        }
        if (time() > $payload['expires_at']) {
            $this->response(['status' => false, 'reason' => 'invalid_context_proof'], self::HTTP_FORBIDDEN);
            return false;
        }
        return $payload;
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
