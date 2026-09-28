<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . '/libraries/REST_Controller.php';

/**
 * Controller for Bot-facing Legal Authority endpoints:
 * - GET /api/bot/legal/current
 * - POST /api/bot/legal/manifestations
 */
class Legal extends REST_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('Tecnina_bot_auth');
        $this->load->library('Tecnina_identity_rate_limiter');
        $this->load->model('Tecnina_legal_model');
    }

    public function current_get()
    {
        if (! $this->authorize()) {
            return;
        }

        if (! $this->rate('legal_current', 'service', 120, 60)) {
            return;
        }

        $asOf = $this->get('as_of');
        if ($asOf !== null && ! is_string($asOf)) {
            return $this->response(['status' => false, 'reason' => 'invalid_payload'], self::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->Tecnina_legal_model->getCurrentVersions($asOf);
        if (! $result['ok']) {
            $status = $result['reason'] === 'legal_catalog_inconsistent'
                ? self::HTTP_CONFLICT
                : self::HTTP_UNPROCESSABLE_ENTITY;

            return $this->response(['status' => false, 'reason' => $result['reason']], $status);
        }

        return $this->response([
            'status' => true,
            'versions' => $result['versions'],
        ], self::HTTP_OK);
    }

    public function manifestations_post()
    {
        if (! $this->authorize()) {
            return;
        }

        if (! $this->rate('legal_manifestations', 'service', 120, 60)) {
            return;
        }

        $input = $this->post();
        if (! is_array($input)) {
            return $this->response(['status' => false, 'reason' => 'invalid_payload'], self::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! empty($input['capability_id']) && is_string($input['capability_id'])) {
            if (! $this->rate('legal_manifestations', 'cap:' . $input['capability_id'], 10, 900)) {
                return;
            }
        }

        $result = $this->Tecnina_legal_model->recordManifestations($input);
        if (! $result['ok']) {
            $status = self::HTTP_UNPROCESSABLE_ENTITY;
            if ($result['reason'] === 'legal_version_changed' || $result['reason'] === 'idempotency_conflict' || $result['reason'] === 'legal_catalog_inconsistent') {
                $status = self::HTTP_CONFLICT;
            }

            return $this->response(['status' => false, 'reason' => $result['reason']], $status);
        }

        $httpStatus = (! empty($result['replayed'])) ? self::HTTP_OK : self::HTTP_CREATED;

        return $this->response([
            'status' => true,
            'replayed' => ! empty($result['replayed']),
            'event_ids' => $result['event_ids'],
            'events' => $result['events'],
        ], $httpStatus);
    }

    private function rate($scope, $subject, $limit, $seconds)
    {
        $allowed = $this->tecnina_identity_rate_limiter->allow($scope, $subject, $limit, $seconds);
        if ($allowed === true) {
            return true;
        }

        $this->response(
            ['status' => false, 'reason' => $allowed === null ? 'unavailable' : 'rate_limited'],
            $allowed === null ? self::HTTP_SERVICE_UNAVAILABLE : self::HTTP_TOO_MANY_REQUESTS
        );

        return false;
    }

    private function authorize()
    {
        $auth = $this->tecnina_bot_auth->authorize($this->input->get_request_header('Authorization', true));
        if (! $auth['ok']) {
            $this->response(['status' => false, 'reason' => $auth['reason']], $auth['status']);
            return false;
        }

        return true;
    }
}
