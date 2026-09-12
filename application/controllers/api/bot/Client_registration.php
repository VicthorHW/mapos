<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . '/libraries/REST_Controller.php';

class Client_registration extends REST_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('Tecnina_bot_auth');
        $this->load->model('mapos_model');
        $this->load->model('email_model');
    }

    public function email_code_post()
    {
        if (! $this->authorizeRequest()) {
            return;
        }
        $input = $this->post();
        $email = trim((string) ($input['email'] ?? ''));
        $code = trim((string) ($input['code'] ?? ''));
        if (! is_array($input)
            || count($input) !== 2
            || array_diff(array_keys($input), ['email', 'code']) !== []
            || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || preg_match('/^\d{6}$/', $code) !== 1) {
            $this->response(['status' => false, 'reason' => 'invalid_payload'], self::HTTP_UNPROCESSABLE_ENTITY);

            return;
        }
        $issuer = $this->mapos_model->getEmitente();
        if (! $issuer || ! filter_var($issuer->email, FILTER_VALIDATE_EMAIL)) {
            $this->response(['status' => false, 'reason' => 'email_not_configured'], self::HTTP_CONFLICT);

            return;
        }
        $message_body = '<p>Use o código abaixo para confirmar seu e-mail no atendimento da TecNina:</p>'
            . '<p style="font-size:24px;font-weight:bold;letter-spacing:4px;text-align:center;">' . html_escape($code) . '</p>'
            . '<p>Se você não iniciou este atendimento, ignore esta mensagem.</p>';

        $message = $this->load->view('emails/layout', [
            'title' => 'Código de verificação',
            'preheader' => 'Use o código para confirmar seu e-mail no atendimento.',
            'content' => $message_body,
            'emitente' => $issuer,
        ], true);
        $queued = $this->email_model->add('email_queue', [
            'to' => $email,
            'message' => $message,
            'status' => 'pending',
            'date' => date('Y-m-d H:i:s'),
            'headers' => json_encode([
                'From' => '"' . $issuer->nome . '" <' . $issuer->email . '>',
                'Subject' => 'Código de confirmação TecNina',
                'Return-Path' => '',
            ]),
        ]);
        if (! $queued) {
            $this->response(['status' => false, 'reason' => 'email_queue_unavailable'], self::HTTP_INTERNAL_SERVER_ERROR);

            return;
        }
        $this->response(['status' => true, 'queued' => true], self::HTTP_OK);
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
