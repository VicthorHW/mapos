<?php

$assertions = 0;
$root = dirname(__DIR__);

function expectIntakePanel($condition, $message)
{
    global $assertions;
    ++$assertions;
    if (! $condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$controller = file_get_contents($root . '/application/controllers/Tecnina_whatsapp.php');
$settingsView = file_get_contents($root . '/application/views/tecnina_whatsapp/index.php');
$view = file_get_contents($root . '/application/views/tecnina_whatsapp/pre_atendimentos.php');
$script = file_get_contents($root . '/assets/tecnina/js/pre-attendance-panel.js');
$menu = file_get_contents($root . '/application/views/tema/menu.php');
$gateway = file_get_contents($root . '/application/libraries/Tecnina_bot_gateway.php');

expectIntakePanel($controller !== false && $settingsView !== false && $view !== false && $script !== false && $menu !== false && $gateway !== false, 'Arquivos do painel ausentes.');
$panel = $view . "\n" . $script;
expectIntakePanel(strpos($controller, "'intakes' => '/admin/intakes'") !== false, 'Listagem de intakes não usa o contrato interno.');
expectIntakePanel(strpos($controller, "userdata('id_admin')") !== false, 'Operador deve vir da sessão MapOS.');
expectIntakePanel(strpos($controller, "post('operator_id'") === false, 'Navegador não pode escolher o ID do operador.');
expectIntakePanel(strpos($controller, "['save', 'reject', 'approve']") !== false, 'Ações da revisão não estão restritas.');
expectIntakePanel(strpos($controller, "['DROP_OFF', 'PICKUP_REQUESTED']") !== false, 'Modalidade de atendimento não usa whitelist.');
expectIntakePanel(substr_count($controller, "authorized(true)") >= 2, 'Rotas JSON devem exigir cSistema.');
expectIntakePanel(strpos($controller, 'function pre_atendimentos()') !== false, 'Página operacional de pré-atendimentos ausente.');
expectIntakePanel(strpos($menu, "site_url('tecnina_whatsapp/pre_atendimentos')") !== false, 'Pré-atendimentos não possui entrada na barra lateral.');
expectIntakePanel(strpos($settingsView, 'wa-intakes') === false, 'Pré-atendimentos ainda aparece nas configurações do WhatsApp.');
expectIntakePanel(strpos($panel, 'Aguardando revisão') !== false && strpos($panel, 'Histórico') !== false, 'Fila e histórico não estão separados.');
expectIntakePanel(strpos($panel, 'wa-intake-workspace') !== false && strpos($panel, 'wa-intake-detail-column') !== false, 'Layout mestre-detalhe ausente.');
expectIntakePanel(strpos($script, 'mapos_client_name') !== false, 'Nome real do cliente MapOS não é priorizado.');
expectIntakePanel(strpos($controller, "select('idClientes, nomeCliente')") !== false, 'Nome do cliente não usa whitelist explícita no MapOS.');
expectIntakePanel(strpos($script, 'statusMeta') !== false, 'Status internos não possuem labels amigáveis.');
expectIntakePanel(strpos($panel, 'function esc(value)') !== false, 'Dados do Gateway devem ser escapados antes da renderização.');
expectIntakePanel(strpos($panel, 'encodeURIComponent') !== false, 'ID do intake deve ser codificado na URL.');
expectIntakePanel(strpos($panel, 'Aprovar e criar OS') !== false, 'Aprovação transacional não está disponível no painel.');
expectIntakePanel(strpos($panel, 'force_create_new') !== false, 'Decisão explícita sobre duplicidade não é enviada.');
expectIntakePanel(strpos($panel, 'remote_jid') === false, 'JID não deve ser exposto no navegador.');
expectIntakePanel(strpos($panel, 'Authorization: Bearer') === false, 'Cabeçalho interno não pode aparecer na view.');
expectIntakePanel(strpos($view, '$_ENV') === false, 'Variáveis do servidor não podem ser lidas na view.');
expectIntakePanel(strpos($gateway, 'Authorization: Bearer ') !== false, 'Proxy server-side deve autenticar no Gateway.');

echo 'TecninaIntakeReviewPanelTest: ' . $assertions . ' assertions passed.' . PHP_EOL;
