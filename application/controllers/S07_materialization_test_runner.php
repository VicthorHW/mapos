<?php

defined('BASEPATH') or exit('No direct script access allowed');

class S07_materialization_test_runner extends CI_Controller
{
    public function index()
    {
        if (! is_cli()) {
            show_404();
            return;
        }

        $this->load->database();
        $this->load->library('Tecnina_materialization_service');
        $this->load->library('Tecnina_attachment_storage');
        $this->load->model('Tecnina_receiving_model');
        require FCPATH . 'tests/TecninaS07MaterializationTest.php';
    }

    public function count_os()
    {
        if (! is_cli()) {
            show_404();
            return;
        }
        $this->load->database();
        echo (int) $this->db->count_all('os');
    }
}
