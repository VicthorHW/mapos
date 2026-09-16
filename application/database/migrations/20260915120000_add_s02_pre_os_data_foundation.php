<?php

defined('BASEPATH') or exit('No direct script access allowed');

/** S02-A persistence only: no intake, approval, or OS flow changes. */
class Migration_add_s02_pre_os_data_foundation extends CI_Migration
{
    public function up()
    {
        $this->addApprovalColumns();
        $this->createPhysicalReceiving();
        $this->createIntakeLocations();
        $this->createPreOsAttachments();
    }

    public function down()
    {
        // Down is intended only for an unused local S02 database.
        foreach (['tecnina_pre_os_attachments', 'tecnina_intake_locations', 'tecnina_physical_receiving'] as $table) {
            $this->db->query('DROP TABLE IF EXISTS `' . $this->db->dbprefix($table) . '`');
        }
        $approval = '`' . $this->db->dbprefix('tecnina_intake_approvals') . '`';
        foreach ([
            'last_error_code', 'bot_finalize_attempts', 'bot_sync_state', 'attachment_sync_state',
            'readiness_contract_version', 'readiness_result', 'seal_expires_at', 'snapshot_fetched_at', 'snapshot_hash', 'snapshot_version',
            'intake_version',
        ] as $column) {
            if ($this->db->field_exists($column, 'tecnina_intake_approvals')) {
                $this->dbforge->drop_column('tecnina_intake_approvals', $column);
            }
        }
    }

    private function addApprovalColumns()
    {
        if (! $this->db->table_exists('tecnina_intake_approvals')) {
            return;
        }
        $columns = [
            'intake_version' => ['type' => 'INT', 'null' => true],
            'snapshot_version' => ['type' => 'INT', 'null' => true],
            'snapshot_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'snapshot_fetched_at' => ['type' => 'DATETIME', 'null' => true],
            'seal_expires_at' => ['type' => 'DATETIME', 'null' => true],
            'readiness_result' => ['type' => 'TEXT', 'null' => true],
            'readiness_contract_version' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'attachment_sync_state' => ['type' => 'VARCHAR', 'constraint' => 24, 'null' => true],
            'bot_sync_state' => ['type' => 'VARCHAR', 'constraint' => 24, 'null' => true],
            'bot_finalize_attempts' => ['type' => 'INT', 'unsigned' => true, 'null' => false, 'default' => 0],
            'last_error_code' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
        ];
        foreach ($columns as $name => $definition) {
            if (! $this->db->field_exists($name, 'tecnina_intake_approvals')) {
                $this->dbforge->add_column('tecnina_intake_approvals', [$name => $definition]);
            }
        }
    }

    private function createPhysicalReceiving()
    {
        $table = '`' . $this->db->dbprefix('tecnina_physical_receiving') . '`';
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS {$table} ("
            . '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . '`intake_id` CHAR(36) NOT NULL,'
            . "`state` VARCHAR(24) NOT NULL DEFAULT 'PENDING',"
            . '`received_at` DATETIME NULL,'
            . '`received_by` INT NULL,'
            . '`device_condition` VARCHAR(64) NULL,'
            . '`accessories` TEXT NULL,'
            . '`serial_number` VARCHAR(128) NULL,'
            . '`imei` VARCHAR(32) NULL,'
            . '`other_identifiers` TEXT NULL,'
            . '`notes` TEXT NULL,'
            . '`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . '`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . 'PRIMARY KEY (`id`), UNIQUE KEY `uq_tecnina_physical_receiving_intake` (`intake_id`),'
            . 'KEY `ix_tecnina_physical_receiving_state` (`state`, `created_at`),'
            . "CONSTRAINT `chk_tecnina_physical_receiving_state` CHECK (`state` IN ('PENDING','RECEIVED','CANCELLED'))"
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function createIntakeLocations()
    {
        $table = '`' . $this->db->dbprefix('tecnina_intake_locations') . '`';
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS {$table} ("
            . '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . '`intake_id` CHAR(36) NOT NULL,'
            . '`original_latitude` DECIMAL(10,7) NULL,'
            . '`original_longitude` DECIMAL(10,7) NULL,'
            . '`original_accuracy_meters` DECIMAL(10,2) NULL,'
            . '`original_source` VARCHAR(32) NULL,'
            . '`original_captured_at` DATETIME NULL,'
            . '`adjusted_latitude` DECIMAL(10,7) NULL,'
            . '`adjusted_longitude` DECIMAL(10,7) NULL,'
            . '`adjusted_accuracy_meters` DECIMAL(10,2) NULL,'
            . '`adjusted_source` VARCHAR(32) NULL,'
            . '`adjusted_at` DATETIME NULL,'
            . '`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . '`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . 'PRIMARY KEY (`id`), UNIQUE KEY `uq_tecnina_intake_locations_intake` (`intake_id`),'
            . 'KEY `ix_tecnina_intake_locations_original` (`original_latitude`, `original_longitude`),'
            . 'CONSTRAINT `chk_tecnina_location_original_lat` CHECK (`original_latitude` IS NULL OR (`original_latitude` BETWEEN -90 AND 90)),'
            . 'CONSTRAINT `chk_tecnina_location_original_lon` CHECK (`original_longitude` IS NULL OR (`original_longitude` BETWEEN -180 AND 180)),'
            . 'CONSTRAINT `chk_tecnina_location_adjusted_lat` CHECK (`adjusted_latitude` IS NULL OR (`adjusted_latitude` BETWEEN -90 AND 90)),'
            . 'CONSTRAINT `chk_tecnina_location_adjusted_lon` CHECK (`adjusted_longitude` IS NULL OR (`adjusted_longitude` BETWEEN -180 AND 180))'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function createPreOsAttachments()
    {
        $table = '`' . $this->db->dbprefix('tecnina_pre_os_attachments') . '`';
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS {$table} ("
            . '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . '`intake_id` CHAR(36) NOT NULL,'
            . '`original_name` VARCHAR(255) NOT NULL,'
            . '`storage_key` VARCHAR(255) NOT NULL,'
            . '`detected_mime` VARCHAR(127) NULL,'
            . '`size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,'
            . '`sha256` CHAR(64) NOT NULL,'
            . "`state` VARCHAR(24) NOT NULL DEFAULT 'PENDING',"
            . '`promoted_anexo_id` INT NULL,'
            . '`attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,'
            . '`last_error_code` VARCHAR(64) NULL,'
            . '`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . '`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . 'PRIMARY KEY (`id`),'
            . 'UNIQUE KEY `uq_tecnina_pre_os_attachment_identity` (`intake_id`, `sha256`, `size_bytes`),'
            . 'KEY `ix_tecnina_pre_os_attachments_state` (`state`, `created_at`),'
            . "CONSTRAINT `chk_tecnina_pre_os_attachment_state` CHECK (`state` IN ('PENDING','SYNCED','PROMOTED','FAILED','CANCELLED'))"
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
