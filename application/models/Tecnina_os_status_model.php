<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tecnina_os_status_model extends CI_Model
{
    public function getBasicStatus($osId)
    {
        return $this->db
            ->select('os.idOs AS os_id, os.clientes_id AS client_id, os.status AS mapos_status')
            ->from('os')
            ->where('os.idOs', (int) $osId)
            ->limit(1)
            ->get()
            ->row_array();
    }
}
