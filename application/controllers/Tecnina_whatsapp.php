<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tecnina_whatsapp extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('tecnina_bot_gateway');
        $this->data['menuConfiguracoes'] = 'WhatsApp';
    }

    public function index()
    {
        if (! $this->authorized()) {
            return;
        }

        $this->data['view'] = 'tecnina_whatsapp/index';
        $this->data['gatewayConfigured'] = $this->tecnina_bot_gateway->available();
        $this->data['csrfName'] = $this->security->get_csrf_token_name();
        $this->data['csrfHash'] = $this->security->get_csrf_hash();

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
            'queue' => '/admin/queue',
            'logs' => '/admin/logs',
            'status-rules' => '/admin/status-rules',
            'templates' => '/admin/templates',
            'settings' => '/admin/settings/status-notifications',
        ];
        if (! isset($paths[$resource])) {
            return $this->json(['ok' => false, 'reason' => 'not_found'], 404);
        }

        $result = $this->tecnina_bot_gateway->request('GET', $paths[$resource]);
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
            return $this->json($result, $result['status']);
        }
        if ($method !== 'POST' || ! in_array($action, ['save', 'reject', 'approve'], true)) {
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
            'brand' => trim((string) $this->input->post('brand', true)),
            'problem_description' => trim((string) $this->input->post('problem_description', true)),
            'city' => trim((string) $this->input->post('city', true)),
        ];
        if (in_array('', $required, true) || ! in_array($serviceMode, ['DROP_OFF', 'PICKUP_REQUESTED'], true)) {
            return $this->json(['ok' => false, 'reason' => 'invalid_intake_fields'], 422);
        }
        $payload = array_merge($required, [
            'review_version' => $version,
            'name' => trim((string) $this->input->post('name', true)),
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

    public function conversa($conversationId = 0, $action = '')
    {
        if (! $this->authorized(true)) {
            return;
        }
        if ($this->input->method(true) !== 'POST' || ! ctype_digit((string) $conversationId) || ! in_array($action, ['manual-lock', 'resume'], true)) {
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
