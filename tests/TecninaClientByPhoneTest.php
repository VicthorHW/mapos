<?php

define('BASEPATH', __DIR__);

$assertions = 0;
$root = dirname(__DIR__);

function expectClientLookup($condition, $message)
{
    global $assertions;
    ++$assertions;
    if (! $condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$library = file_get_contents($root . '/application/libraries/Tecnina_phone.php');
$controller = file_get_contents($root . '/application/controllers/api/bot/Client_by_phone.php');
$model = file_get_contents($root . '/application/models/Tecnina_client_lookup_model.php');
$routes = file_get_contents($root . '/application/config/routes.php');

expectClientLookup($library !== false && $controller !== false && $model !== false, 'Arquivos de consulta ausentes.');
expectClientLookup(strpos($routes, "api/bot/client/by-phone") !== false, 'Rota privada ausente.');
expectClientLookup(strpos($model, "select('idClientes AS client_id, celular, telefone')") !== false, 'Consulta deve usar whitelist explícita.');
expectClientLookup(strpos($model, 'select(\'*\')') === false, 'Consulta não pode usar SELECT *.');
foreach (['password', 'senha', 'documento', 'credencial', 'nome'] as $forbidden) {
    expectClientLookup(stripos($controller, $forbidden) === false, 'Controller referencia dado proibido: ' . $forbidden);
}

require_once $root . '/application/libraries/Tecnina_phone.php';
$phone = new Tecnina_phone();
expectClientLookup($phone->normalizeBrazilianIdentity('+55 41 99740-3509') === '5541997403509', 'Celular atual inválido.');
expectClientLookup($phone->normalizeBrazilianIdentity('4197403509') === '5541997403509', 'Número móvel legado inválido.');
expectClientLookup($phone->normalizeBrazilianIdentity('4133334444') === null, 'Telefone fixo não deve casar.');
expectClientLookup($phone->normalizeBrazilianIdentity('123') === null, 'Telefone curto não deve casar.');

echo 'TecninaClientByPhoneTest: ' . $assertions . ' assertions passed.' . PHP_EOL;
