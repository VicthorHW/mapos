<?php

$assertions = 0;
$root = dirname(__DIR__);

function expectWatchdog($condition, $message)
{
    global $assertions;
    ++$assertions;
    if (! $condition) {
        fwrite(STDERR, "TecninaServiceWatchdogPanelTest falhou: " . $message . PHP_EOL);
        exit(1);
    }
}

$controller = file_get_contents($root . '/application/controllers/Tecnina_whatsapp.php');
$view = file_get_contents($root . '/application/views/tecnina_whatsapp/index.php');
$script = file_get_contents($root . '/assets/tecnina/js/whatsapp-panel.js');
$style = file_get_contents($root . '/assets/tecnina/css/whatsapp-panel.css');

expectWatchdog($controller !== false && $view !== false && $script !== false && $style !== false, 'Arquivos do painel ausentes.');

// Contract 1: Controller paths and endpoints
expectWatchdog(strpos($controller, "'watchdog' => '/admin/watchdog'") !== false, "Rota 'watchdog' ausente no array \$paths de dados().");
expectWatchdog(strpos($controller, 'function watchdog_toggle(') !== false, 'Método watchdog_toggle ausente no controller.');
expectWatchdog(strpos($controller, 'function watchdog_recover(') !== false, 'Método watchdog_recover ausente no controller.');
expectWatchdog(strpos($controller, "request('POST', '/admin/watchdog/toggle'") !== false, 'Encaminhamento POST /admin/watchdog/toggle ausente.');
expectWatchdog(strpos($controller, "request('POST', '/admin/watchdog/recover'") !== false, 'Encaminhamento POST /admin/watchdog/recover ausente.');
expectWatchdog(strpos($controller, 'authorizedMutation(true)') !== false, 'Verificação de authorizedMutation ausente.');

// Contract 2: View structure and naming
expectWatchdog(strpos($view, 'id="wa-overview-watchdog"') !== false, 'Contêiner wa-overview-watchdog ausente na view.');
expectWatchdog(strpos($view, 'id="wa-overview-queue"') !== false, 'Contêiner wa-overview-queue ausente na view.');
expectWatchdog(strpos($view, 'Status da infraestrutura e dos serviços') !== false, 'Texto descritivo de infraestrutura ausente na view.');

// Contract 3: JS service health naming & decoupling
expectWatchdog(strpos($script, 'TecNina Bot') !== false, "Nomenclatura 'TecNina Bot' ausente no JS.");
expectWatchdog(strpos($script, 'WhatsApp Infra') !== false, "Nomenclatura 'WhatsApp Infra' ausente no JS.");
expectWatchdog(strpos($script, 'MapOS Gestão') !== false, "Nomenclatura 'MapOS Gestão' ausente no JS.");
expectWatchdog(strpos($script, 'renderWatchdogSection') !== false, 'Função renderWatchdogSection ausente no JS.');
expectWatchdog(strpos($script, 'renderQueueSummary') !== false, 'Função renderQueueSummary ausente no JS.');
expectWatchdog(strpos($script, 'instance_state') !== false, 'Tratamento de instance_state ausente no JS.');
expectWatchdog(strpos($script, '#wa-watchdog-toggle') !== false, 'Listener para #wa-watchdog-toggle ausente no JS.');
expectWatchdog(strpos($script, '#wa-watchdog-recover-btn') !== false, 'Listener para #wa-watchdog-recover-btn ausente no JS.');
expectWatchdog(strpos($script, "request('/watchdog_toggle'") !== false, 'Chamada request para /watchdog_toggle ausente.');
expectWatchdog(strpos($script, "request('/watchdog_recover'") !== false, 'Chamada request para /watchdog_recover ausente.');

// Contract 4: CSS grid styling
expectWatchdog(strpos($style, 'grid-template-columns: repeat(3, minmax(0, 1fr))') !== false, 'CSS da grade não está configurado para 3 colunas.');

echo "TecninaServiceWatchdogPanelTest: {$assertions} assertions passed." . PHP_EOL;
