<?php

define('BASEPATH', __DIR__);

$assertions = 0;
$root = dirname(__DIR__);

function expectIntegrationContext($condition, $message)
{
    global $assertions;
    ++$assertions;
    if (! $condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$library = file_get_contents($root . '/application/libraries/Tecnina_phone.php');
$controller = file_get_contents($root . '/application/controllers/api/bot/Integration_context.php');
$model = file_get_contents($root . '/application/models/Tecnina_integration_context_model.php');
$routes = file_get_contents($root . '/application/config/routes.php');

expectIntegrationContext($library !== false && $controller !== false && $model !== false, 'Arquivos da integração ausentes.');
expectIntegrationContext(strpos($routes, "api/bot/integration-context/(:num)") !== false, 'Rota privada ausente.');
expectIntegrationContext(strpos($model, "select('os.idOs AS os_id, os.clientes_id AS client_id, clientes.celular, clientes.telefone')") !== false, 'Consulta deve usar whitelist explícita.');
expectIntegrationContext(strpos($model, 'select(\'*\')') === false, 'Consulta não pode usar SELECT *.');
foreach (['password', 'senha', 'documento', 'credencial'] as $forbidden) {
    expectIntegrationContext(stripos($controller, $forbidden) === false, 'Controller referencia dado proibido: ' . $forbidden);
}

require_once $root . '/application/libraries/Tecnina_phone.php';
$phone = new Tecnina_phone();
expectIntegrationContext($phone->normalizeBrazilianWhatsApp('(41) 99740-3509') === '5541997403509', 'Celular formatado inválido.');
expectIntegrationContext($phone->normalizeBrazilianWhatsApp('5541997403509') === '5541997403509', 'E.164 inválido.');
expectIntegrationContext($phone->normalizeBrazilianWhatsApp('4197403509') === null, 'Número legado ambíguo deveria ser rejeitado.');
expectIntegrationContext($phone->normalizeBrazilianWhatsApp('4133334444') === null, 'Telefone fixo deveria ser rejeitado.');
expectIntegrationContext($phone->normalizeBrazilianWhatsApp('123') === null, 'Telefone curto deveria ser rejeitado.');
expectIntegrationContext($phone->normalizeBrazilianWhatsApp('') === null, 'Telefone vazio deveria ser rejeitado.');

echo 'TecninaIntegrationContextTest: ' . $assertions . ' assertions passed.' . PHP_EOL;
