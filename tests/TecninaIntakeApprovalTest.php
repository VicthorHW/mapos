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
$openOsModel = file_get_contents($root . '/application/models/Tecnina_client_open_os_model.php');
$setup = file_get_contents($root . '/application/controllers/Tecnina_integration_setup.php');
$routes = file_get_contents($root . '/application/config/routes.php');
$installer = file_get_contents($root . '/tools/tecnina-integration/install.php');
$customerSurfaces = implode("\n", [
    file_get_contents($root . '/application/controllers/Mine.php'),
    file_get_contents($root . '/application/models/Conecte_model.php'),
    file_get_contents($root . '/application/views/conecte/visualizar_os.php'),
    file_get_contents($root . '/application/views/conecte/imprimirOs.php'),
    file_get_contents($root . '/application/views/os/emails/os.php'),
]);

expectIntakeApproval($controller !== false && $model !== false && $setup !== false, 'Arquivos da aprovação ausentes.');
expectIntakeApproval($openOsModel !== false && strpos($openOsModel, "from('os AS bot_os')") !== false, 'Consulta de OS não usa alias compatível com DB_PREFIX.');
expectIntakeApproval(strpos($routes, 'api/bot/intakes/(:any)/approve') !== false, 'Rota privada de aprovação ausente.');
expectIntakeApproval(strpos($routes, 'api/bot/client/(:num)/open-os') !== false, 'Rota privada de consulta das OS do cliente ausente.');
expectIntakeApproval(strpos($controller, 'authorizeRequest()') < strpos($controller, '$this->post()'), 'Autorização deve ocorrer antes da leitura do payload.');
expectIntakeApproval(strpos($controller, "['operator_id', 'client_action', 'client_id', 'force_create_new', 'intake_created_at', 'client', 'os']") !== false, 'Contrato superior não usa whitelist explícita.');
expectIntakeApproval(strpos($controller, "['name', 'phone', 'city']") !== false, 'Contrato de cliente não usa whitelist explícita.');
expectIntakeApproval(strpos($controller, "['device_type', 'brand', 'model', 'problem_description', 'service_mode', 'city', 'notes', 'pickup_address', 'credential']") !== false, 'Contrato de OS não usa whitelist explícita.');
expectIntakeApproval(strpos($controller, "['DETERMINED', 'MANUAL_QUOTE', 'CONFIRMED']") !== false, 'Estado da taxa de coleta não usa whitelist explícita.');
expectIntakeApproval(strpos($controller, "'status', 'type', 'grid', 'text', 'sequence'") !== false, 'Contrato da credencial não usa whitelist explícita.');
expectIntakeApproval(strpos($controller, 'prepareForStorage') !== false, 'Credencial do intake não passa pela validação e criptografia do MapOS.');
expectIntakeApproval(stripos($controller, 'client_password') === false, 'Endpoint não pode manipular senha do cliente.');
expectIntakeApproval(strpos($model, "'credencial_tipo' => \$os['credential']['credencial_tipo']") !== false, 'OS de intake não persiste o tipo validado da credencial.');
expectIntakeApproval(strpos($model, "'credencial_dados' => \$os['credential']['credencial_dados']") !== false, 'OS de intake não persiste a credencial criptografada pelo MapOS.');
expectIntakeApproval(strpos($model, "'rua' => null") !== false && strpos($model, "'cep' => null") !== false, 'Endereço operacional de coleta não pode virar endereço cadastral silenciosamente.');
expectIntakeApproval(strpos($model, "'observacoes' => null") !== false, 'Metadados internos não podem ir para Observações visíveis ao cliente.');
expectIntakeApproval(strpos($model, "insert('anotacoes_os'") !== false, 'Metadados do intake não são registrados em Anotações internas.');
expectIntakeApproval(strpos($model, "'[Pré-atendimento WhatsApp] '") !== false, 'Anotação interna não identifica sua origem.');
expectIntakeApproval(strpos($model, '255 - mb_strlen($prefix)') !== false, 'Anotações longas precisam ser preservadas em blocos compatíveis com o schema.');
expectIntakeApproval(strpos($customerSurfaces, 'anotacoes_os') === false && strpos($customerSurfaces, 'getAnotacoes') === false, 'Anotações internas estão sendo consultadas por uma superfície destinada ao cliente.');
expectIntakeApproval(strpos($model, 'trans_begin()') !== false && strpos($model, 'trans_commit()') !== false && strpos($model, 'trans_rollback()') !== false, 'Cliente e OS devem ser criados em uma transação.');
expectIntakeApproval(strpos($model, 'INSERT IGNORE') !== false && strpos($model, 'FOR UPDATE') !== false, 'Aprovação deve coordenar concorrência no banco.');
expectIntakeApproval(strpos($model, "dbprefix('tecnina_intake_approvals')") !== false, 'Tabela de idempotência deve respeitar DB_PREFIX.');
expectIntakeApproval(stripos($model, 'select *') === false, 'Aprovação não pode usar SELECT *.');
expectIntakeApproval(strpos($setup, "physicalName('tecnina_intake_approvals')") !== false, 'Instalador não resolve a tabela física de aprovação.');
expectIntakeApproval(strpos($setup, 'UNIQUE KEY `uq_tecnina_intake_approval` (`intake_id`)') !== false, 'Idempotência precisa de índice único por intake.');
expectIntakeApproval(strpos($installer, 'Tecnina_intake_approval_model.php') !== false && strpos($installer, 'Intake_approval.php') !== false, 'Instalador não verifica os arquivos da aprovação.');
expectIntakeApproval(strpos($installer, 'Tecnina_client_open_os_model.php') !== false && strpos($installer, 'Client_open_os.php') !== false, 'Instalador não verifica os arquivos da consulta mínima de OS.');
expectIntakeApproval(strpos($installer, 'Tecnina_bot_client_model.php') !== false && strpos($installer, 'Client_profile.php') !== false, 'Instalador não verifica os contratos privados de cadastro do cliente.');

echo 'TecninaIntakeApprovalTest: ' . $assertions . ' assertions passed.' . PHP_EOL;
