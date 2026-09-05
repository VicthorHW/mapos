<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . '/libraries/REST_Controller.php';

class Outbox extends REST_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('Tecnina_bot_auth');
        $this->load->model('Tecnina_outbox_model');
    }

    public function claim_post()
    {
        if (! $this->authorizeRequest()) {
            return;
        }

        $batchSize = $this->post('batch_size');
        if ($batchSize === null) {
            $batchSize = 25;
        }
        if (filter_var($batchSize, FILTER_VALIDATE_INT) === false
            || (int) $batchSize < 1
            || (int) $batchSize > 100) {
            $this->response(['status' => false, 'reason' => 'invalid_batch_size'], self::HTTP_BAD_REQUEST);

            return;
        }

        try {
            $result = $this->Tecnina_outbox_model->claim(
                (int) $batchSize,
                (int) ($_ENV['MAPOS_BOT_CLAIM_TTL_SECONDS'] ?? 120)
            );
            $this->response([
                'status' => true,
                'claim_token' => $result['claim_token'],
                'events' => array_map([$this, 'eventResponse'], $result['events']),
            ], self::HTTP_OK);
        } catch (Throwable $exception) {
            log_message('error', 'TecNina outbox claim failed: ' . get_class($exception));
            $this->response(['status' => false, 'reason' => 'outbox_unavailable'], self::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function ack_post()
    {
        if (! $this->authorizeRequest()) {
            return;
        }

        $claimToken = $this->post('claim_token');
        $eventIds = $this->post('event_ids');
        if (! $this->validUuid($claimToken)
            || ! is_array($eventIds)
            || $eventIds === []
            || count($eventIds) > 100
            || count(array_filter($eventIds, [$this, 'validEventUuid'])) !== count($eventIds)) {
            $this->response(['status' => false, 'reason' => 'invalid_ack'], self::HTTP_BAD_REQUEST);

            return;
        }

        try {
            if (! $this->Tecnina_outbox_model->acknowledge($claimToken, $eventIds)) {
                $this->response(['status' => false, 'reason' => 'claim_mismatch'], self::HTTP_CONFLICT);

                return;
            }
            $this->response([
                'status' => true,
                'acknowledged' => count(array_unique($eventIds)),
            ], self::HTTP_OK);
        } catch (Throwable $exception) {
            log_message('error', 'TecNina outbox ack failed: ' . get_class($exception));
            $this->response(['status' => false, 'reason' => 'outbox_unavailable'], self::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function eventResponse($event)
    {
        return [
            'event_id' => $event['event_id'],
            'event_type' => $event['event_type'],
            'os_id' => (int) $event['os_id'],
            'client_id' => (int) $event['client_id'],
            'old_status' => $event['old_status'],
            'new_status' => $event['new_status'],
            'created_at' => $event['created_at'],
        ];
    }

    public function validUuid($value)
    {
        return is_string($value)
            && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }

    public function validEventUuid($value)
    {
        return is_string($value)
            && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
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
