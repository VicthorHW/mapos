<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Instalacao idempotente do schema da credencial de aparelho.
 *
 * Este controller existe porque o CodeIgniter 3 registra somente a ultima
 * migration executada. Em uma atualizacao futura, uma migration da feature
 * com timestamp anterior ao upstream poderia ser ignorada.
 */
class Device_credential_setup extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        if (! $this->input->is_cli_request()) {
            show_404();
        }

        $this->load->database();
        $this->load->dbforge();
    }

    public function index()
    {
        echo 'Use: php index.php device_credential_setup install' . PHP_EOL;
    }

    public function install()
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

        foreach (array_keys($columns) as $name) {
            if (! $this->db->field_exists($name, 'os')) {
                fwrite(STDERR, 'Nao foi possivel criar a coluna os.' . $name . PHP_EOL);
                exit(1);
            }
        }

        echo 'Schema da credencial de aparelho verificado com sucesso.' . PHP_EOL;
    }
}
