<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tecnina_whatsapp extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('tecnina_bot_gateway');
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
        return $this->json($result, $result['status']);
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
            }
            return $this->json($result, $result['status']);
        }
        if ($method !== 'POST' || ! in_array($action, ['save', 'reject', 'approve', 'pickup-fee'], true)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_request'], 400);
        }

        $operatorId = (int) $this->session->userdata('id_admin');
        if ($operatorId <= 0) {
            return $this->json(['ok' => false, 'reason' => 'invalid_operator'], 403);
        }
        $version = filter_var($this->input->post('review_version'), FILTER_VALIDATE_INT);
        if ($version === false || $version < 0) {
            return $this->json(['ok' => false, 'reason' => 'invalid_review_version'], 422);
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

        $serviceMode = (string) $this->input->post('service_mode', true);
        $required = [
            'device_type' => trim((string) $this->input->post('device_type', true)),
            'problem_description' => trim((string) $this->input->post('problem_description', true)),
            'city' => trim((string) $this->input->post('city', true)),
        ];
        if (in_array('', $required, true) || ! in_array($serviceMode, ['DROP_OFF', 'PICKUP_REQUESTED'], true)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_intake_fields'], 422);
        }
        $payload = array_merge($required, [
            'review_version' => $version,
            'name' => trim((string) $this->input->post('name', true)),
            'brand' => trim((string) $this->input->post('brand', true)),
            'model' => trim((string) $this->input->post('model', true)),
            'service_mode' => $serviceMode,
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

    public function os_acesso($osId = 0, $action = '')
    {
        if (! $this->authorized(true)) {
            return;
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

    private function authorized($json = false)
    {
        if ($this->permission->checkPermission($this->session->userdata('permissao'), 'cSistema')) {
            return true;
        }
        if ($json) {
            $this->json(['ok' => false, 'reason' => 'forbidden'], 403);
        } else {
            $this->session->set_flashdata('error', 'Você não tem permissão para configurar o sistema');
            redirect(base_url());
        }
        return false;
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
