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
