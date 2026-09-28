<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Migration: CIAO-S04 Legal Catalog & Append-Only Ledger
 *
 * Creates:
 * - tecnina_legal_document_versions (version catalog, immutable published snapshots)
 * - tecnina_legal_acceptances (append-only ledger)
 * - Triggers rejecting UPDATE and DELETE on tecnina_legal_acceptances
 * - Seeds initial v1.0 PUBLISHED rows for TERMS_OF_USE and PRIVACY_POLICY
 */
class Migration_add_s04_legal_catalog_and_ledger extends CI_Migration
{
    public function up()
    {
        $this->createLegalTables();
        $this->createAppendOnlyTriggers();
        $this->seedInitialPublishedVersions();
    }

    public function down()
    {
        // Legal catalog and ledger are audit-grade, forward-recovery only.
        throw new RuntimeException('S04 legal catalog and ledger are forward-recovery only; automatic downgrade is prohibited.');
    }

    private function createLegalTables()
    {
        $versionsTable = '`' . $this->db->dbprefix('tecnina_legal_document_versions') . '`';
        $acceptancesTable = '`' . $this->db->dbprefix('tecnina_legal_acceptances') . '`';

        $this->db->query("CREATE TABLE IF NOT EXISTS {$versionsTable} (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `document_type` VARCHAR(32) NOT NULL,
            `version` VARCHAR(32) NOT NULL,
            `canonical_content` LONGTEXT NOT NULL,
            `content_hash` CHAR(64) NOT NULL,
            `public_url` VARCHAR(255) NOT NULL,
            `status` VARCHAR(16) NOT NULL DEFAULT 'DRAFT',
            `effective_at` DATETIME NOT NULL,
            `published_at` DATETIME NULL,
            `requires_new_acceptance` TINYINT(1) NOT NULL DEFAULT 0,
            `communication_required` TINYINT(1) NOT NULL DEFAULT 0,
            `re_manifestation_reason` VARCHAR(255) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_tecnina_legal_doc_version` (`document_type`, `version`),
            KEY `ix_tecnina_legal_doc_effective` (`document_type`, `status`, `effective_at`),
            CONSTRAINT `chk_tecnina_legal_doc_type` CHECK (`document_type` IN ('TERMS_OF_USE', 'PRIVACY_POLICY')),
            CONSTRAINT `chk_tecnina_legal_doc_status` CHECK (`status` IN ('DRAFT', 'PUBLISHED', 'SUPERSEDED'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS {$acceptancesTable} (
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `event_id` CHAR(36) NOT NULL,
            `idempotency_key` VARCHAR(100) NOT NULL,
            `document_version_id` INT NOT NULL,
            `intake_id` CHAR(36) NULL,
            `client_id_at_event` INT NULL,
            `action` VARCHAR(16) NOT NULL,
            `occurred_at` DATETIME NOT NULL,
            `source` VARCHAR(64) NOT NULL,
            `capability_id` VARCHAR(64) NULL,
            `ip_address` VARCHAR(45) NULL,
            `user_agent` VARCHAR(255) NULL,
            `document_type_snapshot` VARCHAR(32) NOT NULL,
            `document_version_snapshot` VARCHAR(32) NOT NULL,
            `document_hash_snapshot` CHAR(64) NOT NULL,
            UNIQUE KEY `uq_tecnina_legal_event_id` (`event_id`),
            UNIQUE KEY `uq_tecnina_legal_idempotency_key` (`idempotency_key`),
            KEY `ix_tecnina_legal_acceptances_subject` (`intake_id`, `client_id_at_event`),
            KEY `ix_tecnina_legal_acceptances_doc_ver` (`document_version_id`),
            CONSTRAINT `fk_tecnina_legal_doc_version` FOREIGN KEY (`document_version_id`) REFERENCES {$versionsTable} (`id`) ON DELETE RESTRICT,
            CONSTRAINT `chk_tecnina_legal_subject` CHECK (`intake_id` IS NOT NULL OR `client_id_at_event` IS NOT NULL),
            CONSTRAINT `chk_tecnina_legal_action` CHECK (`action` IN ('ACCEPTED', 'ACKNOWLEDGED'))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function createAppendOnlyTriggers()
    {
        $acceptancesTable = '`' . $this->db->dbprefix('tecnina_legal_acceptances') . '`';

        $this->db->query("DROP TRIGGER IF EXISTS `trg_tecnina_legal_acceptances_no_update`");
        $this->db->query("CREATE TRIGGER `trg_tecnina_legal_acceptances_no_update`
            BEFORE UPDATE ON {$acceptancesTable}
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'tecnina_legal_acceptances is append-only';
            END");

        $this->db->query("DROP TRIGGER IF EXISTS `trg_tecnina_legal_acceptances_no_delete`");
        $this->db->query("CREATE TRIGGER `trg_tecnina_legal_acceptances_no_delete`
            BEFORE DELETE ON {$acceptancesTable}
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'tecnina_legal_acceptances is append-only';
            END");
    }

    private function seedInitialPublishedVersions()
    {
        $versionsTable = '`' . $this->db->dbprefix('tecnina_legal_document_versions') . '`';

        $termsFile = APPPATH . 'database/legal/terms-of-use-v1.0.md';
        $privacyFile = APPPATH . 'database/legal/privacy-policy-v1.0.md';

        if (! file_exists($termsFile) || ! file_exists($privacyFile)) {
            throw new RuntimeException("Canonical legal files missing at {$termsFile} or {$privacyFile}");
        }

        $termsContent = file_get_contents($termsFile);
        $privacyContent = file_get_contents($privacyFile);

        $expectedTermsHash = 'de0936f1e54fc9f005202752e66c1ec659035a57fa2dd7e859acd28b458ebd82';
        $expectedPrivacyHash = '4b15348c1a7b30ddb102e44f18b8c72a5b21be9072c3e9a09c3d9c1ab0b30079';

        $actualTermsHash = hash('sha256', $termsContent);
        $actualPrivacyHash = hash('sha256', $privacyContent);

        if ($actualTermsHash !== $expectedTermsHash) {
            throw new RuntimeException("Terms content hash mismatch: expected {$expectedTermsHash}, got {$actualTermsHash}");
        }
        if ($actualPrivacyHash !== $expectedPrivacyHash) {
            throw new RuntimeException("Privacy content hash mismatch: expected {$expectedPrivacyHash}, got {$actualPrivacyHash}");
        }

        $this->db->query("INSERT IGNORE INTO {$versionsTable} (
            `document_type`, `version`, `canonical_content`, `content_hash`,
            `public_url`, `status`, `effective_at`, `published_at`,
            `requires_new_acceptance`, `communication_required`, `created_at`, `updated_at`
        ) VALUES (
            'TERMS_OF_USE', '1.0', " . $this->db->escape($termsContent) . ", '{$actualTermsHash}',
            'https://tecnina.com/termos-de-uso', 'PUBLISHED', '2026-09-15 00:00:00', '2026-09-15 00:00:00',
            0, 0, '2026-09-15 00:00:00', '2026-09-15 00:00:00'
        )");

        $this->db->query("INSERT IGNORE INTO {$versionsTable} (
            `document_type`, `version`, `canonical_content`, `content_hash`,
            `public_url`, `status`, `effective_at`, `published_at`,
            `requires_new_acceptance`, `communication_required`, `created_at`, `updated_at`
        ) VALUES (
            'PRIVACY_POLICY', '1.0', " . $this->db->escape($privacyContent) . ", '{$actualPrivacyHash}',
            'https://tecnina.com/politica-de-privacidade', 'PUBLISHED', '2026-09-15 00:00:00', '2026-09-15 00:00:00',
            0, 0, '2026-09-15 00:00:00', '2026-09-15 00:00:00'
        )");
    }
}
