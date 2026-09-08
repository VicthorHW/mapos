<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . '/libraries/REST_Controller.php';

class Client_open_os extends REST_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('Tecnina_bot_auth');
        $this->load->model('Tecnina_client_open_os_model');
    }

    public function index_get($clientId = null)
    {
        if (! $this->authorizeRequest()) {
            return;
        }
        if (filter_var($clientId, FILTER_VALIDATE_INT) === false || (int) $clientId < 1) {
            $this->response(['status' => false, 'reason' => 'invalid_client_id'], self::HTTP_BAD_REQUEST);

            return;
        }

        $orders = [];
        foreach ($this->Tecnina_client_open_os_model->getOpenByClientId((int) $clientId) as $row) {
            $orders[] = [
                'os_id' => (int) $row['os_id'],
                'mapos_status' => (string) $row['mapos_status'],
                'device_type' => $this->nullableText($row['device_type']),
                'brand' => $this->nullableText($row['brand']),
                'model' => $this->nullableText($row['model']),
            ];
        }
        $this->response(['status' => true, 'orders' => $orders], self::HTTP_OK);
    }

    private function nullableText($value)
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (string) $value;
    }

    private function authorizeRequest()
    {
        $auth = $this->tecnina_bot_auth->authorize(
            $this->input->get_request_header('Authorization', true)
        );
        if (! $auth['ok']) {
            $this->response(['status' => false, 'reason' => $auth['reason']], $auth['status']);

            return false;
        }

        return true;
    }
}
