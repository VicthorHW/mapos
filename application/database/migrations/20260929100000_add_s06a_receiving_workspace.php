<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Migration: S06A Physical Receiving Workspace
 *
 * Adds idempotency tracking to tecnina_physical_receiving and optional caption
 * to tecnina_pre_os_attachments for staff receiving workflow.
 */
class Migration_add_s06a_receiving_workspace extends CI_Migration
{
    public function up()
    {
        $this->addReceivingIdempotencyColumns();
        $this->addAttachmentCaptionColumn();
    }

    public function down()
    {
        if ($this->db->table_exists('tecnina_physical_receiving')) {
            foreach (['request_hash', 'idempotency_key'] as $col) {
                if ($this->db->field_exists($col, 'tecnina_physical_receiving')) {
                    $this->dbforge->drop_column('tecnina_physical_receiving', $col);
                }
            }
        }

        if ($this->db->table_exists('tecnina_pre_os_attachments')) {
            if ($this->db->field_exists('caption', 'tecnina_pre_os_attachments')) {
                $this->dbforge->drop_column('tecnina_pre_os_attachments', 'caption');
            }
        }
    }

    private function addReceivingIdempotencyColumns()
    {
        if (! $this->db->table_exists('tecnina_physical_receiving')) {
            return;
        }

        $fields = [
            'idempotency_key' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'null' => true,
                'after' => 'notes',
            ],
            'request_hash' => [
                'type' => 'CHAR',
                'constraint' => 64,
                'null' => true,
                'after' => 'idempotency_key',
            ],
        ];

        foreach ($fields as $name => $definition) {
            if (! $this->db->field_exists($name, 'tecnina_physical_receiving')) {
                $this->dbforge->add_column('tecnina_physical_receiving', [$name => $definition]);
            }
        }
    }

    private function addAttachmentCaptionColumn()
    {
        if (! $this->db->table_exists('tecnina_pre_os_attachments')) {
            return;
        }

        if (! $this->db->field_exists('caption', 'tecnina_pre_os_attachments')) {
            $this->dbforge->add_column('tecnina_pre_os_attachments', [
                'caption' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                    'after' => 'last_error_code',
                ],
            ]);
        }
    }
}
