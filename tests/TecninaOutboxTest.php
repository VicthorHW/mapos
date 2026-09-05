<?php

define('BASEPATH', __DIR__);

$assertions = 0;
$root = dirname(__DIR__);

function expectOutbox($condition, $message)
{
    global $assertions;
    ++$assertions;
    if (! $condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function sourceFile($root, $path)
{
    $content = file_get_contents($root . '/' . $path);
    expectOutbox($content !== false, 'Arquivo ausente: ' . $path);

    return $content;
}

$setup = sourceFile($root, 'application/controllers/Tecnina_integration_setup.php');
$model = sourceFile($root, 'application/models/Tecnina_outbox_model.php');
$controller = sourceFile($root, 'application/controllers/api/bot/Outbox.php');
$auth = sourceFile($root, 'application/libraries/Tecnina_bot_auth.php');
$postDeploy = sourceFile($root, 'tools/tecnina-integration/post-deploy.sh');

expectOutbox(strpos($setup, "physicalName('os')") !== false, 'Instalador deve resolver DB_PREFIX para OS.');
expectOutbox(
    strpos($setup, "physicalName('tecnina_integration_outbox')") !== false
        && strpos($setup, 'dbprefix($logicalName)') !== false,
    'Instalador deve resolver DB_PREFIX para a outbox.'
);
expectOutbox(strpos($setup, 'SHOW GRANTS FOR CURRENT_USER') !== false, 'Privilegio TRIGGER deve ser validado.');
expectOutbox(strpos($setup, 'CREATE TABLE IF NOT EXISTS') !== false, 'Criacao da outbox deve ser idempotente.');
expectOutbox(strpos($setup, 'IF NOT (OLD.`status` <=> NEW.`status`)') !== false, 'Trigger deve ignorar status inalterado.');
expectOutbox(strpos($setup, "'os.status_changed'") !== false, 'Trigger deve criar o evento esperado.');
expectOutbox(stripos($setup, 'http') === false, 'Trigger/instalador nao pode chamar HTTP.');
expectOutbox(stripos($setup, 'evolution') === false, 'Trigger/instalador nao pode acessar Evolution.');

expectOutbox(strpos($model, 'FOR UPDATE') !== false, 'Claim e ACK devem usar bloqueio transacional.');
expectOutbox(strpos($model, 'claim_expires_at') !== false, 'Claim deve possuir expiracao.');
expectOutbox(strpos($model, 'attempts` = `attempts` + 1') !== false, 'Claim deve contar tentativas.');
expectOutbox(strpos($model, 'ACKNOWLEDGED') !== false, 'ACK deve possuir estado explicito.');

foreach (['event_id', 'event_type', 'os_id', 'client_id', 'old_status', 'new_status', 'created_at'] as $field) {
    expectOutbox(strpos($controller, "'{$field}'") !== false, 'Whitelist sem campo: ' . $field);
}
foreach (['password', 'credencial_dados', 'cpf', 'endereco', 'anexos'] as $forbidden) {
    expectOutbox(stripos($controller, $forbidden) === false, 'Endpoint referencia campo proibido: ' . $forbidden);
}

expectOutbox(strpos($auth, 'hash_equals') !== false, 'Token interno deve usar comparacao segura.');
expectOutbox(strpos($auth, 'API_ENABLED') !== false, 'API desabilitada deve ser tratada.');
expectOutbox(strpos($postDeploy, 'set -eu') !== false, 'Post-deploy deve falhar em erros.');
expectOutbox(strpos($postDeploy, '--verify-only') !== false, 'Instalador deve possuir verificacao sem escrita.');

require_once $root . '/application/libraries/Tecnina_bot_auth.php';
$botAuth = new Tecnina_bot_auth();
$_ENV['API_ENABLED'] = 'false';
expectOutbox($botAuth->availability()['status'] === 503, 'API desabilitada deve retornar indisponivel.');
$_ENV['API_ENABLED'] = 'true';
unset($_ENV['MAPOS_BOT_TOKEN']);
expectOutbox($botAuth->availability()['status'] === 503, 'Token ausente deve retornar indisponivel.');
$_ENV['MAPOS_BOT_TOKEN'] = str_repeat('a', 32);
expectOutbox($botAuth->authorize(null)['status'] === 401, 'Token nao enviado deve retornar 401.');
expectOutbox($botAuth->authorize('Bearer incorreto')['status'] === 403, 'Token incorreto deve retornar 403.');
expectOutbox($botAuth->authorize('Bearer ' . str_repeat('a', 32))['ok'] === true, 'Token correto deve autorizar.');

echo 'TecninaOutboxTest: ' . $assertions . ' assertions passed.' . PHP_EOL;
