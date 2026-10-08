<?php

define('BASEPATH', __DIR__);

$assertions = 0;
$root = dirname(__DIR__);

function expectIntake02($condition, $message)
{
    global $assertions;
    ++$assertions;
    if (! $condition) {
        fwrite(STDERR, "[FAIL] " . $message . PHP_EOL);
        exit(1);
    }
}

echo "=== BACKLOG-INTAKE-02 Test Suite ===" . PHP_EOL;

// 1. View: adicionarCliente.php
$viewPath = $root . '/application/views/clientes/adicionarCliente.php';
expectIntake02(file_exists($viewPath), 'adicionarCliente.php exists.');
$view = file_get_contents($viewPath);

expectIntake02(strpos($view, "\$intakeId = \$this->input->get('intake_id') ?: \$this->input->post('intake_id');") !== false, 'View reads intake_id from GET or POST.');
expectIntake02(strpos($view, 'name="intake_id"') !== false, 'View contains hidden input for intake_id.');
expectIntake02(strpos($view, "set_value('nomeCliente', \$this->input->get('nomeCliente'))") !== false, 'View pre-fills nomeCliente from GET.');
expectIntake02(strpos($view, "set_value('documento', \$this->input->get('documento'))") !== false, 'View pre-fills documento from GET.');
expectIntake02(strpos($view, "set_value('celular', \$this->input->get('celular'))") !== false, 'View pre-fills celular from GET.');
expectIntake02(strpos($view, "set_value('email', \$this->input->get('email'))") !== false, 'View pre-fills email from GET.');
expectIntake02(strpos($view, "set_value('cep', \$this->input->get('cep'))") !== false, 'View pre-fills cep from GET.');
expectIntake02(strpos($view, "set_value('rua', \$this->input->get('rua'))") !== false, 'View pre-fills rua from GET.');
expectIntake02(strpos($view, "set_value('numero', \$this->input->get('numero'))") !== false, 'View pre-fills numero from GET.');
expectIntake02(strpos($view, "set_value('bairro', \$this->input->get('bairro'))") !== false, 'View pre-fills bairro from GET.');
expectIntake02(strpos($view, "set_value('cidade', \$this->input->get('cidade'))") !== false, 'View pre-fills cidade from GET.');
expectIntake02(strpos($view, "set_value('estado', \$this->input->get('estado') ?: 'PR')") !== false, 'View handles estado default from GET.');
expectIntake02(strpos($view, "site_url('tecnina_whatsapp/pre_atendimentos?intake_id='") !== false, 'View provides contextual Voltar link to pre-atendimentos when intake_id is set.');

echo "[PASS] adicionarCliente.php GET pre-population and intake context verified." . PHP_EOL;

// 2. Controller: Clientes.php
$controllerPath = $root . '/application/controllers/Clientes.php';
expectIntake02(file_exists($controllerPath), 'Clientes.php controller exists.');
$controller = file_get_contents($controllerPath);

expectIntake02(strpos($controller, "\$this->input->post('intake_id')") !== false, 'Clientes::adicionar inspects intake_id from POST.');
expectIntake02(strpos($controller, "'tecnina_client_identity'") !== false, 'Clientes::adicionar maintains tecnina_client_identity.');
expectIntake02(strpos($controller, "'phone_state' => \$canonicalPhone ? 'VERIFIED' : 'NONE'") !== false, 'Clientes::adicionar marks phone as VERIFIED upon intake customer creation.');
expectIntake02(strpos($controller, "'possible_mapos_client_id' => (int) \$clienteId") !== false, 'Clientes::adicionar links client ID to intake via bot gateway.');
expectIntake02(strpos($controller, "redirect(site_url('tecnina_whatsapp/pre_atendimentos?intake_id='") !== false, 'Clientes::adicionar redirects back to pre_atendimentos panel.');

echo "[PASS] Clientes.php controller intake linkage and identity verified." . PHP_EOL;

// 3. View: pre_atendimentos.php
$panelViewPath = $root . '/application/views/tecnina_whatsapp/pre_atendimentos.php';
expectIntake02(file_exists($panelViewPath), 'pre_atendimentos.php exists.');
$panelView = file_get_contents($panelViewPath);

expectIntake02(strpos($panelView, 'data-client-add-base') !== false, 'pre_atendimentos.php config contains data-client-add-base.');
expectIntake02(strpos($panelView, 'data-client-edit-base') !== false, 'pre_atendimentos.php config contains data-client-edit-base.');

echo "[PASS] pre_atendimentos.php client action bases verified." . PHP_EOL;

// 4. JS: pre-attendance-panel.js
$panelJsPath = $root . '/assets/tecnina/js/pre-attendance-panel.js';
expectIntake02(file_exists($panelJsPath), 'pre-attendance-panel.js exists.');
$panelJs = file_get_contents($panelJsPath);

expectIntake02(strpos($panelJs, 'clientAddBase') !== false, 'pre-attendance-panel.js reads clientAddBase.');
expectIntake02(strpos($panelJs, 'clientEditBase') !== false, 'pre-attendance-panel.js reads clientEditBase.');
expectIntake02(strpos($panelJs, 'function buildClientAddUrl') !== false, 'pre-attendance-panel.js implements buildClientAddUrl.');
expectIntake02(strpos($panelJs, 'Cadastrar Cliente no MapOS') !== false, 'pre-attendance-panel.js renders Cadastrar Cliente no MapOS button.');
expectIntake02(strpos($panelJs, 'Ver/Editar Cadastro no MapOS') !== false, 'pre-attendance-panel.js renders Ver/Editar Cadastro no MapOS button.');
expectIntake02(strpos($panelJs, 'urlParams.get(\'intake_id\')') !== false, 'pre-attendance-panel.js auto-loads intake_id from URL query string on boot.');

echo "[PASS] pre-attendance-panel.js UI actions and URL routing verified." . PHP_EOL;

// 5. New Walk-In Intake & Gateway Detail Preserving
$routesPath = $root . '/application/config/routes.php';
expectIntake02(file_exists($routesPath), 'routes.php exists.');
$routes = file_get_contents($routesPath);
expectIntake02(strpos($routes, "['tecnina/pre-atendimentos/novo'] = 'tecnina_whatsapp/novo_pre_atendimento'") !== false, 'Route tecnina/pre-atendimentos/novo is configured.');

$waControllerPath = $root . '/application/controllers/Tecnina_whatsapp.php';
expectIntake02(file_exists($waControllerPath), 'Tecnina_whatsapp.php exists.');
$waController = file_get_contents($waControllerPath);
expectIntake02(strpos($waController, 'public function novo_pre_atendimento()') !== false, 'Tecnina_whatsapp implements novo_pre_atendimento().');
expectIntake02(strpos($waController, "confirmPhysicalReceipt(\$intakeId, \$operatorId, \$receivingPayload, \$idempotencyKey)") !== false, 'novo_pre_atendimento supports immediate physical receipt confirmation.');

expectIntake02(strpos($panelView, 'id="wa-btn-open-new-intake"') !== false, 'pre_atendimentos.php includes button #wa-btn-open-new-intake.');
expectIntake02(strpos($panelView, 'id="modal-new-intake"') !== false, 'pre_atendimentos.php includes modal #modal-new-intake.');

expectIntake02(strpos($panelJs, '#wa-btn-open-new-intake') !== false, 'pre-attendance-panel.js handles opening #modal-new-intake.');
expectIntake02(strpos($panelJs, '#btn-submit-new-intake') !== false, 'pre-attendance-panel.js handles submitting new intake.');
expectIntake02(strpos($panelJs, 'whatsapp_send_failed') !== false, 'pre-attendance-panel.js provides explicit message for whatsapp_send_failed.');

$gatewayPath = $root . '/application/libraries/Tecnina_bot_gateway.php';
expectIntake02(file_exists($gatewayPath), 'Tecnina_bot_gateway.php exists.');
$gateway = file_get_contents($gatewayPath);
expectIntake02(strpos($gateway, "'detail' => \$detail") !== false, 'Tecnina_bot_gateway returns detail in error payload.');

echo "[PASS] Walk-in intake creation, UI modal, and Gateway error preserving verified." . PHP_EOL;

echo "SUCCESS: All {$assertions} assertions in BACKLOG-INTAKE-02 PASSED!" . PHP_EOL;
