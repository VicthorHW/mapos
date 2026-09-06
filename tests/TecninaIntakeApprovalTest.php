<?php

$assertions = 0;
$root = dirname(__DIR__);

function expectIntakeApproval($condition, $message)
{
    global $assertions;
    ++$assertions;
    if (! $condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$controller = file_get_contents($root . '/application/controllers/api/bot/Intake_approval.php');
$model = file_get_contents($root . '/application/models/Tecnina_intake_approval_model.php');
$setup = file_get_contents($root . '/application/controllers/Tecnina_integration_setup.php');
$routes = file_get_contents($root . '/application/config/routes.php');
$installer = file_get_contents($root . '/tools/tecnina-integration/install.php');

expectIntakeApproval($controller !== false && $model !== false && $setup !== false, 'Arquivos da aprovação ausentes.');
expectIntakeApproval(strpos($routes, 'api/bot/intakes/(:any)/approve') !== false, 'Rota privada de aprovação ausente.');
expectIntakeApproval(strpos($controller, 'authorizeRequest()') < strpos($controller, '$this->post()'), 'Autorização deve ocorrer antes da leitura do payload.');
expectIntakeApproval(strpos($controller, "['operator_id', 'client_action', 'client_id', 'force_create_new', 'client', 'os']") !== false, 'Contrato superior não usa whitelist explícita.');
expectIntakeApproval(strpos($controller, "['name', 'phone', 'city']") !== false, 'Contrato de cliente não usa whitelist explícita.');
expectIntakeApproval(strpos($controller, "['device_type', 'brand', 'model', 'problem_description', 'service_mode', 'city', 'notes']") !== false, 'Contrato de OS não usa whitelist explícita.');
expectIntakeApproval(stripos($controller, 'credencial') === false, 'Endpoint não pode receber credencial do aparelho.');
expectIntakeApproval(stripos($controller, 'password') === false && stripos($controller, 'senha') === false, 'Endpoint não pode manipular senha do cliente.');
expectIntakeApproval(strpos($model, "'credencial_tipo' => 'nao_informada'") !== false, 'OS de intake deve registrar credencial não informada.');
expectIntakeApproval(strpos($model, "'credencial_dados' => null") !== false, 'OS de intake não pode inventar dados de credencial.');
expectIntakeApproval(strpos($model, 'trans_begin()') !== false && strpos($model, 'trans_commit()') !== false && strpos($model, 'trans_rollback()') !== false, 'Cliente e OS devem ser criados em uma transação.');
expectIntakeApproval(strpos($model, 'INSERT IGNORE') !== false && strpos($model, 'FOR UPDATE') !== false, 'Aprovação deve coordenar concorrência no banco.');
expectIntakeApproval(strpos($model, "dbprefix('tecnina_intake_approvals')") !== false, 'Tabela de idempotência deve respeitar DB_PREFIX.');
expectIntakeApproval(stripos($model, 'select *') === false, 'Aprovação não pode usar SELECT *.');
expectIntakeApproval(strpos($setup, "physicalName('tecnina_intake_approvals')") !== false, 'Instalador não resolve a tabela física de aprovação.');
expectIntakeApproval(strpos($setup, 'UNIQUE KEY `uq_tecnina_intake_approval` (`intake_id`)') !== false, 'Idempotência precisa de índice único por intake.');
expectIntakeApproval(strpos($installer, 'Tecnina_intake_approval_model.php') !== false && strpos($installer, 'Intake_approval.php') !== false, 'Instalador não verifica os arquivos da aprovação.');

echo 'TecninaIntakeApprovalTest: ' . $assertions . ' assertions passed.' . PHP_EOL;
