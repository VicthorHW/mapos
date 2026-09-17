<?php
/** Authorized disposable-target bootstrap contract; never points to production data. */
if (! function_exists('get_instance')) {
    fwrite(STDERR, "S03 bootstrap: SKIPPED / ENVIRONMENT_NOT_AVAILABLE (run through APP_ENVIRONMENT=testing php index.php s03_identity_test_runner)\n");
    exit(77);
}
$_ENV['TECNINA_IDENTITY_HMAC_SECRET'] = 'testing-only-s03-hmac-secret-at-least-32-bytes';
$GLOBALS['tecnina_s03_delivery_spy'] = static function ($email, $code) { $GLOBALS['tecnina_s03_last_code'] = $code; return true; };
$ci = get_instance();
if (! isset($ci->tecnina_identity_authority)) { $ci->load->library('Tecnina_identity_authority'); }
function tecnina_s03_identity_fixture() {
    global $ci; $unique=(int)$ci->db->select('idClientes')->get_where('clientes',['nomeCliente'=>'S03 Unique'])->row()->idClientes;
    return ['none'=>'5511999999999','unique'=>'5541997403509','ambiguous'=>'5541999999999','client_id'=>$unique,'email_issue'=>['client_id'=>$unique,'email_candidate'=>'new@example.test','purpose'=>'PROFILE_CHANGE','idempotency_key'=>'s03-email-issue'],'reset_issue'=>['client_id'=>$unique,'canonical_phone'=>'5541997403509','phone_context_id'=>'fixture','idempotency_key'=>'s03-reset-issue']];
}
function tecnina_s03_identity_row($id){global $ci;return $ci->db->get_where('tecnina_client_identity',['client_id'=>$id])->row();}
function tecnina_s03_conflict_count($phone){global $ci;return $ci->db->get_where('tecnina_client_identity_phone_conflicts',['canonical_phone'=>$phone])->num_rows();}
function tecnina_s03_persisted_contains_plaintext($table,$value){global $ci;$rows=$ci->db->get($table)->result_array();return strpos(json_encode($rows),(string)$value)!==false;}
function tecnina_s03_client_email($id){global $ci;return $ci->db->select('email')->get_where('clientes',['idClientes'=>$id])->row()->email;}
function tecnina_s03_token_from_url($url){return basename(parse_url($url,PHP_URL_PATH));}
function tecnina_s03_credential_version($id){global $ci;return (int)$ci->db->select('credential_version')->get_where('tecnina_client_identity',['client_id'=>$id])->row()->credential_version;}
function tecnina_s03_delivery_code(){return $GLOBALS['tecnina_s03_last_code']??null;}
function tecnina_s03_client_id($name){global $ci;return (int)$ci->db->select('idClientes')->get_where('clientes',['nomeCliente'=>$name])->row()->idClientes;}
function tecnina_s03_verification_state($id){global $ci;return $ci->db->select('state')->get_where('tecnina_email_verifications',['id'=>$id])->row()->state;}
function tecnina_s03_verification_attempts($id){global $ci;return (int)$ci->db->select('attempts')->get_where('tecnina_email_verifications',['id'=>$id])->row()->attempts;}
function tecnina_s03_add_conflict($phone,$id){global $ci;$ci->db->insert('tecnina_client_identity_phone_conflicts',['canonical_phone'=>$phone,'client_id'=>$id]);}
function tecnina_s03_remove_conflict($phone,$id){global $ci;$ci->db->delete('tecnina_client_identity_phone_conflicts',['canonical_phone'=>$phone,'client_id'=>$id]);}
function tecnina_s03_expire_verification($id){global $ci;$ci->db->where('id',$id)->update('tecnina_email_verifications',['expires_at'=>'2000-01-01 00:00:00']);}
function tecnina_s03_expire_latest_reset(){global $ci;$row=$ci->db->order_by('created_at','DESC')->get('tecnina_password_resets',1)->row();$ci->db->where('id',$row->id)->update('tecnina_password_resets',['expires_at'=>'2000-01-01 00:00:00']);return $row->id;}
function tecnina_s03_reset_state($id){global $ci;return $ci->db->select('state')->get_where('tecnina_password_resets',['id'=>$id])->row()->state;}
function tecnina_s03_rate_dependency_unavailable(){global $ci;$table=$ci->db->dbprefix('tecnina_identity_rate_limits');$hidden=$table.'_unavailable_test';$ci->db->query("RENAME TABLE `{$table}` TO `{$hidden}`");try{return $ci->tecnina_identity_rate_limiter->allow('unavailable_test','fixture',1,60);}finally{$ci->db->query("RENAME TABLE `{$hidden}` TO `{$table}`");}}
