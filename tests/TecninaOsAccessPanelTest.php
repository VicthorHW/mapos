<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/application/controllers/Tecnina_whatsapp.php');
$gateway = file_get_contents($root . '/application/libraries/Tecnina_bot_gateway.php');
$script = file_get_contents($root . '/assets/tecnina/js/whatsapp-panel.js');
$assertions = 0;

function expectOsAccessPanel($condition, $message)
{
    global $assertions;
    ++$assertions;
    if (! $condition) {
        fwrite(STDERR, "TecninaOsAccessPanelTest falhou: {$message}\n");
        exit(1);
    }
}

expectOsAccessPanel(strpos($controller, 'function os_acesso') !== false, 'Proxy administrativo ausente.');
expectOsAccessPanel(strpos($controller, "['operator_id' => \$operatorId]") !== false, 'Operador deve vir da sessão MapOS.');
expectOsAccessPanel(strpos($controller, 'Cache-Control: no-store') !== false, 'Resposta com código pode ser armazenada em cache.');
expectOsAccessPanel(strpos($controller, "['rotate', 'revoke']") !== false, 'Ações permitidas não estão limitadas.');
expectOsAccessPanel(strpos($gateway, "'mapos_context_unavailable'") !== false, 'Erro seguro não atravessa o proxy.');
expectOsAccessPanel(strpos($script, 'Acesso seguro ao status da OS pelo WhatsApp') !== false, 'Interface de operação ausente.');
expectOsAccessPanel(strpos($script, 'exibido uma única vez') !== false, 'Interface não alerta sobre exibição única.');
expectOsAccessPanel(strpos($script, 'navigator.clipboard') !== false, 'Cópia controlada do código ausente.');
expectOsAccessPanel(strpos($script, 'Authorization: Bearer') === false, 'Token interno não pode aparecer no navegador.');
expectOsAccessPanel(strpos($script, 'operator_id') === false, 'Navegador não pode escolher o operador auditado.');

echo 'TecninaOsAccessPanelTest: ' . $assertions . " assertions passed.\n";
