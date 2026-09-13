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

// Contract L: Operational config is rendered in Estado tab and script
expectBotLab(strpos($view, 'st-op-captured-at') !== false, 'View não contém st-op-captured-at.');
expectBotLab(strpos($view, 'st-op-city-count') !== false, 'View não contém st-op-city-count.');
expectBotLab(strpos($view, 'st-op-active-city-count') !== false, 'View não contém st-op-active-city-count.');
expectBotLab(strpos($view, 'st-op-rate-count') !== false, 'View não contém st-op-rate-count.');
expectBotLab(strpos($view, 'st-op-dropoff-address') !== false, 'View não contém st-op-dropoff-address.');
expectBotLab(strpos($view, 'st-op-dropoff-days') !== false, 'View não contém st-op-dropoff-days.');
expectBotLab(strpos($script, 'sessionView.operational_config') !== false, 'Browser JS não consome sessionView.operational_config.');
expectBotLab(strpos($script, '#st-op-captured-at') !== false, 'Browser JS não atualiza #st-op-captured-at.');

// Contract M: Deliveries tab exists in view and is rendered in script
expectBotLab(strpos($view, 'href="#tab-deliveries"') !== false, 'Aba Entregas ausente na lista de abas.');
expectBotLab(strpos($view, 'id="tab-deliveries"') !== false, 'Painel tab-deliveries ausente na view.');
expectBotLab(strpos($view, 'wb-deliveries-list') !== false, 'Elemento wb-deliveries-list ausente na view.');
expectBotLab(strpos($script, 'sessionView.deliveries') !== false, 'Browser JS não consome sessionView.deliveries.');
expectBotLab(strpos($script, '#wb-deliveries-list') !== false, 'Browser JS não atualiza #wb-deliveries-list.');

// Contract N: Registration code is rendered through escaped text semantics, never console logged
expectBotLab(strpos($script, 'esc(del.code)') !== false, 'Browser JS não escapa o código de entrega com esc(del.code).');
expectBotLab(strpos($script, 'del.content') === false, 'Browser JS ainda faz referência a del.content.');
expectBotLab(strpos($script, 'console.log') === false, 'Browser JS contém chamadas a console.log.');
expectBotLab(strpos($script, 'console.info') === false, 'Browser JS contém chamadas a console.info.');
expectBotLab(strpos($script, 'console.warn') === false, 'Browser JS contém chamadas a console.warn.');

// Contract O: Gateway safeReasons includes simulation_operational_config_unavailable and JS handles it
expectBotLab(strpos($gateway, "'simulation_operational_config_unavailable'") !== false, 'Gateway safeReasons não contém simulation_operational_config_unavailable.');
expectBotLab(strpos($script, 'simulation_operational_config_unavailable:') !== false, 'Browser JS não mapeia simulation_operational_config_unavailable.');


// Contract P: Safe link renderer uses document DOM creation and no innerHTML
expectBotLab(strpos($script, 'function appendTextWithSafeLinks(') !== false, 'Helper appendTextWithSafeLinks ausente no script.');
expectBotLab(strpos($script, 'document.createTextNode') !== false, 'Safe renderer não utiliza document.createTextNode.');
expectBotLab(strpos($script, "document.createElement('a')") !== false, 'Safe renderer não utiliza document.createElement(a).');
expectBotLab(strpos($script, 'a.textContent = url') !== false, 'Safe renderer não atribui texto via textContent.');
expectBotLab(strpos($script, "a.target = '_blank'") !== false, 'Safe renderer não define target _blank.');
expectBotLab(strpos($script, "a.rel = 'noopener noreferrer'") !== false, 'Safe renderer não define rel noopener noreferrer.');

// Contract Q: Capability event support and distinct labels
expectBotLab(strpos($script, "entry.actor === 'CAPABILITY'") !== false, 'Script não verifica entry.actor === CAPABILITY.');
expectBotLab(strpos($script, 'bot-lab-event-card') !== false, 'Script não utiliza classe bot-lab-event-card.');
expectBotLab(strpos($style, '.bot-lab-event-card') !== false, 'bot-lab.css não estiliza .bot-lab-event-card.');

// Contract R: Passive escaping preservation
expectBotLab(strpos($script, 'esc(') !== false, 'Função esc ausente no script.');

// Contract S: Capability step input display
expectBotLab(strpos($script, "step.input.kind === 'CAPABILITY'") !== false, 'Script não contém ramo explícito para step.input.kind === CAPABILITY.');
expectBotLab(strpos($script, 'step.input.capability_kind') !== false, 'Script não referencia step.input.capability_kind.');
expectBotLab(strpos($script, 'step.input.action') !== false, 'Script não referencia step.input.action.');
expectBotLab(strpos($script, "'Capability: ' + esc(step.input.capability_kind") !== false, 'Script não formata a descrição da capability corretamente.');
preg_match('/else if\s*\(\s*step\.input\s*&&\s*step\.input\.kind\s*===\s*[\'"]CAPABILITY[\'"]\s*\)\s*\{([^}]+)\}/', $script, $capMatch);
expectBotLab(!empty($capMatch), 'Bloco do ramo CAPABILITY não encontrado.');
expectBotLab(strpos($capMatch[1], 'step.input.text') === false, 'Ramo de CAPABILITY não deve renderizar step.input.text.');

// Contract T: Scenario testing proxy endpoints exist in controller
expectBotLab(strpos($controller, 'function simulador_cenarios(') !== false, 'Proxy simulador_cenarios ausente no controller.');
expectBotLab(strpos($controller, 'function simulador_executar_cenarios(') !== false, 'Proxy simulador_executar_cenarios ausente no controller.');

// Contract U: Mode switcher and Scenario workbench markup exist in view
expectBotLab(strpos($view, 'bot-lab-mode-tabs') !== false, 'Tabs de modo (interativo / cenários) ausentes na view.');
expectBotLab(strpos($view, 'panel-interactive-mode') !== false, 'Painel interativo ausente na view.');
expectBotLab(strpos($view, 'panel-scenarios-mode') !== false, 'Painel de cenários automatizados ausente na view.');
expectBotLab(strpos($view, 'sc-filter-tag') !== false, 'Filtro por tag ausente no painel de cenários.');
expectBotLab(strpos($view, 'btn-run-scenarios') !== false, 'Botão de execução de cenários ausente na view.');
expectBotLab(strpos($view, 'scenarios-results-container') !== false, 'Container de resultados de cenários ausente na view.');

// Contract V: Scenario runner script logic exists
expectBotLab(strpos($script, 'loadScenariosCatalog') !== false, 'Função loadScenariosCatalog ausente no script.');
expectBotLab(strpos($script, 'executeScenariosSuite') !== false, 'Função executeScenariosSuite ausente no script.');
expectBotLab(strpos($script, 'renderScenarioResults') !== false, 'Função renderScenarioResults ausente no script.');
expectBotLab(strpos($script, 'switchPanelMode') !== false, 'Função switchPanelMode ausente no script.');

// Contract W: Scenario runner CSS classes exist
expectBotLab(strpos($style, '.bot-lab-mode-tabs') !== false, 'Classe .bot-lab-mode-tabs ausente no bot-lab.css.');
expectBotLab(strpos($style, '.scenario-result-card') !== false, 'Classe .scenario-result-card ausente no bot-lab.css.');
expectBotLab(strpos($style, '.scenario-chip') !== false, 'Classe .scenario-chip ausente no bot-lab.css.');

// Contract X: Section 38 - Catalog list, filters, and rendering logic
expectBotLab(strpos($view, 'id="sc-catalog-list"') !== false, 'Elemento #sc-catalog-list ausente na view.');
expectBotLab(strpos($view, 'id="sc-filter-text"') !== false, 'Campo de busca livre #sc-filter-text ausente na view.');
expectBotLab(strpos($view, 'id="sc-filter-tag"') !== false, 'Filtro de tags #sc-filter-tag ausente na view.');
expectBotLab(strpos($script, 'renderCatalogList') !== false, 'Função renderCatalogList ausente no script.');
expectBotLab(strpos($script, '#sc-filter-text') !== false, 'Script não observa #sc-filter-text.');
expectBotLab(strpos($script, 'VALID') !== false && strpos($script, 'INVALID') !== false, 'Script não renderiza badges VALID / INVALID.');
expectBotLab(strpos($style, '.sc-catalog-invalid') !== false, 'Classe .sc-catalog-invalid ausente no CSS.');
expectBotLab(strpos($script, 'sc-catalog-desc') !== false, 'Script não renderiza descrição do cenário.');
expectBotLab(strpos($script, 'sc-select-checkbox') !== false, 'Script não gera checkbox de seleção sc-select-checkbox.');
expectBotLab(strpos($script, "!sc.valid") !== false && strpos($script, "checkbox.prop('disabled', true)") !== false, 'Script não desabilita checkbox para cenários inválidos.');

// Contract Y: Section 38 - Execution controls and inline warnings
expectBotLab(strpos($view, 'id="btn-run-selected"') !== false, 'Botão #btn-run-selected ausente na view.');
expectBotLab(strpos($view, 'Executar selecionados') !== false, 'Rótulo "Executar selecionados" ausente na view.');
expectBotLab(strpos($view, 'id="btn-run-all-scenarios"') !== false, 'Botão #btn-run-all-scenarios ausente na view.');
expectBotLab(strpos($view, 'Executar todos') !== false, 'Rótulo "Executar todos" ausente na view.');
expectBotLab(strpos($view, 'id="sc-selection-warning"') !== false, 'Alerta de validação #sc-selection-warning ausente na view.');
expectBotLab(strpos($script, '#btn-run-selected') !== false, 'Script não escuta evento de #btn-run-selected.');
expectBotLab(strpos($script, '#btn-run-all-scenarios') !== false, 'Script não escuta evento de #btn-run-all-scenarios.');
expectBotLab(strpos($script, 'scenario_ids: selectedIds') !== false, 'Script não envia scenario_ids selecionados.');

// Contract Z: Section 38 - Summary metrics and result card enrichment
expectBotLab(strpos($view, 'id="sc-stat-errors"') !== false, 'Métrica distinta #sc-stat-errors ausente na view.');
expectBotLab(strpos($view, 'stat-error') !== false, 'Classe stat-error ausente na view.');
expectBotLab(strpos($style, '.bot-lab-stat-box.stat-error') !== false, 'Classe .bot-lab-stat-box.stat-error ausente no CSS.');
expectBotLab(strpos($script, 'suite.errors') !== false, 'Script não consome suite.errors separadamente de failed.');
expectBotLab(strpos($script, '#sc-stat-errors') !== false, 'Script não atualiza #sc-stat-errors.');
expectBotLab(strpos($script, 'sc-result-final-state') !== false && strpos($script, 'res.final_state') !== false, 'Script não renderiza estado final no card.');
expectBotLab(strpos($script, 'sc-result-states-visited') !== false && strpos($script, 'res.states_visited') !== false, 'Script não renderiza estados visitados para falhas/erros.');
expectBotLab(strpos($style, '.sc-result-final-state') !== false, 'Classe .sc-result-final-state ausente no CSS.');
expectBotLab(strpos($style, '.sc-result-states-visited') !== false, 'Classe .sc-result-states-visited ausente no CSS.');

// Contract AA: Section 38 - No YAML editor, no direct bot API, no bearer token
expectBotLab(strpos($view, 'CodeMirror') === false && strpos($script, 'CodeMirror') === false, 'Editor CodeMirror encontrado.');
expectBotLab(strpos($view, 'monaco') === false && strpos($script, 'monaco') === false, 'Editor monaco encontrado.');
expectBotLab(strpos($view, '<textarea name="yaml"') === false, 'Campo textarea de YAML encontrado.');
expectBotLab(strpos($script, 'Bearer') === false, 'Token Bearer encontrado no script do navegador.');

echo "TecninaBotLabPanelTest: " . $assertions . " assertions passed." . PHP_EOL;
