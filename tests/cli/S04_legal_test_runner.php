<?php

defined('BASEPATH') or require_once __DIR__ . '/bootstrap.php';

class S04_legal_test_runner extends CI_Controller
{
    public function index()
    {
        $this->load->database();
        $this->load->model('Tecnina_legal_model');
        require FCPATH . 'tests/TecninaS04LegalBehavioralTest.php';
    }
}

if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $runner = new S04_legal_test_runner();
    $method = $argv[1] ?? 'index';
    if (method_exists($runner, $method)) {
        $runner->$method();
    } else {
        fwrite(STDERR, "Unknown method: {$method}\n");
        exit(1);
    }
}
