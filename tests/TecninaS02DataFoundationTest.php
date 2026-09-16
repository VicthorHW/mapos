<?php

$assertions = 0;
$root = dirname(__DIR__);

function expectS02($condition, $message)
{
    global $assertions;
    ++$assertions;
    if (! $condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$migration = file_get_contents($root . '/application/database/migrations/20260915120000_add_s02_pre_os_data_foundation.php');
$setup = file_get_contents($root . '/application/controllers/Tecnina_integration_setup.php');
expectS02($migration !== false, 'Migração S02 ausente.');
expectS02(strpos($migration, 'CREATE TABLE IF NOT EXISTS') !== false, 'Migração deve ser idempotente.');
foreach (['tecnina_physical_receiving', 'tecnina_intake_locations', 'tecnina_pre_os_attachments'] as $table) {
    expectS02(strpos($migration, $table) !== false, 'Tabela S02 ausente: ' . $table);
}
foreach (['snapshot_hash', 'readiness_contract_version', 'attachment_sync_state', 'bot_sync_state', 'bot_finalize_attempts'] as $column) {
    expectS02(strpos($migration, "'{$column}'") !== false, 'Metadado de aprovação ausente: ' . $column);
}
expectS02(strpos($migration, 'DROP DATABASE') === false && strpos($migration, 'TRUNCATE') === false, 'Migração não pode ser destrutiva.');
expectS02(strpos($migration, 'sha256` CHAR(64)') !== false, 'Anexo precisa de SHA-256 fixo.');
expectS02(strpos($migration, 'size_bytes` BIGINT UNSIGNED') !== false, 'Tamanho de anexo não pode ser negativo.');
expectS02(strpos($migration, 'UNIQUE KEY `uq_tecnina_pre_os_attachment_identity`') !== false, 'Anexo precisa de identidade única.');
expectS02(strpos($migration, 'BETWEEN -90 AND 90') !== false && strpos($migration, 'BETWEEN -180 AND 180') !== false, 'GPS precisa de limites geográficos.');
expectS02(strpos($migration, 'CONSTRAINT `chk_tecnina_physical_receiving_state`') !== false, 'Recebimento precisa de estado controlado.');
expectS02(strpos($setup, "'bot_finalize_attempts'") !== false, 'Instalador deve reconhecer metadados novos.');
expectS02(strpos($setup, "'tecnina_intake_approvals'") !== false, 'Instalador deve preservar prefixo da tabela.');
echo 'TecninaS02DataFoundationTest: ' . $assertions . ' assertions passed.' . PHP_EOL;
