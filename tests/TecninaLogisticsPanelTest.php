<?php

$assertions = 0;
$root = dirname(__DIR__);

function expectLogisticsPanel($condition, $message)
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

expectLogisticsPanel($controller !== false && $view !== false && $script !== false && $gateway !== false, 'Arquivos do painel ausentes.');
$panel = $view . "\n" . $script;
expectLogisticsPanel(strpos($controller, "'logistics-zones' => '/admin/logistics/zones'") !== false, 'Zonas não usam Admin API do Gateway.');
expectLogisticsPanel(strpos($controller, "'logistics-appointments' => '/admin/logistics/appointments'") !== false, 'Movimentos não usam Admin API do Gateway.');
expectLogisticsPanel(strpos($controller, "'zones' => '/admin/logistics/zones'") !== false, 'Whitelist de configuração logística ausente.');
expectLogisticsPanel(strpos($controller, "['confirm', 'cancel', 'complete', 'reschedule-required']") !== false, 'Ações logísticas não estão restritas.');
expectLogisticsPanel(strpos($controller, 'location-request') === false, 'O emissor manual de link legado ainda está exposto.');
expectLogisticsPanel(strpos($controller, "userdata('id_admin')") !== false, 'Operador deve vir da sessão MapOS.');
expectLogisticsPanel(strpos($controller, "post('operator_id'") === false, 'Navegador não pode escolher o operador.');
expectLogisticsPanel(strpos($controller, 'strlen($rawPayload) > 20000') !== false, 'Proxy deve limitar o payload de configuração.');
expectLogisticsPanel(strpos($panel, '>Logística<') !== false, 'Aba Logística ausente.');
expectLogisticsPanel(strpos($panel, 'PICKUP') !== false && strpos($panel, 'DELIVERY') !== false, 'Coleta e entrega não compartilham a mesma interface.');
expectLogisticsPanel(strpos($panel, 'America/Sao_Paulo') !== false, 'Timezone IANA não está explícito no painel.');
expectLogisticsPanel(strpos($panel, 'wa-logistics-location-link') === false, 'O painel ainda reserva espaço para o link legado.');
expectLogisticsPanel(strpos($panel, 'function esc(value)') !== false, 'Dados do Gateway devem ser escapados.');
expectLogisticsPanel(strpos($panel, 'latitude') === false && strpos($panel, 'longitude') === false, 'Lista não deve expor coordenadas exatas.');
expectLogisticsPanel(strpos($panel, 'Authorization: Bearer') === false, 'Token interno não pode aparecer na view.');
expectLogisticsPanel(strpos($gateway, "'confirmed_location_required'") !== false, 'Erros logísticos seguros não atravessam o proxy.');

echo 'TecninaLogisticsPanelTest: ' . $assertions . ' assertions passed.' . PHP_EOL;
