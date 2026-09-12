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
expectClientLookup($phone->normalizeCanonicalIdentity('+55 41 99740-3509') === '5541997403509', 'Celular atual inválido.');
expectClientLookup($phone->normalizeIdentity('+55 41 99740-3509') === '5541997403509', 'Celular atual inválido via normalizeIdentity.');
expectClientLookup($phone->matchesCandidate('5541997403509', '4197403509', null), 'Número móvel legado inválido.');
expectClientLookup($phone->normalizeCanonicalIdentity('4133334444') === null, 'Telefone fixo não deve casar.');
expectClientLookup($phone->normalizeIdentity('4133334444') === null, 'Telefone fixo não deve casar via normalizeIdentity.');
expectClientLookup($phone->normalizeCanonicalIdentity('123') === null, 'Telefone curto não deve casar.');
expectClientLookup($phone->normalizeIdentity('123') === null, 'Telefone curto não deve casar via normalizeIdentity.');
expectClientLookup($phone->normalizeCanonicalIdentity('66912345678') === '66912345678', 'Internacional não deve virar Brasil.');
expectClientLookup($phone->normalizeCanonicalIdentity('351911872552') === '351911872552', 'Portugal deve ser preservado.');
expectClientLookup(! $phone->matchesCandidate('66912345678', '4197403509', null), 'Internacional não deve casar com candidato legado.');
expectClientLookup(! $phone->matchesCandidate('5541997403509', '4133334444', null), 'Fixo não deve casar como móvel.');

echo 'TecninaClientByPhoneTest: ' . $assertions . ' assertions passed.' . PHP_EOL;
