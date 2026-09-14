<?php

define('BASEPATH', __DIR__);

if (! defined('CURLOPT_RETURNTRANSFER')) {
    define('CURLOPT_RETURNTRANSFER', 19913);
}
if (! defined('CURLOPT_CUSTOMREQUEST')) {
    define('CURLOPT_CUSTOMREQUEST', 10036);
}
if (! defined('CURLOPT_CONNECTTIMEOUT')) {
    define('CURLOPT_CONNECTTIMEOUT', 78);
}
if (! defined('CURLOPT_TIMEOUT')) {
    define('CURLOPT_TIMEOUT', 13);
}
if (! defined('CURLOPT_HTTPHEADER')) {
    define('CURLOPT_HTTPHEADER', 10023);
}
if (! defined('CURLOPT_SSL_VERIFYPEER')) {
    define('CURLOPT_SSL_VERIFYPEER', 64);
}
if (! defined('CURLOPT_SSL_VERIFYHOST')) {
    define('CURLOPT_SSL_VERIFYHOST', 81);
}
if (! defined('CURLOPT_POSTFIELDS')) {
    define('CURLOPT_POSTFIELDS', 10015);
}

$assertions = 0;
$root = dirname(__DIR__);

function expectGatewayTimeout($condition, $message)
{
    global $assertions;
    ++$assertions;
    if (! $condition) {
        fwrite(STDERR, 'TecninaBotGatewayTimeoutTest falhou: ' . $message . PHP_EOL);
        exit(1);
    }
}

require_once $root . '/application/libraries/Tecnina_bot_gateway.php';
$controllerCode = file_get_contents($root . '/application/controllers/Tecnina_whatsapp.php');
$scriptCode = file_get_contents($root . '/assets/tecnina/js/bot-lab.js');
$gatewayCode = file_get_contents($root . '/application/libraries/Tecnina_bot_gateway.php');

expectGatewayTimeout($controllerCode !== false && $scriptCode !== false && $gatewayCode !== false, 'Arquivos de teste ausentes.');

$gateway = new Tecnina_bot_gateway();

// Assertion A: default generic timeout = 8
expectGatewayTimeout(Tecnina_bot_gateway::DEFAULT_TIMEOUT_SECONDS === 8, 'Constante DEFAULT_TIMEOUT_SECONDS deve ser 8.');
expectGatewayTimeout(Tecnina_bot_gateway::DEFAULT_CONNECT_TIMEOUT_SECONDS === 3, 'Constante DEFAULT_CONNECT_TIMEOUT_SECONDS deve ser 3.');
expectGatewayTimeout($gateway->resolveTimeout() === 8, 'resolveTimeout() sem argumentos deve retornar 8.');
expectGatewayTimeout($gateway->resolveTimeout(null) === 8, 'resolveTimeout(null) deve retornar 8.');
$defaultOpts = $gateway->buildCurlOptions('GET', ['Authorization: Bearer 12345678901234567890123456789012'], null, null);
expectGatewayTimeout($defaultOpts[CURLOPT_TIMEOUT] === 8, 'cURL options padrão devem usar timeout 8.');
expectGatewayTimeout($defaultOpts[CURLOPT_CONNECTTIMEOUT] === 3, 'cURL options padrão devem usar connect timeout 3.');

// Assertion B: scenario default = 45
expectGatewayTimeout(Tecnina_bot_gateway::DEFAULT_SCENARIO_TIMEOUT_SECONDS === 45, 'Constante DEFAULT_SCENARIO_TIMEOUT_SECONDS deve ser 45.');
unset($_ENV['TECNINA_BOT_SCENARIO_TIMEOUT_SECONDS']);
putenv('TECNINA_BOT_SCENARIO_TIMEOUT_SECONDS');
expectGatewayTimeout($gateway->scenarioTimeoutSeconds() === 45, 'scenarioTimeoutSeconds() sem env deve retornar 45.');
$scenarioOpts = $gateway->buildCurlOptions('POST', [], null, $gateway->scenarioTimeoutSeconds());
expectGatewayTimeout($scenarioOpts[CURLOPT_TIMEOUT] === 45, 'cURL options de cenário padrão devem usar timeout 45.');

// Assertion C: env value 30 -> 30
$_ENV['TECNINA_BOT_SCENARIO_TIMEOUT_SECONDS'] = '30';
putenv('TECNINA_BOT_SCENARIO_TIMEOUT_SECONDS=30');
expectGatewayTimeout($gateway->scenarioTimeoutSeconds() === 30, 'scenarioTimeoutSeconds() com env 30 deve retornar 30.');
expectGatewayTimeout($gateway->resolveTimeout($gateway->scenarioTimeoutSeconds()) === 30, 'resolveTimeout(30) deve retornar 30.');

// Assertion D: env value below minimum -> 15
expectGatewayTimeout(Tecnina_bot_gateway::MIN_SCENARIO_TIMEOUT_SECONDS === 15, 'Constante MIN_SCENARIO_TIMEOUT_SECONDS deve ser 15.');
foreach (['14', '10', '0', '-5', '-99'] as $lowVal) {
    $_ENV['TECNINA_BOT_SCENARIO_TIMEOUT_SECONDS'] = $lowVal;
    putenv('TECNINA_BOT_SCENARIO_TIMEOUT_SECONDS=' . $lowVal);
    expectGatewayTimeout($gateway->scenarioTimeoutSeconds() === 15, 'scenarioTimeoutSeconds() com env ' . $lowVal . ' deve retornar 15.');
}

// Assertion E: env value above maximum -> 90
expectGatewayTimeout(Tecnina_bot_gateway::MAX_SCENARIO_TIMEOUT_SECONDS === 90, 'Constante MAX_SCENARIO_TIMEOUT_SECONDS deve ser 90.');
foreach (['91', '100', '300', '9999'] as $highVal) {
    $_ENV['TECNINA_BOT_SCENARIO_TIMEOUT_SECONDS'] = $highVal;
    putenv('TECNINA_BOT_SCENARIO_TIMEOUT_SECONDS=' . $highVal);
    expectGatewayTimeout($gateway->scenarioTimeoutSeconds() === 90, 'scenarioTimeoutSeconds() com env ' . $highVal . ' deve retornar 90.');
}

// Assertion F: invalid env -> 45
foreach (['invalid', 'abc', '45.5', '15.0', '', '   ', 'true', 'false'] as $invalidVal) {
    $_ENV['TECNINA_BOT_SCENARIO_TIMEOUT_SECONDS'] = $invalidVal;
    putenv('TECNINA_BOT_SCENARIO_TIMEOUT_SECONDS=' . $invalidVal);
    expectGatewayTimeout($gateway->scenarioTimeoutSeconds() === 45, 'scenarioTimeoutSeconds() com env inválido "' . $invalidVal . '" deve retornar 45.');
}

// Reset env
unset($_ENV['TECNINA_BOT_SCENARIO_TIMEOUT_SECONDS']);
putenv('TECNINA_BOT_SCENARIO_TIMEOUT_SECONDS');

// Assertion G: scenario execution controller uses scenario timeout
preg_match('/public function simulador_executar_cenarios\(\)(.*?)\n    \}/s', $controllerCode, $execMatches);
expectGatewayTimeout(! empty($execMatches), 'Método simulador_executar_cenarios não encontrado no controller.');
$execMethodBody = $execMatches[1];
expectGatewayTimeout(
    strpos($execMethodBody, '$this->tecnina_bot_gateway->scenarioTimeoutSeconds()') !== false,
    'simulador_executar_cenarios deve passar scenarioTimeoutSeconds() para o gateway.'
);
expectGatewayTimeout(
    strpos($execMethodBody, "'/admin/simulator/scenarios/run'") !== false,
    'simulador_executar_cenarios deve chamar /admin/simulator/scenarios/run.'
);

// Assertion H: catalog controller does NOT use scenario timeout
preg_match('/public function simulador_cenarios\(\)(.*?)\n    \}/s', $controllerCode, $catMatches);
expectGatewayTimeout(! empty($catMatches), 'Método simulador_cenarios não encontrado no controller.');
$catMethodBody = $catMatches[1];
expectGatewayTimeout(
    strpos($catMethodBody, 'scenarioTimeoutSeconds') === false,
    'simulador_cenarios NÃO deve chamar scenarioTimeoutSeconds().'
);
expectGatewayTimeout(
    strpos($catMethodBody, "\$this->tecnina_bot_gateway->request('GET', '/admin/simulator/scenarios')") !== false,
    'simulador_cenarios deve chamar gateway com timeout padrão (sem 4º argumento).'
);

// Assertion I: browser request payload cannot set gateway timeout
expectGatewayTimeout(
    strpos($execMethodBody, "unset(\$payload['timeout']") !== false &&
    strpos($execMethodBody, "\$payload['timeout_seconds']") !== false &&
    strpos($execMethodBody, "\$payload['scenario_timeout']") !== false,
    'simulador_executar_cenarios deve sanitizar chaves de timeout do payload recebido do browser.'
);
expectGatewayTimeout(
    strpos($scriptCode, 'TECNINA_BOT_SCENARIO_TIMEOUT_SECONDS') === false,
    'Browser JS não deve conter referência a TECNINA_BOT_SCENARIO_TIMEOUT_SECONDS.'
);
expectGatewayTimeout(
    strpos($scriptCode, 'scenario_timeout') === false,
    'Browser JS não deve conter referência a scenario_timeout.'
);

// Assertion J: existing Authorization/Bearer behavior unchanged
expectGatewayTimeout(
    strpos($gatewayCode, "'Authorization: Bearer ' . \$this->token") !== false,
    'Gateway deve manter cabeçalho Authorization: Bearer intacto.'
);
expectGatewayTimeout(
    strpos($gatewayCode, "CURLOPT_CONNECTTIMEOUT => self::DEFAULT_CONNECT_TIMEOUT_SECONDS") !== false ||
    strpos($gatewayCode, "CURLOPT_CONNECTTIMEOUT => 3") !== false,
    'Gateway deve manter CURLOPT_CONNECTTIMEOUT em 3 segundos.'
);
expectGatewayTimeout(
    strpos($gatewayCode, "CURLOPT_SSL_VERIFYPEER => true") !== false &&
    strpos($gatewayCode, "CURLOPT_SSL_VERIFYHOST => 2") !== false,
    'Gateway deve manter verificação SSL habilitada.'
);
expectGatewayTimeout(
    strpos($gatewayCode, "strlen(\$this->token) >= 32") !== false,
    'Gateway deve manter verificação de comprimento do token >= 32.'
);

echo 'TecninaBotGatewayTimeoutTest: ' . $assertions . ' assertions passed.' . PHP_EOL;
