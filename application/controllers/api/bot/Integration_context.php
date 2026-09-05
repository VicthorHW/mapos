<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . '/libraries/REST_Controller.php';

class Integration_context extends REST_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('Tecnina_bot_auth');
        $this->load->library('Tecnina_phone');
        $this->load->model('Tecnina_integration_context_model');
        $this->load->helper('portal_url');
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

        $row = $this->Tecnina_integration_context_model->getForOs((int) $osId);
        if (! $row) {
            $this->response(['status' => false, 'reason' => 'not_found'], self::HTTP_NOT_FOUND);

            return;
        }

        $phone = $this->tecnina_phone->normalizeBrazilianWhatsApp($row['celular']);
        if ($phone === null) {
            $phone = $this->tecnina_phone->normalizeBrazilianWhatsApp($row['telefone']);
        }

        $this->response([
            'status' => true,
            'os_id' => (int) $row['os_id'],
            'client_id' => (int) $row['client_id'],
            'recipient_phone' => $phone,
            'portal_url' => cliente_url(),
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
