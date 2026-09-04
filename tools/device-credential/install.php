<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este instalador deve ser executado pela linha de comando.\n");
    exit(1);
}

$options = getopt('', ['verify-only', 'skip-key', 'skip-migration', 'skip-tests']);
$verifyOnly = array_key_exists('verify-only', $options);
$skipKey = $verifyOnly || array_key_exists('skip-key', $options);
$skipMigration = $verifyOnly || array_key_exists('skip-migration', $options);
$skipTests = array_key_exists('skip-tests', $options);
$root = realpath(__DIR__ . '/../..');

if ($root === false || ! is_file($root . '/index.php') || ! is_dir($root . '/application')) {
    fwrite(STDERR, "Nao foi possivel localizar a raiz do MAP-OS.\n");
    exit(1);
}

chdir($root);

function installerInfo($message)
{
    fwrite(STDOUT, '[device-credential] ' . $message . PHP_EOL);
}

function installerFail($message)
{
    fwrite(STDERR, '[device-credential] ERRO: ' . $message . PHP_EOL);
    exit(1);
}

function verifyFeatureSource($root)
{
    $requiredFiles = [
        'application/controllers/Device_credential_setup.php',
        'application/libraries/Device_credential.php',
        'application/database/migrations/20260904150000_add_device_credentials_to_os.php',
        'application/views/os/_deviceCredentialForm.php',
        'application/views/os/_deviceCredentialDisplay.php',
        'application/views/os/_deviceCredentialPrint.php',
        'application/views/os/_deviceCredentialPrintThermal.php',
        'assets/js/device-pattern.js',
        'assets/js/device-credential-display.js',
        'assets/css/device-credential.css',
        'tools/device-credential/manifest.json',
    ];

    foreach ($requiredFiles as $file) {
        if (! is_file($root . '/' . $file)) {
            installerFail('Arquivo da feature ausente: ' . $file);
        }
    }

    $markers = [
        'application/controllers/Os.php' => [
            "load->library('device_credential')",
            'prepareForStorage',
            'public function credencial',
        ],
        'application/controllers/api/v1/OsController.php' => [
            "load->library('device_credential')",
            'prepareForStorage',
            'withoutCredentialFields',
        ],
        'application/controllers/Mine.php' => [
            'Conecte_model->getById',
        ],
        'application/controllers/api/v1/client/ClientOsController.php' => [
            'Conecte_model->getById',
        ],
        'application/models/Conecte_model.php' => [
            'OS_CAMPOS_PUBLICOS',
            'CLIENTE_CAMPOS_PUBLICOS',
        ],
        'application/views/os/adicionarOs.php' => ["os/_deviceCredentialForm"],
        'application/views/os/editarOs.php' => ["os/_deviceCredentialForm"],
        'application/views/os/visualizarOs.php' => ["os/_deviceCredentialDisplay"],
        'application/views/os/imprimirOs.php' => ["os/_deviceCredentialPrint"],
        'application/views/os/imprimirOsTermica.php' => ["os/_deviceCredentialPrintThermal"],
        'application/.env.example' => ['DEVICE_CREDENTIAL_KEY'],
        'banco.sql' => ['credencial_tipo', 'credencial_dados', 'credencial_grade'],
        'composer.json' => ['DeviceCredentialTest.php', 'DeviceCredentialPrivacyTest.php'],
    ];

    foreach ($markers as $file => $expectedMarkers) {
        $content = @file_get_contents($root . '/' . $file);
        if ($content === false) {
            installerFail('Nao foi possivel ler ' . $file . '.');
        }
        foreach ($expectedMarkers as $marker) {
            if (strpos($content, $marker) === false) {
                installerFail(sprintf(
                    'Integracao incompleta em %s (marcador ausente: %s). Consulte docs/device-credential-feature.md.',
                    $file,
                    $marker
                ));
            }
        }
    }

    $publicModel = file_get_contents($root . '/application/models/Conecte_model.php');
    if (strpos($publicModel, "select('os.*") !== false || strpos($publicModel, 'clientes.*') !== false) {
        installerFail('O model publico voltou a usar SELECT *; a privacidade da credencial nao pode ser garantida.');
    }

    installerInfo('Arquivos e pontos de integracao verificados.');
}

function reportCompatibility($root)
{
    $manifestPath = $root . '/tools/device-credential/manifest.json';
    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    $config = (string) file_get_contents($root . '/application/config/config.php');

    if (! is_array($manifest)
        || empty($manifest['version'])
        || empty($manifest['tested_mapos_versions'])
        || ! preg_match("/app_version'\]\\s*=\\s*'([^']+)'/", $config, $matches)) {
        installerFail('Nao foi possivel identificar as versoes da feature e do MAP-OS.');
    }

    $maposVersion = $matches[1];
    installerInfo(sprintf(
        'Feature v%s; MAP-OS v%s.',
        $manifest['version'],
        $maposVersion
    ));

    if (! in_array($maposVersion, $manifest['tested_mapos_versions'], true)) {
        installerInfo(
            'AVISO: esta versao do MAP-OS ainda nao consta como testada com a feature. '
            . 'A verificacao automatica continuara, mas execute tambem o roteiro manual da documentacao.'
        );
    }
}

function configureCredentialKey($root)
{
    $envPath = $root . '/application/.env';
    if (! is_file($envPath)) {
        installerFail(
            'application/.env nao existe. Configure o MAP-OS, copie .env.example para .env e execute novamente.'
        );
    }

    $environment = file_get_contents($envPath);
    if ($environment === false) {
        installerFail('Nao foi possivel ler application/.env.');
    }

    $pattern = '/^DEVICE_CREDENTIAL_KEY\s*=\s*(.*)$/m';
    if (preg_match($pattern, $environment, $matches)) {
        $current = trim(trim($matches[1]), "\"'");
        if ($current !== '') {
            if (strlen($current) < 16) {
                installerFail('DEVICE_CREDENTIAL_KEY existe, mas e muito curta. Corrija-a manualmente antes de prosseguir.');
            }
            installerInfo('Chave exclusiva ja configurada; nenhuma alteracao foi feita no .env.');
            return;
        }
    }

    $key = base64_encode(random_bytes(32));
    $line = 'DEVICE_CREDENTIAL_KEY="' . $key . '"';

    if (preg_match($pattern, $environment)) {
        $updated = preg_replace($pattern, $line, $environment, 1);
    } else {
        $updated = rtrim($environment, "\r\n") . PHP_EOL . $line . PHP_EOL;
    }

    if ($updated === null || file_put_contents($envPath, $updated, LOCK_EX) === false) {
        installerFail('Nao foi possivel gravar DEVICE_CREDENTIAL_KEY em application/.env.');
    }

    installerInfo('Chave exclusiva gerada em application/.env (o valor nao foi exibido).');
}

function runPhpCommand($root, array $arguments, $label)
{
    $script = array_shift($arguments);
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/' . $script);
    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg($argument);
    }

    installerInfo($label . '...');
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        installerFail($label . ' falhou com codigo ' . $exitCode . '.');
    }
}

verifyFeatureSource($root);
reportCompatibility($root);

if (! $skipKey) {
    configureCredentialKey($root);
}

if (! $skipMigration) {
    runPhpCommand($root, ['index.php', 'tools', 'migrate'], 'Executando migrations do MAP-OS');
    runPhpCommand(
        $root,
        ['index.php', 'device_credential_setup', 'install'],
        'Verificando schema independente da ordem das migrations'
    );
}

if (! $skipTests) {
    runPhpCommand($root, ['tests/DeviceCredentialTest.php'], 'Testando regras de padrao');
    runPhpCommand($root, ['tests/DeviceCredentialPrivacyTest.php'], 'Testando isolamento do portal');
}

installerInfo($verifyOnly ? 'Verificacao concluida.' : 'Feature instalada/atualizada com sucesso.');
