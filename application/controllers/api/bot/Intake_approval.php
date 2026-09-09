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
        $this->load->library('Device_credential');
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
            || ! $this->onlyKeys($input, ['operator_id', 'client_action', 'client_id', 'force_create_new', 'intake_created_at', 'client', 'os'])
            || ! is_array($input['client'] ?? null)
            || ! is_array($input['os'] ?? null)
            || ! $this->onlyKeys($input['client'], ['name', 'phone', 'city'])
            || ! $this->onlyKeys($input['os'], ['device_type', 'brand', 'model', 'problem_description', 'service_mode', 'city', 'notes', 'pickup_address', 'credential'])
            || (isset($input['os']['pickup_address']) && (
                ! is_array($input['os']['pickup_address'])
                || ! $this->onlyKeys($input['os']['pickup_address'], [
                    'postal_code', 'street', 'street_number', 'neighborhood', 'state',
                    'complement', 'reference', 'pickup_fee', 'pickup_fee_status', 'gps_available',
                ])
            ))) {
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
        $brand = $this->bounded($input['os']['brand'] ?? null, 80, true);
        $model = $this->bounded($input['os']['model'] ?? null, 120, true);
        $problem = $this->bounded($input['os']['problem_description'] ?? null, 2000, false, 3);
        $serviceMode = (string) ($input['os']['service_mode'] ?? '');
        $osCity = $this->bounded($input['os']['city'] ?? null, 80, false);
        $notes = $this->bounded($input['os']['notes'] ?? null, 2000, true);
        $pickupAddress = $this->pickupAddress($input['os']['pickup_address'] ?? null);
        $credential = $this->credential($input['os']['credential'] ?? null);
        // Keep the private contract deployable in either order. Older Gateway
        // versions do not send this field yet; once the new Gateway is live it
        // always supplies the actual intake date.
        $intakeDate = array_key_exists('intake_created_at', $input)
            ? $this->intakeDate($input['intake_created_at'])
            : date('Y-m-d');
        if ($phone === null || $clientCity === false || $deviceType === false || $brand === false
            || $problem === false || $osCity === false || $model === false || $notes === false
            || $intakeDate === null
            || $pickupAddress === false
            || $credential === false
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
            'intake_created_at' => $intakeDate,
            'client' => ['name' => $name, 'phone' => $phone, 'city' => $clientCity],
            'os' => [
                'device_type' => $deviceType,
                'brand' => $brand,
                'model' => $model,
                'problem_description' => $problem,
                'service_mode' => $serviceMode,
                'city' => $osCity,
                'notes' => $notes,
                'pickup_address' => $pickupAddress,
                'credential' => $credential,
            ],
        ];
    }

    private function credential($value)
    {
        if ($value === null) {
            return [
                'credencial_tipo' => 'nao_informada',
                'credencial_dados' => null,
                'credencial_grade' => null,
                'credencial_atualizada_em' => null,
            ];
        }
        if (! is_array($value) || ! $this->onlyKeys($value, [
            'status', 'type', 'grid', 'text', 'sequence',
        ])) {
            return false;
        }
        $status = (string) ($value['status'] ?? '');
        if ($status === 'DECLINED') {
            return [
                'credencial_tipo' => 'nao_informada',
                'credencial_dados' => null,
                'credencial_grade' => null,
                'credencial_atualizada_em' => null,
            ];
        }
        if ($status === 'NONE') {
            $prepared = $this->device_credential->prepareForStorage([
                'credencial_sem_senha' => '1',
            ], true, false);
        } elseif ($status === 'PROVIDED' && ($value['type'] ?? null) === 'TEXT') {
            $prepared = $this->device_credential->prepareForStorage([
                'credencial_tipo' => 'texto',
                'credencial_texto' => $value['text'] ?? '',
                'credencial_acao' => 'substituir',
            ], true, false);
        } elseif ($status === 'PROVIDED' && ($value['type'] ?? null) === 'PATTERN') {
            $prepared = $this->device_credential->prepareForStorage([
                'credencial_tipo' => 'padrao',
                'credencial_grade' => $value['grid'] ?? null,
                'credencial_padrao' => json_encode($value['sequence'] ?? []),
                'credencial_acao' => 'substituir',
            ], true, false);
        } else {
            return false;
        }

        return ! empty($prepared['valid']) ? $prepared['data'] : false;
    }

    private function pickupAddress($value)
    {
        if ($value === null) {
            // Deploy-order compatibility: the new Gateway always requires and
            // sends the pickup address before approval, but an older Gateway
            // may briefly coexist with this MapOS endpoint during rollout.
            return null;
        }
        if (! is_array($value)) {
            return false;
        }
        $postalCode = preg_replace('/\D+/', '', (string) ($value['postal_code'] ?? ''));
        $street = $this->bounded($value['street'] ?? null, 160, false);
        $number = $this->bounded($value['street_number'] ?? null, 32, false);
        $neighborhood = $this->bounded($value['neighborhood'] ?? null, 120, false);
        $state = strtoupper((string) ($value['state'] ?? 'PR'));
        $complement = $this->bounded($value['complement'] ?? null, 160, true);
        $reference = $this->bounded($value['reference'] ?? null, 255, true);
        $fee = $value['pickup_fee'] ?? null;
        $feeStatus = $this->bounded($value['pickup_fee_status'] ?? null, 24, true);
        if (strlen($postalCode) !== 8 || $street === false || $number === false
            || $neighborhood === false || preg_match('/^[A-Z]{2}$/', $state) !== 1
            || $complement === false || $reference === false
            || ($fee !== null && (! is_numeric($fee) || (float) $fee < 0))
            || ! in_array($feeStatus, ['DETERMINED', 'MANUAL_QUOTE', 'CONFIRMED'], true)
            || ! is_bool($value['gps_available'] ?? false)) {
            return false;
        }

        return [
            'postal_code' => $postalCode,
            'street' => $street,
            'street_number' => $number,
            'neighborhood' => $neighborhood,
            'state' => $state,
            'complement' => $complement,
            'reference' => $reference,
            'pickup_fee' => $fee === null ? null : number_format((float) $fee, 2, '.', ''),
            'pickup_fee_status' => $feeStatus,
            'gps_available' => (bool) ($value['gps_available'] ?? false),
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

    private function intakeDate($value)
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            ? $date->format('Y-m-d')
            : null;
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
