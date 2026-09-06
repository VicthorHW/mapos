<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Tecnina_integration_setup extends CI_Controller
{
    const SCHEMA_VERSION = 2;

    private $outboxTable;

    private $osTable;

    private $approvalTable;

    private $triggerName;

    public function __construct()
    {
        parent::__construct();
        if (! $this->input->is_cli_request()) {
            show_404();
        }

        $this->load->database();
        $this->outboxTable = $this->physicalName('tecnina_integration_outbox');
        $this->approvalTable = $this->physicalName('tecnina_intake_approvals');
        $this->osTable = $this->physicalName('os');
        $candidate = 'trg_' . $this->osTable . '_tecnina_status_outbox';
        $this->triggerName = strlen($candidate) <= 64
            ? $candidate
            : 'trg_tecnina_status_' . substr(hash('sha256', $this->osTable), 0, 20);
    }

    public function index()
    {
        echo 'Use: php index.php tecnina_integration_setup install|verify' . PHP_EOL;
    }

    public function install()
    {
        $this->validateSourceTable();
        if (! $this->triggerExists()) {
            $this->validateTriggerPrivilege();
        }
        $this->createOrRepairOutbox();
        $this->createOrRepairApprovals();
        $this->createOrValidateTrigger();
        $this->verifyState();
        echo 'Integracao TecNina schema v' . self::SCHEMA_VERSION . ' instalada e verificada.' . PHP_EOL;
    }

    public function verify()
    {
        $this->validateSourceTable();
        $this->verifyState();
        echo 'Integracao TecNina schema v' . self::SCHEMA_VERSION . ' verificada sem alteracoes.' . PHP_EOL;
    }

    private function validateSourceTable()
    {
        if (! $this->db->table_exists('os')) {
            $this->fail('Tabela fisica de OS nao encontrada: ' . $this->osTable);
        }
        foreach (['idOs', 'clientes_id', 'status'] as $field) {
            if (! $this->db->field_exists($field, 'os')) {
                $this->fail('Coluna obrigatoria ausente em ' . $this->osTable . ': ' . $field);
            }
        }
    }

    private function createOrRepairOutbox()
    {
        $table = $this->quote($this->outboxTable);
        $this->mustQuery(
            "CREATE TABLE IF NOT EXISTS {$table} ("
            . '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . '`event_id` CHAR(36) NOT NULL,'
            . '`event_type` VARCHAR(64) NOT NULL,'
            . '`os_id` INT NOT NULL,'
            . '`client_id` INT NOT NULL,'
            . '`old_status` VARCHAR(45) NULL,'
            . '`new_status` VARCHAR(45) NULL,'
            . "`state` VARCHAR(24) NOT NULL DEFAULT 'PENDING',"
            . '`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . '`claimed_at` DATETIME NULL,'
            . '`claim_token` CHAR(36) NULL,'
            . '`claim_expires_at` DATETIME NULL,'
            . '`acknowledged_at` DATETIME NULL,'
            . '`attempts` INT UNSIGNED NOT NULL DEFAULT 0,'
            . '`last_error` TEXT NULL,'
            . 'PRIMARY KEY (`id`),'
            . 'UNIQUE KEY `uq_tecnina_outbox_event_id` (`event_id`),'
            . 'KEY `ix_tecnina_outbox_claimable` (`state`, `claim_expires_at`, `id`),'
            . 'KEY `ix_tecnina_outbox_os` (`os_id`, `created_at`)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $definitions = $this->columnDefinitions();
        $existing = $this->columnNames();
        foreach ($definitions as $name => $definition) {
            if (! in_array($name, $existing, true)) {
                $this->mustQuery("ALTER TABLE {$table} ADD COLUMN " . $this->quote($name) . ' ' . $definition);
            }
        }

        $indexes = $this->indexNames();
        if (! in_array('uq_tecnina_outbox_event_id', $indexes, true)) {
            $this->mustQuery("ALTER TABLE {$table} ADD UNIQUE KEY `uq_tecnina_outbox_event_id` (`event_id`)");
        }
        if (! in_array('ix_tecnina_outbox_claimable', $indexes, true)) {
            $this->mustQuery("ALTER TABLE {$table} ADD KEY `ix_tecnina_outbox_claimable` (`state`, `claim_expires_at`, `id`)");
        }
        if (! in_array('ix_tecnina_outbox_os', $indexes, true)) {
            $this->mustQuery("ALTER TABLE {$table} ADD KEY `ix_tecnina_outbox_os` (`os_id`, `created_at`)");
        }
    }

    private function createOrValidateTrigger()
    {
        $trigger = $this->triggerMetadata();
        if ($trigger !== null) {
            if (! $this->validTriggerMetadata($trigger)) {
                $this->fail('Trigger com nome reservado existe, mas possui definicao conflitante: ' . $this->triggerName);
            }

            return;
        }

        $triggerName = $this->quote($this->triggerName);
        $osTable = $this->quote($this->osTable);
        $outboxTable = $this->quote($this->outboxTable);
        $this->mustQuery(
            "CREATE TRIGGER {$triggerName} AFTER UPDATE ON {$osTable} FOR EACH ROW "
            . 'BEGIN IF NOT (OLD.`status` <=> NEW.`status`) THEN '
            . "INSERT INTO {$outboxTable} "
            . '(`event_id`, `event_type`, `os_id`, `client_id`, `old_status`, `new_status`, `state`) '
            . "VALUES (UUID(), 'os.status_changed', NEW.`idOs`, NEW.`clientes_id`, OLD.`status`, NEW.`status`, 'PENDING'); "
            . 'END IF; END'
        );
    }

    private function createOrRepairApprovals()
    {
        $table = $this->quote($this->approvalTable);
        $this->mustQuery(
            "CREATE TABLE IF NOT EXISTS {$table} ("
            . '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . '`intake_id` CHAR(36) NOT NULL,'
            . '`request_hash` CHAR(64) NOT NULL,'
            . "`state` VARCHAR(24) NOT NULL DEFAULT 'PROCESSING',"
            . '`client_id` INT NULL,'
            . '`os_id` INT NULL,'
            . '`client_created` TINYINT(1) NOT NULL DEFAULT 0,'
            . '`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . '`completed_at` DATETIME NULL,'
            . 'PRIMARY KEY (`id`),'
            . 'UNIQUE KEY `uq_tecnina_intake_approval` (`intake_id`)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $definitions = $this->approvalColumnDefinitions();
        $existing = array_keys($this->approvalColumnMetadata());
        foreach ($definitions as $name => $definition) {
            if (! in_array($name, $existing, true)) {
                $this->mustQuery("ALTER TABLE {$table} ADD COLUMN " . $this->quote($name) . ' ' . $definition);
            }
        }
        if (! in_array('uq_tecnina_intake_approval', $this->approvalIndexNames(), true)) {
            $this->mustQuery("ALTER TABLE {$table} ADD UNIQUE KEY `uq_tecnina_intake_approval` (`intake_id`)");
        }
    }

    private function verifyApprovals()
    {
        if (! $this->db->table_exists('tecnina_intake_approvals')) {
            $this->fail('Tabela de idempotencia de intake ausente: ' . $this->approvalTable);
        }
        $missing = array_diff(
            array_keys($this->approvalColumnDefinitions()),
            array_keys($this->approvalColumnMetadata())
        );
        if ($missing !== []) {
            $this->fail('Tabela de idempotencia incompleta; colunas ausentes: ' . implode(', ', $missing));
        }
        if (! in_array('uq_tecnina_intake_approval', $this->approvalIndexNames(), true)) {
            $this->fail('Indice unico de idempotencia de intake ausente.');
        }
        $this->validateApprovalColumnShapes();
        $this->validateApprovalIndexShapes();
    }

    private function verifyState()
    {
        if (! $this->db->table_exists('tecnina_integration_outbox')) {
            $this->fail('Outbox ausente: ' . $this->outboxTable);
        }
        $missing = array_diff(array_keys($this->columnDefinitions()), $this->columnNames());
        if ($missing !== []) {
            $this->fail('Outbox incompleta; colunas ausentes: ' . implode(', ', $missing));
        }
        $this->validateColumnShapes();
        $this->validateIndexShapes();
        $this->verifyApprovals();
        $trigger = $this->triggerMetadata();
        if ($trigger === null || ! $this->validTriggerMetadata($trigger)) {
            $this->fail('Trigger da outbox ausente ou conflitante: ' . $this->triggerName);
        }
    }

    private function validTriggerMetadata(array $trigger)
    {
        $statement = strtolower(preg_replace('/\s+/', ' ', $trigger['ACTION_STATEMENT']));
        $markers = [
            strtolower($this->outboxTable),
            'old.`status`',
            'new.`status`',
            'new.`idos`',
            'new.`clientes_id`',
            'os.status_changed',
        ];
        foreach ($markers as $marker) {
            if (strpos($statement, $marker) === false) {
                return false;
            }
        }

        return strtoupper($trigger['ACTION_TIMING']) === 'AFTER'
            && strtoupper($trigger['EVENT_MANIPULATION']) === 'UPDATE'
            && $trigger['EVENT_OBJECT_TABLE'] === $this->osTable;
    }

    private function triggerExists()
    {
        return $this->triggerMetadata() !== null;
    }

    private function triggerMetadata()
    {
        $query = $this->db->query(
            'SELECT `TRIGGER_NAME`, `EVENT_MANIPULATION`, `EVENT_OBJECT_TABLE`, '
            . '`ACTION_TIMING`, `ACTION_STATEMENT` FROM information_schema.TRIGGERS '
            . 'WHERE `TRIGGER_SCHEMA` = DATABASE() AND `TRIGGER_NAME` = ?',
            [$this->triggerName]
        );

        return $query->num_rows() === 1 ? $query->row_array() : null;
    }

    private function validateTriggerPrivilege()
    {
        $database = strtolower((string) $this->db->database);
        $allowed = false;
        foreach ($this->db->query('SHOW GRANTS FOR CURRENT_USER')->result_array() as $row) {
            $grant = strtolower((string) reset($row));
            $scopeMatches = strpos($grant, ' on *.* ') !== false
                || strpos($grant, ' on `' . $database . '`.* ') !== false
                || strpos($grant, ' on ' . $database . '.* ') !== false;
            if ($scopeMatches
                && (strpos($grant, 'grant all privileges') === 0
                    || preg_match('/grant\s+[^\n]*\btrigger\b/', $grant))) {
                $allowed = true;
                break;
            }
        }
        if (! $allowed) {
            $this->fail('Usuario MySQL nao possui privilegio TRIGGER no schema atual. Nenhum trigger foi criado.');
        }
    }

    private function columnDefinitions()
    {
        return [
            'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST',
            'event_id' => 'CHAR(36) NOT NULL',
            'event_type' => 'VARCHAR(64) NOT NULL',
            'os_id' => 'INT NOT NULL',
            'client_id' => 'INT NOT NULL',
            'old_status' => 'VARCHAR(45) NULL',
            'new_status' => 'VARCHAR(45) NULL',
            'state' => "VARCHAR(24) NOT NULL DEFAULT 'PENDING'",
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'claimed_at' => 'DATETIME NULL',
            'claim_token' => 'CHAR(36) NULL',
            'claim_expires_at' => 'DATETIME NULL',
            'acknowledged_at' => 'DATETIME NULL',
            'attempts' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'last_error' => 'TEXT NULL',
        ];
    }

    private function columnNames()
    {
        return array_keys($this->columnMetadata());
    }

    private function columnMetadata()
    {
        $rows = $this->db->query('SHOW COLUMNS FROM ' . $this->quote($this->outboxTable))->result_array();
        $metadata = [];
        foreach ($rows as $row) {
            $metadata[$row['Field']] = $row;
        }

        return $metadata;
    }

    private function indexNames()
    {
        $rows = $this->db->query('SHOW INDEX FROM ' . $this->quote($this->outboxTable))->result_array();

        return array_values(array_unique(array_column($rows, 'Key_name')));
    }

    private function validateColumnShapes()
    {
        $columns = $this->columnMetadata();
        $expected = [
            'id' => ['type' => 'bigint unsigned', 'null' => 'NO', 'extra' => 'auto_increment'],
            'event_id' => ['type' => 'char(36)', 'null' => 'NO'],
            'event_type' => ['type' => 'varchar(64)', 'null' => 'NO'],
            'os_id' => ['type' => 'int', 'null' => 'NO'],
            'client_id' => ['type' => 'int', 'null' => 'NO'],
            'old_status' => ['type' => 'varchar(45)', 'null' => 'YES'],
            'new_status' => ['type' => 'varchar(45)', 'null' => 'YES'],
            'state' => ['type' => 'varchar(24)', 'null' => 'NO'],
            'created_at' => ['type' => 'datetime', 'null' => 'NO'],
            'claimed_at' => ['type' => 'datetime', 'null' => 'YES'],
            'claim_token' => ['type' => 'char(36)', 'null' => 'YES'],
            'claim_expires_at' => ['type' => 'datetime', 'null' => 'YES'],
            'acknowledged_at' => ['type' => 'datetime', 'null' => 'YES'],
            'attempts' => ['type' => 'int unsigned', 'null' => 'NO'],
            'last_error' => ['type' => 'text', 'null' => 'YES'],
        ];
        foreach ($expected as $name => $shape) {
            $actualType = strtolower(preg_replace(
                '/^(bigint|int)\([0-9]+\)/i',
                '$1',
                $columns[$name]['Type']
            ));
            if ($actualType !== $shape['type']
                || strtoupper($columns[$name]['Null']) !== $shape['null']
                || (isset($shape['extra']) && strtolower($columns[$name]['Extra']) !== $shape['extra'])) {
                $this->fail('Definicao conflitante na coluna da outbox: ' . $name);
            }
        }
        if ((string) $columns['state']['Default'] !== 'PENDING'
            || (string) $columns['attempts']['Default'] !== '0') {
            $this->fail('Defaults conflitantes nas colunas state/attempts da outbox.');
        }
    }

    private function validateIndexShapes()
    {
        $rows = $this->db->query('SHOW INDEX FROM ' . $this->quote($this->outboxTable))->result_array();
        $actual = [];
        foreach ($rows as $row) {
            $actual[$row['Key_name']]['unique'] = (int) $row['Non_unique'] === 0;
            $actual[$row['Key_name']]['columns'][(int) $row['Seq_in_index']] = $row['Column_name'];
        }
        $expected = [
            'PRIMARY' => ['unique' => true, 'columns' => ['id']],
            'uq_tecnina_outbox_event_id' => ['unique' => true, 'columns' => ['event_id']],
            'ix_tecnina_outbox_claimable' => ['unique' => false, 'columns' => ['state', 'claim_expires_at', 'id']],
            'ix_tecnina_outbox_os' => ['unique' => false, 'columns' => ['os_id', 'created_at']],
        ];
        foreach ($expected as $name => $shape) {
            if (! isset($actual[$name])) {
                $this->fail('Outbox incompleta; indice ausente: ' . $name);
            }
            ksort($actual[$name]['columns']);
            if ($actual[$name]['unique'] !== $shape['unique']
                || array_values($actual[$name]['columns']) !== $shape['columns']) {
                $this->fail('Definicao conflitante no indice da outbox: ' . $name);
            }
        }
    }

    private function approvalColumnDefinitions()
    {
        return [
            'id' => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST',
            'intake_id' => 'CHAR(36) NOT NULL',
            'request_hash' => 'CHAR(64) NOT NULL',
            'state' => "VARCHAR(24) NOT NULL DEFAULT 'PROCESSING'",
            'client_id' => 'INT NULL',
            'os_id' => 'INT NULL',
            'client_created' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'completed_at' => 'DATETIME NULL',
        ];
    }

    private function approvalColumnMetadata()
    {
        $rows = $this->db->query('SHOW COLUMNS FROM ' . $this->quote($this->approvalTable))->result_array();
        $metadata = [];
        foreach ($rows as $row) {
            $metadata[$row['Field']] = $row;
        }

        return $metadata;
    }

    private function approvalIndexNames()
    {
        $rows = $this->db->query('SHOW INDEX FROM ' . $this->quote($this->approvalTable))->result_array();

        return array_values(array_unique(array_column($rows, 'Key_name')));
    }

    private function validateApprovalColumnShapes()
    {
        $columns = $this->approvalColumnMetadata();
        $expected = [
            'id' => ['type' => 'bigint unsigned', 'null' => 'NO', 'extra' => 'auto_increment'],
            'intake_id' => ['type' => 'char(36)', 'null' => 'NO'],
            'request_hash' => ['type' => 'char(64)', 'null' => 'NO'],
            'state' => ['type' => 'varchar(24)', 'null' => 'NO'],
            'client_id' => ['type' => 'int', 'null' => 'YES'],
            'os_id' => ['type' => 'int', 'null' => 'YES'],
            'client_created' => ['type' => 'tinyint(1)', 'null' => 'NO'],
            'created_at' => ['type' => 'datetime', 'null' => 'NO'],
            'completed_at' => ['type' => 'datetime', 'null' => 'YES'],
        ];
        foreach ($expected as $name => $shape) {
            $actualType = strtolower(preg_replace('/^(bigint|int)\([0-9]+\)/i', '$1', $columns[$name]['Type']));
            if ($actualType !== $shape['type']
                || strtoupper($columns[$name]['Null']) !== $shape['null']
                || (isset($shape['extra']) && strtolower($columns[$name]['Extra']) !== $shape['extra'])) {
                $this->fail('Definicao conflitante na coluna de aprovacao: ' . $name);
            }
        }
        if ((string) $columns['state']['Default'] !== 'PROCESSING'
            || (string) $columns['client_created']['Default'] !== '0') {
            $this->fail('Defaults conflitantes na tabela de aprovacao de intake.');
        }
    }

    private function validateApprovalIndexShapes()
    {
        $rows = $this->db->query('SHOW INDEX FROM ' . $this->quote($this->approvalTable))->result_array();
        $actual = [];
        foreach ($rows as $row) {
            $actual[$row['Key_name']]['unique'] = (int) $row['Non_unique'] === 0;
            $actual[$row['Key_name']]['columns'][(int) $row['Seq_in_index']] = $row['Column_name'];
        }
        $expected = [
            'PRIMARY' => ['unique' => true, 'columns' => ['id']],
            'uq_tecnina_intake_approval' => ['unique' => true, 'columns' => ['intake_id']],
        ];
        foreach ($expected as $name => $shape) {
            if (! isset($actual[$name])) {
                $this->fail('Tabela de aprovacao incompleta; indice ausente: ' . $name);
            }
            ksort($actual[$name]['columns']);
            if ($actual[$name]['unique'] !== $shape['unique']
                || array_values($actual[$name]['columns']) !== $shape['columns']) {
                $this->fail('Definicao conflitante no indice de aprovacao: ' . $name);
            }
        }
    }

    private function physicalName($logicalName)
    {
        $name = $this->db->dbprefix($logicalName);
        if (! preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            $this->fail('DB_PREFIX contem caracteres nao permitidos.');
        }

        return $name;
    }

    private function quote($identifier)
    {
        return '`' . $identifier . '`';
    }

    private function mustQuery($sql)
    {
        if (! $this->db->query($sql)) {
            $error = $this->db->error();
            $this->fail('Falha de banco (' . ($error['code'] ?? 'desconhecida') . ').');
        }
    }

    private function fail($message)
    {
        fwrite(STDERR, '[tecnina-integration] ERRO: ' . $message . PHP_EOL);
        exit(1);
    }
}
