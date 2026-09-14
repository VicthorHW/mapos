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
    (strpos($execMethodBody, 'unset($payload->timeout, $payload->timeout_seconds, $payload->scenario_timeout)') !== false ||
     (strpos($execMethodBody, "unset(\$payload['timeout']") !== false &&
      strpos($execMethodBody, "\$payload['timeout_seconds']") !== false &&
      strpos($execMethodBody, "\$payload['scenario_timeout']") !== false)),
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

// =========================================================================
// Section 12: simulador_executar_cenarios Payload Contract & Behavioral Tests
// =========================================================================

if (! class_exists('CI_Controller')) {
    class CI_Controller
    {
        public $load;
        public function __construct()
        {
            $this->load = new class {
                public function library($name) {}
                public function model($name) {}
            };
        }
    }
}

if (! class_exists('MY_Controller')) {
    class MY_Controller extends CI_Controller
    {
        public $data = [];
        public $session;
        public $permission;
        public $security;
        public $output;
        public $input;
        public $db;
        public function __construct()
        {
            parent::__construct();
        }
    }
}

if (! class_exists('GatewayTestMockInput')) {
    class GatewayTestMockInput
    {
        public $method = 'POST';
        public $postData = [];
        public function method($upper = false)
        {
            return $upper ? strtoupper($this->method) : strtolower($this->method);
        }
        public function post($key = null, $xss_clean = null)
        {
            if ($key === null) return $this->postData;
            return $this->postData[$key] ?? null;
        }
    }
}

if (! class_exists('GatewayTestMockOutput')) {
    class GatewayTestMockOutput
    {
        public $statusCode = 200;
        public $contentType = 'application/json';
        public $outputBody = '';
        public function set_status_header($status)
        {
            $this->statusCode = (int) $status;
            return $this;
        }
        public function set_content_type($type)
        {
            $this->contentType = $type;
            return $this;
        }
        public function set_output($body)
        {
            $this->outputBody = $body;
            return $this;
        }
    }
}

if (! class_exists('GatewayTestSpyGateway')) {
    class GatewayTestSpyGateway extends Tecnina_bot_gateway
    {
        public $calls = 0;
        public $lastMethod;
        public $lastPath;
        public $lastPayload;
        public $lastTimeout;
        public $lastSerializedBody;

        public function request($method, $path, $payload = null, $timeoutSeconds = null)
        {
            $this->calls++;
            $this->lastMethod = $method;
            $this->lastPath = $path;
            $this->lastPayload = $payload;
            $this->lastTimeout = $timeoutSeconds;
            $this->lastSerializedBody = ($payload !== null) ? json_encode($payload) : null;
            return ['ok' => true, 'status' => 200, 'data' => ['total' => 23, 'passed' => 23, 'failed' => 0, 'errors' => 0]];
        }
    }
}

require_once $root . '/application/controllers/Tecnina_whatsapp.php';

$behController = new Tecnina_whatsapp();
$behInput = new GatewayTestMockInput();
$behOutput = new GatewayTestMockOutput();
$behSpy = new GatewayTestSpyGateway();

$behController->input = $behInput;
$behController->output = $behOutput;
$behController->tecnina_bot_gateway = $behSpy;
$behController->permission = new class {
    public function checkPermission($permissao, $recurso) { return true; }
};
$behController->session = new class {
    public function userdata($key) { return 1; }
};
$behController->security = new class {
    public function get_csrf_hash() { return 'test-csrf-hash'; }
};

// Case A: raw "{}" -> gateway-bound JSON object "{}"
$behSpy->calls = 0;
$behInput->postData = ['payload' => '{}'];
$behController->simulador_executar_cenarios();
expectGatewayTimeout($behSpy->calls === 1, 'Case A: Gateway deve ser chamado.');
expectGatewayTimeout($behSpy->lastMethod === 'POST', 'Case A: Método deve ser POST.');
expectGatewayTimeout($behSpy->lastPath === '/admin/simulator/scenarios/run', 'Case A: Endpoint deve ser /admin/simulator/scenarios/run.');
expectGatewayTimeout(is_object($behSpy->lastPayload), 'Case A: Payload recebido pelo gateway deve ser objeto.');
expectGatewayTimeout($behSpy->lastSerializedBody === '{}', 'Case A: Serialização para Bot deve ser "{}" e não "[]".');

// Case B: absent/empty payload -> gateway-bound JSON object "{}"
$behSpy->calls = 0;
$behInput->postData = [];
$behController->simulador_executar_cenarios();
expectGatewayTimeout($behSpy->calls === 1, 'Case B (ausente): Gateway deve ser chamado.');
expectGatewayTimeout(is_object($behSpy->lastPayload), 'Case B (ausente): Payload deve ser objeto.');
expectGatewayTimeout($behSpy->lastSerializedBody === '{}', 'Case B (ausente): Serialização deve ser "{}".');

$behSpy->calls = 0;
$behInput->postData = ['payload' => ''];
$behController->simulador_executar_cenarios();
expectGatewayTimeout($behSpy->calls === 1, 'Case B (vazio): Gateway deve ser chamado.');
expectGatewayTimeout(is_object($behSpy->lastPayload), 'Case B (vazio): Payload deve ser objeto.');
expectGatewayTimeout($behSpy->lastSerializedBody === '{}', 'Case B (vazio): Serialização deve ser "{}".');

// Case C: selected: {"scenario_ids":["menu-navigation"]} -> object preserved
$behSpy->calls = 0;
$behInput->postData = ['payload' => '{"scenario_ids":["menu-navigation"]}'];
$behController->simulador_executar_cenarios();
expectGatewayTimeout($behSpy->calls === 1, 'Case C: Gateway deve ser chamado.');
expectGatewayTimeout(is_object($behSpy->lastPayload), 'Case C: Payload deve ser objeto.');
expectGatewayTimeout($behSpy->lastSerializedBody === '{"scenario_ids":["menu-navigation"]}', 'Case C: Payload selecionado preservado.');

// Case D: tags: {"tags":["navigation"]} -> object preserved
$behSpy->calls = 0;
$behInput->postData = ['payload' => '{"tags":["navigation"]}'];
$behController->simulador_executar_cenarios();
expectGatewayTimeout($behSpy->calls === 1, 'Case D: Gateway deve ser chamado.');
expectGatewayTimeout(is_object($behSpy->lastPayload), 'Case D: Payload deve ser objeto.');
expectGatewayTimeout($behSpy->lastSerializedBody === '{"tags":["navigation"]}', 'Case D: Payload tags preservado.');

// Case E: case: {"scenario_ids":["menu-navigation"],"case_id":"entry-menu"} -> object preserved
$behSpy->calls = 0;
$behInput->postData = ['payload' => '{"scenario_ids":["menu-navigation"],"case_id":"entry-menu"}'];
$behController->simulador_executar_cenarios();
expectGatewayTimeout($behSpy->calls === 1, 'Case E: Gateway deve ser chamado.');
expectGatewayTimeout(is_object($behSpy->lastPayload), 'Case E: Payload deve ser objeto.');
expectGatewayTimeout($behSpy->lastSerializedBody === '{"scenario_ids":["menu-navigation"],"case_id":"entry-menu"}', 'Case E: Payload case_id preservado.');

// Case F: raw "[]" -> invalid_scenario_payload / 422
$behSpy->calls = 0;
$behInput->postData = ['payload' => '[]'];
$behController->simulador_executar_cenarios();
expectGatewayTimeout($behSpy->calls === 0, 'Case F: Gateway NÃO deve ser chamado para array JSON top-level.');
expectGatewayTimeout($behOutput->statusCode === 422, 'Case F: Status code deve ser 422.');
$decodedF = json_decode($behOutput->outputBody, true);
expectGatewayTimeout(($decodedF['ok'] ?? null) === false && ($decodedF['reason'] ?? null) === 'invalid_scenario_payload', 'Case F: reason deve ser invalid_scenario_payload.');

// Case G: scalar JSON: "abc" -> invalid_scenario_payload / 422
$behSpy->calls = 0;
$behInput->postData = ['payload' => '"abc"'];
$behController->simulador_executar_cenarios();
expectGatewayTimeout($behSpy->calls === 0, 'Case G: Gateway NÃO deve ser chamado para scalar.');
expectGatewayTimeout($behOutput->statusCode === 422, 'Case G: Status code deve ser 422.');
$decodedG = json_decode($behOutput->outputBody, true);
expectGatewayTimeout(($decodedG['ok'] ?? null) === false && ($decodedG['reason'] ?? null) === 'invalid_scenario_payload', 'Case G: reason deve ser invalid_scenario_payload.');

// Case H: null JSON: null -> invalid_scenario_payload / 422
$behSpy->calls = 0;
$behInput->postData = ['payload' => 'null'];
$behController->simulador_executar_cenarios();
expectGatewayTimeout($behSpy->calls === 0, 'Case H: Gateway NÃO deve ser chamado para null.');
expectGatewayTimeout($behOutput->statusCode === 422, 'Case H: Status code deve ser 422.');
$decodedH = json_decode($behOutput->outputBody, true);
expectGatewayTimeout(($decodedH['ok'] ?? null) === false && ($decodedH['reason'] ?? null) === 'invalid_scenario_payload', 'Case H: reason deve ser invalid_scenario_payload.');

// Case I: malformed JSON -> invalid_scenario_payload / 422
$behSpy->calls = 0;
$behInput->postData = ['payload' => '{"broken": json'];
$behController->simulador_executar_cenarios();
expectGatewayTimeout($behSpy->calls === 0, 'Case I: Gateway NÃO deve ser chamado para JSON malformado.');
expectGatewayTimeout($behOutput->statusCode === 422, 'Case I: Status code deve ser 422.');
$decodedI = json_decode($behOutput->outputBody, true);
expectGatewayTimeout(($decodedI['ok'] ?? null) === false && ($decodedI['reason'] ?? null) === 'invalid_scenario_payload', 'Case I: reason deve ser invalid_scenario_payload.');

// Case J: timeout keys are removed and never reach Bot
$behSpy->calls = 0;
$behInput->postData = ['payload' => '{"scenario_ids":["menu-navigation"],"timeout":999,"timeout_seconds":999,"scenario_timeout":999}'];
$behController->simulador_executar_cenarios();
expectGatewayTimeout($behSpy->calls === 1, 'Case J: Gateway deve ser chamado.');
expectGatewayTimeout(! isset($behSpy->lastPayload->timeout), 'Case J: timeout deve ser removido.');
expectGatewayTimeout(! isset($behSpy->lastPayload->timeout_seconds), 'Case J: timeout_seconds deve ser removido.');
expectGatewayTimeout(! isset($behSpy->lastPayload->scenario_timeout), 'Case J: scenario_timeout deve ser removido.');
expectGatewayTimeout($behSpy->lastSerializedBody === '{"scenario_ids":["menu-navigation"]}', 'Case J: Propriedades de timeout removidas antes de alcançar o Bot.');

// Case K: normal scenario timeout still 45 seconds
expectGatewayTimeout($behSpy->lastTimeout === 45, 'Case K: timeout passado para gateway deve ser 45 segundos.');

echo 'TecninaBotGatewayTimeoutTest: ' . $assertions . ' assertions passed.' . PHP_EOL;
