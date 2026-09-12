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
$routes = file_get_contents($root . '/application/config/routes.php');
$profileController = file_get_contents($root . '/application/controllers/api/bot/Client_profile.php');

expectClientLookup($library !== false, 'Biblioteca Tecnina_phone ausente.');
expectClientLookup($routes !== false, 'Arquivo routes.php ausente.');
expectClientLookup($profileController !== false, 'Controller Client_profile ausente.');
expectClientLookup(strpos($routes, "api/bot/client/by-phone") !== false, 'Rota privada ausente.');

// Static contract check for Client_profile profileResponse (Order 09 Section 6)
expectClientLookup(strpos($profileController, "normalizeIdentity(\$row['celular'])") === false, 'profileResponse não pode usar normalizeIdentity em celular.');
expectClientLookup(strpos($profileController, "normalizeIdentity(\$row['telefone'])") === false, 'profileResponse não pode usar normalizeIdentity em telefone.');
expectClientLookup(strpos($profileController, "canonicalIdentityFromStored(\$row['celular'])") !== false, 'profileResponse deve usar canonicalIdentityFromStored em celular.');
expectClientLookup(strpos($profileController, "canonicalIdentityFromStored(\$row['telefone'])") !== false, 'profileResponse deve usar canonicalIdentityFromStored em telefone.');

// Repository-local checks when candidate files exist
$controllerPath = $root . '/application/controllers/api/bot/Client_by_phone.php';
if (file_exists($controllerPath)) {
    $controller = file_get_contents($controllerPath);
    foreach (['password', 'senha', 'documento', 'credencial', 'nome'] as $forbidden) {
        expectClientLookup(stripos($controller, $forbidden) === false, 'Controller referencia dado proibido: ' . $forbidden);
    }
}
$modelPath = $root . '/application/models/Tecnina_client_lookup_model.php';
if (file_exists($modelPath)) {
    $model = file_get_contents($modelPath);
    expectClientLookup(strpos($model, "select('idClientes AS client_id, celular, telefone')") !== false, 'Consulta deve usar whitelist explícita.');
    expectClientLookup(strpos($model, 'select(\'*\')') === false, 'Consulta não pode usar SELECT *.');
}

require_once $root . '/application/libraries/Tecnina_phone.php';
$phone = new Tecnina_phone();

// 1. Canonical normalization
expectClientLookup($phone->normalizeCanonicalIdentity('+55 41 99740-3509') === '5541997403509', 'Celular atual inválido.');
expectClientLookup($phone->normalizeIdentity('+55 41 99740-3509') === '5541997403509', 'Celular atual inválido via normalizeIdentity.');
expectClientLookup($phone->normalizeCanonicalIdentity('4133334444') === '4133334444', 'Canônico 4133334444 não deve ser rejeitado por heurística.');
expectClientLookup($phone->normalizeIdentity('4133334444') === '4133334444', 'Canônico 4133334444 via normalizeIdentity.');
expectClientLookup($phone->normalizeCanonicalIdentity('554133334444') === '554133334444', 'Canônico 554133334444 deve ser preservado.');
expectClientLookup($phone->normalizeCanonicalIdentity('123') === null, 'Telefone curto não deve casar.');
expectClientLookup($phone->normalizeIdentity('123') === null, 'Telefone curto não deve casar via normalizeIdentity.');
expectClientLookup($phone->normalizeCanonicalIdentity('66912345678') === '66912345678', 'Internacional não deve virar Brasil.');
expectClientLookup($phone->normalizeCanonicalIdentity('351911872552') === '351911872552', 'Portugal deve ser preservado.');

// 2. Storage representation conversion
expectClientLookup($phone->storageValueFromCanonical('5541997403509') === '5541997403509', 'Armazenamento canônico BR incorreto.');
expectClientLookup($phone->storageValueFromCanonical('351911872552') === '+351911872552', 'Armazenamento internacional Portugal incorreto.');
expectClientLookup($phone->storageValueFromCanonical('66912345678') === '+66912345678', 'Armazenamento internacional Tailândia incorreto.');
expectClientLookup($phone->storageValueFromCanonical('14155552671') === '+14155552671', 'Armazenamento internacional EUA incorreto.');

// 3. Candidate provenance and collision tests (Order 08 Section 7)
expectClientLookup($phone->matchesCandidate('66912345678', '+66912345678', null), 'Consulta internacional em armazenamento explícito + deve casar.');
expectClientLookup(! $phone->matchesCandidate('5566912345678', '+66912345678', null), 'Consulta BR não deve casar com internacional explícito + (prevenção de colisão).');
expectClientLookup($phone->matchesCandidate('5566912345678', '66912345678', null), 'Consulta BR deve casar com legado local não marcado.');
expectClientLookup(! $phone->matchesCandidate('66912345678', '66912345678', null), 'Consulta internacional não deve casar com legado local não marcado.');
expectClientLookup($phone->matchesCandidate('351911872552', '+351911872552', null), 'Consulta Portugal em +351 deve casar.');
expectClientLookup($phone->matchesCandidate('14155552671', '+14155552671', null), 'Consulta EUA em +1415 deve casar.');
expectClientLookup($phone->matchesCandidate('5541997403509', '4197403509', null), 'Consulta BR em legado local 4197403509 deve casar.');
expectClientLookup(! $phone->matchesCandidate('5541997403509', '4133334444', null), 'Consulta celular não deve casar com fixo local.');

// 4. normalizeWhatsApp with provenance (Order 08 Section 14)
expectClientLookup($phone->normalizeWhatsApp('+66912345678') === '66912345678', 'WhatsApp internacional com + inválido.');
expectClientLookup($phone->normalizeWhatsApp('5541997403509') === '5541997403509', 'WhatsApp canônico BR inválido.');
expectClientLookup($phone->normalizeWhatsApp('4197403509') === '5541997403509', 'WhatsApp legado local 10 dígitos inválido.');
expectClientLookup($phone->normalizeWhatsApp('(41) 99740-3509') === '5541997403509', 'WhatsApp formatado local inválido.');
expectClientLookup($phone->normalizeWhatsApp('4133334444') === null, 'WhatsApp fixo deve ser rejeitado.');
expectClientLookup($phone->normalizeWhatsApp('123') === null, 'WhatsApp curto deve ser rejeitado.');

// 5. Stored representation readback (Order 09 Section 2 & 5)
expectClientLookup($phone->canonicalIdentityFromStored('+66912345678') === '66912345678', 'Leitura armazenada internacional Tailândia com + inválida.');
expectClientLookup($phone->canonicalIdentityFromStored('+351911872552') === '351911872552', 'Leitura armazenada internacional Portugal com + inválida.');
expectClientLookup($phone->canonicalIdentityFromStored('5541997403509') === '5541997403509', 'Leitura armazenada canônica BR inválida.');
expectClientLookup($phone->canonicalIdentityFromStored('4197403509') === '5541997403509', 'Leitura armazenada legada local 10 dígitos inválida.');
expectClientLookup($phone->canonicalIdentityFromStored('66912345678') === '5566912345678', 'Leitura armazenada legada local DDD 66 inválida.');
expectClientLookup($phone->canonicalIdentityFromStored('') === null, 'Leitura armazenada vazia deve retornar null.');
expectClientLookup($phone->canonicalIdentityFromStored(null) === null, 'Leitura armazenada nula deve retornar null.');

// Prova de bloqueio de distinção de proveniência (Order 09 Section 5)
expectClientLookup($phone->canonicalIdentityFromStored('+66912345678') !== '5566912345678', 'Armazenamento +66 não pode retornar identidade brasileira.');
expectClientLookup($phone->canonicalIdentityFromStored('66912345678') !== '66912345678', 'Armazenamento 66 sem + não pode retornar identidade internacional.');

echo 'TecninaClientByPhoneTest: ' . $assertions . ' assertions passed.' . PHP_EOL;
