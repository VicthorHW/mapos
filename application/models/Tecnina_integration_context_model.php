<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tecnina_integration_context_model extends CI_Model
{
    public function getForOs($osId)
    {
        return $this->db
            ->select('os.idOs AS os_id, os.clientes_id AS client_id, clientes.celular, clientes.telefone')
            ->from('os')
            ->join('clientes', 'clientes.idClientes = os.clientes_id')
            ->where('os.idOs', (int) $osId)
            ->limit(1)
            ->get()
            ->row_array();
    }
}
