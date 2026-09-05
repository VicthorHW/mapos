<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . '/libraries/REST_Controller.php';

class Health extends REST_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('Tecnina_bot_auth');
        $this->load->database();
    }

    public function index_get()
    {
        $authorization = $this->input->get_request_header('Authorization', true);
        $auth = $this->tecnina_bot_auth->authorize($authorization);
        if (! $auth['ok']) {
            $this->response(
                ['status' => false, 'reason' => $auth['reason']],
                $auth['status']
            );

            return;
        }

        if (! $this->integrationSchemaReady()) {
            $this->response([
                'status' => false,
                'reason' => 'integration_schema_unavailable',
            ], self::HTTP_SERVICE_UNAVAILABLE);

            return;
        }

        $this->response([
            'status' => true,
            'service' => 'mapos-bot-integration',
            'contract_version' => 1,
        ], self::HTTP_OK);
    }

    private function integrationSchemaReady()
    {
        if (! $this->db->table_exists('tecnina_integration_outbox')) {
            return false;
        }

        $osTable = $this->db->dbprefix('os');
        $candidate = 'trg_' . $osTable . '_tecnina_status_outbox';
        $triggerName = strlen($candidate) <= 64
            ? $candidate
            : 'trg_tecnina_status_' . substr(hash('sha256', $osTable), 0, 20);
        $trigger = $this->db->query(
            'SELECT COUNT(*) AS `count` FROM information_schema.TRIGGERS '
            . 'WHERE `TRIGGER_SCHEMA` = DATABASE() AND `TRIGGER_NAME` = ?',
            [$triggerName]
        )->row_array();

        return isset($trigger['count']) && (int) $trigger['count'] === 1;
    }
}
