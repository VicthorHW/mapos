(function ($) {
    'use strict';

    window.__tecninaWhatsappPanel = {executed: true, booted: false};
    var config = $('#wa-panel-config');
    var base = String(config.attr('data-base') || '');
    var osEditBase = String(config.attr('data-os-edit-base') || '');
    var csrfName = String(config.attr('data-csrf-name') || '');
    var csrfHash = String(config.attr('data-csrf-hash') || '');
    var currentList = 'pending';

    function esc(value) { return $('<div>').text(value == null ? '' : value).html(); }
    function error(message) { $('#wa-error').text(message || 'Não foi possível comunicar com o Gateway.').show(); }
    function clearError() { $('#wa-error').hide().text(''); }
    function emptyDetail(message) {
        $('#wa-intake-detail').attr('class', 'wa-intake-empty').html('<i class="bx bx-list-check"></i><strong>' + esc(message || 'Selecione um pré-atendimento') + '</strong><span>Os dados para revisão aparecerão aqui.</span>');
    }
    function reasonMessage(reason) {
        var messages = {
            intake_review_conflict: 'Este pré-atendimento foi alterado. Atualize a lista e revise novamente.',
            existing_client_required: 'Informe o ID do cliente existente.',
            client_name_required: 'Informe o nome antes de criar um cliente.',
            incomplete_intake: 'Revise e salve todos os campos obrigatórios antes de aprovar.',
            invalid_operator: 'O usuário atual não pode ser vinculado à OS.',
            ambiguous_client: 'Há mais de um cliente com este telefone. Localize o cadastro correto e informe seu ID.',
            client_match_changed: 'O cadastro correspondente ao telefone mudou. Atualize a revisão.',
            duplicate_client_requires_decision: 'Já existe um cliente com este telefone. Vincule o cadastro ou confirme a criação duplicada.',
            approval_in_progress: 'Esta aprovação já está em processamento. Aguarde e atualize a lista.',
            mapos_unavailable: 'O MapOS não respondeu à aprovação. Tente novamente.',
            approval_unavailable: 'Não foi possível criar a OS. Nenhum cadastro parcial foi mantido.',
            gateway_not_configured: 'O Gateway não está configurado no MapOS.',
            gateway_unavailable: 'O Gateway está indisponível no momento.',
            invalid_pickup_fee: 'Informe uma taxa de coleta válida.',
            pickup_fee_not_confirmed: 'A taxa de coleta precisa ser informada e confirmada pelo cliente antes da aprovação.'
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
    function shortDate(value) {
        var date = parseDate(value);
        if (!date) { return '—'; }
        return date.toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit'}) + ' ' +
            date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
    }
    function statusMeta(value) {
        return {
            READY: {label: 'Para revisar', css: 'wa-status-ready'},
            UNDER_REVIEW: {label: 'Em revisão', css: 'wa-status-review'},
            APPROVING: {label: 'Criando OS', css: 'wa-status-progress'},
            INCOMPLETE: {label: 'Incompleto', css: 'wa-status-closed'},
            EXPIRED: {label: 'Expirado', css: 'wa-status-closed'},
            REJECTED: {label: 'Descartado', css: 'wa-status-closed'},
            APPROVED: {label: 'Aprovado', css: 'wa-state-auto'}
        }[value] || {label: 'Estado desconhecido', css: 'wa-status-closed'};
    }
    function displayName(row) { return row.mapos_client_name || row.name || 'Nome não informado'; }
    function gpsPanel(data) {
        if (!data.gps_available) { return '<br><strong>GPS opcional:</strong> Não informado'; }
        var latitude = Number(data.gps_latitude);
        var longitude = Number(data.gps_longitude);
        if (!isFinite(latitude) || !isFinite(longitude) || latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
            return '<br><strong>GPS opcional:</strong> Recebido, mas sem coordenadas válidas para exibição';
        }
        var delta = 0.003;
        var bbox = [longitude - delta, latitude - delta, longitude + delta, latitude + delta].join(',');
        var mapUrl = 'https://www.openstreetmap.org/export/embed.html?bbox=' + encodeURIComponent(bbox) + '&layer=mapnik&marker=' + encodeURIComponent(latitude + ',' + longitude);
        var googleUrl = 'https://www.google.com/maps?q=' + encodeURIComponent(latitude + ',' + longitude);
        var accuracy = data.gps_accuracy_meters ? ' · precisão aproximada de ' + esc(data.gps_accuracy_meters) + ' m' : '';
        return '<div class="wa-gps-panel"><div class="wa-gps-heading"><div><strong>Localização enviada por GPS</strong><span>' + esc(latitude.toFixed(6) + ', ' + longitude.toFixed(6)) + accuracy + '</span></div><a class="btn btn-mini btn-primary" href="' + esc(googleUrl) + '" target="_blank" rel="noopener noreferrer">Abrir no Google Maps</a></div><iframe title="Mapa da localização de coleta" loading="lazy" referrerpolicy="no-referrer" src="' + esc(mapUrl) + '"></iframe><p class="muted">Mapa: OpenStreetMap. O botão do Google Maps usa somente um link de coordenadas, sem API paga.</p></div>';
    }
    function intakeTable(rows, historical) {
        if (!rows.length) { return '<p class="muted">' + (historical ? 'Nenhum registro no histórico.' : 'Nenhum pré-atendimento aguardando revisão.') + '</p>'; }
        var html = '<div class="wa-table-wrap"><table class="table table-bordered wa-table wa-intake-table"><thead><tr><th class="wa-col-date">Atualizado</th><th>Contato</th><th>Nome</th><th>Equipamento</th><th class="wa-col-city">Cidade</th><th class="wa-col-status">Status</th><th class="wa-col-action"></th></tr></thead><tbody>';
        $.each(rows, function (_, row) {
            var status = statusMeta(row.status);
            html += '<tr><td>' + esc(shortDate(row.ready_at || row.updated_at)) + '</td><td>' + esc(row.phone_display || '—') + '</td><td><strong>' + esc(displayName(row)) + '</strong></td><td>' + esc(row.equipment || '—') + '</td><td>' + esc(row.city || '—') + '</td><td><span class="wa-state-badge ' + status.css + '">' + esc(status.label) + '</span></td><td><button class="btn btn-mini ' + (historical ? '' : 'btn-primary') + ' wa-intake-open" data-id="' + esc(row.id) + '">' + (historical ? 'Consultar' : 'Revisar') + '</button></td></tr>';
        });
        return html + '</tbody></table></div>';
    }
    function loadList() {
        var historical = currentList === 'history';
        $('#wa-intakes-list').attr('class', 'wa-loading').html('<i class="fas fa-spinner fa-spin"></i> Carregando…');
        request(historical ? '/dados/intake-history' : '/dados/intakes', 'GET', null, function (rows) {
            $('#wa-intakes-list').removeClass('wa-loading').html('<h4>' + (historical ? 'Histórico de pré-atendimentos' : 'Aguardando revisão') + '</h4>' + intakeTable(rows, historical));
        });
    }
    function pickupSummary(data) {
        if (data.service_mode !== 'PICKUP_REQUESTED') { return ''; }
        return '<div class="well well-small"><strong>Coleta confirmada pelo cliente</strong><br>' +
            esc((data.street || '—') + ', ' + (data.street_number || '—') + ' — ' + (data.neighborhood || '—')) + '<br>' +
            esc((data.city || '—') + '/' + (data.address_state || 'PR') + ' — CEP ' + (data.postal_code || '—')) +
            (data.complement ? '<br><strong>Complemento:</strong> ' + esc(data.complement) : '') +
            (data.reference ? '<br><strong>Referência:</strong> ' + esc(data.reference) : '') +
            '<br><strong>Taxa:</strong> ' + esc(data.pickup_fee == null ? 'A confirmar pela equipe' : 'R$ ' + String(data.pickup_fee).replace('.', ',')) +
            gpsPanel(data) + '</div>';
    }
    function pickupFeeAction(data, actionable) {
        if (!actionable || data.service_mode !== 'PICKUP_REQUESTED' || data.pickup_fee_status !== 'MANUAL_QUOTE') { return ''; }
        return '<div class="alert alert-warning wa-pickup-fee-action"><strong>Taxa aguardando definição</strong><p>Informe o valor calculado pela equipe. O cliente receberá a proposta no WhatsApp e deverá confirmar antes da criação da OS.</p><div class="input-append"><input class="input-small wa-i-pickup-fee" inputmode="decimal" placeholder="0,00"><button class="btn btn-warning wa-intake-offer-fee" type="button">Enviar taxa</button></div></div>';
    }
    function credentialSummary(data) {
        var status = data.credential_status || 'DECLINED';
        if (status === 'NONE') { return 'Equipamento informado como sem senha.'; }
        if (status === 'PROVIDED') {
            return data.credential_type === 'PATTERN' ? 'Padrão de desenho recebido por canal seguro.' : 'Senha/PIN recebida por canal seguro.';
        }
        return 'Credencial não informada; deverá ser tratada na triagem física.';
    }
    function loadIntake(id) {
        request('/pre_atendimento/' + encodeURIComponent(id), 'GET', null, function (data) {
            var pickup = data.service_mode === 'PICKUP_REQUESTED';
            var actionable = data.status === 'READY' || data.status === 'UNDER_REVIEW';
            var existingId = data.possible_mapos_client_id || data.mapos_client_id || '';
            var linkChecked = existingId ? ' checked' : '';
            var createChecked = existingId ? '' : ' checked';
            var status = statusMeta(data.status);
            var name = data.mapos_client_name || data.name || '';
            var readonlyNotice = actionable ? '' : '<div class="alert alert-info">Registro histórico somente para consulta. Situação: <strong>' + esc(status.label) + '</strong>.</div>';
            var actions = actionable ? '<button class="btn btn-primary wa-intake-save">Salvar revisão</button> <button class="btn btn-success wa-intake-approve">Aprovar e criar OS</button> <button class="btn btn-danger wa-intake-reject">Descartar</button>' : '';
            var html = '<div class="well wa-intake-form" data-id="' + esc(data.id) + '" data-version="' + esc(data.review_version) + '">' +
                '<h4>Pré-atendimento</h4><p class="muted">Identificador ' + esc(data.id) + '</p>' +
                '<p><strong>WhatsApp:</strong> ' + esc(data.phone_display || '—') + ' &nbsp; <span class="wa-state-badge ' + status.css + '">' + esc(status.label) + '</span></p>' + readonlyNotice +
                '<div class="row-fluid"><div class="span7"><label>Nome</label><input class="input-block-level wa-i-name" maxlength="120" value="' + esc(name) + '"></div><div class="span5"><label>Cidade</label><input class="input-block-level wa-i-city" maxlength="80" value="' + esc(data.city || '') + '"></div></div>' +
                '<div class="row-fluid"><div class="span4"><label>Equipamento</label><input class="input-block-level wa-i-device" maxlength="80" value="' + esc(data.device_type || '') + '"></div><div class="span4"><label>Marca</label><input class="input-block-level wa-i-brand" maxlength="80" value="' + esc(data.brand || '') + '"></div><div class="span4"><label>Modelo</label><input class="input-block-level wa-i-model" maxlength="120" value="' + esc(data.model || '') + '"></div></div>' +
                '<label>Problema informado</label><textarea class="input-block-level wa-i-problem" maxlength="2000" rows="5">' + esc(data.problem_description || '') + '</textarea>' +
                '<label>Forma de atendimento</label><select class="input-block-level wa-i-mode"><option value="DROP_OFF"' + (!pickup ? ' selected' : '') + '>Cliente traz o equipamento</option><option value="PICKUP_REQUESTED"' + (pickup ? ' selected' : '') + '>Solicitação de coleta</option></select>' +
                pickupSummary(data) +
                pickupFeeAction(data, actionable) +
                '<label>Observações internas</label><textarea class="input-block-level wa-i-notes" maxlength="2000" rows="4">' + esc(data.notes || '') + '</textarea>' +
                '<div class="well well-small"><strong>Destino no MapOS</strong><label class="radio"><input type="radio" name="wa-client-action" value="LINK_EXISTING"' + linkChecked + '> Vincular cliente existente</label><label>ID do cliente</label><input class="input-small wa-i-client-id" type="number" min="1" value="' + esc(existingId) + '"><label class="radio"><input type="radio" name="wa-client-action" value="CREATE_NEW"' + createChecked + '> Criar novo cliente</label><label class="checkbox"><input class="wa-i-force-create" type="checkbox"> Confirmo criar mesmo se o telefone já estiver cadastrado</label><p class="muted">O endereço de coleta não substituirá o endereço cadastral. ' + esc(credentialSummary(data)) + '</p></div>' +
                '<div class="wa-form-actions">' + actions + '</div></div>';
            $('#wa-intake-detail').attr('class', '').html(html);
            if (!actionable) { $('#wa-intake-detail').find('input,select,textarea').prop('disabled', true); }
        });
    }
    function formData(form) {
        return {
            review_version: form.data('version'),
            name: form.find('.wa-i-name').val(),
            city: form.find('.wa-i-city').val(),
            device_type: form.find('.wa-i-device').val(),
            brand: form.find('.wa-i-brand').val(),
            model: form.find('.wa-i-model').val(),
            problem_description: form.find('.wa-i-problem').val(),
            service_mode: form.find('.wa-i-mode').val(),
            notes: form.find('.wa-i-notes').val()
        };
    }
    $(document).on('click', '.wa-history-filter button', function () {
        currentList = $(this).data('list');
        $('.wa-history-filter button').removeClass('btn-primary active');
        $(this).addClass('btn-primary active');
        emptyDetail(currentList === 'history' ? 'Selecione um registro do histórico' : 'Selecione um pré-atendimento');
        loadList();
    });
    $(document).on('click', '.wa-intake-open', function () { loadIntake(String($(this).data('id'))); });
    $(document).on('click', '.wa-intake-save', function () {
        var form = $(this).closest('.wa-intake-form');
        request('/pre_atendimento/' + encodeURIComponent(form.data('id')) + '/save', 'POST', formData(form), function (data) { loadList(); loadIntake(data.id); });
    });
    $(document).on('click', '.wa-intake-offer-fee', function () {
        var form = $(this).closest('.wa-intake-form');
        var fee = String(form.find('.wa-i-pickup-fee').val() || '').trim();
        if (!fee) { error('Informe o valor da taxa de coleta.'); return; }
        request('/pre_atendimento/' + encodeURIComponent(form.data('id')) + '/pickup-fee', 'POST', {
            review_version: form.data('version'), fee: fee
        }, function () {
            emptyDetail('Taxa enviada; aguardando confirmação do cliente');
            loadList();
        });
    });
    $(document).on('click', '.wa-intake-approve', function () {
        var form = $(this).closest('.wa-intake-form');
        var action = form.find('input[name="wa-client-action"]:checked').val();
        request('/pre_atendimento/' + encodeURIComponent(form.data('id')) + '/approve', 'POST', {
            review_version: form.data('version'), client_action: action, client_id: form.find('.wa-i-client-id').val(), force_create_new: form.find('.wa-i-force-create').is(':checked')
        }, function (data) {
            var osId = data.mapos_os_id || data.os_id;
            $('#wa-intake-detail').attr('class', '').html('<div class="alert alert-success"><strong>Pré-atendimento aprovado.</strong><br>OS ' + esc(osId) + ' criada com sucesso. <a href="' + esc(osEditBase + '/' + osId) + '">Abrir a OS</a>.</div>');
            currentList = 'pending'; loadList();
        });
    });
    $(document).on('click', '.wa-intake-reject', function () {
        var form = $(this).closest('.wa-intake-form');
        var reason = window.prompt('Informe o motivo do descarte:');
        if (!reason) { return; }
        request('/pre_atendimento/' + encodeURIComponent(form.data('id')) + '/reject', 'POST', {review_version: form.data('version'), reason: reason}, function () { emptyDetail('Pré-atendimento descartado'); loadList(); });
    });
    var booted = false;
    function bootPanel() {
        if (booted) { return; }
        booted = true;
        window.__tecninaWhatsappPanel.booted = true;
        loadList();
    }
    $(bootPanel);
    window.setTimeout(bootPanel, 500);
}(jQuery));
