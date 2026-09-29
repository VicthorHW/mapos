<?php

defined('BASEPATH') or exit('No direct script access allowed');

class S06a_receiving_test_runner extends CI_Controller
{
    public function index()
    {
        if (! is_cli()) {
            show_404();
            return;
        }

        $this->load->database();
        $this->load->library('tecnina_attachment_storage');
        $this->load->model('Tecnina_receiving_model');
        require FCPATH . 'tests/TecninaS06APhysicalReceivingTest.php';
    }
}
