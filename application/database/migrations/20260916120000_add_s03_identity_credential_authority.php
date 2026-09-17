<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * S03-A identity authority.  It is intentionally dormant: Mine's active
 * login/profile paths are not changed by this migration.
 */
class Migration_add_s03_identity_credential_authority extends CI_Migration
{
    public function up()
    {
        $this->widenLegacyAddressColumns();
        $this->createIdentityTables();
        $this->seedLegacyIdentityRows();
    }

    public function down()
    {
        // Never narrow legacy client address fields or alter clientes data.
        // The authority tables may contain subsequently issued credentials;
        // target downgrade therefore requires an explicit reviewed procedure.
        throw new RuntimeException('S03-A identity authority is forward-recovery only; automatic downgrade is prohibited.');
    }

    private function widenLegacyAddressColumns()
    {
        $table = '`' . $this->db->dbprefix('clientes') . '`';
        foreach ([
            'rua' => 'VARCHAR(160)', 'numero' => 'VARCHAR(32)', 'bairro' => 'VARCHAR(120)',
            'cidade' => 'VARCHAR(80)', 'complemento' => 'VARCHAR(160)',
        ] as $column => $definition) {
            if ($this->db->field_exists($column, 'clientes')) {
                $this->db->query("ALTER TABLE {$table} MODIFY `{$column}` {$definition} NULL");
            }
        }
    }

    private function createIdentityTables()
    {
        $identity = '`' . $this->db->dbprefix('tecnina_client_identity') . '`';
        $profile = '`' . $this->db->dbprefix('tecnina_client_profile') . '`';
        $verification = '`' . $this->db->dbprefix('tecnina_email_verifications') . '`';
        $reset = '`' . $this->db->dbprefix('tecnina_password_resets') . '`';
        $conflict = '`' . $this->db->dbprefix('tecnina_client_identity_phone_conflicts') . '`';
        $limits = '`' . $this->db->dbprefix('tecnina_identity_rate_limits') . '`';
        $this->db->query("CREATE TABLE IF NOT EXISTS {$identity} (
            `client_id` INT NOT NULL, `canonical_phone` VARCHAR(15) NULL,
            `phone_state` VARCHAR(16) NOT NULL DEFAULT 'NONE', `phone_confirmed_at` DATETIME NULL,
            `email_candidate` VARCHAR(100) NULL, `email_state` VARCHAR(16) NOT NULL DEFAULT 'NONE', `email_verified_at` DATETIME NULL,
            `credential_version` INT UNSIGNED NOT NULL DEFAULT 1, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`client_id`), KEY `ix_tecnina_identity_phone` (`canonical_phone`),
            CONSTRAINT `fk_tecnina_identity_client` FOREIGN KEY (`client_id`) REFERENCES `clientes` (`idClientes`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->db->query("CREATE TABLE IF NOT EXISTS {$profile} (
            `client_id` INT NOT NULL, `birth_date` DATE NULL, `address_reference` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`client_id`), CONSTRAINT `fk_tecnina_profile_client` FOREIGN KEY (`client_id`) REFERENCES `clientes` (`idClientes`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->db->query("CREATE TABLE IF NOT EXISTS {$verification} (
            `id` CHAR(36) NOT NULL, `client_id` INT NULL, `intake_id` CHAR(36) NULL, `purpose` VARCHAR(32) NOT NULL,
            `email_candidate` VARCHAR(100) NOT NULL, `code_digest` CHAR(64) NOT NULL, `state` VARCHAR(16) NOT NULL DEFAULT 'PENDING',
            `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0, `expires_at` DATETIME NOT NULL, `verified_at` DATETIME NULL, `consumed_at` DATETIME NULL,
            `verify_idempotency_key` VARCHAR(100) NULL, `verify_fingerprint` CHAR(64) NULL,
            `idempotency_key` VARCHAR(100) NULL, `request_fingerprint` CHAR(64) NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`), KEY `ix_tecnina_email_verification_subject` (`client_id`, `intake_id`, `state`), UNIQUE KEY `uq_tecnina_email_verification_idempotency` (`idempotency_key`), UNIQUE KEY `uq_tecnina_email_verification_verify_key` (`verify_idempotency_key`),
            CONSTRAINT `chk_tecnina_email_verification_subject` CHECK ((`client_id` IS NULL) <> (`intake_id` IS NULL))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->db->query("CREATE TABLE IF NOT EXISTS {$reset} (
            `id` CHAR(36) NOT NULL, `client_id` INT NOT NULL, `canonical_phone` VARCHAR(15) NOT NULL,
            `token_digest` CHAR(64) NOT NULL, `state` VARCHAR(16) NOT NULL DEFAULT 'PENDING', `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0, `proof_failures` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `expires_at` DATETIME NOT NULL, `consumed_at` DATETIME NULL, `idempotency_key` VARCHAR(100) NULL, `request_fingerprint` CHAR(64) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), KEY `ix_tecnina_reset_client` (`client_id`, `state`),
            UNIQUE KEY `uq_tecnina_reset_idempotency` (`idempotency_key`), CONSTRAINT `fk_tecnina_reset_client` FOREIGN KEY (`client_id`) REFERENCES `clientes` (`idClientes`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->db->query("CREATE TABLE IF NOT EXISTS {$conflict} (
            `canonical_phone` VARCHAR(15) NOT NULL, `client_id` INT NOT NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`canonical_phone`, `client_id`), KEY `ix_tecnina_identity_conflict_client` (`client_id`),
            CONSTRAINT `fk_tecnina_identity_conflict_client` FOREIGN KEY (`client_id`) REFERENCES `clientes` (`idClientes`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->db->query("CREATE TABLE IF NOT EXISTS {$limits} (
            `bucket_key` CHAR(64) NOT NULL, `scope` VARCHAR(32) NOT NULL, `bucket_start` DATETIME NOT NULL,
            `count` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`bucket_key`), KEY `ix_tecnina_identity_rate_window` (`scope`, `bucket_start`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function seedLegacyIdentityRows()
    {
        $identity = '`' . $this->db->dbprefix('tecnina_client_identity') . '`';
        $clients = '`' . $this->db->dbprefix('clientes') . '`';
        // Do not infer verification, but do preserve uniquely resolvable legacy phone identity.
        $this->db->query("INSERT IGNORE INTO {$identity} (`client_id`, `email_state`) SELECT `idClientes`, CASE WHEN `email` IS NULL OR `email` = '' THEN 'NONE' ELSE 'LEGACY_EXISTING' END FROM {$clients}");
        $this->load->library('Tecnina_phone');
        $rows = $this->db->select('idClientes, celular, telefone')->get('clientes')->result();
        $owners = [];
        foreach ($rows as $row) {
            // This is storage provenance, not incoming-API normalization: an
            // unmarked legacy value is Brazilian local, '+' is international.
            foreach ($this->tecnina_phone->candidateIdentities($row->celular, $row->telefone) as $canonical) {
                $owners[$canonical][(int) $row->idClientes] = true;
            }
        }
        foreach ($owners as $canonical => $clientIds) {
            if (count($clientIds) === 1) {
                $this->db->where('client_id', (int) array_key_first($clientIds))->update('tecnina_client_identity', ['canonical_phone' => $canonical, 'phone_state' => 'LEGACY_EXISTING']);
            } else {
                foreach (array_keys($clientIds) as $clientId) {
                    $this->db->insert('tecnina_client_identity_phone_conflicts', ['canonical_phone' => $canonical, 'client_id' => (int) $clientId]);
                }
            }
        }
    }
}
