<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/application/controllers/Tecnina_whatsapp.php');
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
expectOsAccessPanel(strpos($controller, "'os_access_code_retired'], 410") !== false, 'Endpoint legado não retorna 410.');
expectOsAccessPanel(strpos($controller, '/admin/os-access-codes/') === false, 'Proxy legado ainda alcança o Gateway.');
expectOsAccessPanel(strpos($script, 'Acesso seguro ao status da OS pelo WhatsApp') === false, 'Interface aposentada ainda está visível.');
expectOsAccessPanel(strpos($script, 'wa-os-access-rotate') === false, 'Ação aposentada ainda está ligada no navegador.');
expectOsAccessPanel(strpos($script, 'wa-os-access-copy') === false, 'Código de cópia aposentado ainda está carregado.');
expectOsAccessPanel(strpos($script, 'Authorization: Bearer') === false, 'Token interno não pode aparecer no navegador.');
expectOsAccessPanel(strpos($script, 'operator_id') === false, 'Navegador não pode escolher o operador auditado.');

echo 'TecninaOsAccessPanelTest: ' . $assertions . " assertions passed.\n";
