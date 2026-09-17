<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** S03-A authority rules: sensitive request values are never logged or persisted plaintext. */
class Tecnina_identity_authority
{
    private $CI;
    public function __construct() { $this->CI =& get_instance(); $this->CI->load->library('Tecnina_phone'); }
    public function passwordHash($password, $confirmation = null) {
        if (!is_string($password) || ($confirmation !== null && (!is_string($confirmation) || !hash_equals($password,$confirmation)))) return ['ok'=>false,'reason'=>'password_confirmation'];
        if (mb_strlen($password,'UTF-8') < 6) return ['ok'=>false,'reason'=>'password_too_short'];
        if (PASSWORD_DEFAULT === PASSWORD_BCRYPT && strlen($password)>72) return ['ok'=>false,'reason'=>'password_too_long'];
        $hash=password_hash($password,PASSWORD_DEFAULT); return $hash===false?['ok'=>false,'reason'=>'password_hash_failed']:['ok'=>true,'hash'=>$hash,'algorithm_runtime'=>password_get_info($hash)['algoName']];
    }
    public function lookupCanonicalPhone($phone) {
        if (!is_string($phone)||preg_match('/^\d{8,15}$/',$phone)!==1) return ['ok'=>false,'reason'=>'invalid_canonical_phone'];
        $rows=$this->CI->db->select('i.client_id,i.phone_state,c.senha')->from('tecnina_client_identity i')->join('clientes c','c.idClientes=i.client_id')->where('i.canonical_phone',$phone)->get()->result();
        if(count($rows)!==1) return ['ok'=>true,'match'=>count($rows)>1?'AMBIGUOUS':'NONE'];
        return ['ok'=>true,'match'=>'UNIQUE','client_id'=>(int)$rows[0]->client_id,'phone_state'=>$rows[0]->phone_state,'has_password_credential'=>!empty($rows[0]->senha)];
    }
    public function issueEmailVerification(array $input) {
        $one=isset($input['client_id']) xor isset($input['intake_id']); $email=$input['email_candidate']??null; $key=$input['idempotency_key']??null; $purpose=$input['purpose']??null;
        if(!$one||!is_string($email)||strlen($email)>100||!filter_var($email,FILTER_VALIDATE_EMAIL)||!in_array($purpose,['ACCOUNT_CREATION','PROFILE_CHANGE','LEGACY_EMAIL_CONFIRMATION'],true)||!$this->key($key))return ['ok'=>false,'reason'=>'invalid_payload'];
        $subject=isset($input['client_id'])?'client_id':'intake_id';$id=$this->uuid();$code=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);$fp=hash('sha256',$subject.'|'.$input[$subject].'|'.strtolower($email).'|'.$purpose);
        $old=$this->CI->db->get_where('tecnina_email_verifications',['idempotency_key'=>$key])->row();if($old)return hash_equals($old->request_fingerprint,$fp)?['ok'=>true,'challenge_id'=>$old->id,'expires_at'=>$old->expires_at,'replayed'=>true]:['ok'=>false,'reason'=>'idempotency_conflict'];
        $this->CI->db->trans_start();$this->CI->db->where($subject,$input[$subject])->where('state','PENDING')->update('tecnina_email_verifications',['state'=>'SUPERSEDED']);$expires=date('Y-m-d H:i:s',time()+900);$this->CI->db->insert('tecnina_email_verifications',['id'=>$id,$subject=>$input[$subject],'purpose'=>$purpose,'email_candidate'=>$email,'code_digest'=>$this->digest($id.'|'.$code),'expires_at'=>$expires,'idempotency_key'=>$key,'request_fingerprint'=>$fp]);$this->CI->db->trans_complete();return $this->CI->db->trans_status()?['ok'=>true,'challenge_id'=>$id,'expires_at'=>$expires,'_delivery_code'=>$code]:['ok'=>false,'reason'=>'unavailable'];
    }
    public function verifyEmail(array $input) {
        $row=$this->CI->db->get_where('tecnina_email_verifications',['id'=>$input['challenge_id']??''])->row();$good=$row&&$row->state==='PENDING'&&strtotime($row->expires_at)>=time()&&$row->attempts<5&&isset($input['code'])&&hash_equals($row->code_digest,$this->digest($row->id.'|'.$input['code']));
        if(!$good){if($row&&$row->state==='PENDING')$this->CI->db->where('id',$row->id)->set('attempts','attempts + 1',false)->update('tecnina_email_verifications');return ['ok'=>false,'reason'=>'invalid_or_expired_code'];}$now=date('Y-m-d H:i:s');$this->CI->db->trans_start();$this->CI->db->where('id',$row->id)->where('state','PENDING')->update('tecnina_email_verifications',['state'=>'VERIFIED','verified_at'=>$now,'consumed_at'=>$now]);if($row->client_id!==null)$this->CI->db->where('client_id',$row->client_id)->update('tecnina_client_identity',['email_candidate'=>$row->email_candidate,'email_state'=>'VERIFIED','email_verified_at'=>$now]);$this->CI->db->trans_complete();return ['ok'=>true,'state'=>'VERIFIED','verified_at'=>$now];
    }
    public function issuePasswordReset(array $input) {
        $lookup=$this->lookupCanonicalPhone($input['canonical_phone']??'');$key=$input['idempotency_key']??null;if(!$lookup['ok']||$lookup['match']!=='UNIQUE'||$lookup['client_id']!==(int)($input['client_id']??0)||!$this->key($key))return ['ok'=>true,'state'=>'REQUEST_ACCEPTED'];$fp=hash('sha256',$lookup['client_id'].'|'.$input['canonical_phone']);$old=$this->CI->db->get_where('tecnina_password_resets',['idempotency_key'=>$key])->row();if($old)return hash_equals($old->request_fingerprint,$fp)?['ok'=>true,'reset_url'=>null,'expires_at'=>$old->expires_at,'replayed'=>true]:['ok'=>false,'reason'=>'idempotency_conflict'];$id=$this->uuid();$token=bin2hex(random_bytes(32));$expires=date('Y-m-d H:i:s',time()+900);$this->CI->db->trans_start();$this->CI->db->where('client_id',$lookup['client_id'])->where('state','PENDING')->update('tecnina_password_resets',['state'=>'SUPERSEDED']);$this->CI->db->insert('tecnina_password_resets',['id'=>$id,'client_id'=>$lookup['client_id'],'canonical_phone'=>$input['canonical_phone'],'token_digest'=>$this->digest($token),'expires_at'=>$expires,'idempotency_key'=>$key,'request_fingerprint'=>$fp]);$this->CI->db->trans_complete();return ['ok'=>true,'reset_url'=>site_url('cliente/password-reset/'.$token),'expires_at'=>$expires];
    }
    public function consumePasswordReset($token,$password,$confirmation) {$row=$this->CI->db->get_where('tecnina_password_resets',['token_digest'=>$this->digest($token)])->row();$p=$this->passwordHash($password,$confirmation);if(!$row||$row->state!=='PENDING'||strtotime($row->expires_at)<time()||!$p['ok'])return ['ok'=>false,'reason'=>'invalid_or_expired_reset'];$this->CI->db->trans_start();$this->CI->db->where('id',$row->id)->where('state','PENDING')->update('tecnina_password_resets',['state'=>'CONSUMED','consumed_at'=>date('Y-m-d H:i:s')]);$this->CI->db->where('idClientes',$row->client_id)->update('clientes',['senha'=>$p['hash']]);$this->CI->db->where('client_id',$row->client_id)->set('credential_version','credential_version + 1',false)->update('tecnina_client_identity');$this->CI->db->trans_complete();return ['ok'=>true,'reset'=>true];}
    private function key($v){return is_string($v)&&$v!==''&&strlen($v)<=100;} private function digest($v){$key=(string)($_ENV['MAPOS_BOT_TOKEN']??'');if(strlen($key)<32)throw new RuntimeException('Identity authority unavailable');return hash_hmac('sha256',$v,$key);}private function uuid(){$b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4));}
}
