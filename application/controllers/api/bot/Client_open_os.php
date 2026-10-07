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
        if ($value === null) {
            return null;
        }

        $text = (string) $value;
        // Separate paragraph/block tags with hyphen separator
        $text = preg_replace('/<\s*\/\s*(?:p|div|li)\s*>\s*<\s*(?:p|div|li)[^>]*>/i', ' - ', $text);
        // Replace block closing and break tags with spaces
        $text = preg_replace('/<\s*\/\s*(?:p|div|li)\s*>/i', ' ', $text);
        $text = preg_replace('/<\s*br\s*\/?>/i', ' ', $text);
        // Strip any remaining HTML tags
        $text = strip_tags($text);
        // Decode HTML entities (e.g. &nbsp;, &amp;)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Normalize unicode whitespace (including \u{00A0} non-breaking spaces)
        $text = preg_replace('/[\s\x{00a0}]+/u', ' ', $text);
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, 250);
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
