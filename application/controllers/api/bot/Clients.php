<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . '/libraries/REST_Controller.php';

class Clients extends REST_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('Tecnina_bot_auth');
        $this->load->library('Tecnina_phone');
        $this->load->library('Customer_welcome_email');
        $this->load->model('Tecnina_bot_client_model');
    }

    public function index_post()
    {
        if (! $this->authorizeRequest()) {
            return;
        }
        $input = $this->post();
        if (! is_array($input) || array_diff(array_keys($input), ['name', 'email', 'cpf', 'phone']) !== []) {
            $this->response(['status' => false, 'reason' => 'invalid_payload'], self::HTTP_BAD_REQUEST);

            return;
        }
        $name = trim((string) ($input['name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $cpf = preg_replace('/\D+/', '', (string) ($input['cpf'] ?? ''));
        $phone = $this->tecnina_phone->normalizeIdentity($input['phone'] ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 255
            || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ! $this->validCpf($cpf) || $phone === null) {
            $this->response(['status' => false, 'reason' => 'invalid_client_fields'], self::HTTP_UNPROCESSABLE_ENTITY);

            return;
        }
        try {
            $result = $this->Tecnina_bot_client_model->createOrFind([
                'name' => $name, 'email' => $email, 'cpf' => $cpf, 'phone' => $phone,
            ]);
            if (! $result['ok']) {
                $this->response(['status' => false, 'reason' => $result['reason']], self::HTTP_CONFLICT);

                return;
            }
            if ($result['result'] === 'created') {
                $this->customer_welcome_email->queue((int) $result['client_id']);
            }
            $this->response([
                'status' => true,
                'result' => $result['result'],
                'client_id' => (int) $result['client_id'],
            ], self::HTTP_OK);
        } catch (Throwable $exception) {
            log_message('error', 'TecNina bot client creation failed: ' . get_class($exception));
            $this->response(['status' => false, 'reason' => 'client_creation_unavailable'], self::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function validCpf($cpf)
    {
        if (! is_string($cpf) || strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        for ($digit = 9; $digit < 11; $digit++) {
            $sum = 0;
            for ($index = 0; $index < $digit; $index++) {
                $sum += ((int) $cpf[$index]) * (($digit + 1) - $index);
            }
            $check = ((10 * $sum) % 11) % 10;
            if ($check !== (int) $cpf[$digit]) {
                return false;
            }
        }

        return true;
    }

    private function authorizeRequest()
    {
        $auth = $this->tecnina_bot_auth->authorize($this->input->get_request_header('Authorization', true));
        if (! $auth['ok']) {
            $this->response(['status' => false, 'reason' => $auth['reason']], $auth['status']);

            return false;
        }

        return true;
    }
}
