<?php

$assertions = 0;
$root = dirname(__DIR__);

function expectBotLab($condition, $message)
{
    global $assertions;
    ++$assertions;
    if (! $condition) {
        fwrite(STDERR, "TecninaBotLabPanelTest falhou: " . $message . PHP_EOL);
        exit(1);
    }
}

$controller = file_get_contents($root . '/application/controllers/Tecnina_whatsapp.php');
$menu = file_get_contents($root . '/application/views/tema/menu.php');
$view = file_get_contents($root . '/application/views/tecnina_whatsapp/bot_lab.php');
$script = file_get_contents($root . '/assets/tecnina/js/bot-lab.js');
$style = file_get_contents($root . '/assets/tecnina/css/bot-lab.css');
$gateway = file_get_contents($root . '/application/libraries/Tecnina_bot_gateway.php');

expectBotLab($controller !== false && $menu !== false && $view !== false && $script !== false && $style !== false && $gateway !== false, 'Arquivos do Bot Lab ausentes.');

// Contract A: Controller contains all required methods
expectBotLab(strpos($controller, 'function bot_lab(') !== false, 'Método bot_lab ausente no controller.');
expectBotLab(strpos($controller, 'function simulador_criar(') !== false, 'Proxy simulador_criar ausente no controller.');
expectBotLab(strpos($controller, 'function simulador_sessao(') !== false, 'Proxy simulador_sessao ausente no controller.');
expectBotLab(strpos($controller, 'function simulador_mensagem(') !== false, 'Proxy simulador_mensagem ausente no controller.');
expectBotLab(strpos($controller, 'function simulador_localizacao(') !== false, 'Proxy simulador_localizacao ausente no controller.');
expectBotLab(strpos($controller, 'function simulador_reset(') !== false, 'Proxy simulador_reset ausente no controller.');
expectBotLab(strpos($controller, 'function simulador_excluir(') !== false, 'Proxy simulador_excluir ausente no controller.');

// Contract B: Bot Lab page is cSistema protected
expectBotLab(strpos($controller, "\$this->data['menuBotLab'] = 'Bot Lab';") !== false, 'Marker menuBotLab não definido na página.');
expectBotLab(strpos($controller, "preparePanel('tecnina_whatsapp/bot_lab')") !== false, 'preparePanel para bot_lab ausente.');

// Contract C: menu.php contains Bot Lab link and menuBotLab marker under cSistema
expectBotLab(strpos($menu, "site_url('tecnina_whatsapp/bot_lab')") !== false, 'Link do Bot Lab ausente na navegação.');
expectBotLab(strpos($menu, '$menuBotLab') !== false, 'Marker menuBotLab ausente no menu.');
expectBotLab(strpos($menu, 'bx-bot') !== false, 'Ícone bx-bot ausente no menu do Bot Lab.');

// Contract D: view references dedicated bot-lab.css and bot-lab.js
expectBotLab(strpos($view, 'assets/tecnina/css/bot-lab.css') !== false, 'bot_lab.php não referencia bot-lab.css.');
expectBotLab(strpos($view, 'assets/tecnina/js/bot-lab.js') !== false, 'bot_lab.php não referencia bot-lab.js.');

// Contract E: browser JS does NOT contain secrets or direct Bot API paths
expectBotLab(strpos($script, 'MAPOS_BOT_TOKEN') === false, 'Browser JS contém MAPOS_BOT_TOKEN.');
expectBotLab(strpos($script, 'TECNINA_BOT_BASE_URL') === false, 'Browser JS contém TECNINA_BOT_BASE_URL.');
expectBotLab(strpos($script, '/admin/simulator/') === false, 'Browser JS chama diretamente /admin/simulator/.');

// Contract F: JS includes CSRF handling
expectBotLab(strpos($script, 'data[csrfName] = csrfHash') !== false, 'Browser JS não inclui CSRF em requisições não-GET.');
expectBotLab(strpos($script, 'csrfHash = response.csrf') !== false, 'Browser JS não renova o hash CSRF na resposta.');

// Contract G: gateway safeReasons include all 5 simulator codes
expectBotLab(strpos($gateway, "'simulation_not_found'") !== false, 'Gateway safeReasons não contém simulation_not_found.');
expectBotLab(strpos($gateway, "'simulation_fixture_incomplete'") !== false, 'Gateway safeReasons não contém simulation_fixture_incomplete.');
expectBotLab(strpos($gateway, "'simulation_runtime_state'") !== false, 'Gateway safeReasons não contém simulation_runtime_state.');
expectBotLab(strpos($gateway, "'simulation_manager_unavailable'") !== false, 'Gateway safeReasons não contém simulation_manager_unavailable.');
expectBotLab(strpos($gateway, "'simulation_execution_failed'") !== false, 'Gateway safeReasons não contém simulation_execution_failed.');

// Contract H: gateway supports successful empty 204
expectBotLab(strpos($gateway, '$status === 204') !== false && strpos($gateway, "'status' => 204") !== false, 'Gateway não suporta HTTP 204 vazio.');

// Contract I: controller delete proxy returns browser JSON/CSRF using HTTP 200 after upstream 204
expectBotLab(strpos($controller, "\$result['status'] === 204") !== false && strpos($controller, "'status' => 200") !== false, 'Proxy simulador_excluir não converte upstream 204 em HTTP 200 para emissão de CSRF.');

// Contract J: WIP simulator source is NOT referenced from current implementation
expectBotLab(strpos($controller, '/simulator/message') === false, 'Controller ainda referencia rota rejeitada /simulator/message do WIP.');
expectBotLab(strpos($view, 'sim-chat-box') === false, 'View atual referencia DOM do WIP simulador.php.');
expectBotLab(strpos($script, 'addMessage') === false, 'Script atual referencia funções do WIP.');

// Contract K: Location validation UX uses inline element and no native alert()
expectBotLab(strpos($script, 'alert(') === false, 'Browser JS ainda contém chamadas nativas de alert().');
expectBotLab(strpos($view, 'location-validation-error') !== false, 'View não contém elemento location-validation-error.');
expectBotLab(strpos($script, 'location-validation-error') !== false, 'Browser JS não utiliza o elemento location-validation-error.');

echo "TecninaBotLabPanelTest: " . $assertions . " assertions passed." . PHP_EOL;
