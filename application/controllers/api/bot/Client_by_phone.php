<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . '/libraries/REST_Controller.php';

class Client_by_phone extends REST_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('Tecnina_bot_auth');
        $this->load->library('Tecnina_phone');
        $this->load->model('Tecnina_client_lookup_model');
    }

    public function index_get()
    {
        if (! $this->authorizeRequest()) {
            return;
        }

        $phone = $this->tecnina_phone->normalizeBrazilianIdentity(
            $this->input->get('phone', true)
        );
        if ($phone === null) {
            $this->response(['status' => false, 'reason' => 'invalid_phone'], self::HTTP_BAD_REQUEST);

            return;
        }

        $clientIds = [];
        foreach ($this->Tecnina_client_lookup_model->mobileCandidates() as $candidate) {
            if (
                $this->tecnina_phone->normalizeBrazilianIdentity($candidate['celular']) === $phone ||
                $this->tecnina_phone->normalizeBrazilianIdentity($candidate['telefone']) === $phone
            ) {
                $clientIds[(int) $candidate['client_id']] = true;
            }
        }

        $ids = array_keys($clientIds);
        if (count($ids) === 0) {
            $this->response(['status' => true, 'match' => 'none'], self::HTTP_OK);

            return;
        }

        if (count($ids) > 1) {
            $this->response(['status' => true, 'match' => 'ambiguous'], self::HTTP_OK);

            return;
        }

        $this->response([
            'status' => true,
            'match' => 'unique',
            'client_id' => (int) $ids[0],
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
