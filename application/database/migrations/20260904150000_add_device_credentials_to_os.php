<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_add_device_credentials_to_os extends CI_Migration
{
    public function up()
    {
        $columns = [
            'credencial_tipo' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => false,
                'default' => 'nao_informada',
                'after' => 'laudoTecnico',
            ],
            'credencial_dados' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'credencial_tipo',
            ],
            'credencial_grade' => [
                'type' => 'TINYINT',
                'constraint' => 3,
                'unsigned' => true,
                'null' => true,
                'after' => 'credencial_dados',
            ],
            'credencial_atualizada_em' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'credencial_grade',
            ],
        ];

        foreach ($columns as $name => $definition) {
            if (! $this->db->field_exists($name, 'os')) {
                $this->dbforge->add_column('os', [$name => $definition]);
            }
        }
    }

    public function down()
    {
        foreach ([
            'credencial_atualizada_em',
            'credencial_grade',
            'credencial_dados',
            'credencial_tipo',
        ] as $column) {
            if ($this->db->field_exists($column, 'os')) {
                $this->dbforge->drop_column('os', $column);
            }
        }
    }
}
