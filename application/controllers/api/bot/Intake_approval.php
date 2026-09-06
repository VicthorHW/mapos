<?php

defined('BASEPATH') or exit('No direct script access allowed');

require APPPATH . '/libraries/REST_Controller.php';

class Intake_approval extends REST_Controller
{
    private const CLIENT_ACTIONS = ['LINK_EXISTING', 'CREATE_NEW'];
    private const SERVICE_MODES = ['DROP_OFF', 'PICKUP_REQUESTED'];

    public function __construct()
    {
        parent::__construct();
        $this->load->library('Tecnina_bot_auth');
        $this->load->library('Tecnina_phone');
        $this->load->model('Tecnina_intake_approval_model');
    }

    public function index_post($intakeId = null)
    {
        if (! $this->authorizeRequest()) {
            return;
        }
        if (! $this->validUuid($intakeId)) {
            $this->response(['status' => false, 'reason' => 'invalid_intake_id'], self::HTTP_BAD_REQUEST);

            return;
        }

        $input = $this->post();
        if (! is_array($input)
            || ! $this->onlyKeys($input, ['operator_id', 'client_action', 'client_id', 'force_create_new', 'client', 'os'])
            || ! is_array($input['client'] ?? null)
            || ! is_array($input['os'] ?? null)
            || ! $this->onlyKeys($input['client'], ['name', 'phone', 'city'])
            || ! $this->onlyKeys($input['os'], ['device_type', 'brand', 'model', 'problem_description', 'service_mode', 'city', 'notes'])) {
            $this->response(['status' => false, 'reason' => 'invalid_payload'], self::HTTP_BAD_REQUEST);

            return;
        }
        if (array_key_exists('force_create_new', $input) && ! is_bool($input['force_create_new'])) {
            $this->response(['status' => false, 'reason' => 'invalid_payload'], self::HTTP_BAD_REQUEST);

            return;
        }

        $payload = $this->validatedPayload($input);
        if ($payload === null) {
            return;
        }

        $requestHash = hash(
            'sha256',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        try {
            $result = $this->Tecnina_intake_approval_model->approve($intakeId, $requestHash, $payload);
            if (! $result['ok']) {
                $status = $result['reason'] === 'invalid_operator'
                    ? self::HTTP_UNPROCESSABLE_ENTITY
                    : self::HTTP_CONFLICT;
                $this->response(['status' => false, 'reason' => $result['reason']], $status);

                return;
            }
            $this->response([
                'status' => true,
                'result' => $result['result'],
                'intake_id' => $intakeId,
                'client_id' => (int) $result['client_id'],
                'os_id' => (int) $result['os_id'],
                'client_created' => (bool) $result['client_created'],
            ], self::HTTP_OK);
        } catch (Throwable $exception) {
            log_message('error', 'TecNina intake approval failed: ' . get_class($exception));
            $this->response(['status' => false, 'reason' => 'approval_unavailable'], self::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function validatedPayload(array $input)
    {
        $operatorId = filter_var($input['operator_id'] ?? null, FILTER_VALIDATE_INT);
        $clientAction = (string) ($input['client_action'] ?? '');
        $clientId = $input['client_id'] ?? null;
        if ($clientId !== null) {
            $clientId = filter_var($clientId, FILTER_VALIDATE_INT);
        }
        if ($operatorId === false || $operatorId < 1
            || ! in_array($clientAction, self::CLIENT_ACTIONS, true)
            || ($clientAction === 'LINK_EXISTING' && ($clientId === false || $clientId === null || $clientId < 1))
            || ($clientAction === 'CREATE_NEW' && $clientId !== null)) {
            $this->response(['status' => false, 'reason' => 'invalid_client_decision'], self::HTTP_UNPROCESSABLE_ENTITY);

            return null;
        }

        $phone = $this->tecnina_phone->normalizeBrazilianIdentity($input['client']['phone'] ?? '');
        $name = $this->bounded($input['client']['name'] ?? null, 120, true);
        $clientCity = $this->bounded($input['client']['city'] ?? null, 80, false);
        $deviceType = $this->bounded($input['os']['device_type'] ?? null, 80, false);
        $brand = $this->bounded($input['os']['brand'] ?? null, 80, false);
        $model = $this->bounded($input['os']['model'] ?? null, 120, true);
        $problem = $this->bounded($input['os']['problem_description'] ?? null, 2000, false, 3);
        $serviceMode = (string) ($input['os']['service_mode'] ?? '');
        $osCity = $this->bounded($input['os']['city'] ?? null, 80, false);
        $notes = $this->bounded($input['os']['notes'] ?? null, 2000, true);
        if ($phone === null || $clientCity === false || $deviceType === false || $brand === false
            || $problem === false || $osCity === false || $model === false || $notes === false
            || ! in_array($serviceMode, self::SERVICE_MODES, true)
            || ($clientAction === 'CREATE_NEW' && ($name === null || $name === false))) {
            $this->response(['status' => false, 'reason' => 'invalid_intake_fields'], self::HTTP_UNPROCESSABLE_ENTITY);

            return null;
        }

        return [
            'operator_id' => (int) $operatorId,
            'client_action' => $clientAction,
            'client_id' => $clientId === null ? null : (int) $clientId,
            'force_create_new' => filter_var($input['force_create_new'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'client' => ['name' => $name, 'phone' => $phone, 'city' => $clientCity],
            'os' => [
                'device_type' => $deviceType,
                'brand' => $brand,
                'model' => $model,
                'problem_description' => $problem,
                'service_mode' => $serviceMode,
                'city' => $osCity,
                'notes' => $notes,
            ],
        ];
    }

    private function bounded($value, $max, $nullable, $min = 1)
    {
        if ($value === null && $nullable) {
            return null;
        }
        if (! is_string($value)) {
            return false;
        }
        $value = trim($value);
        if ($value === '' && $nullable) {
            return null;
        }
        $length = mb_strlen($value);

        return $length >= $min && $length <= $max ? $value : false;
    }

    private function onlyKeys(array $input, array $allowed)
    {
        return array_diff(array_keys($input), $allowed) === [];
    }

    private function validUuid($value)
    {
        return is_string($value)
            && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
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
