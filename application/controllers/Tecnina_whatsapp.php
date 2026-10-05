<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tecnina_whatsapp extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('tecnina_bot_gateway');
        $this->load->library('tecnina_attachment_storage');
        $this->load->model('Tecnina_receiving_model');
    }

    public function index()
    {
        if (! $this->authorized()) {
            return;
        }

        $this->data['menuConfiguracoes'] = 'WhatsApp';
        $this->preparePanel('tecnina_whatsapp/index');

        return $this->layout();
    }

    public function pre_atendimentos()
    {
        if (! $this->authorized()) {
            return;
        }

        $this->data['menuPreAtendimentos'] = 'Pré-atendimentos';
        $this->preparePanel('tecnina_whatsapp/pre_atendimentos');

        return $this->layout();
    }

    public function bot_lab()
    {
        if (! $this->authorized()) {
            return;
        }

        $this->data['menuBotLab'] = 'Bot Lab';
        $this->preparePanel('tecnina_whatsapp/bot_lab');

        return $this->layout();
    }

    public function dados($resource = '')
    {
        if (! $this->authorized(true)) {
            return;
        }

        $paths = [
            'overview' => '/admin/overview',
            'conversations' => '/admin/conversations',
            'intakes' => '/admin/intakes',
            'intake-history' => '/admin/intakes-history',
            'logistics-overview' => '/admin/logistics/overview',
            'logistics-zones' => '/admin/logistics/zones',
            'logistics-routes' => '/admin/logistics/routes',
            'logistics-capacity-rules' => '/admin/logistics/capacity-rules',
            'logistics-equipment-profiles' => '/admin/logistics/equipment-profiles',
            'logistics-appointments' => '/admin/logistics/appointments',
            'queue' => '/admin/queue',
            'logs' => '/admin/logs',
            'status-rules' => '/admin/status-rules',
            'templates' => '/admin/templates',
            'settings' => '/admin/settings/status-notifications',
            'pickup-cities' => '/admin/pickup-cities',
            'dropoff-schedule' => '/admin/dropoff-schedule',
        ];
        if (! isset($paths[$resource])) {
            return $this->json(['ok' => false, 'reason' => 'not_found'], 404);
        }

        $result = $this->tecnina_bot_gateway->request('GET', $paths[$resource]);

        if (
            $result['ok']
            && in_array($resource, ['intakes', 'intake-history'], true)
            && is_array($result['data'])
        ) {
            $result['data'] = $this->withMaposClientNames($result['data']);
        }
        return $this->json($result, $result['status'] ?? 200);
    }

    public function pre_atendimento($intakeId = '', $action = '')
    {
        if (! $this->authorized(true)) {
            return;
        }
        if (! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', (string) $intakeId)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_intake_id'], 400);
        }

        $method = $this->input->method(true);
        if ($method === 'GET' && $action === '') {
            $result = $this->tecnina_bot_gateway->request('GET', '/admin/intakes/' . rawurlencode($intakeId));

            if ($result['ok'] && is_array($result['data'])) {
                $rows = $this->withMaposClientNames([$result['data']]);
                $result['data'] = $rows[0];
                $result['data']['receiving'] = $this->Tecnina_receiving_model->getReceiving($intakeId) ?? [
                    'state' => 'PENDING_DELIVERY',
                    'device_condition' => null,
                    'accessories' => null,
                    'serial_number' => null,
                    'imei' => null,
                    'other_identifiers' => null,
                    'notes' => null,
                    'received_at' => null,
                    'received_by' => null,
                    'received_by_name' => null,
                ];
                $result['data']['attachments'] = $this->Tecnina_receiving_model->getAttachments($intakeId);
                $result['data']['location_data'] = $this->Tecnina_receiving_model->getLocation($intakeId);
            }
            return $this->json($result, $result['status'] ?? 200);
        }
        if ($method !== 'POST' || ! in_array($action, ['save', 'reject', 'approve', 'pickup-fee'], true)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_request'], 400);
        }

        if (! $this->authorizedMutation(true)) {
            return;
        }

        $operatorId = (int) $this->session->userdata('id_admin');
        if ($operatorId <= 0) {
            return $this->json(['ok' => false, 'reason' => 'invalid_operator'], 403);
        }
        $version = filter_var($this->input->post('review_version'), FILTER_VALIDATE_INT);
        if ($version === false || $version < 0) {
            return $this->json(['ok' => false, 'reason' => 'invalid_review_version'], 422);
        }

        if ($action === 'save') {
            $payload = [
                'review_version' => $version,
                'name' => trim((string) $this->input->post('name', true)) ?: null,
                'city' => trim((string) $this->input->post('city', true)) ?: null,
                'device_type' => trim((string) $this->input->post('device_type', true)) ?: null,
                'brand' => trim((string) $this->input->post('brand', true)) ?: null,
                'model' => trim((string) $this->input->post('model', true)) ?: null,
                'problem_description' => trim((string) $this->input->post('problem_description', true)) ?: null,
                'notes' => trim((string) $this->input->post('notes', true)) ?: null,
            ];
            $sm = trim((string) $this->input->post('service_mode', true));
            if ($sm === 'DROPOFF') {
                $sm = 'DROP_OFF';
            }
            if (in_array($sm, ['DROP_OFF', 'PICKUP_REQUESTED'], true)) {
                $payload['service_mode'] = $sm;
            }
            $payload = array_filter($payload, function ($v) { return $v !== null; });
            $result = $this->tecnina_bot_gateway->request('PUT', '/admin/intakes/' . rawurlencode($intakeId), $payload);
            return $this->json($result, $result['status'] ?? 200);
        }

        if ($action === 'reject') {
            $reason = trim((string) $this->input->post('reason', true));
            if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
                return $this->json(['ok' => false, 'reason' => 'invalid_rejection_reason'], 422);
            }
            $payload = ['review_version' => $version, 'operator_id' => $operatorId, 'reason' => $reason];
            $result = $this->tecnina_bot_gateway->request('POST', '/admin/intakes/' . rawurlencode($intakeId) . '/reject', $payload);
            return $this->json($result, $result['status']);
        }

        if ($action === 'approve') {
            $this->load->library('Tecnina_readiness_service');
            $readiness = $this->tecnina_readiness_service->evaluateReadiness($intakeId);
            if (! $readiness['ready']) {
                return $this->json([
                    'ok' => false,
                    'reason' => 'readiness_gate_blocked',
                    'blocking_reasons' => $readiness['blocking_reasons'],
                    'readiness' => $readiness,
                ], 409);
            }

            $clientAction = (string) $this->input->post('client_action', true);
            $clientId = $this->input->post('client_id', true);
            if (! in_array($clientAction, ['LINK_EXISTING', 'CREATE_NEW'], true)) {
                return $this->json(['ok' => false, 'reason' => 'invalid_client_decision'], 422);
            }
            if ($clientAction === 'LINK_EXISTING') {
                $clientId = filter_var($clientId, FILTER_VALIDATE_INT);
                if ($clientId === false || $clientId < 1) {
                    return $this->json(['ok' => false, 'reason' => 'invalid_client_id'], 422);
                }
            } else {
                $clientId = null;
            }
            $payload = [
                'review_version' => $version,
                'operator_id' => $operatorId,
                'client_action' => $clientAction,
                'client_id' => $clientId,
                'force_create_new' => filter_var(
                    $this->input->post('force_create_new'),
                    FILTER_VALIDATE_BOOLEAN
                ),
            ];
            $result = $this->tecnina_bot_gateway->request(
                'POST',
                '/admin/intakes/' . rawurlencode($intakeId) . '/approve',
                $payload
            );

            return $this->json($result, $result['status']);
        }

        if ($action === 'pickup-fee') {
            $fee = str_replace(',', '.', trim((string) $this->input->post('fee', true)));
            if (! is_numeric($fee) || (float) $fee < 0 || (float) $fee > 100000) {
                return $this->json(['ok' => false, 'reason' => 'invalid_pickup_fee'], 422);
            }
            $payload = [
                'review_version' => $version,
                'operator_id' => $operatorId,
                'fee' => number_format((float) $fee, 2, '.', ''),
            ];
            $result = $this->tecnina_bot_gateway->request(
                'POST',
                '/admin/intakes/' . rawurlencode($intakeId) . '/pickup-fee',
                $payload
            );

            return $this->json($result, $result['status']);
        }

        $serviceMode = (string) $this->input->post('service_mode', true);
        $required = [
            'device_type' => trim((string) $this->input->post('device_type', true)),
            'problem_description' => trim((string) $this->input->post('problem_description', true)),
        ];
        $city = trim((string) $this->input->post('city', true));
        if ($serviceMode === 'PICKUP_REQUESTED') {
            $required['city'] = $city;
        }
        if (in_array('', $required, true) || ! in_array($serviceMode, ['DROP_OFF', 'PICKUP_REQUESTED'], true)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_intake_fields'], 422);
        }
        $payload = array_merge($required, [
            'review_version' => $version,
            'name' => trim((string) $this->input->post('name', true)),
            'brand' => trim((string) $this->input->post('brand', true)),
            'model' => trim((string) $this->input->post('model', true)),
            'service_mode' => $serviceMode,
            'city' => $city === '' ? null : $city,
            'notes' => trim((string) $this->input->post('notes', true)),
        ]);
        $result = $this->tecnina_bot_gateway->request('PUT', '/admin/intakes/' . rawurlencode($intakeId), $payload);
        return $this->json($result, $result['status']);
    }

    public function notificacoes()
    {
        if (! $this->authorized(true)) {
            return;
        }
        if ($this->input->method(true) !== 'POST') {
            return $this->json(['ok' => false, 'reason' => 'method_not_allowed'], 405);
        }
        $enabled = filter_var($this->input->post('enabled'), FILTER_VALIDATE_BOOLEAN);
        $result = $this->tecnina_bot_gateway->request('PUT', '/admin/settings/status-notifications', ['enabled' => $enabled]);
        return $this->json($result, $result['status']);
    }

    public function entrega_configuracao()
    {
        if (! $this->authorized(true)) {
            return;
        }
        if ($this->input->method(true) !== 'POST') {
            return $this->json(['ok' => false, 'reason' => 'method_not_allowed'], 405);
        }

        $payload = json_decode((string) $this->input->post('schedule_json', false), true);
        if (! is_array($payload)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_dropoff_schedule'], 422);
        }

        $result = $this->tecnina_bot_gateway->request('PUT', '/admin/dropoff-schedule', $payload);

        return $this->json($result, $result['status']);
    }

    public function os_acesso($osId = 0, $action = '')
    {
        if (! $this->authorized(true)) {
            return;
        }

        unset($osId, $action);

        return $this->json(['ok' => false, 'reason' => 'os_access_code_retired'], 410);
    }

    public function conversa($conversationId = 0, $action = '')
    {
        if (! $this->authorized(true)) {
            return;
        }
        if (! ctype_digit((string) $conversationId)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_request'], 400);
        }
        if ($this->input->method(true) !== 'POST' || ! in_array($action, ['manual-lock', 'resume'], true)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_request'], 400);
        }
        $result = $this->tecnina_bot_gateway->request('POST', '/admin/conversations/' . $conversationId . '/' . $action, []);
        return $this->json($result, $result['status']);
    }

    public function fila($jobId = 0, $action = '')
    {
        if (! $this->authorized(true)) {
            return;
        }
        if ($this->input->method(true) !== 'POST' || ! ctype_digit((string) $jobId) || $action !== 'retry') {
            return $this->json(['ok' => false, 'reason' => 'invalid_request'], 400);
        }
        $result = $this->tecnina_bot_gateway->request('POST', '/admin/queue/' . $jobId . '/retry', []);
        return $this->json($result, $result['status']);
    }

    public function regra($ruleId = 0)
    {
        if (! $this->authorized(true)) {
            return;
        }
        if ($this->input->method(true) !== 'POST' || ! ctype_digit((string) $ruleId)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_request'], 400);
        }
        $payload = [
            'enabled' => filter_var($this->input->post('enabled'), FILTER_VALIDATE_BOOLEAN),
            'public_label' => trim((string) $this->input->post('public_label')),
            'priority' => (int) $this->input->post('priority'),
        ];
        $result = $this->tecnina_bot_gateway->request('PUT', '/admin/status-rules/' . $ruleId, $payload);
        return $this->json($result, $result['status']);
    }

    public function template($templateKey = '')
    {
        if (! $this->authorized(true)) {
            return;
        }
        if ($this->input->method(true) !== 'POST' || ! preg_match('/^[a-zA-Z0-9_]{1,64}$/', $templateKey)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_request'], 400);
        }
        $payload = [
            'body' => (string) $this->input->post('body'),
            'enabled' => filter_var($this->input->post('enabled'), FILTER_VALIDATE_BOOLEAN),
        ];
        $result = $this->tecnina_bot_gateway->request('POST', '/admin/templates/' . rawurlencode($templateKey) . '/versions', $payload);
        return $this->json($result, $result['status']);
    }

    public function logistica_configuracao($resource = '', $resourceId = 0)
    {
        if (! $this->authorized(true)) {
            return;
        }
        if ($this->input->method(true) !== 'POST') {
            return $this->json(['ok' => false, 'reason' => 'method_not_allowed'], 405);
        }

        $paths = [
            'zones' => '/admin/logistics/zones',
            'routes' => '/admin/logistics/routes',
            'capacity-rules' => '/admin/logistics/capacity-rules',
            'equipment-profiles' => '/admin/logistics/equipment-profiles',
        ];
        if (! isset($paths[$resource])) {
            return $this->json(['ok' => false, 'reason' => 'invalid_logistics_resource'], 400);
        }

        $rawPayload = (string) $this->input->post('payload', false);
        if ($rawPayload === '' || strlen($rawPayload) > 20000) {
            return $this->json(['ok' => false, 'reason' => 'invalid_logistics_payload'], 422);
        }
        $payload = json_decode($rawPayload, true);
        if (! is_array($payload)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_logistics_payload'], 422);
        }

        $method = 'POST';
        $path = $paths[$resource];
        if ((string) $resourceId !== '0') {
            if (! ctype_digit((string) $resourceId) || (int) $resourceId < 1) {
                return $this->json(['ok' => false, 'reason' => 'invalid_logistics_resource_id'], 400);
            }
            $method = 'PUT';
            $path .= '/' . (int) $resourceId;
        }

        $result = $this->tecnina_bot_gateway->request($method, $path, $payload);
        return $this->json($result, $result['status']);
    }

    public function logistica_appointment($appointmentId = '', $action = '')
    {
        if (! $this->authorized(true)) {
            return;
        }
        if (
            $this->input->method(true) !== 'POST'
            || ! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', (string) $appointmentId)
            || ! in_array($action, ['confirm', 'cancel', 'complete', 'reschedule-required'], true)
        ) {
            return $this->json(['ok' => false, 'reason' => 'invalid_logistics_action'], 400);
        }
        $operatorId = (int) $this->session->userdata('id_admin');
        if ($operatorId < 1) {
            return $this->json(['ok' => false, 'reason' => 'invalid_logistics_action'], 422);
        }
        $version = filter_var($this->input->post('state_version'), FILTER_VALIDATE_INT);
        if ($version === false || $version < 0) {
            return $this->json(['ok' => false, 'reason' => 'invalid_logistics_action'], 422);
        }
        $payload = ['operator_id' => $operatorId, 'expected_version' => $version];
        $result = $this->tecnina_bot_gateway->request(
            'POST',
            '/admin/logistics/appointments/' . rawurlencode($appointmentId) . '/' . $action,
            $payload
        );
        return $this->json($result, $result['status']);
    }

    public function coleta($action = '', $cityId = 0, $rateId = 0)
    {
        if (! $this->authorized(true)) {
            return;
        }
        if ($this->input->method(true) !== 'POST') {
            return $this->json(['ok' => false, 'reason' => 'method_not_allowed'], 405);
        }

        if ($action === 'save-city') {
            $city = trim((string) $this->input->post('city', true));
            $mode = (string) $this->input->post('pricing_mode', true);
            $fee = $this->input->post('flat_fee', true);
            if (mb_strlen($city) < 2 || ! in_array($mode, ['FIXED_FEE', 'NEIGHBORHOOD', 'MANUAL_QUOTE'], true)) {
                return $this->json(['ok' => false, 'reason' => 'invalid_pickup_city'], 422);
            }
            $payload = [
                'city' => $city,
                'uf' => strtoupper(substr(trim((string) $this->input->post('uf', true)), 0, 2)),
                'pricing_mode' => $mode,
                'flat_fee' => $fee === '' ? null : (float) $fee,
                'active' => filter_var($this->input->post('active'), FILTER_VALIDATE_BOOLEAN),
            ];
            $result = $this->tecnina_bot_gateway->request('POST', '/admin/pickup-cities', $payload);

            return $this->json($result, $result['status']);
        }

        if (! ctype_digit((string) $cityId) || (int) $cityId < 1) {
            return $this->json(['ok' => false, 'reason' => 'invalid_pickup_city'], 422);
        }
        if ($action === 'save-neighborhood') {
            $neighborhood = trim((string) $this->input->post('neighborhood', true));
            $fee = $this->input->post('fee', true);
            if (mb_strlen($neighborhood) < 2 || ! is_numeric($fee) || (float) $fee < 0) {
                return $this->json(['ok' => false, 'reason' => 'invalid_pickup_neighborhood'], 422);
            }
            $result = $this->tecnina_bot_gateway->request(
                'POST',
                '/admin/pickup-cities/' . (int) $cityId . '/neighborhoods',
                [
                    'neighborhood' => $neighborhood,
                    'fee' => (float) $fee,
                    'active' => filter_var($this->input->post('active'), FILTER_VALIDATE_BOOLEAN),
                ]
            );

            return $this->json($result, $result['status']);
        }
        if ($action === 'delete-neighborhood' && ctype_digit((string) $rateId) && (int) $rateId > 0) {
            $result = $this->tecnina_bot_gateway->request(
                'DELETE',
                '/admin/pickup-cities/' . (int) $cityId . '/neighborhoods/' . (int) $rateId
            );

            return $this->json($result, $result['status']);
        }

        return $this->json(['ok' => false, 'reason' => 'invalid_request'], 400);
    }

    public function simulador_criar()
    {
        if (! $this->authorized(true)) {
            return;
        }
        if ($this->input->method(true) !== 'POST') {
            return $this->json(['ok' => false, 'reason' => 'method_not_allowed'], 405);
        }

        $rawPayload = (string) $this->input->post('payload', false);
        if ($rawPayload === '' || strlen($rawPayload) > 65535) {
            return $this->json(['ok' => false, 'reason' => 'invalid_simulator_payload'], 422);
        }

        $payload = json_decode($rawPayload, true);
        if (! is_array($payload)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_simulator_payload'], 422);
        }

        $result = $this->tecnina_bot_gateway->request('POST', '/admin/simulator/sessions', $payload);
        return $this->json($result, $result['status']);
    }

    public function simulador_sessao($simulationId = '')
    {
        if (! $this->authorized(true)) {
            return;
        }
        if ($this->input->method(true) !== 'GET') {
            return $this->json(['ok' => false, 'reason' => 'method_not_allowed'], 405);
        }
        if (! $this->validateSimulationId($simulationId)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_simulation_id'], 400);
        }

        $result = $this->tecnina_bot_gateway->request('GET', '/admin/simulator/sessions/' . rawurlencode($simulationId));
        return $this->json($result, $result['status']);
    }

    public function simulador_mensagem($simulationId = '')
    {
        if (! $this->authorized(true)) {
            return;
        }
        if ($this->input->method(true) !== 'POST') {
            return $this->json(['ok' => false, 'reason' => 'method_not_allowed'], 405);
        }
        if (! $this->validateSimulationId($simulationId)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_simulation_id'], 400);
        }

        $text = $this->input->post('text', false);
        if ($text === null || trim((string) $text) === '') {
            return $this->json(['ok' => false, 'reason' => 'invalid_simulator_message'], 422);
        }

        $payload = ['text' => (string) $text];
        $result = $this->tecnina_bot_gateway->request(
            'POST',
            '/admin/simulator/sessions/' . rawurlencode($simulationId) . '/messages',
            $payload
        );
        return $this->json($result, $result['status']);
    }

    public function simulador_localizacao($simulationId = '')
    {
        if (! $this->authorized(true)) {
            return;
        }
        if ($this->input->method(true) !== 'POST') {
            return $this->json(['ok' => false, 'reason' => 'method_not_allowed'], 405);
        }
        if (! $this->validateSimulationId($simulationId)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_simulation_id'], 400);
        }

        $latRaw = $this->input->post('latitude', true);
        $lonRaw = $this->input->post('longitude', true);
        $accRaw = $this->input->post('accuracy_meters', true);

        if (! is_numeric($latRaw) || ! is_numeric($lonRaw)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_simulator_location'], 422);
        }

        $lat = (float) $latRaw;
        $lon = (float) $lonRaw;
        if ($lat < -90.0 || $lat > 90.0 || $lon < -180.0 || $lon > 180.0) {
            return $this->json(['ok' => false, 'reason' => 'invalid_simulator_location'], 422);
        }

        $accuracy = null;
        if ($accRaw !== null && trim((string) $accRaw) !== '') {
            if (! is_numeric($accRaw) || (float) $accRaw < 0.0) {
                return $this->json(['ok' => false, 'reason' => 'invalid_simulator_location'], 422);
            }
            $accuracy = (float) $accRaw;
        }

        $payload = [
            'latitude' => $lat,
            'longitude' => $lon,
            'accuracy_meters' => $accuracy,
        ];

        $result = $this->tecnina_bot_gateway->request(
            'POST',
            '/admin/simulator/sessions/' . rawurlencode($simulationId) . '/locations',
            $payload
        );
        return $this->json($result, $result['status']);
    }

    public function simulador_reset($simulationId = '')
    {
        if (! $this->authorized(true)) {
            return;
        }
        if ($this->input->method(true) !== 'POST') {
            return $this->json(['ok' => false, 'reason' => 'method_not_allowed'], 405);
        }
        if (! $this->validateSimulationId($simulationId)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_simulation_id'], 400);
        }

        $result = $this->tecnina_bot_gateway->request(
            'POST',
            '/admin/simulator/sessions/' . rawurlencode($simulationId) . '/reset'
        );
        return $this->json($result, $result['status']);
    }

    public function simulador_excluir($simulationId = '')
    {
        if (! $this->authorized(true)) {
            return;
        }
        if ($this->input->method(true) !== 'POST') {
            return $this->json(['ok' => false, 'reason' => 'method_not_allowed'], 405);
        }
        if (! $this->validateSimulationId($simulationId)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_simulation_id'], 400);
        }

        $result = $this->tecnina_bot_gateway->request(
            'DELETE',
            '/admin/simulator/sessions/' . rawurlencode($simulationId)
        );

        if ($result['ok'] && $result['status'] === 204) {
            return $this->json(['ok' => true, 'status' => 200, 'reason' => 'ok', 'data' => null], 200);
        }

        return $this->json($result, $result['status']);
    }

    public function simulador_cenarios()
    {
        if (! $this->authorized(true)) {
            return;
        }
        if ($this->input->method(true) !== 'GET') {
            return $this->json(['ok' => false, 'reason' => 'method_not_allowed'], 405);
        }

        $result = $this->tecnina_bot_gateway->request('GET', '/admin/simulator/scenarios');
        return $this->json($result, $result['status']);
    }

    public function simulador_executar_cenarios()
    {
        if (! $this->authorized(true)) {
            return;
        }
        if ($this->input->method(true) !== 'POST') {
            return $this->json(['ok' => false, 'reason' => 'method_not_allowed'], 405);
        }

        $rawPayload = (string) $this->input->post('payload', false);
        $payload = (object) [];
        if ($rawPayload !== '') {
            $decoded = json_decode($rawPayload);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_object($decoded)) {
                return $this->json(['ok' => false, 'reason' => 'invalid_scenario_payload'], 422);
            }
            $payload = $decoded;
        }

        unset($payload->timeout, $payload->timeout_seconds, $payload->scenario_timeout);

        $result = $this->tecnina_bot_gateway->request(
            'POST',
            '/admin/simulator/scenarios/run',
            $payload,
            $this->tecnina_bot_gateway->scenarioTimeoutSeconds()
        );
        return $this->json($result, $result['status']);
    }

    public function receiving($intakeId = '')
    {
        $method = $this->input->method(true);
        if ($method === 'GET') {
            if (! $this->authorizedRead(true)) {
                return;
            }
            if (! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', (string) $intakeId)) {
                return $this->json(['ok' => false, 'reason' => 'invalid_intake_id'], 400);
            }

            $receiving = $this->Tecnina_receiving_model->getReceiving($intakeId) ?? [
                'state' => 'PENDING_DELIVERY',
                'device_condition' => null,
                'accessories' => null,
                'serial_number' => null,
                'imei' => null,
                'other_identifiers' => null,
                'notes' => null,
                'received_at' => null,
                'received_by' => null,
                'received_by_name' => null,
            ];
            $attachments = $this->Tecnina_receiving_model->getAttachments($intakeId);
            $location = $this->Tecnina_receiving_model->getLocation($intakeId);
            return $this->json([
                'ok' => true,
                'intake_id' => $intakeId,
                'receiving' => $receiving,
                'attachments' => $attachments,
                'location' => $location,
            ], 200);
        }

        if ($method !== 'POST') {
            return $this->json(['ok' => false, 'reason' => 'invalid_method'], 405);
        }

        if (! $this->authorizedMutation(true)) {
            return;
        }

        if (! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', (string) $intakeId)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_intake_id'], 400);
        }

        $operatorId = (int) $this->session->userdata('id_admin');
        if ($operatorId <= 0) {
            return $this->json(['ok' => false, 'reason' => 'invalid_operator'], 403);
        }

        $action = (string) $this->input->post('action', true);
        $payload = [
            'device_condition' => $this->input->post('device_condition', true),
            'accessories' => $this->input->post('accessories', true),
            'serial_number' => $this->input->post('serial_number', true),
            'imei' => $this->input->post('imei', true),
            'other_identifiers' => $this->input->post('other_identifiers', true),
            'notes' => $this->input->post('notes', true),
        ];

        if ($action === 'prepare') {
            $record = $this->Tecnina_receiving_model->savePreparation($intakeId, $payload, $operatorId);
            if (! $record['ok']) {
                $status = ($record['reason'] === 'receiving_already_confirmed') ? 409 : 422;
                return $this->json($record, $status);
            }
            return $this->json($record, 200);
        }

        $idempotencyKey = $this->input->get_request_header('Idempotency-Key', true);
        if (empty($idempotencyKey)) {
            $idempotencyKey = $this->input->post('idempotency_key', true);
        }

        if (empty($idempotencyKey) || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string) $idempotencyKey)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_idempotency_key'], 422);
        }

        // Explicit physical receipt confirmation
        $result = $this->Tecnina_receiving_model->confirmPhysicalReceipt($intakeId, $operatorId, $payload, $idempotencyKey);
        if (! $result['ok']) {
            $status = in_array($result['reason'], ['idempotency_conflict', 'receiving_already_confirmed'], true) ? 409 : 422;
            return $this->json($result, $status);
        }

        return $this->json($result, 200);
    }

    public function readiness($intakeId = '')
    {
        if (! $this->authorizedRead(true)) {
            return;
        }

        if (! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', (string) $intakeId)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_intake_id'], 400);
        }

        $this->load->library('Tecnina_readiness_service');
        $report = $this->tecnina_readiness_service->evaluateReadiness($intakeId);

        return $this->json([
            'ok' => true,
            'intake_id' => $intakeId,
            'readiness' => $report,
        ], 200);
    }

    public function attachments($intakeId = '')
    {
        $method = $this->input->method(true);
        if ($method === 'GET') {
            if (! $this->authorizedRead(true)) {
                return;
            }
            if (! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', (string) $intakeId)) {
                return $this->json(['ok' => false, 'reason' => 'invalid_intake_id'], 400);
            }
            $list = $this->Tecnina_receiving_model->getAttachments($intakeId);
            return $this->json(['ok' => true, 'data' => $list], 200);
        }

        if ($method !== 'POST') {
            return $this->json(['ok' => false, 'reason' => 'invalid_method'], 405);
        }

        if (! $this->authorizedMutation(true)) {
            return;
        }

        if (! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', (string) $intakeId)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_intake_id'], 400);
        }

        if (! isset($_FILES['file']) || ! is_uploaded_file($_FILES['file']['tmp_name'])) {
            return $this->json(['ok' => false, 'reason' => 'missing_file'], 422);
        }

        $currentTotal = $this->Tecnina_receiving_model->getTotalAttachmentBytes($intakeId);
        $validation = $this->tecnina_attachment_storage->validateUpload($_FILES['file'], $currentTotal);
        if (! $validation['ok']) {
            return $this->json($validation, 422);
        }

        $stored = $this->tecnina_attachment_storage->storeUpload($_FILES['file'], $validation);
        if (! $stored['ok']) {
            return $this->json($stored, 500);
        }

        $caption = $this->input->post('caption', true);
        $saved = $this->Tecnina_receiving_model->saveAttachment(
            $intakeId,
            $stored['original_name'],
            $stored['storage_key'],
            $stored['detected_mime'],
            $stored['size_bytes'],
            $stored['sha256'],
            $caption
        );

        return $this->json(['ok' => true, 'result' => 'uploaded', 'data' => $saved], 201);
    }

    public function attachment_download($intakeId = '', $attachmentId = 0)
    {
        if (! $this->authorizedRead(false)) {
            return;
        }
        $attachmentId = (int) $attachmentId;
        if (! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', (string) $intakeId) || $attachmentId <= 0) {
            show_404();
            return;
        }

        $row = $this->Tecnina_receiving_model->getAttachment($intakeId, $attachmentId);
        if (! $row) {
            show_404();
            return;
        }

        $path = $this->tecnina_attachment_storage->resolveFilePath($row['storage_key'], false);
        if (! $path || ! file_exists($path)) {
            show_404();
            return;
        }

        $mime = $row['detected_mime'] ?: 'application/octet-stream';
        $filename = rawurlencode($row['original_name']);

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . addslashes($row['original_name']) . '"; filename*=UTF-8\'\'' . $filename);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        readfile($path);
        exit;
    }

    public function attachment_thumbnail($intakeId = '', $attachmentId = 0)
    {
        if (! $this->authorizedRead(false)) {
            return;
        }
        $attachmentId = (int) $attachmentId;
        if (! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', (string) $intakeId) || $attachmentId <= 0) {
            show_404();
            return;
        }

        $row = $this->Tecnina_receiving_model->getAttachment($intakeId, $attachmentId);
        if (! $row || ! in_array($row['detected_mime'], ['image/jpeg', 'image/png'], true)) {
            show_404();
            return;
        }

        $path = $this->tecnina_attachment_storage->resolveFilePath($row['storage_key'], true);
        if (! $path || ! file_exists($path)) {
            $path = $this->tecnina_attachment_storage->resolveFilePath($row['storage_key'], false);
        }

        if (! $path || ! file_exists($path)) {
            show_404();
            return;
        }

        header('Content-Type: ' . $row['detected_mime']);
        header('Content-Disposition: inline');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    public function attachment_delete($intakeId = '', $attachmentId = 0)
    {
        if (! $this->authorizedMutation(true)) {
            return;
        }
        $attachmentId = (int) $attachmentId;
        if (! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', (string) $intakeId) || $attachmentId <= 0) {
            return $this->json(['ok' => false, 'reason' => 'invalid_request'], 400);
        }

        $row = $this->Tecnina_receiving_model->getAttachment($intakeId, $attachmentId);
        if (! $row) {
            return $this->json(['ok' => false, 'reason' => 'attachment_not_found'], 404);
        }

        $this->tecnina_attachment_storage->deleteFile($row['storage_key']);
        $this->Tecnina_receiving_model->deleteAttachment($intakeId, $attachmentId);

        return $this->json(['ok' => true, 'result' => 'deleted'], 200);
    }

    public function location($intakeId = '')
    {
        if (! $this->authorizedMutation(true)) {
            return;
        }
        if (! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', (string) $intakeId)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_intake_id'], 400);
        }

        $lat = $this->input->post('latitude');
        $lon = $this->input->post('longitude');
        if (! is_numeric($lat) || ! is_numeric($lon) || (float) $lat < -90 || (float) $lat > 90 || (float) $lon < -180 || (float) $lon > 180) {
            return $this->json(['ok' => false, 'reason' => 'invalid_coordinates'], 422);
        }

        $saved = $this->Tecnina_receiving_model->saveLocation($intakeId, [
            'adjusted_latitude' => (float) $lat,
            'adjusted_longitude' => (float) $lon,
            'adjusted_accuracy_meters' => $this->input->post('accuracy') ? (float) $this->input->post('accuracy') : null,
            'adjusted_source' => 'STAFF_ADJUSTED',
        ]);

        return $this->json(['ok' => true, 'result' => 'saved', 'data' => $saved], 200);
    }

    public function trigger_deferred_registration($intakeId = '')
    {
        if (! $this->authorizedMutation(true)) {
            return;
        }
        if (! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', (string) $intakeId)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_intake_id'], 400);
        }

        $result = $this->tecnina_bot_gateway->request(
            'POST',
            '/admin/intakes/' . rawurlencode($intakeId) . '/deferred-registration',
            ['purpose' => 'DEFERRED_AT_RECEIVING']
        );

        return $this->json($result, $result['status'] ?? 200);
    }

    public function materialize($intakeId = '')
    {
        if (! $this->authorizedMutation(true)) {
            return;
        }

        if ($this->input->method(true) !== 'POST') {
            return $this->json(['ok' => false, 'reason' => 'method_not_allowed'], 405);
        }

        if (! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', (string) $intakeId)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_intake_id'], 400);
        }

        $operatorId = (int) $this->session->userdata('id_admin');
        if ($operatorId <= 0) {
            return $this->json(['ok' => false, 'reason' => 'invalid_operator'], 403);
        }

        $options = [];
        $clientAction = $this->input->post('client_action');
        if (in_array($clientAction, ['CREATE_NEW', 'LINK_EXISTING'], true)) {
            $options['client_action'] = $clientAction;
        }
        $clientId = $this->input->post('client_id');
        if (! empty($clientId) && is_numeric($clientId)) {
            $options['client_id'] = (int) $clientId;
        }
        if ($this->input->post('force_create_new') !== null) {
            $options['force_create_new'] = filter_var($this->input->post('force_create_new'), FILTER_VALIDATE_BOOLEAN);
        }

        $this->load->library('Tecnina_materialization_service');
        $result = $this->tecnina_materialization_service->materialize($intakeId, $operatorId, $options);

        if (! ($result['ok'] ?? false)) {
            $status = 422;
            if (($result['reason'] ?? '') === 'snapshot_stale' || ($result['reason'] ?? '') === 'idempotency_conflict') {
                $status = 409;
            } elseif (($result['reason'] ?? '') === 'invalid_operator') {
                $status = 403;
            } elseif (($result['reason'] ?? '') === 'invalid_intake_id') {
                $status = 400;
            }
            return $this->json($result, $status);
        }

        return $this->json($result, 200);
    }

    private function validateSimulationId($simulationId)
    {
        return (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', (string) $simulationId);
    }

    private function checkAccess(array $allowedPerms, $json = false)
    {
        if (! $this->session->userdata('logado')) {
            if ($json) {
                $this->json(['ok' => false, 'reason' => 'unauthorized'], 401);
            } else {
                redirect(site_url('login'));
            }
            return false;
        }

        $perm = $this->session->userdata('permissao');
        $hasPerm = false;
        foreach ($allowedPerms as $permKey) {
            if ($this->permission->checkPermission($perm, $permKey)) {
                $hasPerm = true;
                break;
            }
        }

        if ($hasPerm) {
            return true;
        }

        if ($json) {
            $this->json(['ok' => false, 'reason' => 'forbidden'], 403);
        } else {
            $this->session->set_flashdata('error', 'Você não tem permissão para acessar esta área');
            redirect(base_url());
        }
        return false;
    }

    private function authorizedRead($json = false)
    {
        return $this->checkAccess(['cSistema', 'vOs', 'aOs', 'eOs'], $json);
    }

    private function authorizedMutation($json = false)
    {
        return $this->checkAccess(['cSistema', 'aOs', 'eOs'], $json);
    }

    private function authorized($json = false, $requireAdmin = false)
    {
        if ($requireAdmin) {
            return $this->checkAccess(['cSistema'], $json);
        }
        return $this->authorizedRead($json);
    }

    private function preparePanel($view)
    {
        $this->data['view'] = $view;
        $this->data['gatewayConfigured'] = $this->tecnina_bot_gateway->available();
        $this->data['csrfName'] = $this->security->get_csrf_token_name();
        $this->data['csrfHash'] = $this->security->get_csrf_hash();
    }

    private function withMaposClientNames(array $rows)
    {
        $clientIds = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $clientId = (int) ($row['mapos_client_id'] ?? $row['possible_mapos_client_id'] ?? 0);
            if ($clientId > 0) {
                $clientIds[$clientId] = true;
            }
        }
        if ($clientIds === []) {
            return $rows;
        }

        $names = [];
        $clients = $this->db
            ->select('idClientes, nomeCliente')
            ->from('clientes')
            ->where_in('idClientes', array_keys($clientIds))
            ->get()
            ->result_array();
        foreach ($clients as $client) {
            $names[(int) $client['idClientes']] = (string) $client['nomeCliente'];
        }
        foreach ($rows as &$row) {
            if (! is_array($row)) {
                continue;
            }
            $clientId = (int) ($row['mapos_client_id'] ?? $row['possible_mapos_client_id'] ?? 0);
            $row['mapos_client_name'] = $names[$clientId] ?? null;
        }
        unset($row);

        return $rows;
    }

    private function json($body, $status = 200)
    {
        if (is_array($body)) {
            $body['csrf'] = $this->security->get_csrf_hash();
        }
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($body));
    }
}
