<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tecnina_intake_approval_model extends CI_Model
{
    public function approve($intakeId, $requestHash, array $payload)
    {
        $table = '`' . $this->db->dbprefix('tecnina_intake_approvals') . '`';
        $this->db->trans_begin();
        try {
            $this->db->query(
                "INSERT IGNORE INTO {$table} (`intake_id`, `request_hash`, `state`) VALUES (?, ?, 'PROCESSING')",
                [$intakeId, $requestHash]
            );
            $ownsApproval = $this->db->affected_rows() === 1;
            $approval = $this->db->query(
                "SELECT `request_hash`, `state`, `client_id`, `os_id`, `client_created` FROM {$table} "
                . 'WHERE `intake_id` = ? FOR UPDATE',
                [$intakeId]
            )->row_array();
            if (! $approval || ! hash_equals($approval['request_hash'], $requestHash)) {
                $this->db->trans_rollback();

                return ['ok' => false, 'reason' => 'idempotency_conflict'];
            }
            if ($approval['state'] === 'COMPLETED') {
                $this->db->trans_commit();

                return [
                    'ok' => true,
                    'result' => 'already_completed',
                    'client_id' => (int) $approval['client_id'],
                    'os_id' => (int) $approval['os_id'],
                    'client_created' => (bool) $approval['client_created'],
                ];
            }
            if (! $ownsApproval) {
                $this->db->trans_rollback();

                return ['ok' => false, 'reason' => 'approval_in_progress'];
            }

            if (! $this->validOperator($payload['operator_id'])) {
                $this->db->trans_rollback();

                return ['ok' => false, 'reason' => 'invalid_operator'];
            }
            $matches = $this->matchingClientIds($payload['client']['phone']);
            $clientCreated = false;
            if ($payload['client_action'] === 'LINK_EXISTING') {
                if (count($matches) !== 1 || (int) $matches[0] !== (int) $payload['client_id']) {
                    $this->db->trans_rollback();

                    return ['ok' => false, 'reason' => count($matches) > 1 ? 'ambiguous_client' : 'client_match_changed'];
                }
                $clientId = (int) $payload['client_id'];
            } else {
                if ($matches !== [] && ! $payload['force_create_new']) {
                    $this->db->trans_rollback();

                    return ['ok' => false, 'reason' => count($matches) > 1 ? 'ambiguous_client' : 'duplicate_client_requires_decision'];
                }
                $clientId = $this->insertClient($payload['client']);
                $clientCreated = true;
            }

            $osId = $this->insertOs($clientId, $payload['operator_id'], $intakeId, $payload['os']);
            $this->db->query(
                "UPDATE {$table} SET `state` = 'COMPLETED', `client_id` = ?, `os_id` = ?, "
                . '`client_created` = ?, `completed_at` = UTC_TIMESTAMP() WHERE `intake_id` = ?',
                [$clientId, $osId, $clientCreated ? 1 : 0, $intakeId]
            );
            if ($this->db->trans_status() === false) {
                throw new RuntimeException('intake approval transaction failed');
            }
            $this->db->trans_commit();

            return [
                'ok' => true,
                'result' => 'created',
                'client_id' => $clientId,
                'os_id' => $osId,
                'client_created' => $clientCreated,
            ];
        } catch (Throwable $exception) {
            $this->db->trans_rollback();
            throw $exception;
        }
    }

    private function validOperator($operatorId)
    {
        return $this->db
            ->select('idUsuarios')
            ->from('usuarios')
            ->where('idUsuarios', (int) $operatorId)
            ->where('situacao', 1)
            ->limit(1)
            ->get()
            ->num_rows() === 1;
    }

    private function matchingClientIds($phone)
    {
        $matches = [];
        $rows = $this->db
            ->select('idClientes, celular, telefone')
            ->from('clientes')
            ->get()
            ->result_array();
        foreach ($rows as $row) {
            if ($this->tecnina_phone->normalizeBrazilianIdentity($row['celular']) === $phone
                || $this->tecnina_phone->normalizeBrazilianIdentity($row['telefone']) === $phone) {
                $matches[(int) $row['idClientes']] = true;
            }
        }

        return array_keys($matches);
    }

    private function insertClient(array $client)
    {
        $password = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
        $created = $this->db->insert('clientes', [
            'nomeCliente' => $this->limited($client['name'], 255),
            'contato' => null,
            'pessoa_fisica' => 1,
            'documento' => '',
            'telefone' => '',
            'celular' => $client['phone'],
            'email' => '',
            'senha' => $password,
            'rua' => null,
            'numero' => null,
            'complemento' => null,
            'bairro' => null,
            'cidade' => $this->limited($client['city'], 45),
            'estado' => null,
            'cep' => null,
            'dataCadastro' => date('Y-m-d'),
            'fornecedor' => 0,
        ]);
        if (! $created || ! is_numeric($this->db->insert_id())) {
            throw new RuntimeException('client insert failed');
        }

        return (int) $this->db->insert_id();
    }

    private function insertOs($clientId, $operatorId, $intakeId, array $os)
    {
        $description = implode(' ', array_filter([
            $this->limited($os['device_type'], 80),
            $this->limited($os['brand'], 80),
            $this->limited($os['model'], 120),
        ]));
        $serviceMode = $os['service_mode'] === 'PICKUP_REQUESTED'
            ? 'Coleta solicitada'
            : 'Cliente levará o equipamento';
        $observations = 'OS criada a partir do pré-atendimento WhatsApp #' . $intakeId . ".\n"
            . "Credencial do equipamento ainda não informada.\n"
            . 'Forma de atendimento: ' . $serviceMode . ".\n"
            . 'Cidade informada: ' . $os['city'] . '.';
        if ($os['notes'] !== null && trim($os['notes']) !== '') {
            $observations .= "\nObservações da revisão: " . trim($os['notes']);
        }

        $created = $this->db->insert('os', [
            'dataInicial' => date('Y-m-d'),
            'dataFinal' => null,
            'garantia' => '0',
            'garantias_id' => null,
            'descricaoProduto' => $description,
            'defeito' => $os['problem_description'],
            'status' => 'Aberto',
            'observacoes' => $observations,
            'laudoTecnico' => null,
            'credencial_tipo' => 'nao_informada',
            'credencial_dados' => null,
            'credencial_grade' => null,
            'credencial_atualizada_em' => null,
            'clientes_id' => (int) $clientId,
            'usuarios_id' => (int) $operatorId,
            'faturado' => 0,
        ]);
        if (! $created || ! is_numeric($this->db->insert_id())) {
            throw new RuntimeException('os insert failed');
        }

        return (int) $this->db->insert_id();
    }

    private function limited($value, $length)
    {
        return mb_substr(trim((string) $value), 0, $length);
    }
}
