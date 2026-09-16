<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Shared authority rules; passwords are accepted only transiently and never logged. */
class Tecnina_identity_authority
{
    private $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->library('Tecnina_phone');
    }

    public function passwordHash($password, $confirmation = null)
    {
        if (! is_string($password) || ($confirmation !== null && (! is_string($confirmation) || ! hash_equals($password, $confirmation)))) {
            return ['ok' => false, 'reason' => 'password_confirmation'];
        }
        if (mb_strlen($password, 'UTF-8') < 6) {
            return ['ok' => false, 'reason' => 'password_too_short'];
        }
        // PASSWORD_DEFAULT is currently bcrypt; enforce its 72-byte effective boundary without trimming.
        if (PASSWORD_DEFAULT === PASSWORD_BCRYPT && strlen($password) > 72) {
            return ['ok' => false, 'reason' => 'password_too_long'];
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        return $hash === false ? ['ok' => false, 'reason' => 'password_hash_failed'] : ['ok' => true, 'hash' => $hash, 'algorithm' => password_get_info($hash)['algoName']];
    }

    public function lookupPhone($phone)
    {
        $canonical = $this->CI->tecnina_phone->normalizeCanonicalIdentity($phone);
        if ($canonical === null) {
            return ['ok' => false, 'reason' => 'invalid_phone'];
        }
        $rows = $this->CI->db->select('idClientes, celular, telefone, senha')->get('clientes')->result();
        $matches = [];
        foreach ($rows as $row) {
            foreach ([$row->celular, $row->telefone] as $candidate) {
                if ($this->CI->tecnina_phone->normalizeCanonicalIdentity($candidate) === $canonical) { $matches[(int) $row->idClientes] = $row; }
            }
        }
        if (count($matches) === 0) { return ['ok' => true, 'match' => 'NONE']; }
        if (count($matches) !== 1) { return ['ok' => true, 'match' => 'AMBIGUOUS']; }
        $client = reset($matches); $this->ensureLegacyIdentity($client->idClientes);
        $identity = $this->CI->db->get_where('tecnina_client_identity', ['client_id' => $client->idClientes])->row();
        return ['ok' => true, 'match' => 'UNIQUE', 'client_id' => (int) $client->idClientes,
            'phone_state' => $identity ? $identity->phone_state : 'NONE', 'has_password_credential' => ! empty($client->senha)];
    }

    public function ensureLegacyIdentity($clientId)
    {
        $existing = $this->CI->db->get_where('tecnina_client_identity', ['client_id' => $clientId])->row();
        if (! $existing) { $this->CI->db->insert('tecnina_client_identity', ['client_id' => $clientId]); }
    }

    public function issueEmailVerification($email, $purpose, $clientId = null, $intakeId = null, $idempotencyKey = null)
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! in_array($purpose, ['ACCOUNT_CREATION', 'PROFILE_CHANGE', 'LEGACY_EMAIL_CONFIRMATION'], true)) {
            return ['ok' => false, 'reason' => 'invalid_verification_request'];
        }
        $id = $this->uuid(); $code = (string) random_int(100000, 999999);
        $this->CI->db->insert('tecnina_email_verifications', [
            'id' => $id, 'client_id' => $clientId, 'intake_id' => $intakeId, 'purpose' => $purpose, 'email_candidate' => $email,
            'code_digest' => $this->digest($id . '|' . $code), 'expires_at' => date('Y-m-d H:i:s', time() + 900), 'idempotency_key' => $idempotencyKey,
        ]);
        // Delivery belongs to later channel integration; do not return the code over this API.
        return ['ok' => true, 'verification_id' => $id, 'expires_in_seconds' => 900];
    }

    public function issuePasswordReset($clientId, $canonicalPhone, $idempotencyKey = null)
    {
        $token = bin2hex(random_bytes(32)); $id = $this->uuid();
        $this->CI->db->insert('tecnina_password_resets', ['id' => $id, 'client_id' => $clientId, 'canonical_phone' => $canonicalPhone,
            'token_digest' => $this->digest($token), 'expires_at' => date('Y-m-d H:i:s', time() + 900), 'idempotency_key' => $idempotencyKey]);
        // The raw link token is intentionally returned only to the trusted delivery adapter.
        return ['ok' => true, 'reset_id' => $id, 'token' => $token, 'expires_in_seconds' => 900];
    }

    private function digest($value)
    {
        $key = (string) ($_ENV['MAPOS_BOT_TOKEN'] ?? '');
        if (strlen($key) < 32) { throw new RuntimeException('Identity authority is not configured.'); }
        return hash_hmac('sha256', $value, $key);
    }

    private function uuid()
    {
        $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
