<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Cliente extends CI_Controller
{
    public function password_reset($token = null)
    {
        $this->load->library('Tecnina_identity_authority');
        if ($this->input->method(true) !== 'POST') { show_error('Method not allowed', 405); return; }
        $result=$this->tecnina_identity_authority->consumePasswordReset((string)$token,$this->input->post('password'),$this->input->post('password_confirmation'));
        $this->output->set_content_type('application/json')->set_output(json_encode($result));
    }
}
