<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tecnina_bot_client_model extends CI_Model
{
    public function profile($clientId)
    {
        return $this->db
            ->select('idClientes, nomeCliente, celular, telefone, rua, numero, complemento, bairro, cidade, estado, cep')
            ->from('clientes')
            ->where('idClientes', (int) $clientId)
            ->limit(1)
            ->get()
            ->row_array();
    }

    public function createOrFind(array $client)
    {
        $matches = $this->matchingIds($client['phone']);
        if (count($matches) > 1) {
            return ['ok' => false, 'reason' => 'ambiguous_phone'];
        }
        if (count($matches) === 1) {
            $identity = $this->db
                ->select('documento, email')
                ->from('clientes')
                ->where('idClientes', (int) $matches[0])
                ->limit(1)
                ->get()
                ->row_array();
            $storedCpf = preg_replace('/\D+/', '', (string) ($identity['documento'] ?? ''));
            $storedEmail = trim((string) ($identity['email'] ?? ''));
            if (! $identity
                || ! hash_equals((string) $client['cpf'], $storedCpf)
                || strcasecmp((string) $client['email'], $storedEmail) !== 0) {
                return ['ok' => false, 'reason' => 'phone_identity_conflict'];
            }

            return ['ok' => true, 'result' => 'existing', 'client_id' => (int) $matches[0]];
        }

        $duplicate = $this->db
            ->select('idClientes')
            ->from('clientes')
            ->group_start()
            ->where('documento', $client['cpf'])
            ->or_where('email', $client['email'])
            ->group_end()
            ->limit(1)
            ->get()
            ->row_array();
        if ($duplicate) {
            return ['ok' => false, 'reason' => 'identity_already_registered'];
        }

        $created = $this->db->insert('clientes', [
            'nomeCliente' => $client['name'],
            'contato' => null,
            'pessoa_fisica' => 1,
            'documento' => $client['cpf'],
            'telefone' => '',
            'celular' => $client['phone'],
            'email' => $client['email'],
            'senha' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
            'rua' => null,
            'numero' => null,
            'complemento' => null,
            'bairro' => null,
            'cidade' => null,
            'estado' => null,
            'cep' => null,
            'dataCadastro' => date('Y-m-d'),
            'fornecedor' => 0,
        ]);
        if (! $created || ! is_numeric($this->db->insert_id())) {
            throw new RuntimeException('client insert failed');
        }

        return ['ok' => true, 'result' => 'created', 'client_id' => (int) $this->db->insert_id()];
    }

    public function updateProfile($clientId, array $changes)
    {
        if ($changes === []) {
            return $this->profile($clientId) !== null;
        }

        return (bool) $this->db
            ->where('idClientes', (int) $clientId)
            ->update('clientes', $changes);
    }

    public function unlinkPhone($clientId, $canonicalPhone, $phoneLibrary)
    {
        $row = $this->db
            ->select('idClientes, celular, telefone')
            ->from('clientes')
            ->where('idClientes', (int) $clientId)
            ->limit(1)
            ->get()
            ->row_array();
        if (! $row) {
            return false;
        }

        $changes = [];
        foreach (['celular', 'telefone'] as $field) {
            if ($phoneLibrary->matchesCandidate($canonicalPhone, $row[$field])) {
                $changes[$field] = '';
            }
        }
        if ($changes === []) {
            return false;
        }

        return (bool) $this->db
            ->where('idClientes', (int) $clientId)
            ->update('clientes', $changes);
    }

    private function matchingIds($phone)
    {
        $this->load->library('Tecnina_phone');
        $matches = [];
        $rows = $this->db
            ->select('idClientes, celular, telefone')
            ->from('clientes')
            ->get()
            ->result_array();
        foreach ($rows as $row) {
            if ($this->tecnina_phone->matchesCandidate($phone, $row['celular'], $row['telefone'])) {
                $matches[(int) $row['idClientes']] = true;
            }
        }

        return array_keys($matches);
    }
}
