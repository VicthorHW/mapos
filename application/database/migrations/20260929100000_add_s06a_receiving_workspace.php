<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Migration: S06A Physical Receiving Workspace
 *
 * Adds idempotency tracking to tecnina_physical_receiving and optional caption
 * to tecnina_pre_os_attachments for staff receiving workflow.
 *
 * Idempotency Index Semantics:
 * - idx_tpr_idempotency is a plain (non-unique) index on idempotency_key.
 * - Accelerates replay lookup (confirmPhysicalReceipt checks for existing keys).
 * - Non-unique because preparing drafts have idempotency_key = NULL, and
 *   intake uniqueness is already guaranteed by idx_tpr_intake (UNIQUE on intake_id).
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
            $table = $this->db->dbprefix('tecnina_physical_receiving');
            $indexQuery = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = 'idx_tpr_idempotency'");
            if ($indexQuery->num_rows() > 0) {
                $this->db->query("DROP INDEX `idx_tpr_idempotency` ON `{$table}`");
            }

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

        // Plain index on idempotency_key for accelerated lookup
        $table = $this->db->dbprefix('tecnina_physical_receiving');
        $indexQuery = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = 'idx_tpr_idempotency'");
        if ($indexQuery->num_rows() === 0) {
            $this->db->query("CREATE INDEX `idx_tpr_idempotency` ON `{$table}` (`idempotency_key`)");
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
