(function ($) {
    'use strict';

    window.__tecninaWhatsappPanel = {executed: true, booted: false};
    var config = $('#wa-panel-config');
    var base = String(config.attr('data-base') || '');
    var csrfName = String(config.attr('data-csrf-name') || '');
    var csrfHash = String(config.attr('data-csrf-hash') || '');
    var overviewData = null;
    var runtimeNotifications = false;
    var loaded = {};

    function esc(value) { return $('<div>').text(value == null ? '' : value).html(); }
    function error(message) { $('#wa-error').text(message || 'Não foi possível comunicar com o Gateway.').show(); }
    function clearError() { $('#wa-error').hide().text(''); }
    function reasonMessage(reason) {
        var messages = {
            gateway_not_configured: 'O Gateway não está configurado no MapOS.',
            gateway_unavailable: 'O Gateway está indisponível no momento.',
            gateway_request_failed: 'O Gateway recusou a solicitação.',
            invalid_gateway_response: 'O Gateway devolveu uma resposta inválida.'
        };
        return messages[reason] || 'Não foi possível concluir a operação.';
    }
    function request(path, method, data, done, retryAttempt) {
        data = data || {};
        if (method !== 'GET') { data[csrfName] = csrfHash; }
        return $.ajax({url: base + path, method: method, data: data, dataType: 'json', timeout: 12000})
            .done(function (response) {
                if (response.csrf) { csrfHash = response.csrf; }
                if (!response.ok) { error(reasonMessage(response.reason)); return; }
                clearError();
                done(response.data);
            })
            .fail(function (xhr) {
                var response = xhr.responseJSON || {};
                if (response.csrf) { csrfHash = response.csrf; }
                if (method === 'GET' && !retryAttempt) {
                    window.setTimeout(function () { request(path, method, data, done, true); }, 800);
                    return;
                }
                error(reasonMessage(response.reason));
            });
    }
    function parseDate(value) {
        if (!value) { return null; }
        var normalized = String(value);
        if (!/[zZ]|[+-]\d\d:\d\d$/.test(normalized)) { normalized += 'Z'; }
        var date = new Date(normalized);
        return isNaN(date.getTime()) ? null : date;
    }
    function shortDate(value, prefix) {
        var date = parseDate(value);
        if (!date) { return '—'; }
        var text = date.toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit'}) + ' às ' +
            date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
        return prefix ? prefix + ' ' + text : text;
    }
    function healthCard(name, ok, detail, warning) {
        var state = ok ? 'is-ok' : (warning ? 'is-warning' : 'is-error');
        return '<div class="wa-health-card ' + state + '"><span class="wa-health-dot"></span><div><strong>' +
            esc(name) + '</strong><span>' + esc(detail) + '</span></div></div>';
    }
    function componentLabel(component) {
        if (!component) { return 'Sem informação'; }
        return component.ok ? 'Funcionando normalmente' : 'Indisponível';
    }
    function renderConnection() {
        if (!overviewData) { return; }
        var components = overviewData.components || {};
        var evolution = components.evolution || {};
        var gateway = components.gateway || {};
        var mapos = components.mapos || {};
        var manager = evolution.manager_url ? '<p style="margin:14px 0 0"><a class="btn btn-primary" href="' + esc(evolution.manager_url) + '" target="_blank" rel="noopener noreferrer">Abrir gerenciador da sessão</a></p>' : '';
        $('#wa-connection').html('<div class="wa-connection-card">' +
            '<div class="wa-connection-line"><span>Sessão WhatsApp</span><strong>' + esc(componentLabel(evolution)) + '</strong></div>' +
            '<div class="wa-connection-line"><span>Gateway</span><strong>' + esc(componentLabel(gateway)) + '</strong></div>' +
            '<div class="wa-connection-line"><span>MapOS</span><strong>' + esc(componentLabel(mapos)) + '</strong></div>' +
            '<p class="muted" style="margin:12px 0 0">A autenticação e o QR Code continuam restritos ao gerenciador da Evolution.</p>' + manager + '</div>');
    }
    function loadOverview() {
        request('/dados/overview', 'GET', null, function (data) {
            overviewData = data;
            runtimeNotifications = data.runtime_notifications_enabled === true;
            var components = data.components || {};
            var queue = data.queue || {};
            var pending = (queue.PENDING || 0) + (queue.RETRY || 0) + (queue.DEFERRED || 0);
            $('#wa-overview').html(
                healthCard('Gateway', components.gateway && components.gateway.ok, componentLabel(components.gateway)) +
                healthCard('MapOS', components.mapos && components.mapos.ok, componentLabel(components.mapos)) +
                healthCard('Evolution', components.evolution && components.evolution.ok, componentLabel(components.evolution)) +
                healthCard('Fila de envios', pending === 0, pending === 0 ? 'Nenhum envio pendente' : pending + ' envio(s) pendente(s)', pending > 0)
            );
            renderConnection();
            loaded.overview = true;
        });
    }
    function conversationState(row) {
        if (row.state === 'AUTO') { return {label: 'Atendimento automático', css: 'wa-state-auto'}; }
        if (row.state === 'HUMAN_TEMPORARY') { return {label: 'Atendimento humano em andamento', css: 'wa-state-human'}; }
        if (row.hold_source === 'CLIENT_REQUEST') { return {label: 'Cliente solicitou atendimento humano', css: 'wa-state-human'}; }
        if (row.hold_source === 'OPERATOR') { return {label: 'Pausa manual', css: 'wa-state-manual'}; }
        return {label: 'Atendimento humano', css: 'wa-state-human'};
    }
    function holdUntil(row) {
        if (row.state === 'AUTO') { return '—'; }
        if (!row.human_until) { return 'Sem retorno automático'; }
        return shortDate(row.human_until, 'Até');
    }
    function loadConversations() {
        request('/dados/conversations', 'GET', null, function (rows) {
            if (!rows.length) { $('#wa-conversations').html('<p class="muted">Nenhuma conversa registrada.</p>'); return; }
            var html = '<div class="wa-table-wrap"><table class="table table-bordered wa-table"><thead><tr><th>Contato</th><th>Atendimento</th><th>Retorno do bot</th><th>Última atividade</th><th>Ação</th></tr></thead><tbody>';
            $.each(rows, function (_, row) {
                var state = conversationState(row);
                var action = row.state === 'AUTO'
                    ? '<button class="btn btn-mini wa-lock" data-id="' + row.id + '">Pausar bot</button>'
                    : '<button class="btn btn-mini btn-primary wa-resume" data-id="' + row.id + '">Retomar bot</button>';
                html += '<tr><td><strong>' + esc(row.phone_display || row.phone_tail || '—') + '</strong></td>' +
                    '<td><span class="wa-state-badge ' + state.css + '">' + esc(state.label) + '</span></td>' +
                    '<td>' + esc(holdUntil(row)) + '</td><td>' + esc(shortDate(row.last_activity_at, '')) + '</td>' +
                    '<td class="wa-actions">' + action + '</td></tr>';
            });
            $('#wa-conversations').html(html + '</tbody></table></div>');
            loaded.conversations = true;
        });
    }
    function queueState(value) {
        return {PENDING: 'Pendente', RETRY: 'Nova tentativa', DEFERRED: 'Adiado', FAILED: 'Falhou', SENT: 'Enviado', ACKNOWLEDGED: 'Confirmado', DELIVERY_UNKNOWN: 'Entrega incerta'}[value] || value;
    }
    function loadQueue() {
        request('/dados/queue', 'GET', null, function (rows) {
            if (!rows.length) { $('#wa-queue').html('<p class="muted">Nenhum envio na fila.</p>'); return; }
            var html = '<div class="wa-table-wrap"><table class="table table-bordered wa-table"><thead><tr><th>OS</th><th>Status</th><th>Fila</th><th>Tentativas</th><th>Erro</th><th></th></tr></thead><tbody>';
            $.each(rows, function (_, row) {
                var retry = (row.state === 'FAILED' || row.state === 'RETRY' || row.state === 'DEFERRED') ? '<button class="btn btn-mini wa-retry" data-id="' + row.id + '">Tentar agora</button>' : '—';
                html += '<tr><td>' + esc(row.os_id) + '</td><td>' + esc(row.mapos_status) + '</td><td>' + esc(queueState(row.state)) + '</td><td>' + esc(row.attempts) + '</td><td>' + esc(row.last_error_code || '—') + '</td><td>' + retry + '</td></tr>';
            });
            $('#wa-queue').html(html + '</tbody></table></div>');
            loaded.queue = true;
        });
    }
    function loadLogs() {
        request('/dados/logs', 'GET', null, function (rows) {
            if (!rows.length) { $('#wa-logs-list').html('<p class="muted">Nenhum evento recente.</p>'); return; }
            var html = '<div class="wa-table-wrap"><table class="table table-bordered wa-table"><thead><tr><th>Quando</th><th>OS</th><th>Evento</th><th>Estado</th><th>Erro</th></tr></thead><tbody>';
            $.each(rows, function (_, row) { html += '<tr><td>' + esc(shortDate(row.occurred_at, '')) + '</td><td>' + esc(row.os_id || '—') + '</td><td>' + esc(row.event_type) + '</td><td>' + esc(queueState(row.state)) + '</td><td>' + esc(row.error_code || '—') + '</td></tr>'; });
            $('#wa-logs-list').html(html + '</tbody></table></div>');
            loaded.logs = true;
        });
    }
    function loadRules() {
        request('/dados/status-rules', 'GET', null, function (rows) {
            var html = '<div class="wa-table-wrap"><table class="table table-bordered wa-table"><thead><tr><th>Status MapOS</th><th>Enviar</th><th>Texto público</th><th>Prioridade</th><th></th></tr></thead><tbody>';
            $.each(rows, function (_, row) { html += '<tr data-id="' + row.id + '"><td>' + esc(row.mapos_status) + '</td><td><input class="wa-enabled" type="checkbox"' + (row.enabled ? ' checked' : '') + '></td><td><input class="wa-label input-block-level" value="' + esc(row.public_label) + '"></td><td><input class="wa-priority input-mini" type="number" value="' + esc(row.priority) + '"></td><td><button class="btn btn-mini wa-rule-save">Salvar</button></td></tr>'; });
            $('#wa-rules').html(html + '</tbody></table></div>');
        });
    }
    function loadTemplates() {
        request('/dados/templates', 'GET', null, function (rows) {
            var html = '';
            $.each(rows, function (_, row) { html += '<div class="well"><strong>' + esc(row.template_key) + ' · versão ' + esc(row.version) + '</strong><br><textarea class="wa-template-body input-block-level" rows="5" data-key="' + esc(row.template_key) + '">' + esc(row.body) + '</textarea><button class="btn btn-mini wa-template-save" data-key="' + esc(row.template_key) + '">Salvar nova versão</button></div>'; });
            $('#wa-templates-list').html(html || '<p class="muted">Nenhum template disponível.</p>');
        });
    }
    function loadSettings() {
        request('/dados/settings', 'GET', null, function (data) {
            var runtime = runtimeNotifications ? '' : '<div class="alert alert-warning">O interruptor STATUS_NOTIFICATIONS_ENABLED do Gateway está desligado. A preferência pode ser salva, mas os envios continuarão suspensos até um deploy controlado.</div>';
            $('#wa-settings').html('<label class="checkbox"><input id="wa-notifications" type="checkbox"' + (data.enabled ? ' checked' : '') + '> Habilitar atualizações automáticas de status da OS</label><p class="muted">Este controle afeta notificações transacionais e não o menu conversacional do bot.</p>' + runtime);
        });
    }
    function loadAutomaticMessages() {
        if (loaded.automatic) { return; }
        loaded.automatic = true;
        loadSettings(); loadRules(); loadTemplates();
    }
    function pickupModeLabel(mode) { return {FIXED_FEE: 'Taxa fixa', NEIGHBORHOOD: 'Por bairro', MANUAL_QUOTE: 'Valor definido pela equipe'}[mode] || mode; }
    function renderPickupCities(rows) {
        var html = '<div class="alert alert-info"><strong>Área atendida.</strong> O bot usa somente cidades ativas e nunca inventa uma taxa.</div>';
        $.each(rows, function (_, city) {
            var rates = '';
            $.each(city.neighborhood_rates || [], function (_, rate) { rates += '<tr><td>' + esc(rate.neighborhood) + '</td><td>R$ ' + esc(rate.fee) + '</td><td>' + (rate.active ? 'Ativo' : 'Inativo') + '</td><td><button class="btn btn-mini btn-danger wa-pickup-rate-delete" data-city="' + city.id + '" data-id="' + rate.id + '">Excluir</button></td></tr>'; });
            html += '<div class="wa-city-card wa-pickup-city" data-id="' + city.id + '"><div class="wa-city-card-header"><h5>' + esc(city.city) + ' / ' + esc(city.uf) + '</h5><span class="wa-state-badge ' + (city.active ? 'wa-state-auto' : 'wa-status-closed') + '">' + (city.active ? 'Ativa' : 'Inativa') + '</span></div>' +
                '<div class="row-fluid wa-city-fields"><div class="span3"><label>Cidade</label><input class="input-block-level wa-pc-city" maxlength="120" value="' + esc(city.city) + '"></div><div class="span1"><label>UF</label><input class="input-block-level wa-pc-uf" maxlength="2" value="' + esc(city.uf) + '"></div><div class="span3"><label>Precificação</label><select class="input-block-level wa-pc-mode"><option value="FIXED_FEE"' + (city.pricing_mode === 'FIXED_FEE' ? ' selected' : '') + '>Taxa fixa</option><option value="NEIGHBORHOOD"' + (city.pricing_mode === 'NEIGHBORHOOD' ? ' selected' : '') + '>Por bairro</option><option value="MANUAL_QUOTE"' + (city.pricing_mode === 'MANUAL_QUOTE' ? ' selected' : '') + '>Definida pela equipe</option></select></div><div class="span2"><label>Taxa fixa</label><input class="input-block-level wa-pc-fee" type="number" min="0" step="0.01" value="' + esc(city.flat_fee || '') + '"></div><div class="span3"><label class="checkbox"><input class="wa-pc-active" type="checkbox"' + (city.active ? ' checked' : '') + '> Cidade ativa</label><button class="btn btn-primary wa-pickup-city-save">Salvar cidade</button></div></div><p class="muted">Modo atual: ' + esc(pickupModeLabel(city.pricing_mode)) + '</p>';
            if (city.pricing_mode === 'NEIGHBORHOOD') {
                html += '<div class="wa-city-neighborhoods"><h5>Bairros e taxas</h5><table class="table table-bordered table-condensed"><thead><tr><th>Bairro</th><th>Taxa</th><th>Estado</th><th></th></tr></thead><tbody>' + (rates || '<tr><td colspan="4">Nenhum bairro cadastrado; a equipe fará a cotação.</td></tr>') + '</tbody></table><div class="form-inline"><input class="wa-pr-name" maxlength="120" placeholder="Nome oficial do bairro"> <input class="input-small wa-pr-fee" type="number" min="0" step="0.01" placeholder="Taxa"> <label class="checkbox inline"><input class="wa-pr-active" type="checkbox" checked> Ativo</label> <button class="btn wa-pickup-rate-save">Adicionar bairro</button></div></div>';
            }
            html += '</div>';
        });
        html += '<div class="wa-city-card wa-pickup-city" data-id="0"><div class="wa-city-card-header"><h5>Adicionar cidade</h5></div><div class="row-fluid wa-city-fields"><div class="span3"><label>Cidade</label><input class="input-block-level wa-pc-city" maxlength="120"></div><div class="span1"><label>UF</label><input class="input-block-level wa-pc-uf" maxlength="2" value="PR"></div><div class="span3"><label>Precificação</label><select class="input-block-level wa-pc-mode"><option value="MANUAL_QUOTE">Definida pela equipe</option><option value="FIXED_FEE">Taxa fixa</option><option value="NEIGHBORHOOD">Por bairro</option></select></div><div class="span2"><label>Taxa fixa</label><input class="input-block-level wa-pc-fee" type="number" min="0" step="0.01"></div><div class="span3"><label class="checkbox"><input class="wa-pc-active" type="checkbox" checked> Cidade ativa</label><button class="btn btn-primary wa-pickup-city-save">Adicionar cidade</button></div></div></div>';
        $('#wa-pickup-cities').html(html);
    }
    function loadPickupCities() { request('/dados/pickup-cities', 'GET', null, function (rows) { renderPickupCities(rows); loaded.cities = true; }); }
    function renderDropoffSchedule(data) {
        var html = '<div class="alert alert-info"><strong>Entrega presencial.</strong> Estes horários são apenas informativos e o bot mostra os próximos quatro dias disponíveis, sem criar reserva.</div>' +
            '<div class="wa-city-card"><label><strong>Endereço da TecNina</strong></label><textarea id="wa-dropoff-address" class="input-block-level" rows="2" maxlength="500">' + esc(data.address || '') + '</textarea>' +
            '<input id="wa-dropoff-timezone" type="hidden" value="' + esc(data.timezone || 'America/Sao_Paulo') + '">' +
            '<div class="wa-schedule-days">';
        $.each(data.days || [], function (_, day) {
            html += '<div class="wa-schedule-day" data-weekday="' + day.weekday + '"><div class="wa-schedule-day-head"><label class="checkbox"><input class="wa-day-enabled" type="checkbox"' + (day.enabled ? ' checked' : '') + '> <strong>' + esc(day.label) + '</strong></label><button type="button" class="btn btn-mini wa-period-add">Adicionar período</button></div><div class="wa-periods">';
            $.each(day.periods || [], function (_, period) {
                html += periodRow(period.start, period.end);
            });
            html += '</div></div>';
        });
        html += '</div><div class="wa-form-actions"><button type="button" class="btn btn-primary" id="wa-dropoff-save">Salvar horários de entrega</button></div></div>';
        $('#wa-dropoff-schedule').html(html);
    }
    function periodRow(start, end) {
        return '<div class="wa-period-row"><label>Das <input class="input-small wa-period-start" type="time" value="' + esc(start || '09:00') + '"></label><label>até <input class="input-small wa-period-end" type="time" value="' + esc(end || '18:00') + '"></label><button type="button" class="btn btn-mini btn-danger wa-period-remove" aria-label="Remover período">Remover</button></div>';
    }
    function loadDropoffSchedule() {
        request('/dados/dropoff-schedule', 'GET', null, function (data) {
            renderDropoffSchedule(data);
            loaded.dropoff = true;
        });
    }
    function collectDropoffSchedule() {
        var days = [];
        $('.wa-schedule-day').each(function () {
            var day = $(this), periods = [];
            day.find('.wa-period-row').each(function () {
                periods.push({start: String($(this).find('.wa-period-start').val() || ''), end: String($(this).find('.wa-period-end').val() || '')});
            });
            days.push({weekday: Number(day.data('weekday')), enabled: day.find('.wa-day-enabled').is(':checked'), periods: periods});
        });
        return {address: String($('#wa-dropoff-address').val() || '').trim(), timezone: String($('#wa-dropoff-timezone').val() || 'America/Sao_Paulo'), days: days};
    }

    $(document).on('click', '.wa-lock,.wa-resume', function () { var button = $(this); button.prop('disabled', true); request('/conversa/' + button.data('id') + '/' + (button.hasClass('wa-lock') ? 'manual-lock' : 'resume'), 'POST', {}, loadConversations); });
    $(document).on('click', '.wa-retry', function () { request('/fila/' + $(this).data('id') + '/retry', 'POST', {}, loadQueue); });
    $(document).on('click', '.wa-rule-save', function () { var row = $(this).closest('tr'); request('/regra/' + row.data('id'), 'POST', {enabled: row.find('.wa-enabled').is(':checked'), public_label: row.find('.wa-label').val(), priority: row.find('.wa-priority').val()}, loadRules); });
    $(document).on('click', '.wa-template-save', function () { var button = $(this); request('/template/' + encodeURIComponent(button.data('key')), 'POST', {body: button.siblings('.wa-template-body').val(), enabled: true}, loadTemplates); });
    $(document).on('change', '#wa-notifications', function () { request('/notificacoes', 'POST', {enabled: $(this).is(':checked')}, loadSettings); });
    $(document).on('click', '.wa-pickup-city-save', function () { var card = $(this).closest('.wa-pickup-city'); request('/coleta/save-city/' + card.data('id'), 'POST', {city: card.find('.wa-pc-city').val(), uf: card.find('.wa-pc-uf').val(), pricing_mode: card.find('.wa-pc-mode').val(), flat_fee: card.find('.wa-pc-fee').val(), active: card.find('.wa-pc-active').is(':checked')}, loadPickupCities); });
    $(document).on('click', '.wa-pickup-rate-save', function () { var card = $(this).closest('.wa-pickup-city'); request('/coleta/save-neighborhood/' + card.data('id'), 'POST', {neighborhood: card.find('.wa-pr-name').val(), fee: card.find('.wa-pr-fee').val(), active: card.find('.wa-pr-active').is(':checked')}, loadPickupCities); });
    $(document).on('click', '.wa-pickup-rate-delete', function () { if (window.confirm('Excluir esta taxa de bairro?')) { request('/coleta/delete-neighborhood/' + $(this).data('city') + '/' + $(this).data('id'), 'POST', {}, loadPickupCities); } });
    $(document).on('click', '.wa-period-add', function () { $(this).closest('.wa-schedule-day').find('.wa-periods').append(periodRow('', '')); });
    $(document).on('click', '.wa-period-remove', function () { $(this).closest('.wa-period-row').remove(); });
    $(document).on('click', '#wa-dropoff-save', function () { request('/entrega_configuracao', 'POST', {schedule_json: JSON.stringify(collectDropoffSchedule())}, loadDropoffSchedule); });
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (event) {
        var target = $(event.target).attr('href');
        if (target === '#wa-conversas' && !loaded.conversations) { loadConversations(); }
        else if (target === '#wa-cidades' && !loaded.cities) { loadPickupCities(); }
        else if (target === '#wa-entrega' && !loaded.dropoff) { loadDropoffSchedule(); }
        else if (target === '#wa-conexao') { if (!loaded.queue) { loadQueue(); } if (!overviewData) { loadOverview(); } else { renderConnection(); } }
        else if (target === '#wa-automaticas') { loadAutomaticMessages(); }
        else if (target === '#wa-logs' && !loaded.logs) { loadLogs(); }
    });
    var booted = false;
    function bootPanel() {
        if (booted) { return; }
        booted = true;
        window.__tecninaWhatsappPanel.booted = true;
        loadOverview();
    }
    $(bootPanel);
    window.setTimeout(bootPanel, 500);
}(jQuery));
