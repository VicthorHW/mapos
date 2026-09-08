<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** Minimal, allowlisted read model for the TecNina Bot contract. */
class Tecnina_client_open_os_model extends CI_Model
{
    public function getOpenByClientId($clientId)
    {
        return $this->db
            ->select(
                "bot_os.idOs AS os_id, bot_os.status AS mapos_status, "
                . "COALESCE(MIN(NULLIF(bot_equipment.equipamento, '')), NULLIF(bot_os.descricaoProduto, '')) AS device_type, "
                . "MIN(NULLIF(bot_brand.marca, '')) AS brand, "
                . "MIN(NULLIF(bot_equipment.modelo, '')) AS model",
                false
            )
            ->from('os AS bot_os')
            ->join('equipamentos_os AS bot_equipment_os', 'bot_equipment_os.os_id = bot_os.idOs', 'left')
            ->join('equipamentos AS bot_equipment', 'bot_equipment.idEquipamentos = bot_equipment_os.equipamentos_id', 'left')
            ->join('marcas AS bot_brand', 'bot_brand.idMarcas = bot_equipment.marcas_id', 'left')
            ->where('bot_os.clientes_id', (int) $clientId)
            ->where('bot_os.faturado', 0)
            ->where_not_in('bot_os.status', ['Faturado', 'Cancelado'])
            ->group_by(['bot_os.idOs', 'bot_os.status', 'bot_os.descricaoProduto'])
            ->order_by('bot_os.idOs', 'DESC')
            ->get()
            ->result_array();
    }
}
