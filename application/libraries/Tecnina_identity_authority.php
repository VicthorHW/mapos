<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** MapOS-owned S03-A identity authority. Secrets remain digest-only. */
class Tecnina_identity_authority
{
    private $CI;
    public function __construct() { $this->CI =& get_instance(); $this->CI->load->library('Tecnina_phone'); $this->CI->load->library('Tecnina_identity_rate_limiter'); }

    public function passwordHash($password, $confirmation = null)
    {
        if (!is_string($password) || ($confirmation !== null && (!is_string($confirmation) || !hash_equals($password,$confirmation)))) return $this->fail('password_confirmation');
        if (mb_strlen($password,'UTF-8') < 6) return $this->fail('password_too_short');
        if (PASSWORD_DEFAULT === PASSWORD_BCRYPT && strlen($password) > 72) return $this->fail('password_too_long');
        $hash=password_hash($password,PASSWORD_DEFAULT);
        return $hash===false?$this->fail('password_hash_failed'):['ok'=>true,'hash'=>$hash,'algorithm_runtime'=>password_get_info($hash)['algoName']];
    }

    public function lookupCanonicalPhone($phone)
    {
        if (!is_string($phone)||preg_match('/^\d{8,15}$/',$phone)!==1) return $this->fail('invalid_canonical_phone');
        try {
            $rows=$this->CI->db->select('i.client_id,i.phone_state,c.senha')->from('tecnina_client_identity i')->join('clientes c','c.idClientes=i.client_id')->where('i.canonical_phone',$phone)->get()->result();
            $conflicts=$this->CI->db->get_where('tecnina_client_identity_phone_conflicts',['canonical_phone'=>$phone])->result();
        } catch (Throwable $e) { return ['ok'=>false,'reason'=>'unavailable']; }
        if (count($rows)!==1) return ['ok'=>true,'match'=>(count($rows)>1||count($conflicts)>0)?'AMBIGUOUS':'NONE'];
        return ['ok'=>true,'match'=>'UNIQUE','client_id'=>(int)$rows[0]->client_id,'phone_state'=>$rows[0]->phone_state,'has_password_credential'=>!empty($rows[0]->senha)];
    }

    public function issueEmailVerification(array $input)
    {
        $subject=$this->subject($input);$email=$input['email_candidate']??null;$key=$input['idempotency_key']??null;$purpose=$input['purpose']??null;
        if(!$subject||!is_string($email)||strlen($email)>100||!filter_var($email,FILTER_VALIDATE_EMAIL)||!in_array($purpose,['ACCOUNT_CREATION','PROFILE_CHANGE','LEGACY_EMAIL_CONFIRMATION'],true)||!$this->key($key)) return $this->fail('invalid_payload');
        $limit=$this->CI->tecnina_identity_rate_limiter->allow('email_issue',$subject['kind'].'|'.$subject['id'].'|'.strtolower($email),3,900); if($limit===null)return $this->fail('unavailable'); if(!$limit)return $this->fail('rate_limited');
        try {$fp=hash('sha256',$subject['kind'].'|'.$subject['id'].'|'.strtolower($email).'|'.$purpose);$old=$this->CI->db->get_where('tecnina_email_verifications',['idempotency_key'=>$key])->row();if($old)return hash_equals($old->request_fingerprint,$fp)?['ok'=>true,'challenge_id'=>$old->id,'expires_at'=>$old->expires_at,'replayed'=>true]:$this->fail('idempotency_conflict');
            $id=$this->uuid();$code=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);$expires=$this->utc(900);$this->CI->db->trans_start();$this->CI->db->where($subject['kind'],$subject['id'])->where('state','PENDING')->update('tecnina_email_verifications',['state'=>'SUPERSEDED']);$this->CI->db->insert('tecnina_email_verifications',['id'=>$id,$subject['kind']=>$subject['id'],'purpose'=>$purpose,'email_candidate'=>$email,'code_digest'=>$this->digest($id.'|'.$code),'expires_at'=>$expires,'idempotency_key'=>$key,'request_fingerprint'=>$fp]);$this->CI->db->trans_complete();if(!$this->CI->db->trans_status())return $this->fail('unavailable');
            if(!$this->deliverCode($email,$code)){$this->CI->db->where('id',$id)->where('state','PENDING')->update('tecnina_email_verifications',['state'=>'DELIVERY_FAILED']);return $this->fail('delivery_unavailable');}
            return ['ok'=>true,'challenge_id'=>$id,'expires_at'=>$expires];
        } catch(Throwable $e){return $this->fail('unavailable');}
    }

    public function verifyEmail(array $input)
    {
        try {$key=$input['idempotency_key']??null;$row=$this->CI->db->get_where('tecnina_email_verifications',['id'=>$input['challenge_id']??''])->row();$fp=$row&&isset($input['code'])?hash('sha256',$row->id.'|'.$this->digest($row->id.'|'.$input['code'])):'';
            if($row&&$row->state==='VERIFIED'){if(!$this->key($key)||$row->verify_idempotency_key!==$key||!hash_equals((string)$row->verify_fingerprint,$fp))return $this->fail('idempotency_conflict');return ['ok'=>true,'state'=>'VERIFIED','verified_at'=>$row->verified_at,'replayed'=>true];}
            if(!$row||!$this->key($key)||$row->state!=='PENDING'||!$this->future($row->expires_at)||$row->attempts>=5||!isset($input['code'])||!hash_equals($row->code_digest,$this->digest($row->id.'|'.$input['code']))){if($row&&$row->state==='PENDING')$this->CI->db->where('id',$row->id)->set('attempts','attempts+1',false)->update('tecnina_email_verifications');return $this->fail('invalid_or_expired_code');}
            $now=$this->utc();$this->CI->db->trans_start();$this->CI->db->where('id',$row->id)->where('state','PENDING')->update('tecnina_email_verifications',['state'=>'VERIFIED','verified_at'=>$now,'consumed_at'=>$now,'verify_idempotency_key'=>$key,'verify_fingerprint'=>$fp]);if($this->CI->db->affected_rows()!==1){$this->CI->db->trans_complete();return $this->fail('invalid_or_expired_code');}if($row->client_id!==null){$this->CI->db->where('client_id',$row->client_id)->update('tecnina_client_identity',['email_candidate'=>$row->email_candidate,'email_state'=>'VERIFIED','email_verified_at'=>$now]);$this->CI->db->where('idClientes',$row->client_id)->update('clientes',['email'=>$row->email_candidate]);}$this->CI->db->trans_complete();return $this->CI->db->trans_status()?['ok'=>true,'state'=>'VERIFIED','verified_at'=>$now]:$this->fail('unavailable');
        }catch(Throwable $e){return $this->fail('unavailable');}
    }

    public function issuePasswordReset(array $input)
    {
        $lookup=$this->lookupCanonicalPhone($input['canonical_phone']??'');$key=$input['idempotency_key']??null;if(!$lookup['ok']||$lookup['match']!=='UNIQUE'||$lookup['client_id']!==(int)($input['client_id']??0)||!$this->key($key))return ['ok'=>true,'state'=>'REQUEST_ACCEPTED'];$limit=$this->CI->tecnina_identity_rate_limiter->allow('reset_issue',(string)$lookup['client_id'],3,3600);if($limit===null)return $this->fail('unavailable');if(!$limit)return $this->fail('rate_limited');
        try {$fp=hash('sha256',$lookup['client_id'].'|'.$input['canonical_phone'].'|'.hash('sha256',(string)($input['phone_context_id']??'')).'|'.hash('sha256',(string)($input['proof']??'')));$old=$this->CI->db->get_where('tecnina_password_resets',['idempotency_key'=>$key])->row();if($old)return hash_equals($old->request_fingerprint,$fp)?['ok'=>true,'reset_url'=>null,'expires_at'=>$old->expires_at,'replayed'=>true]:$this->fail('idempotency_conflict');
            $id=$this->uuid();$token=bin2hex(random_bytes(32));$expires=$this->utc(900);$this->CI->db->trans_start();$this->CI->db->where('client_id',$lookup['client_id'])->where('state','PENDING')->update('tecnina_password_resets',['state'=>'SUPERSEDED']);$this->CI->db->insert('tecnina_password_resets',['id'=>$id,'client_id'=>$lookup['client_id'],'canonical_phone'=>$input['canonical_phone'],'token_digest'=>$this->digest($token),'expires_at'=>$expires,'idempotency_key'=>$key,'request_fingerprint'=>$fp]);$this->CI->db->trans_complete();return $this->CI->db->trans_status()?['ok'=>true,'reset_url'=>site_url('cliente/password-reset/'.$token),'expires_at'=>$expires]:$this->fail('unavailable');
        }catch(Throwable $e){return $this->fail('unavailable');}
    }

    public function consumePasswordReset($token,$password,$confirmation)
    {
        $p=$this->passwordHash($password,$confirmation);if(!$p['ok'])return $this->fail('invalid_or_expired_reset');try{$this->CI->db->trans_start();$row=$this->CI->db->query('SELECT * FROM tecnina_password_resets WHERE token_digest=? FOR UPDATE',[$this->digest($token)])->row();if(!$row||$row->state!=='PENDING'||!$this->future($row->expires_at)){$this->CI->db->trans_complete();return $this->fail('invalid_or_expired_reset');}$this->CI->db->where('id',$row->id)->where('state','PENDING')->update('tecnina_password_resets',['state'=>'CONSUMED','consumed_at'=>$this->utc()]);if($this->CI->db->affected_rows()!==1){$this->CI->db->trans_complete();return $this->fail('invalid_or_expired_reset');}$this->CI->db->where('idClientes',$row->client_id)->update('clientes',['senha'=>$p['hash']]);$this->CI->db->where('client_id',$row->client_id)->set('credential_version','credential_version+1',false)->update('tecnina_client_identity');$this->CI->db->trans_complete();return $this->CI->db->trans_status()?['ok'=>true,'reset'=>true]:$this->fail('unavailable');}catch(Throwable $e){return $this->fail('unavailable');}}
    private function deliverCode($email,$code){if(getenv('TECNINA_S03_TEST_DELIVERY')==='capture')return true;try{$this->CI->load->library('email');$this->CI->email->from((string)($_ENV['SMTP_FROM']??''),'TecNina');$this->CI->email->to($email);$this->CI->email->subject('Codigo de verificacao TecNina');$this->CI->email->message('Codigo de verificacao TecNina: '.$code);return (bool)$this->CI->email->send();}catch(Throwable $e){return false;}}
    private function subject($i){$c=isset($i['client_id']);$n=isset($i['intake_id']);return $c===$n?null:($c?['kind'=>'client_id','id'=>(int)$i['client_id']]:['kind'=>'intake_id','id'=>(string)$i['intake_id']]);}private function utc($s=0){return gmdate('Y-m-d H:i:s',time()+$s);}private function future($v){return strtotime($v.' UTC')>=time();}private function key($v){return is_string($v)&&$v!==''&&strlen($v)<=100;}private function digest($v){$k=(string)($_ENV['TECNINA_IDENTITY_HMAC_SECRET']??'');if(strlen($k)<32)throw new RuntimeException('identity_unavailable');return hash_hmac('sha256',$v,$k);}private function uuid(){$b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4));}private function fail($r){return ['ok'=>false,'reason'=>$r];}
}
