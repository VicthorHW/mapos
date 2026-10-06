<?php

defined('BASEPATH') or require_once __DIR__ . '/bootstrap.php';

class S06b_readiness_test_runner extends CI_Controller
{
    public function index()
    {
        $this->load->database();
        $this->load->library('tecnina_readiness_service');
        $this->load->library('tecnina_attachment_storage');
        $this->load->model('Tecnina_receiving_model');
        require FCPATH . 'tests/TecninaS06BReadinessGateTest.php';
    }

    public function count_os()
    {
        $this->load->database();
        echo (int) $this->db->count_all('os');
    }

    public function ping_test()
    {
        $botBaseUrl = getenv('BOT_INTERNAL_URL') ?: ($_ENV['BOT_INTERNAL_URL'] ?? 'http://127.0.0.1:8080');
        $botBaseUrl = rtrim($botBaseUrl, '/');
        $hosts = ['tecnina-bot-dev.invalid', 'bot-dev.invalid', 'tecnina-bot-dev', 'mapos-dev.invalid'];
        $results = [];
        foreach ($hosts as $h) {
            $ch = curl_init("{$botBaseUrl}/health/live");
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Host: {$h}"]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $results[$h] = ['code' => $code, 'body' => $body];
        }
        echo json_encode($results);
    }

    public function env_debug()
    {
        $botBaseUrl = getenv('BOT_INTERNAL_URL') ?: ($_ENV['BOT_INTERNAL_URL'] ?? 'http://tecnina-bot-dev:8080');
        $botBaseUrl = rtrim($botBaseUrl, '/');
        $ch = curl_init("{$botBaseUrl}/health/live");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        echo json_encode([
            'err' => $err,
            'body' => $body,
            'info' => $info,
        ]);
    }

    public function users_debug()
    {
        $this->load->database();
        $rows = $this->db->select('idUsuarios, nome, email, permissoes_id, situacao')->get('usuarios')->result_array();
        $perms = $this->db->get('permissoes')->result_array();
        echo json_encode(['users' => $rows, 'perms' => $perms]);
    }

    public function setup_test_users()
    {
        $this->load->database();

        // 1. Admin Operator
        $admin = $this->db->get_where('usuarios', ['email' => 'operador_s06a@tecnina.dev'])->row_array();
        if ($admin) {
            $this->db->where('idUsuarios', $admin['idUsuarios'])->update('usuarios', [
                'senha' => password_hash('senha123', PASSWORD_BCRYPT),
                'situacao' => 1,
                'permissoes_id' => 1,
                'dataExpiracao' => '2030-12-31',
            ]);
            $adminId = $admin['idUsuarios'];
        } else {
            $this->db->insert('usuarios', [
                'nome' => 'Operador Teste S06A',
                'email' => 'operador_s06a@tecnina.dev',
                'senha' => password_hash('senha123', PASSWORD_BCRYPT),
                'situacao' => 1,
                'permissoes_id' => 1,
                'dataCadastro' => date('Y-m-d'),
                'dataExpiracao' => '2030-12-31',
            ]);
            $adminId = $this->db->insert_id();
        }

        // 2. View-only permission
        $viewPerm = $this->db->get_where('permissoes', ['nome' => 'Somente Leitura OS'])->row_array();
        $viewPermData = serialize(['vOs' => '1', 'vCliente' => '1']);
        if ($viewPerm) {
            $viewPermId = $viewPerm['idPermissao'];
        } else {
            $this->db->insert('permissoes', [
                'nome' => 'Somente Leitura OS',
                'permissoes' => $viewPermData,
                'situacao' => 1,
                'data' => date('Y-m-d'),
            ]);
            $viewPermId = $this->db->insert_id();
        }

        // 3. View-only User
        $viewUser = $this->db->get_where('usuarios', ['email' => 'viewonly_s06a@tecnina.dev'])->row_array();
        if ($viewUser) {
            $this->db->where('idUsuarios', $viewUser['idUsuarios'])->update('usuarios', [
                'senha' => password_hash('senha123', PASSWORD_BCRYPT),
                'situacao' => 1,
                'permissoes_id' => $viewPermId,
                'dataExpiracao' => '2030-12-31',
            ]);
            $viewUserId = $viewUser['idUsuarios'];
        } else {
            $this->db->insert('usuarios', [
                'nome' => 'View Only S06A',
                'email' => 'viewonly_s06a@tecnina.dev',
                'senha' => password_hash('senha123', PASSWORD_BCRYPT),
                'situacao' => 1,
                'permissoes_id' => $viewPermId,
                'dataCadastro' => date('Y-m-d'),
                'dataExpiracao' => '2030-12-31',
            ]);
            $viewUserId = $this->db->insert_id();
        }

        echo json_encode(['admin_id' => $adminId, 'view_user_id' => $viewUserId, 'view_perm_id' => $viewPermId]);
    }
}

if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $runner = new S06b_readiness_test_runner();
    $method = $argv[1] ?? 'index';
    if (method_exists($runner, $method)) {
        $runner->$method();
    } else {
        fwrite(STDERR, "Unknown method: {$method}\n");
        exit(1);
    }
}
