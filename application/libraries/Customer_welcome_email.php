<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Customer_welcome_email
{
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Adiciona o e-mail de boas-vindas à fila sem interferir no cadastro
     * principal do cliente.
     *
     * @param int $customerId
     * @return bool
     */
    public function queue($customerId)
    {
        $customerId = (int) $customerId;
        if ($customerId <= 0) {
            return false;
        }

        $this->CI->load->model('mapos_model');
        $this->CI->load->model('clientes_model');
        $this->CI->load->model('email_model');

        $emitente = $this->CI->mapos_model->getEmitente();
        $cliente = $this->CI->clientes_model->getById($customerId);

        if (! $emitente || ! $cliente || ! filter_var($cliente->email, FILTER_VALIDATE_EMAIL) || ! filter_var($emitente->email, FILTER_VALIDATE_EMAIL)) {
            log_message('error', 'E-mail de boas-vindas não adicionado à fila. Cliente ID: ' . $customerId);

            return false;
        }

        $html = $this->CI->load->view('os/emails/clientenovo', [
            'emitente' => $emitente,
            'cliente' => $cliente,
        ], true);

        $headers = [
            'From' => "\"$emitente->nome\" <$emitente->email>",
            'Subject' => 'Bem-vindo à TecNina',
            'Return-Path' => '',
        ];

        return (bool) $this->CI->email_model->add('email_queue', [
            'to' => $cliente->email,
            'message' => $html,
            'status' => 'pending',
            'date' => date('Y-m-d H:i:s'),
            'headers' => json_encode($headers),
        ]);
    }
}
