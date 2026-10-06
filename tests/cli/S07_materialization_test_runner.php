<?php

defined('BASEPATH') or require_once __DIR__ . '/bootstrap.php';

class S07_materialization_test_runner extends CI_Controller
{
    public function index()
    {
        $this->load->database();
        $this->load->library('Tecnina_materialization_service');
        $this->load->library('Tecnina_attachment_storage');
        $this->load->model('Tecnina_receiving_model');
        require FCPATH . 'tests/TecninaS07MaterializationTest.php';
    }

    public function count_os()
    {
        $this->load->database();
        echo (int) $this->db->count_all('os');
    }
}

if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $runner = new S07_materialization_test_runner();
    $method = $argv[1] ?? 'index';
    if (method_exists($runner, $method)) {
        $runner->$method();
    } else {
        fwrite(STDERR, "Unknown method: {$method}\n");
        exit(1);
    }
}
