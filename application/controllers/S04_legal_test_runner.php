<?php

defined('BASEPATH') or exit('No direct script access allowed');

class S04_legal_test_runner extends CI_Controller
{
    public function index()
    {
        if (! is_cli()) {
            show_404();
            return;
        }

        $this->load->database();
        $this->load->model('Tecnina_legal_model');
        require FCPATH . 'tests/TecninaS04LegalBehavioralTest.php';
    }
}
