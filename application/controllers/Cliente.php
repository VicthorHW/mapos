<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Cliente extends CI_Controller
{
    public function password_reset($token = null)
    {
        $this->load->library('Tecnina_identity_authority');
        $this->load->library('Tecnina_identity_rate_limiter');
        if ($this->input->method(true) !== 'POST') { $this->output->set_status_header(405); return; }
        // CodeIgniter CSRF middleware protects this POST when csrf_protection is enabled.
        // Proxy access-log token redaction is a target-infrastructure validation item.
        $tokenLimit=$this->tecnina_identity_rate_limiter->allow('reset_consume_token',hash('sha256',(string)$token),10,3600);$ipLimit=$this->tecnina_identity_rate_limiter->allow('reset_consume_ip',hash('sha256',$this->input->ip_address()),30,3600);if($tokenLimit===null||$ipLimit===null){$this->output->set_status_header(503)->set_content_type('application/json')->set_output(json_encode(['ok'=>false,'reason'=>'unavailable']));return;}if(!$tokenLimit||!$ipLimit){$this->output->set_status_header(429)->set_content_type('application/json')->set_output(json_encode(['ok'=>false,'reason'=>'rate_limited']));return;}
        $result=$this->tecnina_identity_authority->consumePasswordReset((string)$token,$this->input->post('password'),$this->input->post('password_confirmation'));
        $this->output->set_status_header($result['ok'] ? 200 : 409)->set_content_type('application/json')->set_output(json_encode($result));
    }
}
