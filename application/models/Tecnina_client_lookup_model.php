<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tecnina_client_lookup_model extends CI_Model
{
    /**
     * Return only the columns required to perform a local exact identity
     * comparison. The controller never exposes these raw values to Gateway.
     */
    public function mobileCandidates()
    {
        return $this->db
            ->select('idClientes AS client_id, celular, telefone')
            ->from('clientes')
            ->get()
            ->result_array();
    }
}
