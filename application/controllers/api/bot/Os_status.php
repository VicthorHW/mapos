<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . '/libraries/REST_Controller.php';

class Os_status extends REST_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('Tecnina_bot_auth');
        $this->load->model('Tecnina_os_status_model');
    }

    public function index_get($osId = null)
    {
        if (! $this->authorizeRequest()) {
            return;
        }
        if (filter_var($osId, FILTER_VALIDATE_INT) === false || (int) $osId < 1) {
            $this->response(['status' => false, 'reason' => 'invalid_os_id'], self::HTTP_BAD_REQUEST);

            return;
        }

        $row = $this->Tecnina_os_status_model->getBasicStatus((int) $osId);
        if (! $row) {
            $this->response(['status' => false, 'reason' => 'not_found'], self::HTTP_NOT_FOUND);

            return;
        }

        $this->response([
            'status' => true,
            'os_id' => (int) $row['os_id'],
            'client_id' => (int) $row['client_id'],
            'mapos_status' => (string) $row['mapos_status'],
        ], self::HTTP_OK);
    }

    private function authorizeRequest()
    {
        $authorization = $this->input->get_request_header('Authorization', true);
        $auth = $this->tecnina_bot_auth->authorize($authorization);
        if (! $auth['ok']) {
            $this->response(['status' => false, 'reason' => $auth['reason']], $auth['status']);

            return false;
        }

        return true;
    }
}
