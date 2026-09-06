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
                    <li><a href="#wa-fila" data-toggle="tab">Fila</a></li>
                    <li><a href="#wa-logs" data-toggle="tab">Logs</a></li>
                    <li><a href="#wa-regras" data-toggle="tab">Regras de status</a></li>
                    <li><a href="#wa-templates" data-toggle="tab">Templates</a></li>
                    <li><a href="#wa-config" data-toggle="tab">Configuração</a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane active" id="wa-conversas"><div id="wa-conversations">Carregando…</div></div>
                    <div class="tab-pane" id="wa-intakes"><div id="wa-intakes-list">Carregando…</div><div id="wa-intake-detail"></div></div>
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
    var csrfName = <?= json_encode($csrfName) ?>, csrfHash = <?= json_encode($csrfHash) ?>;
    var runtimeNotifications = false;
    function esc(value) { return $('<div>').text(value == null ? '' : value).html(); }
    function error(message) { $('#wa-error').text(message || 'Não foi possível comunicar com o Gateway.').show(); }
    function request(path, method, data, done) {
        data = data || {}; if (method !== 'GET') { data[csrfName] = csrfHash; }
        $.ajax({url: base + path, method: method, data: data, dataType: 'json'})
            .done(function (response) { if (response.csrf) { csrfHash = response.csrf; } if (!response.ok) { error(); return; } done(response.data); })
            .fail(function () { error(); });
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
            '<div class="alert alert-info">A criação de cliente e OS será habilitada somente após a validação do contrato de aprovação.</div>' +
            '<button class="btn btn-primary wa-intake-save">Salvar revisão</button> <button class="btn btn-danger wa-intake-reject">Descartar</button> <button class="btn wa-intake-close">Fechar</button></div>';
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
    $(document).on('click', '.wa-lock,.wa-resume', function () { var id=$(this).data('id'), action=$(this).hasClass('wa-lock') ? 'manual-lock' : 'resume'; request('/conversa/' + id + '/' + action, 'POST', {}, loadConversations); });
    $(document).on('click', '.wa-retry', function () { request('/fila/' + $(this).data('id') + '/retry', 'POST', {}, loadQueue); });
    $(document).on('click', '.wa-rule-save', function () { var row=$(this).closest('tr'); request('/regra/' + row.data('id'), 'POST', {enabled: row.find('.wa-enabled').is(':checked'), public_label: row.find('.wa-label').val(), priority: row.find('.wa-priority').val()}, loadRules); });
    $(document).on('click', '.wa-template-save', function () { var key=$(this).data('key'), body=$(this).siblings('.wa-template-body').val(); request('/template/' + key, 'POST', {body: body, enabled: true}, loadTemplates); });
    $(document).on('click', '.wa-intake-open', function () { loadIntake($(this).data('id')); });
    $(document).on('click', '.wa-intake-close', function () { $('#wa-intake-detail').empty(); });
    $(document).on('click', '.wa-intake-save', function () { var form=$(this).closest('.wa-intake-form'); request('/pre_atendimento/' + encodeURIComponent(form.data('id')) + '/save', 'POST', {review_version: form.data('version'), name: form.find('.wa-i-name').val(), city: form.find('.wa-i-city').val(), device_type: form.find('.wa-i-device').val(), brand: form.find('.wa-i-brand').val(), model: form.find('.wa-i-model').val(), problem_description: form.find('.wa-i-problem').val(), service_mode: form.find('.wa-i-mode').val(), notes: form.find('.wa-i-notes').val()}, function (d) { loadIntakes(); loadIntake(d.id); }); });
    $(document).on('click', '.wa-intake-reject', function () { var form=$(this).closest('.wa-intake-form'), reason=window.prompt('Informe o motivo do descarte:'); if (reason === null) { return; } request('/pre_atendimento/' + encodeURIComponent(form.data('id')) + '/reject', 'POST', {review_version: form.data('version'), reason: reason}, function () { $('#wa-intake-detail').empty(); loadIntakes(); }); });
    $(document).on('change', '#wa-notifications', function () { request('/notificacoes', 'POST', {enabled: $(this).is(':checked')}, loadSettings); });
    loadOverview(); loadConversations(); loadIntakes(); loadQueue(); loadLogs(); loadRules(); loadTemplates();
}(jQuery));
</script>
