<?php

$assertions = 0;
$root = dirname(__DIR__);

function expectContainsCredential($needle, $haystack, $message)
{
    global $assertions;
    ++$assertions;
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function expectNotContainsCredential($needle, $haystack, $message)
{
    global $assertions;
    ++$assertions;
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$publicModel = file_get_contents($root . '/application/models/Conecte_model.php');
expectContainsCredential('OS_CAMPOS_PUBLICOS', $publicModel, 'O model do cliente deve usar uma lista publica de colunas.');
expectNotContainsCredential(
    "select('os.*",
    $publicModel,
    'O model do cliente nao pode selecionar os.*.'
);
expectNotContainsCredential(
    'clientes.*',
    $publicModel,
    'O model do cliente nao pode selecionar clientes.*.'
);

$mineController = file_get_contents($root . '/application/controllers/Mine.php');
expectNotContainsCredential(
    'os_model->getById',
    $mineController,
    'A area do cliente deve buscar OS pelo model com projecao publica.'
);

$clientApi = file_get_contents($root . '/application/controllers/api/v1/client/ClientOsController.php');
expectNotContainsCredential(
    'os_model->getById',
    $clientApi,
    'A API do cliente deve buscar OS pelo model com projecao publica.'
);

foreach (glob($root . '/application/views/conecte/*.php') as $clientView) {
    expectNotContainsCredential(
        'credencial_',
        file_get_contents($clientView),
        basename($clientView) . ' nao pode renderizar campos de credencial.'
    );
}

$emailView = file_get_contents($root . '/application/views/os/emails/os.php');
expectNotContainsCredential(
    'credencial_',
    $emailView,
    'O email de OS nao pode conter a credencial do aparelho.'
);

$adminApi = file_get_contents($root . '/application/controllers/api/v1/OsController.php');
expectContainsCredential(
    'withoutCredentialFields',
    $adminApi,
    'A API administrativa deve remover a credencial de suas respostas comuns.'
);

$postDeploy = file_get_contents($root . '/tools/device-credential/post-deploy.sh');
expectContainsCredential('set -eu', $postDeploy, 'O post-deploy deve interromper em erros e variaveis ausentes.');
expectContainsCredential('trap cleanup EXIT', $postDeploy, 'O post-deploy deve limpar o lock e relatar falhas.');
expectContainsCredential(
    'tools/device-credential/install.php',
    $postDeploy,
    'O post-deploy deve reutilizar o instalador idempotente da feature.'
);

echo "DeviceCredentialPrivacyTest: {$assertions} assertions passed." . PHP_EOL;
