<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/application/controllers/api/bot/Os_status.php');
$model = file_get_contents($root . '/application/models/Tecnina_os_status_model.php');
$routes = file_get_contents($root . '/application/config/routes.php');

function expectOsStatusContract($condition, $message)
{
    static $assertions = 0;
    $assertions++;
    if (! $condition) {
        fwrite(STDERR, "TecninaOsStatusContractTest falhou: {$message}\n");
        exit(1);
    }
    if ($assertions === 10) {
        fwrite(STDOUT, "TecninaOsStatusContractTest: {$assertions} assertions passed.\n");
    }
}

expectOsStatusContract(strpos($routes, "api/bot/os/(:num)/status") !== false, 'Rota privada de status não cadastrada.');
expectOsStatusContract(strpos($controller, "Tecnina_bot_auth") !== false, 'Endpoint não usa autenticação interna.');
expectOsStatusContract(strpos($controller, "authorizeRequest") !== false, 'Endpoint não bloqueia chamada sem autorização.');
expectOsStatusContract(strpos($controller, "FILTER_VALIDATE_INT") !== false, 'OS não é validada como inteiro positivo.');
expectOsStatusContract(strpos($controller, "'mapos_status'") !== false, 'Status básico não aparece na whitelist.');
expectOsStatusContract(strpos($controller, "'client_id'") !== false, 'Vínculo de cliente não aparece no contrato.');
expectOsStatusContract(stripos($controller, 'password') === false, 'Contrato não pode mencionar senha.');
expectOsStatusContract(strpos($model, "select('os.idOs AS os_id, os.clientes_id AS client_id, os.status AS mapos_status')") !== false, 'Model deve usar whitelist explícita.');
expectOsStatusContract(strpos($model, "select('*')") === false, 'Model não pode usar SELECT *.');
expectOsStatusContract(strpos($model, "->where('os.idOs', (int) \$osId)") !== false, 'Consulta deve ser exata por OS.');
