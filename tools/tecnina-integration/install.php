<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este instalador deve ser executado pela linha de comando.\n");
    exit(1);
}

$options = getopt('', ['verify-only']);
$verifyOnly = array_key_exists('verify-only', $options);
$root = realpath(__DIR__ . '/../..');
if ($root === false || ! is_file($root . '/index.php')) {
    fwrite(STDERR, "[tecnina-integration] ERRO: raiz do MAP-OS nao encontrada.\n");
    exit(1);
}

$required = [
    'application/controllers/Tecnina_integration_setup.php',
    'application/controllers/api/bot/Health.php',
    'application/controllers/api/bot/Outbox.php',
    'application/libraries/Tecnina_bot_auth.php',
    'application/models/Tecnina_outbox_model.php',
];
foreach ($required as $file) {
    if (! is_file($root . '/' . $file)) {
        fwrite(STDERR, '[tecnina-integration] ERRO: arquivo ausente: ' . $file . PHP_EOL);
        exit(1);
    }
}

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/index.php')
    . ' tecnina_integration_setup ' . ($verifyOnly ? 'verify' : 'install');
passthru($command, $exitCode);
exit($exitCode);
