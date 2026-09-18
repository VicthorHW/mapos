<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Tecnina_identity_authority
{
    private $CI;
    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->library('Tecnina_phone');
        $this->CI->load->library('Tecnina_identity_rate_limiter');
    }

    public function passwordHash($password, $confirmation = null)
    {
        if (!is_string($password) || ($confirmation !== null && (!is_string($confirmation) || !hash_equals($password, $confirmation)))) return $this->fail('password_confirmation');
        if (mb_strlen($password, 'UTF-8') < 6) return $this->fail('password_too_short');
        if (PASSWORD_DEFAULT === PASSWORD_BCRYPT && strlen($password) > 72) return $this->fail('password_too_long');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        return $hash === false ? $this->fail('password_hash_failed') : ['ok'=>true,'hash'=>$hash,'algorithm_runtime'=>password_get_info($hash)['algoName']];
    }

    public function lookupCanonicalPhone($phone)
    {
        if (!is_string($phone) || preg_match('/^\d{8,15}$/', $phone) !== 1) return $this->fail('invalid_canonical_phone');
        try {
            $rows = $this->CI->db->select('i.client_id,i.phone_state,c.senha')->from('tecnina_client_identity i')->join('clientes c','c.idClientes=i.client_id')->where('i.canonical_phone',$phone)->get()->result();
            $conflicts = $this->CI->db->get_where('tecnina_client_identity_phone_conflicts',['canonical_phone'=>$phone])->result();
        } catch (Throwable $e) { return $this->fail('unavailable'); }
        if (count($conflicts) > 0) return ['ok'=>true,'match'=>'AMBIGUOUS'];
        if (count($rows) !== 1) return ['ok'=>true,'match'=>count($rows)>1?'AMBIGUOUS':'NONE'];
        return ['ok'=>true,'match'=>'UNIQUE','client_id'=>(int)$rows[0]->client_id,'phone_state'=>$rows[0]->phone_state,'has_password_credential'=>!empty($rows[0]->senha)];
    }

    public function issueEmailVerification(array $input)
    {
        $subject = $this->subject($input);
        $email = $input['email_candidate'] ?? null;
        $purpose = $input['purpose'] ?? null;
        $key = $input['idempotency_key'] ?? null;
        if (!$subject || !is_string($email) || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($purpose, ['ACCOUNT_CREATION', 'PROFILE_CHANGE', 'LEGACY_EMAIL_CONFIRMATION'], true) || !$this->key($key)) {
            return $this->fail('invalid_payload');
        }
        try {
            $fingerprint = hash_hmac('sha256', $subject['kind'] . '|' . $subject['id'] . '|' . strtolower($email) . '|' . $purpose, $this->secret());
            $old = $this->CI->db->get_where('tecnina_email_verifications', ['idempotency_key' => $key])->row();
            if ($old) {
                if (!hash_equals((string)$old->request_fingerprint, $fingerprint)) return $this->fail('idempotency_conflict');
                if ($old->state === 'PENDING' && $this->future($old->expires_at)) {
                    if ($old->client_id !== null) {
                        $this->CI->db->where('client_id', $old->client_id)->update('tecnina_client_identity', [
                            'email_candidate' => $old->email_candidate,
                            'email_state' => 'PENDING',
                            'email_verified_at' => null
                        ]);
                    }
                    return ['ok' => true, 'challenge_id' => $old->id, 'expires_at' => $old->expires_at, 'replayed' => true];
                }
                return $this->fail($old->state === 'DELIVERY_FAILED' ? 'delivery_unavailable' : 'idempotency_conflict');
            }
            foreach ([['email_issue_15m', 3, 900], ['email_issue_day', 10, 86400]] as [$scope, $max, $seconds]) {
                $allowed = $this->CI->tecnina_identity_rate_limiter->allow($scope, $subject['kind'] . '|' . $subject['id'] . '|' . strtolower($email), $max, $seconds);
                if ($allowed === null) return $this->fail('unavailable');
                if (!$allowed) return $this->fail('rate_limited');
            }
            $id = $this->uuid();
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = $this->utc(900);

            $this->CI->db->trans_start();
            $this->CI->db->where($subject['kind'], $subject['id'])->where('state', 'PENDING')->update('tecnina_email_verifications', ['state' => 'SUPERSEDED']);
            $this->CI->db->insert('tecnina_email_verifications', [
                'id' => $id,
                $subject['kind'] => $subject['id'],
                'purpose' => $purpose,
                'email_candidate' => $email,
                'code_digest' => $this->digest($id . '|' . $code),
                'expires_at' => $expires,
                'idempotency_key' => $key,
                'request_fingerprint' => $fingerprint
            ]);
            $this->CI->db->trans_complete();
            if (!$this->CI->db->trans_status()) return $this->fail('unavailable');

            if (!$this->deliverCode($email, $code)) {
                $this->CI->db->where('id', $id)->where('state', 'PENDING')->update('tecnina_email_verifications', ['state' => 'DELIVERY_FAILED']);
                return $this->fail('delivery_unavailable');
            }

            // Post-delivery: verify challenge is still PENDING before updating identity candidate (protect against supersede race)
            $this->CI->db->trans_start();
            $table = '`' . $this->CI->db->dbprefix('tecnina_email_verifications') . '`';
            $current = $this->CI->db->query("SELECT id, state FROM {$table} WHERE id = ? FOR UPDATE", [$id])->row();
            if ($current && $current->state === 'PENDING') {
                if ($subject['kind'] === 'client_id') {
                    $idTable = '`' . $this->CI->db->dbprefix('tecnina_client_identity') . '`';
                    $clientExists = $this->CI->db->query("SELECT client_id FROM {$idTable} WHERE client_id = ? FOR UPDATE", [$subject['id']])->row();
                    if (!$clientExists) {
                        $this->CI->db->trans_rollback();
                        $this->CI->db->where('id', $id)->where('state', 'PENDING')->update('tecnina_email_verifications', ['state' => 'DELIVERY_FAILED']);
                        return $this->fail('unavailable');
                    }
                    $this->CI->db->where('client_id', $subject['id'])->update('tecnina_client_identity', [
                        'email_candidate' => $email,
                        'email_state' => 'PENDING',
                        'email_verified_at' => null
                    ]);
                }
            }
            $this->CI->db->trans_complete();
            if (!$this->CI->db->trans_status()) return $this->fail('unavailable');

            return ['ok' => true, 'challenge_id' => $id, 'expires_at' => $expires];
        } catch (Throwable $e) {
            return $this->fail('unavailable');
        }
    }

    public function verifyEmail(array $input)
    {
        if (!isset($input['challenge_id'], $input['code'], $input['idempotency_key']) 
            || !is_string($input['challenge_id']) 
            || !is_string($input['code']) 
            || preg_match('/^\d{6}$/', $input['code']) !== 1 
            || !$this->key($input['idempotency_key'])) {
            return $this->fail('invalid_payload');
        }
        try {
            $key = $input['idempotency_key'];
            $challengeId = $input['challenge_id'];

            // Fast-path check: cross-challenge idempotency key collision
            $existingKey = $this->CI->db->get_where('tecnina_email_verifications', ['verify_idempotency_key' => $key])->row();
            if ($existingKey && $existingKey->id !== $challengeId) {
                return $this->fail('idempotency_conflict');
            }

            // Transactional row lock: authoritative check and mutation
            $this->CI->db->trans_start();
            $table = '`' . $this->CI->db->dbprefix('tecnina_email_verifications') . '`';
            $row = $this->CI->db->query("SELECT * FROM {$table} WHERE id = ? FOR UPDATE", [$challengeId])->row();
            if (!$row) {
                $this->CI->db->trans_rollback();
                return $this->fail('invalid_or_expired_code');
            }

            $fingerprint = hash('sha256', $row->id . '|' . $this->digest($row->id . '|' . $input['code']));

            if ($row->state === 'VERIFIED') {
                $this->CI->db->trans_complete();
                return $this->verifyReplay($row, $key, $fingerprint);
            }

            // Enforce five-attempt boundary and valid state on the locked row
            if ($row->state !== 'PENDING' || !$this->future($row->expires_at) || (int)$row->attempts >= 5) {
                $this->CI->db->trans_rollback();
                return $this->fail('invalid_or_expired_code');
            }

            $isCodeValid = hash_equals($row->code_digest, $this->digest($row->id . '|' . $input['code']));
            if (!$isCodeValid) {
                $this->CI->db->where('id', $row->id)->where('state', 'PENDING')->where('attempts <', 5)->set('attempts', 'attempts+1', false)->update('tecnina_email_verifications');
                $affected = $this->CI->db->affected_rows();
                $this->CI->db->trans_complete();
                if ($affected !== 1 || !$this->CI->db->trans_status()) {
                    return $this->fail('unavailable');
                }
                return $this->fail('invalid_or_expired_code');
            }

            // In-transaction cross-challenge key collision check
            $collision = $this->CI->db->query("SELECT id FROM {$table} WHERE verify_idempotency_key = ? AND id <> ? FOR UPDATE", [$key, $row->id])->row();
            if ($collision) {
                $this->CI->db->trans_rollback();
                return $this->fail('idempotency_conflict');
            }

            $now = $this->utc();
            $this->CI->db->where('id', $row->id)->where('state', 'PENDING')->where('attempts <', 5)->update('tecnina_email_verifications', [
                'state' => 'VERIFIED',
                'verified_at' => $now,
                'consumed_at' => $now,
                'verify_idempotency_key' => $key,
                'verify_fingerprint' => $fingerprint
            ]);
            if ($this->CI->db->affected_rows() !== 1) {
                $this->CI->db->trans_rollback();
                return $this->fail('invalid_or_expired_code');
            }

            if ($row->client_id !== null) {
                $this->CI->db->where('client_id', $row->client_id)->update('tecnina_client_identity', [
                    'email_candidate' => $row->email_candidate,
                    'email_state' => 'VERIFIED',
                    'email_verified_at' => $now
                ]);
                $this->CI->db->where('idClientes', $row->client_id)->update('clientes', [
                    'email' => $row->email_candidate
                ]);
            }
            $this->CI->db->trans_complete();
            return $this->CI->db->trans_status() ? ['ok' => true, 'state' => 'VERIFIED', 'verified_at' => $now] : $this->fail('unavailable');
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'uq_tecnina_email_verification_verify_key') !== false || strpos($msg, 'Duplicate entry') !== false) {
                return $this->fail('idempotency_conflict');
            }
            return $this->fail('unavailable');
        }
    }

    public function issuePasswordReset(array $input)
    {
        $phone = $input['canonical_phone'] ?? null;
        $key = $input['idempotency_key'] ?? null;
        $clientId = $input['client_id'] ?? null;
        $contextId = $input['phone_context_id'] ?? null;

        // Syntactic validation: malformed inputs must return 422 validation failure
        if (!is_string($phone) || preg_match('/^\d{8,15}$/', $phone) !== 1) {
            return $this->fail('invalid_canonical_phone');
        }
        if (!$this->key($key)) {
            return $this->fail('invalid_payload');
        }
        $isValidClientId = is_int($clientId) ? $clientId > 0 : (is_string($clientId) && ctype_digit($clientId) && (int)$clientId > 0);
        if (!$isValidClientId) {
            return $this->fail('invalid_payload');
        }
        if (!is_string($contextId) || $contextId === '' || strlen($contextId) > 100) {
            return $this->fail('invalid_payload');
        }

        $lookup = $this->lookupCanonicalPhone($phone);
        if (!$lookup['ok']) {
            return $this->fail($lookup['reason']);
        }

        // Privacy-preserving anti-enumeration for nonexistent or ambiguous identity
        if ($lookup['match'] !== 'UNIQUE' || $lookup['client_id'] !== (int)$clientId) {
            return ['ok' => true, 'state' => 'REQUEST_ACCEPTED'];
        }

        try {
            $fingerprint = hash_hmac('sha256', $lookup['client_id'] . '|' . $phone . '|' . (string)$contextId, $this->secret());
            $old = $this->CI->db->get_where('tecnina_password_resets', ['idempotency_key' => $key])->row();
            if ($old) {
                if (!hash_equals((string)$old->request_fingerprint, $fingerprint) || $old->state !== 'PENDING' || !$this->future($old->expires_at)) {
                    return $this->fail('idempotency_conflict');
                }
                return ['ok' => true, 'reset_url' => site_url('cliente/password-reset/' . $this->resetToken($old->id, $key)), 'expires_at' => $old->expires_at, 'replayed' => true];
            }
            $allowed = $this->CI->tecnina_identity_rate_limiter->allow('reset_issue', (string)$lookup['client_id'], 3, 3600);
            if ($allowed === null) return $this->fail('unavailable');
            if (!$allowed) return $this->fail('rate_limited');

            $id = $this->uuid();
            $token = $this->resetToken($id, $key);
            $expires = $this->utc(900);
            $this->CI->db->trans_start();
            $this->CI->db->where('client_id', $lookup['client_id'])->where('state', 'PENDING')->update('tecnina_password_resets', ['state' => 'SUPERSEDED']);
            $this->CI->db->insert('tecnina_password_resets', [
                'id' => $id,
                'client_id' => $lookup['client_id'],
                'canonical_phone' => $phone,
                'token_digest' => $this->digest($token),
                'expires_at' => $expires,
                'idempotency_key' => $key,
                'request_fingerprint' => $fingerprint
            ]);
            $this->CI->db->trans_complete();
            return $this->CI->db->trans_status() ? ['ok' => true, 'reset_url' => site_url('cliente/password-reset/' . $token), 'expires_at' => $expires] : $this->fail('unavailable');
        } catch (Throwable $e) {
            return $this->fail('unavailable');
        }
    }

    public function consumePasswordReset($token, $password, $confirmation)
    {
        try {
            $this->CI->db->trans_start();
            $table = '`' . $this->CI->db->dbprefix('tecnina_password_resets') . '`';
            $row = $this->CI->db->query("SELECT * FROM {$table} WHERE token_digest=? FOR UPDATE", [$this->digest($token)])->row();
            if (!$row) {
                $this->CI->db->trans_complete();
                return $this->fail('invalid_or_expired_reset');
            }
            if ($row->state !== 'PENDING' || !$this->future($row->expires_at)) {
                $this->CI->db->trans_complete();
                return $this->fail('invalid_or_expired_reset');
            }
            if ((int)$row->attempts >= 10) {
                $this->CI->db->trans_complete();
                return $this->fail('rate_limited');
            }

            $isValidConfirmation = is_string($password) && is_string($confirmation) && hash_equals($password, $confirmation);
            $passwordResult = $isValidConfirmation ? $this->passwordHash($password, $confirmation) : ['ok' => false, 'reason' => 'password_confirmation'];
            if (!$passwordResult['ok']) {
                $this->CI->db->where('id', $row->id)->where('state', 'PENDING')->set('attempts', 'attempts+1', false)->update('tecnina_password_resets');
                $affected = $this->CI->db->affected_rows();
                $this->CI->db->trans_complete();
                if ($affected !== 1 || !$this->CI->db->trans_status()) {
                    return $this->fail('unavailable');
                }
                return $this->fail('invalid_or_expired_reset');
            }

            $this->CI->db->where('id', $row->id)->where('state', 'PENDING')
                ->set('attempts', 'attempts+1', false)
                ->set('state', 'CONSUMED')
                ->set('consumed_at', $this->utc())
                ->update('tecnina_password_resets');
            if ($this->CI->db->affected_rows() !== 1) {
                $this->CI->db->trans_complete();
                return $this->fail('invalid_or_expired_reset');
            }
            $this->CI->db->where('idClientes', $row->client_id)->update('clientes', ['senha' => $passwordResult['hash']]);
            $this->CI->db->where('client_id', $row->client_id)->set('credential_version', 'credential_version+1', false)->update('tecnina_client_identity');
            $this->CI->db->trans_complete();
            return $this->CI->db->trans_status() ? ['ok' => true, 'reset' => true] : $this->fail('unavailable');
        } catch (Throwable $e) {
            return $this->fail('unavailable');
        }
    }

    private function verifyReplay($row,$key,$fingerprint){if($row->state!=='VERIFIED'||!$this->key($key)||$row->verify_idempotency_key!==$key||!hash_equals((string)$row->verify_fingerprint,$fingerprint))return $this->fail('idempotency_conflict');return ['ok'=>true,'state'=>'VERIFIED','verified_at'=>$row->verified_at,'replayed'=>true];}
    private function deliverCode($email,$code){if(ENVIRONMENT==='testing'&&isset($GLOBALS['tecnina_s03_delivery_spy'])&&is_callable($GLOBALS['tecnina_s03_delivery_spy']))return (bool)call_user_func($GLOBALS['tecnina_s03_delivery_spy'],$email,$code);try{$this->CI->load->library('email');$this->CI->email->from((string)($_ENV['SMTP_FROM']??''),'TecNina');$this->CI->email->to($email);$this->CI->email->subject('Codigo de verificacao TecNina');$this->CI->email->message('Codigo de verificacao TecNina: '.$code);return (bool)$this->CI->email->send();}catch(Throwable $e){return false;}}
    private function resetToken($id,$key){return hash_hmac('sha256','reset|'.$id.'|'.$key,$this->secret());}
    private function subject($i)
    {
        $hasClient = array_key_exists('client_id', $i) && $i['client_id'] !== null;
        $hasIntake = array_key_exists('intake_id', $i) && $i['intake_id'] !== null;
        if ($hasClient === $hasIntake) {
            return null;
        }
        if ($hasClient) {
            $val = $i['client_id'];
            $isValidInt = is_int($val) ? $val > 0 : (is_string($val) && ctype_digit($val) && (int)$val > 0);
            return $isValidInt ? ['kind' => 'client_id', 'id' => (int)$val] : null;
        }
        $val = $i['intake_id'];
        if (!is_string($val) || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $val) !== 1) {
            return null;
        }
        return ['kind' => 'intake_id', 'id' => strtolower($val)];
    }
    private function utc($s=0){return gmdate('Y-m-d H:i:s',time()+$s);} private function future($v){return strtotime($v.' UTC')>=time();} private function key($v){return is_string($v)&&$v!==''&&strlen($v)<=100;}
    private function secret(){$key=(string)($_ENV['TECNINA_IDENTITY_HMAC_SECRET']??'');if(strlen($key)<32)throw new RuntimeException('identity_unavailable');return $key;} private function digest($v){return hash_hmac('sha256',$v,$this->secret());}
    private function uuid(){$b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4));} private function fail($reason){return ['ok'=>false,'reason'=>$reason];}
}
