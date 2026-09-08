<?php

declare(strict_types=1);

function expectFlowStudioRemoved(bool $condition, string $message): void
{
    static $assertions = 0;
    $assertions++;
    if (! $condition) {
        throw new RuntimeException($message);
    }
    $GLOBALS['flowStudioRemovalAssertions'] = $assertions;
}

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/application/controllers/Tecnina_whatsapp.php');
$view = file_get_contents($root . '/application/views/tecnina_whatsapp/index.php');
$script = file_get_contents($root . '/assets/tecnina/js/whatsapp-panel.js');

expectFlowStudioRemoved($controller !== false && $view !== false && $script !== false, 'Arquivos do painel ausentes.');
expectFlowStudioRemoved(strpos($view, 'wa-fluxos') === false, 'A aba Fluxos ainda está renderizada.');
expectFlowStudioRemoved(strpos($controller, '/admin/flows') === false, 'O proxy do Flow Studio ainda está exposto.');
expectFlowStudioRemoved(strpos($controller, 'function fluxo(') === false, 'O controller ainda aceita ações do Flow Studio.');
expectFlowStudioRemoved(strpos($script, 'wa-flow-') === false, 'O JavaScript ainda contém componentes do Flow Studio.');
expectFlowStudioRemoved(strpos($script, '/dados/flows') === false, 'O painel ainda consulta o catálogo de fluxos.');

echo 'TecninaFlowStudioRemovalTest: ' . $GLOBALS['flowStudioRemovalAssertions'] . ' assertions passed.' . PHP_EOL;
