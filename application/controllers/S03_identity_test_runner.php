<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Repository-owned CLI entry point for an isolated APP_ENVIRONMENT=testing database. */
class S03_identity_test_runner extends CI_Controller
{
    public function index()
    {
        if (! is_cli() || ENVIRONMENT !== 'testing') { show_404(); return; }
        $this->load->database();
        $this->load->library('Tecnina_identity_authority');
        $this->load->library('migration');
        if ($this->db->table_exists('tecnina_client_identity')) { fwrite(STDERR, "S03 runner: FAIL (disposable DB must start before S03 migration)\n"); exit(1); }
        foreach ([
            ['nomeCliente'=>'S03 None','documento'=>'S03-0','email'=>'none@example.test','celular'=>null,'telefone'=>'','senha'=>''],
            ['nomeCliente'=>'S03 Unique','documento'=>'S03-1','email'=>'old@example.test','celular'=>'4197403509','telefone'=>'','senha'=>''],
            ['nomeCliente'=>'S03 Ambiguous A','documento'=>'S03-2','email'=>'a@example.test','celular'=>'4199999999','telefone'=>'','senha'=>''],
            ['nomeCliente'=>'S03 Ambiguous B','documento'=>'S03-3','email'=>'b@example.test','celular'=>'+5541999999999','telefone'=>'','senha'=>''],
            ['nomeCliente'=>'S03 Multiple','documento'=>'S03-4','email'=>'multi@example.test','celular'=>'4198888888','telefone'=>'4197777777','senha'=>''],
        ] as $client) { $this->db->insert('clientes',$client); }
        $this->load->dbforge(); require_once APPPATH . 'database/migrations/20260916120000_add_s03_identity_credential_authority.php';
        $migration = new Migration_add_s03_identity_credential_authority(); $migration->up();
        require FCPATH . 'tests/TecninaS03IdentityAuthorityBehaviorTest.php';
    }
}
