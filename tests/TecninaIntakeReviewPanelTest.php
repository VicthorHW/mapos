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
$view = file_get_contents($root . '/application/views/tecnina_whatsapp/index.php');
$gateway = file_get_contents($root . '/application/libraries/Tecnina_bot_gateway.php');

expectIntakePanel($controller !== false && $view !== false && $gateway !== false, 'Arquivos do painel ausentes.');
expectIntakePanel(strpos($controller, "'intakes' => '/admin/intakes'") !== false, 'Listagem de intakes não usa o contrato interno.');
expectIntakePanel(strpos($controller, "userdata('id_admin')") !== false, 'Operador deve vir da sessão MapOS.');
expectIntakePanel(strpos($controller, "post('operator_id'") === false, 'Navegador não pode escolher o ID do operador.');
expectIntakePanel(strpos($controller, "['save', 'reject', 'approve']") !== false, 'Ações da revisão não estão restritas.');
expectIntakePanel(strpos($controller, "['DROP_OFF', 'PICKUP_REQUESTED']") !== false, 'Modalidade de atendimento não usa whitelist.');
expectIntakePanel(substr_count($controller, "authorized(true)") >= 2, 'Rotas JSON devem exigir cSistema.');
expectIntakePanel(strpos($view, 'Pré-atendimentos') !== false, 'Aba de pré-atendimentos ausente.');
expectIntakePanel(strpos($view, 'function esc(value)') !== false, 'Dados do Gateway devem ser escapados antes da renderização.');
expectIntakePanel(strpos($view, 'encodeURIComponent') !== false, 'ID do intake deve ser codificado na URL.');
expectIntakePanel(strpos($view, 'Aprovar e criar OS') !== false, 'Aprovação transacional não está disponível no painel.');
expectIntakePanel(strpos($view, 'force_create_new') !== false, 'Decisão explícita sobre duplicidade não é enviada.');
expectIntakePanel(strpos($view, 'remote_jid') === false, 'JID não deve ser exposto no navegador.');
expectIntakePanel(strpos($view, 'Authorization: Bearer') === false, 'Cabeçalho interno não pode aparecer na view.');
expectIntakePanel(strpos($view, '$_ENV') === false, 'Variáveis do servidor não podem ser lidas na view.');
expectIntakePanel(strpos($gateway, 'Authorization: Bearer ') !== false, 'Proxy server-side deve autenticar no Gateway.');

echo 'TecninaIntakeReviewPanelTest: ' . $assertions . ' assertions passed.' . PHP_EOL;
