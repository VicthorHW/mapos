<?php

$assertions = 0;
$root = dirname(__DIR__);

function expectBotClientProfile($condition, $message)
{
    global $assertions;
    ++$assertions;
    if (! $condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$routes = file_get_contents($root . '/application/config/routes.php');
$profile = file_get_contents($root . '/application/controllers/api/bot/Client_profile.php');
$registration = file_get_contents($root . '/application/controllers/api/bot/Client_registration.php');
$clients = file_get_contents($root . '/application/controllers/api/bot/Clients.php');
$model = file_get_contents($root . '/application/models/Tecnina_bot_client_model.php');
$installer = file_get_contents($root . '/tools/tecnina-integration/install.php');

expectBotClientProfile($routes !== false && $profile !== false && $registration !== false && $clients !== false && $model !== false, 'Contratos privados de cliente ausentes.');
expectBotClientProfile(strpos($routes, "api/bot/client/(:num)/profile") !== false, 'Rota do perfil mínimo ausente.');
expectBotClientProfile(strpos($routes, "api/bot/client/(:num)/unlink-phone") !== false, 'Rota de desvinculação segura ausente.');
expectBotClientProfile(strpos($routes, "api/bot/client-registration/email-code") !== false, 'Rota de confirmação de e-mail ausente.');
expectBotClientProfile(strpos($routes, "api/bot/clients") !== false, 'Rota de cadastro opcional ausente.');
foreach ([$profile, $registration, $clients] as $controller) {
    expectBotClientProfile(strpos($controller, 'authorizeRequest()') !== false, 'Endpoint privado sem autenticação interna.');
    expectBotClientProfile(stripos($controller, 'select *') === false, 'Endpoint privado não pode usar SELECT *.');
}
expectBotClientProfile(strpos($model, "select('idClientes, nomeCliente, celular, telefone, rua, numero, complemento, bairro, cidade, estado, cep')") !== false, 'Perfil não usa whitelist explícita.');
expectBotClientProfile(stripos($profile . $clients . $model, "'senha'") !== false, 'Cadastro novo deve gerar senha aleatória interna.');
expectBotClientProfile(stripos($profile, 'senha') === false && stripos($profile, 'documento') === false && stripos($profile, 'email') === false, 'Perfil mínimo expõe dados não necessários.');
expectBotClientProfile(stripos($model, ' like ') === false, 'Telefone não pode ser autenticado por LIKE parcial.');
expectBotClientProfile(strpos($model, "'phone_identity_conflict'") !== false && strpos($model, 'hash_equals') !== false, 'Retry de cadastro não revalida CPF/e-mail do telefone existente.');
expectBotClientProfile(strpos($registration, "preg_match('/^\\d{6}$/', \$code)") !== false, 'Código de e-mail não exige exatamente seis dígitos.');
expectBotClientProfile(strpos($registration, "insert('email_queue'") === false && strpos($registration, "add('email_queue'") !== false, 'Código de e-mail não usa a fila existente do MapOS.');
expectBotClientProfile(strpos($installer, 'Client_registration.php') !== false && strpos($installer, 'Tecnina_bot_client_model.php') !== false, 'Instalador não verifica os novos contratos privados.');

echo 'TecninaBotClientProfileTest: ' . $assertions . ' assertions passed.' . PHP_EOL;
