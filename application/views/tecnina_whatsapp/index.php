<div class="row-fluid">
    <div class="span12">
        <div class="widget-box">
            <div class="widget-title"><span class="icon"><i class="bx bxl-whatsapp"></i></span><h5>Configurações → WhatsApp</h5></div>
            <div class="widget-content">
                <p class="muted">Painel interno da integração TecNina. Chaves e dados de contato completos não são expostos nesta tela.</p>
                <?php if (! $gatewayConfigured): ?>
                    <div class="alert alert-error">Gateway não configurado. Defina TECNINA_BOT_BASE_URL e MAPOS_BOT_TOKEN no ambiente do MapOS.</div>
                <?php endif; ?>
                <div id="wa-error" class="alert alert-error" style="display:none"></div>
                <div class="row-fluid" id="wa-overview"><div class="span12"><i class="fas fa-spinner fa-spin"></i> Carregando visão geral…</div></div>
                <hr>
                <ul class="nav nav-tabs">
                    <li class="active"><a href="#wa-conversas" data-toggle="tab">Conversas</a></li>
                    <li><a href="#wa-intakes" data-toggle="tab">Pré-atendimentos</a></li>
                    <li><a href="#wa-logistica" data-toggle="tab">Logística</a></li>
                    <li><a href="#wa-fila" data-toggle="tab">Fila</a></li>
                    <li><a href="#wa-logs" data-toggle="tab">Logs</a></li>
                    <li><a href="#wa-regras" data-toggle="tab">Regras de status</a></li>
                    <li><a href="#wa-templates" data-toggle="tab">Templates</a></li>
                    <li><a href="#wa-config" data-toggle="tab">Configuração</a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane active" id="wa-conversas"><div id="wa-conversations">Carregando…</div></div>
                    <div class="tab-pane" id="wa-intakes"><div id="wa-intakes-list">Carregando…</div><div id="wa-intake-detail"></div></div>
                    <div class="tab-pane" id="wa-logistica">
                        <div id="wa-logistics-overview">Carregando…</div>
                        <div id="wa-logistics-location-link"></div>
                        <div id="wa-logistics-appointments"></div>
                        <hr>
                        <div id="wa-logistics-zones"></div>
                        <div id="wa-logistics-routes"></div>
                        <div id="wa-logistics-capacity"></div>
                        <div id="wa-logistics-profiles"></div>
                    </div>
                    <div class="tab-pane" id="wa-fila"><div id="wa-queue">Carregando…</div></div>
                    <div class="tab-pane" id="wa-logs"><div id="wa-logs-list">Carregando…</div></div>
                    <div class="tab-pane" id="wa-regras"><div id="wa-rules">Carregando…</div></div>
                    <div class="tab-pane" id="wa-templates"><div id="wa-templates-list">Carregando…</div></div>
                    <div class="tab-pane" id="wa-config"><div id="wa-settings">Carregando…</div></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function ($) {
    'use strict';
    var base = <?= json_encode(site_url('tecnina_whatsapp')) ?>;
    var osEditBase = <?= json_encode(site_url('os/editar')) ?>;
    var csrfName = <?= json_encode($csrfName) ?>, csrfHash = <?= json_encode($csrfHash) ?>;
    var runtimeNotifications = false;
    function esc(value) { return $('<div>').text(value == null ? '' : value).html(); }
    function error(message) { $('#wa-error').text(message || 'Não foi possível comunicar com o Gateway.').show(); }
    function reasonMessage(reason) {
        var messages = {
            intake_review_conflict: 'Este pré-atendimento foi alterado. Reabra a revisão e tente novamente.',
            existing_client_required: 'Informe o ID do cliente existente.',
            client_name_required: 'Informe o nome antes de criar um cliente.',
            incomplete_intake: 'Revise e salve todos os campos obrigatórios antes de aprovar.',
            invalid_operator: 'O usuário atual não pode ser vinculado à OS.',
            ambiguous_client: 'Há mais de um cliente com este telefone. Localize o cadastro correto e informe seu ID.',
            client_match_changed: 'O cadastro correspondente ao telefone mudou. Reabra a revisão.',
            duplicate_client_requires_decision: 'Já existe um cliente com este telefone. Vincule o cadastro existente ou confirme a criação duplicada.',
            approval_in_progress: 'Esta aprovação já está em processamento. Aguarde e atualize a lista.',
            mapos_unavailable: 'O MapOS não respondeu à aprovação. Tente novamente.',
            approval_unavailable: 'Não foi possível criar a OS. Nenhum cadastro parcial foi mantido.'
            ,zone_key_already_exists: 'Já existe uma zona com esta chave.'
            ,route_key_already_exists: 'Já existe uma rota com esta chave.'
            ,capacity_rule_key_already_exists: 'Já existe uma regra de capacidade com esta chave.'
            ,equipment_type_key_already_exists: 'Já existe um perfil para este tipo de equipamento.'
            ,zone_not_found: 'A zona selecionada não existe mais.'
            ,route_not_found: 'A rota selecionada não existe mais.'
            ,appointment_not_found: 'O movimento logístico não existe mais.'
            ,stale_appointment_version: 'Este movimento foi alterado. Atualize a lista e tente novamente.'
            ,confirmed_location_required: 'Confirme a localização antes de confirmar este movimento.'
            ,capacity_rule_missing: 'Configure uma regra de capacidade para esta rota.'
            ,window_capacity_exceeded: 'A capacidade desta janela foi atingida.'
            ,daily_capacity_exceeded: 'A capacidade diária foi atingida.'
            ,blackout_capacity_exceeded: 'A capacidade excepcional desta data foi atingida.'
            ,route_blackout: 'Esta rota está indisponível na data solicitada.'
            ,pricing_requires_review: 'O preço da zona mudou e precisa ser revisado.'
            ,route_schedule_mismatch: 'A janela não corresponde mais ao horário da rota.'
            ,minimum_notice_not_met: 'A antecedência mínima desta rota não é mais atendida.'
        };
        return messages[reason] || 'Não foi possível concluir a operação.';
    }
    function request(path, method, data, done) {
        data = data || {}; if (method !== 'GET') { data[csrfName] = csrfHash; }
        return $.ajax({url: base + path, method: method, data: data, dataType: 'json'})
            .done(function (response) { if (response.csrf) { csrfHash = response.csrf; } if (!response.ok) { error(reasonMessage(response.reason)); return; } $('#wa-error').hide(); done(response.data); })
            .fail(function (xhr) { var response=xhr.responseJSON || {}; if (response.csrf) { csrfHash=response.csrf; } error(reasonMessage(response.reason)); });
    }
    function loadOverview() { request('/dados/overview', 'GET', null, function (d) {
        var c = d.components || {}, q = d.queue || {};
        runtimeNotifications = d.runtime_notifications_enabled === true;
        $('#wa-overview').html('<div class="span3"><strong>Gateway</strong><br>' + (c.gateway && c.gateway.ok ? 'Online' : 'Indisponível') + '</div>' +
            '<div class="span3"><strong>MapOS</strong><br>' + (c.mapos && c.mapos.ok ? 'Online' : 'Indisponível') + '</div>' +
            '<div class="span3"><strong>Evolution</strong><br>' + (c.evolution && c.evolution.ok ? 'Online' : 'Indisponível') + '</div>' +
            '<div class="span3"><strong>Fila pendente</strong><br>' + esc((q.PENDING || 0) + (q.RETRY || 0) + (q.DEFERRED || 0)) + '</div>');
        loadSettings();
    }); }
    function loadConversations() { request('/dados/conversations', 'GET', null, function (rows) {
        var h = '<table class="table table-bordered"><thead><tr><th>Contato</th><th>Estado</th><th>Até</th><th>Ação</th></tr></thead><tbody>';
        $.each(rows, function(_, r) { h += '<tr><td>' + esc(r.phone_tail) + '</td><td>' + esc(r.state) + '</td><td>' + esc(r.human_until || '—') + '</td><td><button class="btn btn-mini wa-lock" data-id="' + r.id + '">Pausar bot</button> <button class="btn btn-mini wa-resume" data-id="' + r.id + '">Retomar</button></td></tr>'; });
        $('#wa-conversations').html(h + '</tbody></table>');
    }); }
    function loadIntakes() { request('/dados/intakes', 'GET', null, function (rows) {
        var h = '<table class="table table-bordered"><thead><tr><th>Recebido</th><th>Contato</th><th>Nome</th><th>Equipamento</th><th>Cidade</th><th>Status</th><th></th></tr></thead><tbody>';
        $.each(rows, function(_, r) { h += '<tr><td>' + esc(r.ready_at || '—') + '</td><td>' + esc(r.phone_display) + '</td><td>' + esc(r.name || 'Cliente já cadastrado') + '</td><td>' + esc(r.equipment) + '</td><td>' + esc(r.city) + '</td><td>' + esc(r.status) + '</td><td><button class="btn btn-mini btn-primary wa-intake-open" data-id="' + esc(r.id) + '">Revisar</button></td></tr>'; });
        $('#wa-intakes-list').html(rows.length ? h + '</tbody></table>' : '<p>Nenhum pré-atendimento aguardando revisão.</p>');
    }); }
    function loadIntake(id) { request('/pre_atendimento/' + encodeURIComponent(id), 'GET', null, function (d) {
        var pickup = d.service_mode === 'PICKUP_REQUESTED';
        var existingId = d.possible_mapos_client_id || '';
        var linkChecked = existingId ? ' checked' : '';
        var createChecked = existingId ? '' : ' checked';
        var h = '<div class="well wa-intake-form" data-id="' + esc(d.id) + '" data-version="' + esc(d.review_version) + '">' +
            '<h5>Pré-atendimento ' + esc(d.id) + '</h5><p><strong>WhatsApp:</strong> ' + esc(d.phone_display) + '</p>' +
            '<div class="row-fluid"><div class="span6"><label>Nome</label><input class="input-block-level wa-i-name" maxlength="120" value="' + esc(d.name || '') + '"></div>' +
            '<div class="span6"><label>Cidade</label><input class="input-block-level wa-i-city" maxlength="80" value="' + esc(d.city || '') + '"></div></div>' +
            '<div class="row-fluid"><div class="span4"><label>Equipamento</label><input class="input-block-level wa-i-device" maxlength="80" value="' + esc(d.device_type || '') + '"></div>' +
            '<div class="span4"><label>Marca</label><input class="input-block-level wa-i-brand" maxlength="80" value="' + esc(d.brand || '') + '"></div>' +
            '<div class="span4"><label>Modelo</label><input class="input-block-level wa-i-model" maxlength="120" value="' + esc(d.model || '') + '"></div></div>' +
            '<label>Problema informado</label><textarea class="input-block-level wa-i-problem" maxlength="2000" rows="4">' + esc(d.problem_description || '') + '</textarea>' +
            '<label>Forma de atendimento</label><select class="wa-i-mode"><option value="DROP_OFF"' + (!pickup ? ' selected' : '') + '>Cliente traz o equipamento</option><option value="PICKUP_REQUESTED"' + (pickup ? ' selected' : '') + '>Solicitação de coleta</option></select>' +
            '<label>Observações internas</label><textarea class="input-block-level wa-i-notes" maxlength="2000" rows="3">' + esc(d.notes || '') + '</textarea>' +
            '<div class="well well-small"><strong>Destino no MapOS</strong>' +
            '<label class="radio"><input type="radio" name="wa-client-action" value="LINK_EXISTING"' + linkChecked + '> Vincular cliente existente</label>' +
            '<label>ID do cliente</label><input class="input-small wa-i-client-id" type="number" min="1" value="' + esc(existingId) + '">' +
            '<label class="radio"><input type="radio" name="wa-client-action" value="CREATE_NEW"' + createChecked + '> Criar novo cliente</label>' +
            '<label class="checkbox"><input class="wa-i-force-create" type="checkbox"> Confirmo criar mesmo se o telefone já estiver cadastrado</label>' +
            '<p class="muted">Salve eventuais alterações acima antes de aprovar. A credencial do aparelho ficará como não informada para coleta na triagem física.</p></div>' +
            '<button class="btn btn-primary wa-intake-save">Salvar revisão</button> <button class="btn btn-success wa-intake-approve">Aprovar e criar OS</button> <button class="btn btn-danger wa-intake-reject">Descartar</button> <button class="btn wa-intake-close">Fechar</button></div>';
        $('#wa-intake-detail').html(h);
    }); }
    function loadQueue() { request('/dados/queue', 'GET', null, function (rows) {
        var h = '<table class="table table-bordered"><thead><tr><th>OS</th><th>Cliente</th><th>Status</th><th>Estado</th><th>Tentativas</th><th>Erro</th><th></th></tr></thead><tbody>';
        $.each(rows, function(_, r) { var retry = (r.state === 'FAILED' || r.state === 'RETRY' || r.state === 'DEFERRED') ? '<button class="btn btn-mini wa-retry" data-id="' + r.id + '">Tentar agora</button>' : '—'; h += '<tr><td>' + esc(r.os_id) + '</td><td>' + esc(r.client_id) + '</td><td>' + esc(r.mapos_status) + '</td><td>' + esc(r.state) + '</td><td>' + esc(r.attempts) + '</td><td>' + esc(r.last_error_code || '—') + '</td><td>' + retry + '</td></tr>'; });
        $('#wa-queue').html(h + '</tbody></table>');
    }); }
    function loadLogs() { request('/dados/logs', 'GET', null, function (rows) {
        var h = '<table class="table table-bordered"><thead><tr><th>Quando</th><th>OS</th><th>Cliente</th><th>Evento</th><th>Estado</th><th>Erro</th></tr></thead><tbody>';
        $.each(rows, function(_, r) { h += '<tr><td>' + esc(r.occurred_at || '—') + '</td><td>' + esc(r.os_id || '—') + '</td><td>' + esc(r.client_id || '—') + '</td><td>' + esc(r.event_type) + '</td><td>' + esc(r.state) + '</td><td>' + esc(r.error_code || '—') + '</td></tr>'; });
        $('#wa-logs-list').html(h + '</tbody></table>');
    }); }
    function loadRules() { request('/dados/status-rules', 'GET', null, function (rows) {
        var h = '<table class="table table-bordered"><thead><tr><th>Status MapOS</th><th>Enviar</th><th>Texto público</th><th>Prioridade</th><th></th></tr></thead><tbody>';
        $.each(rows, function(_, r) { h += '<tr data-id="' + r.id + '"><td>' + esc(r.mapos_status) + '</td><td><input class="wa-enabled" type="checkbox"' + (r.enabled ? ' checked' : '') + '></td><td><input class="wa-label" value="' + esc(r.public_label) + '"></td><td><input class="wa-priority input-mini" type="number" value="' + esc(r.priority) + '"></td><td><button class="btn btn-mini wa-rule-save">Salvar</button></td></tr>'; });
        $('#wa-rules').html(h + '</tbody></table>');
    }); }
    function loadTemplates() { request('/dados/templates', 'GET', null, function (rows) {
        var h = ''; $.each(rows, function(_, r) { h += '<div class="well"><strong>' + esc(r.template_key) + ' v' + esc(r.version) + '</strong><br><textarea class="wa-template-body input-xxlarge" rows="5" data-key="' + esc(r.template_key) + '">' + esc(r.body) + '</textarea><br><button class="btn btn-mini wa-template-save" data-key="' + esc(r.template_key) + '">Salvar nova versão</button></div>'; });
        $('#wa-templates-list').html(h || '<p>Nenhum template disponível.</p>');
    }); }
    function loadSettings() { request('/dados/settings', 'GET', null, function (d) { var runtime = runtimeNotifications ? '' : '<div class="alert alert-warning">O kill switch STATUS_NOTIFICATIONS_ENABLED do Gateway está desligado. Esta opção será salva, mas nenhum envio ocorrerá até ele ser habilitado em um deploy controlado.</div>'; $('#wa-settings').html('<label class="checkbox"><input id="wa-notifications" type="checkbox"' + (d.enabled ? ' checked' : '') + '> Habilitar notificações transacionais de status</label><p class="muted">O interruptor de segurança do ambiente também precisa estar habilitado para qualquer envio ocorrer.</p>' + runtime); }); }
    var logisticsZones = [], logisticsRoutes = [], logisticsCapacity = [], logisticsProfiles = [];
    function byId(rows, id) { var found=null; $.each(rows, function(_, row) { if (String(row.id) === String(id)) { found=row; return false; } }); return found; }
    function optionList(rows, selected) { var h=''; selected=selected || []; $.each(rows, function(_, row) { h += '<option value="' + esc(row.id) + '"' + ($.inArray(Number(row.id), $.map(selected, Number)) >= 0 ? ' selected' : '') + '>' + esc(row.name || row.label || row.rule_key) + '</option>'; }); return h; }
    function enumOptions(values, selected) { var h=''; selected=selected || []; $.each(values, function(_, value) { h += '<option value="' + value + '"' + ($.inArray(value, selected) >= 0 ? ' selected' : '') + '>' + value + '</option>'; }); return h; }
    function selectedValues(element) { return $.map(element.val() || [], function(value) { return /^\d+$/.test(value) ? Number(value) : value; }); }
    function lines(value) { return $.grep($.map(String(value || '').split(/[\r\n]+/), function(item) { item=$.trim(item); return item || null; }), function(item) { return !!item; }); }
    function parsePostalRanges(value) { var result=[], valid=true; $.each(lines(value), function(_, line) { var parts=line.split('..'); if (parts.length !== 2) { valid=false; return false; } var start=parts[0].replace(/\D/g,''), end=parts[1].replace(/\D/g,''); if (start.length !== 8 || end.length !== 8 || start > end) { valid=false; return false; } result.push({start:start,end:end}); }); return valid ? result : null; }
    function renderLogisticsOverview(d) { var counts=d.appointments || {}; $('#wa-logistics-overview').html('<div class="alert alert-info"><strong>Agenda logística ativa.</strong> Zonas: ' + esc(d.active_zones || 0) + ' · Rotas: ' + esc(d.active_routes || 0) + ' · Aguardando confirmação: ' + esc(counts.PENDING_CONFIRMATION || 0) + ' · Confirmados: ' + esc(counts.CONFIRMED || 0) + '</div><p class="muted">Coleta e entrega são movimentos do Gateway e não alteram o status de reparo da OS.</p>'); }
    function renderLogisticsAppointments(rows) { var h='<h4>Movimentos</h4><table class="table table-bordered table-condensed"><thead><tr><th>Operação</th><th>OS</th><th>Zona / rota</th><th>Janela solicitada (UTC)</th><th>Estado</th><th>Ações</th></tr></thead><tbody>'; $.each(rows, function(_, r) { var actions=''; if (r.status === 'PENDING_CONFIRMATION') { actions += '<button class="btn btn-mini btn-success wa-log-action" data-action="confirm" data-id="' + esc(r.id) + '" data-version="' + esc(r.state_version) + '">Confirmar</button> '; } if ($.inArray(r.status, ['REQUESTED','PENDING_CONFIRMATION','CONFIRMED','RESCHEDULE_REQUIRED']) >= 0) { actions += '<button class="btn btn-mini wa-log-action" data-action="location-request" data-id="' + esc(r.id) + '" data-version="' + esc(r.state_version) + '">Link de localização</button> <button class="btn btn-mini wa-log-action" data-action="reschedule-required" data-id="' + esc(r.id) + '" data-version="' + esc(r.state_version) + '">Reagendar</button> <button class="btn btn-mini btn-danger wa-log-action" data-action="cancel" data-id="' + esc(r.id) + '" data-version="' + esc(r.state_version) + '">Cancelar</button>'; } if (r.status === 'CONFIRMED') { actions += ' <button class="btn btn-mini btn-primary wa-log-action" data-action="complete" data-id="' + esc(r.id) + '" data-version="' + esc(r.state_version) + '">Concluir</button>'; } h += '<tr><td>' + esc(r.operation_type) + '</td><td>' + (r.mapos_os_id ? '<a href="' + esc(osEditBase + '/' + r.mapos_os_id) + '">#' + esc(r.mapos_os_id) + '</a>' : '—') + '</td><td>' + esc((r.zone_name || '—') + ' / ' + (r.route_name || '—')) + '</td><td>' + esc(r.requested_window_start || '—') + '<br>' + esc(r.requested_window_end || '') + '</td><td>' + esc(r.status) + '</td><td>' + (actions || '—') + '</td></tr>'; }); $('#wa-logistics-appointments').html(rows.length ? h + '</tbody></table>' : '<h4>Movimentos</h4><p>Nenhuma coleta ou entrega registrada.</p>'); }
    function renderLogisticsZones(rows) { logisticsZones=rows; var h='<h4>Zonas</h4><table class="table table-bordered table-condensed"><thead><tr><th>Nome</th><th>Cidade</th><th>Operações</th><th>Preço</th><th>Ativa</th><th></th></tr></thead><tbody>'; $.each(rows, function(_, r) { h += '<tr><td>' + esc(r.name) + '<br><small>' + esc(r.zone_key) + '</small></td><td>' + esc(r.city) + '</td><td>' + esc((r.allowed_operations || []).join(', ')) + '</td><td>' + esc(r.pricing_mode + (r.fixed_fee ? ' · R$ ' + r.fixed_fee : '')) + '</td><td>' + (r.active ? 'Sim' : 'Não') + '</td><td><button class="btn btn-mini wa-log-zone-edit" data-id="' + r.id + '">Editar</button></td></tr>'; }); h += '</tbody></table><div class="well wa-log-zone-form" data-id="0"><strong>Adicionar zona</strong><div class="row-fluid"><div class="span3"><label>Chave</label><input class="input-block-level wa-lz-key" placeholder="antonina-centro"></div><div class="span3"><label>Nome</label><input class="input-block-level wa-lz-name"></div><div class="span3"><label>Cidade</label><input class="input-block-level wa-lz-city"></div><div class="span3"><label>Preço</label><select class="wa-lz-price"><option>FREE</option><option>FIXED_FEE</option><option>MANUAL_QUOTE</option><option>UNAVAILABLE</option></select> <input class="input-small wa-lz-fee" type="number" min="0" step="0.01" placeholder="Taxa"></div></div><div class="row-fluid"><div class="span4"><label>Bairros (um por linha)</label><textarea class="input-block-level wa-lz-neighborhoods" rows="3"></textarea></div><div class="span4"><label>Faixas de CEP (início..fim)</label><textarea class="input-block-level wa-lz-postals" rows="3" placeholder="83370000..83370999"></textarea></div><div class="span4"><label>Operações</label><select multiple class="input-block-level wa-lz-operations">' + enumOptions(['PICKUP','DELIVERY'], ['PICKUP','DELIVERY']) + '</select><label class="checkbox"><input type="checkbox" class="wa-lz-active" checked> Ativa</label></div></div><button class="btn btn-primary wa-log-zone-save">Salvar zona</button> <button class="btn wa-log-zone-clear">Limpar</button></div>'; $('#wa-logistics-zones').html(h); }
    function renderLogisticsRoutes(rows) { logisticsRoutes=rows; var h='<h4>Rotas</h4><table class="table table-bordered table-condensed"><thead><tr><th>Nome</th><th>Dias</th><th>Janela</th><th>Zonas</th><th>Perfil</th><th></th></tr></thead><tbody>'; $.each(rows, function(_, r) { h += '<tr><td>' + esc(r.name) + '<br><small>' + esc(r.route_key) + '</small></td><td>' + esc((r.weekdays || []).join(', ')) + '</td><td>' + esc(r.window_start + '–' + r.window_end) + '</td><td>' + esc((r.zone_ids || []).join(', ')) + '</td><td>' + esc(r.transport_profile) + '</td><td><button class="btn btn-mini wa-log-route-edit" data-id="' + r.id + '">Editar</button></td></tr>'; }); h += '</tbody></table><div class="well wa-log-route-form" data-id="0"><strong>Adicionar rota</strong><div class="row-fluid"><div class="span3"><label>Chave</label><input class="input-block-level wa-lr-key" placeholder="rota-terca"></div><div class="span3"><label>Nome</label><input class="input-block-level wa-lr-name"></div><div class="span3"><label>Início / fim</label><input class="input-small wa-lr-start" type="time" value="09:00"> <input class="input-small wa-lr-end" type="time" value="12:00"></div><div class="span3"><label>Perfil de transporte</label><input class="input-block-level wa-lr-transport" value="standard"></div></div><div class="row-fluid"><div class="span3"><label>Dias (0=segunda)</label><select multiple class="input-block-level wa-lr-weekdays">' + enumOptions(['0','1','2','3','4','5','6'], []) + '</select></div><div class="span3"><label>Zonas</label><select multiple class="input-block-level wa-lr-zones">' + optionList(logisticsZones, []) + '</select></div><div class="span3"><label>Operações</label><select multiple class="input-block-level wa-lr-operations">' + enumOptions(['PICKUP','DELIVERY'], ['PICKUP','DELIVERY']) + '</select></div><div class="span3"><label>Classes</label><select multiple class="input-block-level wa-lr-classes">' + enumOptions(['COMPACT','MEDIUM','BULKY'], ['COMPACT']) + '</select><label>Antecedência (min)</label><input class="input-small wa-lr-notice" type="number" min="0" value="60"></div></div><label class="checkbox inline"><input type="checkbox" class="wa-lr-location" checked> Exigir localização confirmada</label> <label class="checkbox inline"><input type="checkbox" class="wa-lr-active" checked> Ativa</label><label>Chave de capacidade diária compartilhada (opcional)</label><input class="wa-lr-shared" maxlength="64"><br><button class="btn btn-primary wa-log-route-save">Salvar rota</button> <button class="btn wa-log-route-clear">Limpar</button></div>'; $('#wa-logistics-routes').html(h); }
    function renderLogisticsCapacity(rows) { logisticsCapacity=rows; var h='<h4>Capacidade</h4><table class="table table-bordered table-condensed"><thead><tr><th>Regra</th><th>Rota/escopo</th><th>Classe</th><th>Janela</th><th>Dia</th><th></th></tr></thead><tbody>'; $.each(rows, function(_, r) { h += '<tr><td>' + esc(r.rule_key) + '</td><td>' + esc(r.route_id || r.shared_daily_capacity_key || '—') + '</td><td>' + esc(r.equipment_class) + '</td><td>' + esc(r.max_window_items || '—') + '</td><td>' + esc(r.max_daily_items || '—') + '</td><td><button class="btn btn-mini wa-log-capacity-edit" data-id="' + r.id + '">Editar</button></td></tr>'; }); h += '</tbody></table><div class="well wa-log-capacity-form" data-id="0"><strong>Adicionar regra</strong><div class="row-fluid"><div class="span3"><label>Chave</label><input class="input-block-level wa-lc-key"></div><div class="span3"><label>Rota</label><select class="input-block-level wa-lc-route"><option value="">Capacidade compartilhada</option>' + optionList(logisticsRoutes, []) + '</select></div><div class="span3"><label>Chave compartilhada</label><input class="input-block-level wa-lc-shared"></div><div class="span3"><label>Classe</label><select class="wa-lc-class"><option>*</option><option>COMPACT</option><option>MEDIUM</option><option>BULKY</option></select></div></div><label>Máx. janela</label> <input class="input-mini wa-lc-window" type="number" min="1"> <label class="inline">Máx. dia</label> <input class="input-mini wa-lc-day" type="number" min="1"> <label class="checkbox inline"><input type="checkbox" class="wa-lc-active" checked> Ativa</label><br><button class="btn btn-primary wa-log-capacity-save">Salvar regra</button> <button class="btn wa-log-capacity-clear">Limpar</button></div>'; $('#wa-logistics-capacity').html(h); }
    function renderLogisticsProfiles(rows) { logisticsProfiles=rows; var h='<h4>Perfis de equipamento</h4><table class="table table-bordered table-condensed"><thead><tr><th>Tipo</th><th>Classe</th><th>Transportes</th><th>Ativo</th><th></th></tr></thead><tbody>'; $.each(rows, function(_, r) { h += '<tr><td>' + esc(r.label) + '<br><small>' + esc(r.equipment_type_key) + '</small></td><td>' + esc(r.equipment_class) + '</td><td>' + esc((r.compatible_transport_profiles || []).join(', ')) + '</td><td>' + (r.active ? 'Sim' : 'Não') + '</td><td><button class="btn btn-mini wa-log-profile-edit" data-id="' + r.id + '">Editar</button></td></tr>'; }); h += '</tbody></table><div class="well wa-log-profile-form" data-id="0"><strong>Adicionar perfil</strong><label>Chave do tipo</label><input class="wa-lp-key"> <label class="inline">Nome</label><input class="wa-lp-label"> <label class="inline">Classe</label><select class="wa-lp-class"><option>COMPACT</option><option>MEDIUM</option><option>BULKY</option></select><label>Perfis de transporte (um por linha)</label><textarea class="input-block-level wa-lp-transports" rows="2">standard</textarea><label class="checkbox"><input type="checkbox" class="wa-lp-active" checked> Ativo</label><button class="btn btn-primary wa-log-profile-save">Salvar perfil</button> <button class="btn wa-log-profile-clear">Limpar</button></div>'; $('#wa-logistics-profiles').html(h); }
    function loadLogistics() { request('/dados/logistics-overview', 'GET', null, renderLogisticsOverview); request('/dados/logistics-zones', 'GET', null, function(rows) { renderLogisticsZones(rows); request('/dados/logistics-routes', 'GET', null, function(routes) { renderLogisticsRoutes(routes); request('/dados/logistics-capacity-rules', 'GET', null, renderLogisticsCapacity); }); }); request('/dados/logistics-equipment-profiles', 'GET', null, renderLogisticsProfiles); request('/dados/logistics-appointments', 'GET', null, renderLogisticsAppointments); }
    $(document).on('click', '.wa-lock,.wa-resume', function () { var id=$(this).data('id'), action=$(this).hasClass('wa-lock') ? 'manual-lock' : 'resume'; request('/conversa/' + id + '/' + action, 'POST', {}, loadConversations); });
    $(document).on('click', '.wa-retry', function () { request('/fila/' + $(this).data('id') + '/retry', 'POST', {}, loadQueue); });
    $(document).on('click', '.wa-rule-save', function () { var row=$(this).closest('tr'); request('/regra/' + row.data('id'), 'POST', {enabled: row.find('.wa-enabled').is(':checked'), public_label: row.find('.wa-label').val(), priority: row.find('.wa-priority').val()}, loadRules); });
    $(document).on('click', '.wa-template-save', function () { var key=$(this).data('key'), body=$(this).siblings('.wa-template-body').val(); request('/template/' + key, 'POST', {body: body, enabled: true}, loadTemplates); });
    $(document).on('click', '.wa-intake-open', function () { loadIntake($(this).data('id')); });
    $(document).on('click', '.wa-intake-close', function () { $('#wa-intake-detail').empty(); });
    $(document).on('click', '.wa-intake-save', function () { var form=$(this).closest('.wa-intake-form'); request('/pre_atendimento/' + encodeURIComponent(form.data('id')) + '/save', 'POST', {review_version: form.data('version'), name: form.find('.wa-i-name').val(), city: form.find('.wa-i-city').val(), device_type: form.find('.wa-i-device').val(), brand: form.find('.wa-i-brand').val(), model: form.find('.wa-i-model').val(), problem_description: form.find('.wa-i-problem').val(), service_mode: form.find('.wa-i-mode').val(), notes: form.find('.wa-i-notes').val()}, function (d) { loadIntakes(); loadIntake(d.id); }); });
    $(document).on('click', '.wa-intake-approve', function () { var button=$(this), form=button.closest('.wa-intake-form'), action=form.find('input[name="wa-client-action"]:checked').val(), force=form.find('.wa-i-force-create').is(':checked'); if (action === 'CREATE_NEW' && force && !window.confirm('Confirma a criação de um cliente duplicado com o mesmo telefone?')) { return; } button.prop('disabled',true); request('/pre_atendimento/' + encodeURIComponent(form.data('id')) + '/approve', 'POST', {review_version: form.data('version'), client_action: action, client_id: form.find('.wa-i-client-id').val(), force_create_new: force}, function (d) { $('#wa-intake-detail').html('<div class="alert alert-success">OS #' + esc(d.mapos_os_id) + ' criada com sucesso. <a href="' + esc(osEditBase + '/' + d.mapos_os_id) + '">Abrir OS</a></div>'); loadIntakes(); }).always(function () { button.prop('disabled',false); }); });
    $(document).on('click', '.wa-intake-reject', function () { var form=$(this).closest('.wa-intake-form'), reason=window.prompt('Informe o motivo do descarte:'); if (reason === null) { return; } request('/pre_atendimento/' + encodeURIComponent(form.data('id')) + '/reject', 'POST', {review_version: form.data('version'), reason: reason}, function () { $('#wa-intake-detail').empty(); loadIntakes(); }); });
    $(document).on('change', '#wa-notifications', function () { request('/notificacoes', 'POST', {enabled: $(this).is(':checked')}, loadSettings); });
    $(document).on('click', '.wa-log-action', function () { var button=$(this), action=button.data('action'); if ((action === 'cancel' || action === 'complete') && !window.confirm('Confirma esta ação logística?')) { return; } button.prop('disabled', true); request('/logistica_appointment/' + encodeURIComponent(button.data('id')) + '/' + action, 'POST', {state_version: button.data('version')}, function (d) { if (action === 'location-request') { $('#wa-logistics-location-link').html('<div class="alert alert-success"><strong>Link temporário:</strong> <a target="_blank" rel="noopener noreferrer" href="' + esc(d.url) + '">' + esc(d.url) + '</a><br><small>Expira em ' + esc(d.expires_at) + '. Compartilhe somente com o cliente deste atendimento.</small></div>'); } loadLogistics(); }).always(function () { button.prop('disabled', false); }); });
    $(document).on('click', '.wa-log-zone-save', function () { var form=$(this).closest('.wa-log-zone-form'), ranges=parsePostalRanges(form.find('.wa-lz-postals').val()), fee=form.find('.wa-lz-fee').val(); if (ranges === null) { error('Use uma faixa de CEP por linha no formato 83370000..83370999.'); return; } var payload={zone_key:$.trim(form.find('.wa-lz-key').val()),name:$.trim(form.find('.wa-lz-name').val()),city:$.trim(form.find('.wa-lz-city').val()),neighborhoods:lines(form.find('.wa-lz-neighborhoods').val()),postal_code_ranges:ranges,allowed_operations:selectedValues(form.find('.wa-lz-operations')),pricing_mode:form.find('.wa-lz-price').val(),fixed_fee:fee === '' ? null : Number(fee),active:form.find('.wa-lz-active').is(':checked'),sort_order:100}; request('/logistica_configuracao/zones/' + form.data('id'), 'POST', {payload:JSON.stringify(payload)}, loadLogistics); });
    $(document).on('click', '.wa-log-zone-edit', function () { var r=byId(logisticsZones,$(this).data('id')), form=$('.wa-log-zone-form'); if (!r) { return; } form.data('id',r.id).find('strong').text('Editar zona'); form.find('.wa-lz-key').val(r.zone_key); form.find('.wa-lz-name').val(r.name); form.find('.wa-lz-city').val(r.city); form.find('.wa-lz-neighborhoods').val((r.neighborhoods || []).join('\n')); form.find('.wa-lz-postals').val($.map(r.postal_code_ranges || [],function(p){return p.start+'..'+p.end;}).join('\n')); form.find('.wa-lz-operations').val(r.allowed_operations); form.find('.wa-lz-price').val(r.pricing_mode); form.find('.wa-lz-fee').val(r.fixed_fee || ''); form.find('.wa-lz-active').prop('checked',r.active); });
    $(document).on('click', '.wa-log-zone-clear', function () { renderLogisticsZones(logisticsZones); });
    $(document).on('click', '.wa-log-route-save', function () { var form=$(this).closest('.wa-log-route-form'); var payload={route_key:$.trim(form.find('.wa-lr-key').val()),name:$.trim(form.find('.wa-lr-name').val()),timezone:'America/Sao_Paulo',weekdays:selectedValues(form.find('.wa-lr-weekdays')),window_start:form.find('.wa-lr-start').val(),window_end:form.find('.wa-lr-end').val(),allowed_operations:selectedValues(form.find('.wa-lr-operations')),transport_profile:$.trim(form.find('.wa-lr-transport').val()),supported_equipment_classes:selectedValues(form.find('.wa-lr-classes')),minimum_notice_minutes:Number(form.find('.wa-lr-notice').val() || 0),requires_exact_location:form.find('.wa-lr-location').is(':checked'),shared_daily_capacity_key:$.trim(form.find('.wa-lr-shared').val()) || null,active:form.find('.wa-lr-active').is(':checked'),sort_order:100,zone_ids:selectedValues(form.find('.wa-lr-zones'))}; request('/logistica_configuracao/routes/' + form.data('id'), 'POST', {payload:JSON.stringify(payload)}, loadLogistics); });
    $(document).on('click', '.wa-log-route-edit', function () { var r=byId(logisticsRoutes,$(this).data('id')), form=$('.wa-log-route-form'); if (!r) { return; } form.data('id',r.id).find('strong').text('Editar rota'); form.find('.wa-lr-key').val(r.route_key); form.find('.wa-lr-name').val(r.name); form.find('.wa-lr-start').val(r.window_start); form.find('.wa-lr-end').val(r.window_end); form.find('.wa-lr-transport').val(r.transport_profile); form.find('.wa-lr-weekdays').val($.map(r.weekdays,String)); form.find('.wa-lr-zones').val($.map(r.zone_ids,String)); form.find('.wa-lr-operations').val(r.allowed_operations); form.find('.wa-lr-classes').val(r.supported_equipment_classes); form.find('.wa-lr-notice').val(r.minimum_notice_minutes); form.find('.wa-lr-location').prop('checked',r.requires_exact_location); form.find('.wa-lr-shared').val(r.shared_daily_capacity_key || ''); form.find('.wa-lr-active').prop('checked',r.active); });
    $(document).on('click', '.wa-log-route-clear', function () { renderLogisticsRoutes(logisticsRoutes); });
    $(document).on('click', '.wa-log-capacity-save', function () { var form=$(this).closest('.wa-log-capacity-form'), route=form.find('.wa-lc-route').val(), windowMax=form.find('.wa-lc-window').val(), dayMax=form.find('.wa-lc-day').val(); var payload={rule_key:$.trim(form.find('.wa-lc-key').val()),route_id:route ? Number(route) : null,shared_daily_capacity_key:$.trim(form.find('.wa-lc-shared').val()) || null,equipment_class:form.find('.wa-lc-class').val(),max_window_items:windowMax ? Number(windowMax) : null,max_daily_items:dayMax ? Number(dayMax) : null,active:form.find('.wa-lc-active').is(':checked')}; request('/logistica_configuracao/capacity-rules/' + form.data('id'), 'POST', {payload:JSON.stringify(payload)}, loadLogistics); });
    $(document).on('click', '.wa-log-capacity-edit', function () { var r=byId(logisticsCapacity,$(this).data('id')), form=$('.wa-log-capacity-form'); if (!r) { return; } form.data('id',r.id).find('strong').text('Editar regra'); form.find('.wa-lc-key').val(r.rule_key); form.find('.wa-lc-route').val(r.route_id || ''); form.find('.wa-lc-shared').val(r.shared_daily_capacity_key || ''); form.find('.wa-lc-class').val(r.equipment_class); form.find('.wa-lc-window').val(r.max_window_items || ''); form.find('.wa-lc-day').val(r.max_daily_items || ''); form.find('.wa-lc-active').prop('checked',r.active); });
    $(document).on('click', '.wa-log-capacity-clear', function () { renderLogisticsCapacity(logisticsCapacity); });
    $(document).on('click', '.wa-log-profile-save', function () { var form=$(this).closest('.wa-log-profile-form'); var payload={equipment_type_key:$.trim(form.find('.wa-lp-key').val()),label:$.trim(form.find('.wa-lp-label').val()),equipment_class:form.find('.wa-lp-class').val(),compatible_transport_profiles:lines(form.find('.wa-lp-transports').val()),active:form.find('.wa-lp-active').is(':checked')}; request('/logistica_configuracao/equipment-profiles/' + form.data('id'), 'POST', {payload:JSON.stringify(payload)}, loadLogistics); });
    $(document).on('click', '.wa-log-profile-edit', function () { var r=byId(logisticsProfiles,$(this).data('id')), form=$('.wa-log-profile-form'); if (!r) { return; } form.data('id',r.id).find('strong').text('Editar perfil'); form.find('.wa-lp-key').val(r.equipment_type_key); form.find('.wa-lp-label').val(r.label); form.find('.wa-lp-class').val(r.equipment_class); form.find('.wa-lp-transports').val((r.compatible_transport_profiles || []).join('\n')); form.find('.wa-lp-active').prop('checked',r.active); });
    $(document).on('click', '.wa-log-profile-clear', function () { renderLogisticsProfiles(logisticsProfiles); });
    loadOverview(); loadConversations(); loadIntakes(); loadLogistics(); loadQueue(); loadLogs(); loadRules(); loadTemplates();
}(jQuery));
</script>
