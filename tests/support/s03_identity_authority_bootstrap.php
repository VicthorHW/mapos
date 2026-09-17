<?php
/** Authorized disposable-target bootstrap contract; never points to production data. */
$ciBootstrap = getenv('TECNINA_S03_CI_BOOTSTRAP');
if (! $ciBootstrap || ! is_file($ciBootstrap)) {
    fwrite(STDERR, "S03 bootstrap: SKIPPED / ENVIRONMENT_NOT_AVAILABLE (missing isolated CI bootstrap)\n");
    exit(77);
}
require $ciBootstrap;
putenv('TECNINA_IDENTITY_HMAC_SECRET=testing-only-s03-hmac-secret-at-least-32-bytes');
putenv('TECNINA_S03_TEST_CODE=123456');
putenv('TECNINA_S03_TEST_DELIVERY=capture');
$ci = get_instance();
if (! isset($ci->tecnina_identity_authority)) { $ci->load->library('Tecnina_identity_authority'); }
function tecnina_s03_identity_fixture() {
    global $ci; $ci->db->trans_start();
    // The isolated bootstrap must have migrated S03-A; fixture inserts only test rows.
    foreach ([['nomeCliente'=>'S03 Unique','email'=>'old@example.test','celular'=>'4197403509'],['nomeCliente'=>'S03 Ambiguous A','email'=>'a@example.test','celular'=>'4199999999'],['nomeCliente'=>'S03 Ambiguous B','email'=>'b@example.test','celular'=>'4199999999']] as $client) { $ci->db->insert('clientes',$client); }
    $ids=$ci->db->select('idClientes')->like('nomeCliente','S03 ','after')->get('clientes')->result(); $unique=(int)$ids[0]->idClientes; $a=(int)$ids[1]->idClientes; $b=(int)$ids[2]->idClientes;
    $ci->db->insert('tecnina_client_identity',['client_id'=>$unique,'canonical_phone'=>'5541997403509','phone_state'=>'LEGACY_EXISTING']);
    foreach ([$a,$b] as $id) { $ci->db->insert('tecnina_client_identity',['client_id'=>$id]); $ci->db->insert('tecnina_client_identity_phone_conflicts',['canonical_phone'=>'5541999999999','client_id'=>$id]); }
    $ci->db->trans_complete(); return ['none'=>'5511999999999','unique'=>'5541997403509','ambiguous'=>'5541999999999','client_id'=>$unique,'email_code'=>'123456','email_issue'=>['client_id'=>$unique,'email_candidate'=>'new@example.test','purpose'=>'PROFILE_CHANGE','idempotency_key'=>'s03-email-issue'],'reset_issue'=>['client_id'=>$unique,'canonical_phone'=>'5541997403509','phone_context_id'=>'fixture','idempotency_key'=>'s03-reset-issue']];
}
function tecnina_s03_identity_row($id){global $ci;return $ci->db->get_where('tecnina_client_identity',['client_id'=>$id])->row();}
function tecnina_s03_conflict_count($phone){global $ci;return $ci->db->get_where('tecnina_client_identity_phone_conflicts',['canonical_phone'=>$phone])->num_rows();}
function tecnina_s03_persisted_contains_plaintext($table,$value){global $ci;$rows=$ci->db->get($table)->result_array();return strpos(json_encode($rows),(string)$value)!==false;}
function tecnina_s03_client_email($id){global $ci;return $ci->db->select('email')->get_where('clientes',['idClientes'=>$id])->row()->email;}
function tecnina_s03_token_from_url($url){return basename(parse_url($url,PHP_URL_PATH));}
function tecnina_s03_credential_version($id){global $ci;return (int)$ci->db->select('credential_version')->get_where('tecnina_client_identity',['client_id'=>$id])->row()->credential_version;}
