<?php

$assertions = 0;
$root = dirname(__DIR__);

function expectFlowStudioPanel($condition, $message)
{
    global $assertions;
    ++$assertions;
    if (! $condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$controller = file_get_contents($root . '/application/controllers/Tecnina_whatsapp.php');
$view = file_get_contents($root . '/application/views/tecnina_whatsapp/index.php');
$script = file_get_contents($root . '/assets/tecnina/js/whatsapp-panel.js');
$gateway = file_get_contents($root . '/application/libraries/Tecnina_bot_gateway.php');

expectFlowStudioPanel($controller !== false && $view !== false && $script !== false && $gateway !== false, 'Arquivos do Flow Studio ausentes.');
expectFlowStudioPanel(strpos($view, 'assets/tecnina/js/whatsapp-panel.js') !== false, 'Script principal não está isolado da view.');
expectFlowStudioPanel(strpos($controller, "'/ai-export'") !== false, 'Exportação segura para IA não usa o Gateway.');
expectFlowStudioPanel(strpos($controller, "'/draft/update'") !== false, 'Atualização de rascunho não usa o Gateway.');
expectFlowStudioPanel(strpos($controller, 'strlen($rawDefinition) > 100000') !== false, 'Proxy não limita o JSON do fluxo.');
expectFlowStudioPanel(strpos($script, 'function flowLayout(') !== false, 'Diagrama não possui layout em camadas.');
expectFlowStudioPanel(strpos($script, 'Copiar pacote para IA') !== false, 'Editor não oferece pacote explicativo para IA.');
expectFlowStudioPanel(strpos($script, 'Salvar rascunho') !== false, 'Editor não diferencia rascunho de publicação.');
expectFlowStudioPanel(strpos($script, 'nenhum efeito externo') !== false, 'Simulador não esclarece sua segurança.');
expectFlowStudioPanel(strpos($script, 'LOCATION_PENDING') !== false && strpos($script, 'PICKUP') !== false, 'Cenários de localização não estão disponíveis.');
expectFlowStudioPanel(strpos($script, 'Chat de teste seguro') !== false, 'Simulador conversacional interno não está disponível.');
expectFlowStudioPanel(strpos($script, 'Executar testes automáticos') !== false, 'Suíte regressiva do Flow Studio não está disponível.');
expectFlowStudioPanel(strpos($script, 'flowChats') !== false, 'Estado local do chat simulado não está isolado no navegador.');
expectFlowStudioPanel(strpos($script, 'Authorization: Bearer') === false, 'Token interno não pode aparecer no JavaScript.');
expectFlowStudioPanel(strpos($gateway, "'stale_flow_revision'") !== false, 'Conflito de revisão não atravessa o proxy de forma segura.');

echo 'TecninaFlowStudioPanelTest: ' . $assertions . ' assertions passed.' . PHP_EOL;
