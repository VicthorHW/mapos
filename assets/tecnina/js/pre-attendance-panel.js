(function ($) {
    'use strict';

    window.__tecninaWhatsappPanel = {executed: true, booted: false};
    var config = $('#wa-panel-config');
    var base = String(config.attr('data-base') || '');
    var receivingBase = String(config.attr('data-receiving-base') || '/tecnina/pre-atendimentos');
    var osEditBase = String(config.attr('data-os-edit-base') || '');
    var csrfName = $('meta[name="csrf-token-name"]').attr('content') || String(config.attr('data-csrf-name') || '');
    var csrfHash = $('meta[name="csrf-token"]').attr('content') || String(config.attr('data-csrf-hash') || '');
    var currentList = 'pending';
    var currentIntakeData = null;

    function updateCsrf(token) {
        if (!token) { return; }
        csrfHash = token;
        $('meta[name="csrf-token"]').attr('content', token);
        config.attr('data-csrf-hash', token);
    }

    function esc(value) { return $('<div>').text(value == null ? '' : value).html(); }
    function error(message) { $('#wa-error').text(message || 'Não foi possível comunicar com o Gateway.').show(); }
    function clearError() { $('#wa-error').hide().text(''); }
    function emptyDetail(message) {
        currentIntakeData = null;
        $('#wa-intake-detail').attr('class', 'wa-intake-empty').html('<i class="bx bx-list-check"></i><strong>' + esc(message || 'Selecione um pré-atendimento') + '</strong><span>Os dados para revisão aparecerão aqui.</span>');
    }

    function uuidv4() {
        if (window.crypto && window.crypto.randomUUID) {
            return window.crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    function reasonMessage(reason) {
        var debugMode = false;
        try { debugMode = window.localStorage.getItem('tecnina_debug') === '1'; } catch (e) {}

        var parsedReason = reason || 'unknown';
        var detail = '';
        if (parsedReason.indexOf(':') !== -1) {
            var parts = parsedReason.split(':');
            parsedReason = parts[0];
            detail = parts.slice(1).join(':').trim();
        }

        var messages = {
            intake_review_conflict: 'Este pré-atendimento foi alterado. Atualize a lista e revise novamente.',
            existing_client_required: 'Informe o ID do cliente existente.',
            client_name_required: 'Informe o nome antes de criar um cliente.',
            incomplete_intake: 'Revise os seguintes campos obrigatórios ausentes: ' + (detail ? detail : 'equipamento, cliente ou endereço') + '.',
            invalid_operator: 'O usuário atual não pode ser vinculado ao recebimento.',
            ambiguous_client: 'Há mais de um cliente com este telefone. Localize o cadastro correto e informe seu ID.',
            client_match_changed: 'O cadastro correspondente ao telefone mudou. Atualize a revisão.',
            duplicate_client_requires_decision: 'Já existe um cliente com este telefone. Vincule o cadastro ou confirme a criação duplicada.',
            approval_in_progress: 'Esta aprovação já está em processamento. Aguarde e atualize a lista.',
            mapos_unavailable: 'O MapOS não respondeu. Tente novamente.',
            approval_unavailable: 'Não foi possível concluir. Nenhum dado parcial foi mantido.',
            gateway_not_configured: 'O Gateway não está configurado no MapOS.',
            gateway_unavailable: 'O Gateway está indisponível no momento.',
            invalid_pickup_fee: 'Informe uma taxa de coleta válida.',
            invalid_intake_fields: 'Revise os campos. Faltam informações obrigatórias do equipamento, cliente ou endereço.',
            pickup_fee_not_confirmed: 'A taxa de coleta precisa ser informada e confirmada pelo cliente antes da aprovação.',
            idempotency_conflict: 'Uma confirmação diferente já foi registrada com esta chave de idempotência.',
            receiving_already_confirmed: 'O recebimento físico deste equipamento já foi confirmado e não pode ser alterado.',
            invalid_idempotency_key: 'Chave de idempotência ausente ou inválida.',
            file_size_exceeded: 'O arquivo excede o limite máximo permitido de 15 MiB.',
            intake_total_size_exceeded: 'O tamanho total de anexos deste atendimento ultrapassou o limite de 60 MiB.',
            unsupported_file_type: 'Tipo de arquivo não permitido. Apenas JPEG, PNG e PDF são aceitos.',
            extension_mime_mismatch: 'A extensão do arquivo não corresponde ao seu tipo real.',
            dangerous_content_detected: 'Arquivo rejeitado por conter código potencialmente perigoso.',
            server_error: 'Erro interno ao processar a requisição no servidor. Tente novamente ou verifique os logs.',
            error: 'Erro ao comunicar com o servidor ou processar a solicitação.',
            unauthorized: 'Sessão expirada. Recarregue a página ou faça login novamente.',
            forbidden: 'Você não tem permissão para realizar esta operação.'
        };
        var msg = messages[parsedReason] || ('Operação não concluída (' + parsedReason + ').');
        if (debugMode) {
            msg += ' [Debug: ' + reason + ']';
            console.error('TecNina Debug Error:', reason);
        }
        return msg;
    }

    function request(path, method, data, done, retryAttempt, extraHeaders) {
        data = data || {};
        if (method !== 'GET' && !(data instanceof FormData)) {
            data[csrfName] = csrfHash;
        }
        var url;
        if (path.indexOf('http://') === 0 || path.indexOf('https://') === 0) {
            url = path;
        } else if (path.indexOf('/tecnina/pre-atendimentos') === 0) {
            url = receivingBase.replace(/\/+$/, '') + path.substring('/tecnina/pre-atendimentos'.length);
        } else if (path.indexOf('/index.php') === 0) {
            url = path;
        } else {
            url = base.replace(/\/+$/, '') + '/' + path.replace(/^\/+/, '');
        }
        var ajaxOpts = {
            url: url,
            method: method,
            data: data,
            dataType: 'json',
            timeout: 15000,
            headers: extraHeaders || {}
        };
        if (data instanceof FormData) {
            data.append(csrfName, csrfHash);
            ajaxOpts.processData = false;
            ajaxOpts.contentType = false;
        }

        return $.ajax(ajaxOpts)
            .done(function (response) {
                if (typeof response === 'string') {
                    try { response = JSON.parse(response); } catch (e) {}
                }
                if (response && response.csrf) {
                    updateCsrf(response.csrf);
                }
                if (!response || !response.ok) { error(reasonMessage(response ? response.reason : 'unknown')); return; }
                clearError();
                done(response.data !== undefined ? response.data : response);
            })
            .fail(function (xhr) {
                var response = xhr.responseJSON || {};
                if (typeof response === 'string') {
                    try { response = JSON.parse(response); } catch (e) {}
                }
                if (response && response.csrf) {
                    updateCsrf(response.csrf);
                }
                if (method === 'GET' && !retryAttempt) {
                    window.setTimeout(function () { request(path, method, data, done, true, extraHeaders); }, 800);
                    return;
                }
                var errReason = response.reason;
                if (!errReason) {
                    if (xhr.status === 500) {
                        errReason = 'server_error';
                    } else if (xhr.status === 403) {
                        errReason = 'forbidden';
                    } else if (xhr.status === 401) {
                        errReason = 'unauthorized';
                    } else {
                        errReason = xhr.statusText || 'error';
                    }
                }
                error(reasonMessage(errReason));
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

    function formatBytes(bytes) {
        if (!bytes || bytes <= 0) { return '0 B'; }
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }

    function statusMeta(row) {
        var value = typeof row === 'string' ? row : row.status;
        if (value === 'COLLECTING' && typeof row === 'object' && row.service_mode === 'PICKUP_REQUESTED') {
            if (row.pickup_fee_status === 'PENDING_TEAM') { return {label: 'Aguardando envio da taxa', css: 'wa-status-progress'}; }
            if (row.pickup_fee_status === 'PENDING_CUSTOMER') { return {label: 'Aguardando confirmação da taxa', css: 'wa-status-progress'}; }
        }
        return {
            READY: {label: 'Para revisar', css: 'wa-status-ready'},
            UNDER_REVIEW: {label: 'Em revisão', css: 'wa-status-review'},
            COLLECTING: {label: 'Aguardando conclusão', css: 'wa-status-progress'},
            APPROVING: {label: 'Criando OS', css: 'wa-status-progress'},
            INCOMPLETE: {label: 'Incompleto', css: 'wa-status-closed'},
            EXPIRED: {label: 'Expirado', css: 'wa-status-closed'},
            REJECTED: {label: 'Descartado', css: 'wa-status-closed'},
            APPROVED: {label: 'Aprovado', css: 'wa-state-auto'}
        }[value] || {label: 'Estado desconhecido', css: 'wa-status-closed'};
    }

    function displayName(row) { return row.mapos_client_name || row.name || 'Nome não informado'; }

    function gpsPanel(data) {
        if (!data.gps_available) { return '<br><strong>GPS:</strong> Não informado'; }
        var latitude = Number(data.gps_latitude);
        var longitude = Number(data.gps_longitude);
        if (!isFinite(latitude) || !isFinite(longitude) || latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
            return '<br><strong>GPS:</strong> Recebido, mas sem coordenadas válidas para exibição';
        }
        var delta = 0.003;
        var bbox = [longitude - delta, latitude - delta, longitude + delta, latitude + delta].join(',');
        var mapUrl = 'https://www.openstreetmap.org/export/embed.html?bbox=' + encodeURIComponent(bbox) + '&layer=mapnik&marker=' + encodeURIComponent(latitude + ',' + longitude);
        var googleUrl = 'https://www.google.com/maps?q=' + encodeURIComponent(latitude + ',' + longitude);
        var accuracy = data.gps_accuracy_meters ? ' · precisão aprox. ' + esc(data.gps_accuracy_meters) + ' m' : '';
        var sourceText = data.gps_source === 'WEB_FORM' ? 'GPS recebido pelo formulário web' : 'GPS recebido pelo WhatsApp';
        return '<div class="wa-gps-panel"><div class="wa-gps-heading"><div><strong>' + esc(sourceText) + '</strong><span>' + esc(latitude.toFixed(6) + ', ' + longitude.toFixed(6)) + accuracy + '</span></div><a class="btn btn-mini btn-primary" href="' + esc(googleUrl) + '" target="_blank" rel="noopener noreferrer"><i class="fas fa-map-marker-alt"></i> Abrir no Google Maps</a></div><iframe title="Mapa da localização de coleta" loading="lazy" referrerpolicy="no-referrer" src="' + esc(mapUrl) + '"></iframe></div>';
    }

    function intakeTable(rows, historical) {
        if (!rows.length) { return '<p class="muted">' + (historical ? 'Nenhum registro no histórico.' : 'Nenhum pré-atendimento aguardando revisão.') + '</p>'; }
        var html = '<div class="wa-table-wrap"><table class="table table-bordered wa-table wa-intake-table"><thead><tr><th class="wa-col-date">Atualizado</th><th>Contato</th><th>Nome</th><th>Equipamento</th><th class="wa-col-city">Cidade</th><th class="wa-col-status">Status</th><th class="wa-col-action"></th></tr></thead><tbody>';
        $.each(rows, function (_, row) {
            var status = statusMeta(row);
            html += '<tr><td>' + esc(shortDate(row.ready_at || row.updated_at)) + '</td><td>' + esc(row.phone_display || '—') + '</td><td><strong>' + esc(displayName(row)) + '</strong></td><td>' + esc(row.equipment || '—') + '</td><td>' + esc(row.city || '—') + '</td><td><span class="wa-state-badge ' + status.css + '">' + esc(status.label) + '</span></td><td><button class="btn btn-mini ' + (historical ? '' : 'btn-primary') + ' wa-intake-open" data-id="' + esc(row.id) + '">' + (historical ? 'Consultar' : 'Receber / Revisar') + '</button></td></tr>';
        });
        return html + '</tbody></table></div>';
    }

    function loadList() {
        var historical = currentList === 'history';
        $('#wa-intakes-list').attr('class', 'wa-loading').html('<i class="fas fa-spinner fa-spin"></i> Carregando…');
        request(historical ? '/dados/intake-history' : '/dados/intakes', 'GET', null, function (rows) {
            $('#wa-intakes-list').removeClass('wa-loading').html('<h4>' + (historical ? 'Histórico de pré-atendimentos' : 'Aguardando recebimento e revisão') + '</h4>' + intakeTable(rows, historical));
        });
    }

    function pickupSummary(data) {
        if (data.service_mode !== 'PICKUP_REQUESTED') { return ''; }
        return '<div class="well well-small"><strong><i class="fas fa-truck"></i> Coleta confirmada pelo cliente</strong><br>' +
            esc((data.street || '—') + ', ' + (data.street_number || '—') + ' — ' + (data.neighborhood || '—')) + '<br>' +
            esc((data.city || '—') + '/' + (data.address_state || 'PR') + ' — CEP ' + (data.postal_code || '—')) +
            (data.complement ? '<br><strong>Complemento:</strong> ' + esc(data.complement) : '') +
            (data.reference ? '<br><strong>Referência:</strong> ' + esc(data.reference) : '') +
            '<br><strong>Taxa de coleta:</strong> ' + esc(data.pickup_fee == null ? 'A confirmar pela equipe' : 'R$ ' + String(data.pickup_fee).replace('.', ',')) +
            gpsPanel(data) + '</div>';
    }

    function pickupFeeAction(data, actionable) {
        if (!actionable || data.service_mode !== 'PICKUP_REQUESTED' || data.pickup_fee_status !== 'PENDING_TEAM') { return ''; }
        return '<div class="alert alert-warning wa-pickup-fee-action"><strong>Taxa aguardando definição</strong><p>Informe o valor calculado pela equipe. O cliente receberá a proposta no WhatsApp e deverá confirmar antes da triagem.</p><div class="input-append"><input class="input-small wa-i-pickup-fee" inputmode="decimal" placeholder="0,00"><button class="btn btn-warning wa-intake-offer-fee" type="button">Enviar taxa</button></div></div>';
    }

    function credentialSummary(data) {
        var status = data.credential_status || 'DECLINED';
        if (status === 'NONE') { return 'Equipamento sem senha (informado pelo cliente).'; }
        if (status === 'PROVIDED') {
            return data.credential_type === 'PATTERN' ? 'Padrão de desenho recebido por canal seguro.' : 'Senha/PIN recebida por canal seguro.';
        }
        return 'Credencial não informada no formulário.';
    }

    function renderAttachmentsGallery(attachments, intakeId) {
        if (!attachments || !attachments.length) {
            return '<p class="muted" id="wa-no-attachments">Nenhuma foto ou anexo cadastrado neste recebimento.</p>';
        }
        var html = '<div class="wa-attachment-gallery row-fluid" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px;">';
        $.each(attachments, function (_, att) {
            var isImage = att.detected_mime === 'image/jpeg' || att.detected_mime === 'image/png';
            var downloadUrl = receivingBase + '/' + encodeURIComponent(intakeId) + '/attachments/' + encodeURIComponent(att.id);
            var thumbUrl = receivingBase + '/' + encodeURIComponent(intakeId) + '/attachments/' + encodeURIComponent(att.id) + '/thumbnail';
            var preview = isImage
                ? '<img src="' + esc(thumbUrl) + '" alt="' + esc(att.original_name) + '" style="max-height:80px;max-width:110px;border-radius:3px;display:block;margin:0 auto 4px;">'
                : '<div style="font-size:36px;color:#c0392b;text-align:center;padding:10px 0;"><i class="fas fa-file-pdf"></i></div>';

            html += '<div class="wa-attachment-card well well-small span4" style="margin:0;width:130px;text-align:center;box-sizing:border-box;">' +
                '<a href="' + esc(downloadUrl) + '" target="_blank" title="' + esc(att.original_name) + '">' + preview + '</a>' +
                '<div style="font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + esc(att.original_name) + '">' + esc(att.original_name) + '</div>' +
                '<small class="muted">' + formatBytes(att.size_bytes) + '</small>' +
                '<div style="margin-top:4px;"><a href="' + esc(downloadUrl) + '" class="btn btn-mini btn-info" title="Baixar"><i class="fas fa-download"></i></a> ' +
                '<button type="button" class="btn btn-mini btn-danger wa-attachment-delete" data-id="' + esc(att.id) + '" title="Excluir"><i class="fas fa-trash"></i></button></div>' +
                '</div>';
        });
        html += '</div>';
        return html;
    }

    function renderReadinessPlaceholder(intakeId) {
        return '<div class="well well-small" id="wa-readiness-box" style="margin-top:16px;background:#fcfcfc;">' +
            '<div style="display:flex;justify-content:space-between;align-items:center;">' +
            '<h6><i class="fas fa-traffic-light"></i> Gate de Prontidão para Abertura de OS (S06B)</h6>' +
            '<button type="button" class="btn btn-mini btn-info" id="btn-recheck-readiness" data-id="' + esc(intakeId) + '"><i class="fas fa-sync"></i> Revalidar</button>' +
            '</div>' +
            '<div id="wa-readiness-content" class="muted" style="font-size:12px;margin-top:6px;"><i class="fas fa-spinner fa-spin"></i> Avaliando dimensões de prontidão…</div>' +
            '</div>';
    }

    function renderReadinessDetails(report) {
        var physicalBadge = (report.physical_receiving === 'RECEIVED')
            ? '<span class="label label-success"><i class="fas fa-check"></i> POSSE CONFIRMADA</span>'
            : '<span class="label label-warning"><i class="fas fa-clock"></i> PENDENTE</span>';

        var identityBadge = (report.identity_resolution === 'READY')
            ? '<span class="label label-success"><i class="fas fa-check"></i> RESOLVIDO</span>'
            : (report.identity_resolution === 'AMBIGUOUS' ? '<span class="label label-important">AMBÍGUO</span>' : '<span class="label label-warning">PENDENTE</span>');

        var regBadge = (report.registration === 'READY_TO_MATERIALIZE' || report.registration === 'EXISTING_ACCOUNT')
            ? '<span class="label label-success"><i class="fas fa-check"></i> ' + esc(report.registration) + '</span>'
            : '<span class="label label-warning"><i class="fas fa-clock"></i> CADASTRO PENDENTE</span>';

        var credBadge = (report.credential === 'PRESENT' || report.credential === 'EXISTING_ACCOUNT')
            ? '<span class="label label-success"><i class="fas fa-check"></i> ' + esc(report.credential) + '</span>'
            : '<span class="label label-warning"><i class="fas fa-key"></i> AUSENTE</span>';

        var legalBadge = (report.legal === 'SATISFIED')
            ? '<span class="label label-success"><i class="fas fa-check"></i> SATISFEITO</span>'
            : '<span class="label label-important"><i class="fas fa-times"></i> PENDENTE</span>';

        var snapshotBadge = (report.intake_snapshot === 'READY')
            ? '<span class="label label-success"><i class="fas fa-check"></i> SELADO</span>'
            : '<span class="label label-important"><i class="fas fa-times"></i> ' + esc(report.intake_snapshot) + '</span>';

        var items = '<table class="table table-condensed table-bordered" style="margin-top:8px;font-size:11px;background:#fff;">' +
            '<thead><tr><th>Dimensão</th><th>Status</th></tr></thead>' +
            '<tbody>' +
            '<tr><td><strong>1. Posse Física (ADR-004)</strong></td><td>' + physicalBadge + '</td></tr>' +
            '<tr><td><strong>2. Identificação do Cliente</strong></td><td>' + identityBadge + '</td></tr>' +
            '<tr><td><strong>3. Cadastro de Conta</strong></td><td>' + regBadge + '</td></tr>' +
            '<tr><td><strong>4. Credencial de Acesso</strong></td><td>' + credBadge + '</td></tr>' +
            '<tr><td><strong>5. Manifestações Legais</strong></td><td>' + legalBadge + '</td></tr>' +
            '<tr><td><strong>6. Snapshot Autoritativo</strong></td><td>' + snapshotBadge + '</td></tr>' +
            '</tbody></table>';

        var statusAlert = '';
        if (report.ready) {
            statusAlert = '<div class="alert alert-success" style="margin-bottom:0;"><i class="fas fa-check-circle"></i> <strong>Gate S06B Satisfeito:</strong> Todos os requisitos foram cumpridos. Pronto para abertura de OS (aguardando conversão S07).</div>';
        } else {
            var reasonsHtml = '';
            if (report.blocking_reasons && report.blocking_reasons.length > 0) {
                reasonsHtml = '<ul style="margin:4px 0 0 16px;">';
                $.each(report.blocking_reasons, function (i, r) {
                    reasonsHtml += '<li>' + esc(r) + '</li>';
                });
                reasonsHtml += '</ul>';
            }
            statusAlert = '<div class="alert alert-block alert-warning" style="margin-bottom:0;"><i class="fas fa-lock"></i> <strong>Abertura de OS Bloqueada (Gate S06B):</strong> Requisitos pendentes:' + reasonsHtml + '</div>';
        }

        return items + statusAlert;
    }

    function fetchReadiness(intakeId) {
        var base = $('#wa-panel-config').data('receiving-base');
        var url = base + '/' + encodeURIComponent(intakeId) + '/readiness';
        $.getJSON(url, function (res) {
            if (res.ok && res.readiness) {
                $('#wa-readiness-content').html(renderReadinessDetails(res.readiness));
            } else {
                $('#wa-readiness-content').html('<span class="text-error">Erro ao carregar prontidão.</span>');
            }
        }).fail(function () {
            $('#wa-readiness-content').html('<span class="text-error">Falha na verificação de prontidão.</span>');
        });
    }

    function loadIntake(id) {
        request('/pre_atendimento/' + encodeURIComponent(id), 'GET', null, function (data) {
            currentIntakeData = data;
            var pickup = data.service_mode === 'PICKUP_REQUESTED';
            var actionable = data.status === 'READY' || data.status === 'UNDER_REVIEW';
            var feeActionable = data.status === 'COLLECTING' && pickup && data.pickup_fee_status === 'PENDING_TEAM';
            var existingId = data.possible_mapos_client_id || data.mapos_client_id || '';
            var status = statusMeta(data);
            var name = displayName(data);

            var receiving = data.receiving || {
                state: 'PENDING_DELIVERY',
                device_condition: null,
                accessories: null,
                serial_number: null,
                imei: null,
                other_identifiers: null,
                notes: null,
                received_at: null,
                received_by_name: null
            };

            var isReceived = receiving.state === 'RECEIVED';

            // State Banner
            var stateBanner = '';
            if (isReceived) {
                stateBanner = '<div class="alert alert-success wa-receiving-status-banner">' +
                    '<h4><i class="fas fa-check-circle"></i> Equipamento Recebido Fisicamente</h4>' +
                    '<p style="margin:4px 0 0 0;">Confirmado por <strong>' + esc(receiving.received_by_name || 'Equipe TecNina') + '</strong> em ' +
                    esc(shortDate(receiving.received_at)) + ' UTC.</p>' +
                    '<small class="muted"><i class="fas fa-shield-alt"></i> TecNina possui fisicamente o equipamento. Ordem de Serviço não criada (aguardando gate de prontidão S06B/S07).</small>' +
                    '</div>';
            } else {
                stateBanner = '<div class="alert alert-warning wa-receiving-status-banner">' +
                    '<h4><i class="fas fa-box-open"></i> Aguardando Recebimento Físico</h4>' +
                    '<small class="muted">Preencha a conferência física e confirme a posse quando o equipamento for entregue na TecNina.</small>' +
                    '</div>';
            }

            // Customer verification card
            var clientResolutionCard = '';
            if (existingId) {
                clientResolutionCard = '<div class="well well-small" style="background:#f4fbf4;border-color:#bce8f1;">' +
                    '<strong><i class="fas fa-user-check text-success"></i> Cliente Cadastrado no MapOS</strong><br>' +
                    '<span>Nome: <strong>' + esc(name) + '</strong> · ID MapOS: <strong>#' + esc(existingId) + '</strong></span><br>' +
                    '<small class="muted"><i class="fas fa-info-circle"></i> Cadastro existente preservado. Senha de acesso e dados cadastrais não serão alterados.</small>' +
                    '</div>';
            } else if (data.registration_choice === 'DEFER_REGISTRATION') {
                clientResolutionCard = '<div class="well well-small" style="background:#fcf8e3;border-color:#faebcc;">' +
                    '<strong><i class="fas fa-user-clock text-warning"></i> Cadastro Adiado pelo Cliente</strong><br>' +
                    '<span class="muted">O cliente optou por adiar o cadastro web de conta.</span><br>' +
                    '<button type="button" class="btn btn-mini btn-info wa-trigger-deferred-reg" data-id="' + esc(data.id) + '" style="margin-top:6px;">' +
                    '<i class="fas fa-paper-plane"></i> Enviar link de cadastro ao cliente' +
                    '</button>' +
                    '</div>';
            } else {
                clientResolutionCard = '<div class="well well-small">' +
                    '<strong><i class="fas fa-user-plus text-info"></i> Novo Cliente (Cadastro Web)</strong><br>' +
                    '<span>Nome informado: <strong>' + esc(name) + '</strong></span><br>' +
                    '<small class="muted">Os dados cadastrais serão materializados atomicamente com a OS em S07.</small>' +
                    '</div>';
            }

            // Accessories parsing
            var accText = receiving.accessories || '';
            var hasCharger = accText.indexOf('Carregador') !== -1;
            var hasCable = accText.indexOf('Cabo') !== -1;
            var hasCase = accText.indexOf('Capa') !== -1;
            var hasAdapter = accText.indexOf('Adaptador') !== -1;
            var hasMedia = accText.indexOf('Mídia') !== -1 || accText.indexOf('Cartão') !== -1;

            var conditionValue = receiving.device_condition || '';

            var actions = '';
            if (!isReceived) {
                actions += '<button type="button" class="btn btn-primary wa-receiving-save"><i class="fas fa-save"></i> Salvar preparação</button> ';
                actions += '<button type="button" class="btn btn-success wa-receiving-modal-open"><i class="fas fa-boxes"></i> Confirmar recebimento físico</button> ';
            } else {
                if (data.mapos_os_id) {
                    actions += '<a href="' + esc(osEditBase + '/' + data.mapos_os_id) + '" class="btn btn-info btn-large" target="_blank"><i class="fas fa-external-link-alt"></i> Ver Ordem de Serviço #' + esc(data.mapos_os_id) + '</a> ';
                } else {
                    actions += '<button type="button" class="btn btn-success btn-large" id="btn-materialize-os" data-id="' + esc(data.id) + '"><i class="fas fa-file-invoice"></i> Converter em Ordem de Serviço (OS)</button> ';
                }
            }
            actions += '<button type="button" class="btn btn-danger wa-intake-reject">Descartar</button>';

            var html = '<div class="well wa-intake-form" data-id="' + esc(data.id) + '" data-version="' + esc(data.review_version) + '">' +
                '<div style="display:flex;justify-content:space-between;align-items:center;">' +
                '<h4>Workspace de Recebimento Físico</h4><span class="muted" style="font-size:12px;">ID: ' + esc(data.id) + '</span>' +
                '</div>' +
                stateBanner +
                '<p><strong>WhatsApp:</strong> <input type="text" readonly class="input-medium" style="background:#eee;cursor:not-allowed;" value="' + esc(data.phone_display || '—') + '"> &nbsp; <span class="wa-state-badge ' + status.css + '">' + esc(status.label) + '</span></p>' +
                clientResolutionCard +

                '<div class="row-fluid">' +
                '<div class="span7"><label>Nome</label><input class="input-block-level wa-i-name" maxlength="120" value="' + esc(name) + '"></div>' +
                '<div class="span5"><label>Cidade</label><input class="input-block-level wa-i-city" maxlength="80" value="' + esc(data.city || '') + '"></div>' +
                '</div>' +

                '<div class="row-fluid">' +
                '<div class="span4"><label>Equipamento</label><input class="input-block-level wa-i-device" maxlength="80" value="' + esc(data.device_type || '') + '"></div>' +
                '<div class="span4"><label>Marca</label><input class="input-block-level wa-i-brand" maxlength="80" value="' + esc(data.brand || '') + '"></div>' +
                '<div class="span4"><label>Modelo</label><input class="input-block-level wa-i-model" maxlength="120" value="' + esc(data.model || '') + '"></div>' +
                '</div>' +

                '<label>Problema informado</label><textarea class="input-block-level wa-i-problem" maxlength="2000" rows="3">' + esc(data.problem_description || '') + '</textarea>' +
                '<p class="muted" style="margin-top:-6px;font-size:11px;"><i class="fas fa-key"></i> ' + esc(credentialSummary(data)) + '</p>' +

                '<label>Forma de atendimento</label><select class="input-block-level wa-i-mode"><option value="DROP_OFF"' + (!pickup ? ' selected' : '') + '>Cliente traz o equipamento à loja (Balcão)</option><option value="PICKUP_REQUESTED"' + (pickup ? ' selected' : '') + '>Coleta no endereço do cliente</option></select>' +
                pickupSummary(data) +
                pickupFeeAction(data, feeActionable) +

                '<hr style="margin:16px 0 12px 0;">' +
                '<h5><i class="fas fa-search"></i> Triagem e Inspeção Física do Equipamento' + (isReceived ? ' <small class="text-success">(Concluída e Bloqueada)</small>' : '') + '</h5>' +

                '<label><strong>Condição física geral do equipamento</strong></label>' +
                '<select class="input-block-level wa-i-condition"' + (isReceived ? ' disabled' : '') + '>' +
                '<option value=""' + (!conditionValue ? ' selected' : '') + '>— Selecione a condição física —</option>' +
                '<option value="EXCELENTE"' + (conditionValue === 'EXCELENTE' ? ' selected' : '') + '>Intacto / Excelente (sem marcas de uso)</option>' +
                '<option value="BOM"' + (conditionValue === 'BOM' ? ' selected' : '') + '>Bom estado (marcas de uso leves)</option>' +
                '<option value="RISCOS_VISIVEIS"' + (conditionValue === 'RISCOS_VISIVEIS' ? ' selected' : '') + '>Arranhões / desgastes visíveis na carcaça</option>' +
                '<option value="TELA_TRINCADA"' + (conditionValue === 'TELA_TRINCADA' ? ' selected' : '') + '>Tela ou vidro quebrado / trincado</option>' +
                '<option value="CARCACA_DANIFICADA"' + (conditionValue === 'CARCACA_DANIFICADA' ? ' selected' : '') + '>Carcaça quebrada, amassada ou com peças faltando</option>' +
                '<option value="DANOS_LIQUIDO"' + (conditionValue === 'DANOS_LIQUIDO' ? ' selected' : '') + '>Sinais de oxidação ou contato com líquido</option>' +
                '<option value="NAO_LIGA"' + (conditionValue === 'NAO_LIGA' ? ' selected' : '') + '>Equipamento não liga / dano severo</option>' +
                '<option value="OUTRO"' + (conditionValue === 'OUTRO' ? ' selected' : '') + '>Outra condição (detalhar nas notas)</option>' +
                '</select>' +

                '<label><strong>Acessórios entregues junto ao equipamento</strong></label>' +
                '<div class="well well-small" style="padding:6px 12px;margin-bottom:10px;">' +
                '<label class="checkbox inline" style="margin-right:12px;"><input type="checkbox" class="wa-acc-charger"' + (hasCharger ? ' checked' : '') + (isReceived ? ' disabled' : '') + '> Carregador / Fonte</label>' +
                '<label class="checkbox inline" style="margin-right:12px;"><input type="checkbox" class="wa-acc-cable"' + (hasCable ? ' checked' : '') + (isReceived ? ' disabled' : '') + '> Cabo de força/USB</label>' +
                '<label class="checkbox inline" style="margin-right:12px;"><input type="checkbox" class="wa-acc-case"' + (hasCase ? ' checked' : '') + (isReceived ? ' disabled' : '') + '> Capa / Case</label>' +
                '<label class="checkbox inline" style="margin-right:12px;"><input type="checkbox" class="wa-acc-adapter"' + (hasAdapter ? ' checked' : '') + (isReceived ? ' disabled' : '') + '> Adaptador</label>' +
                '<label class="checkbox inline" style="margin-right:12px;"><input type="checkbox" class="wa-acc-media"' + (hasMedia ? ' checked' : '') + (isReceived ? ' disabled' : '') + '> Cartão de memória / Mídia</label>' +
                '<div style="margin-top:6px;"><label style="font-size:12px;">Outros acessórios entregues:</label><input class="input-block-level wa-i-other-acc" placeholder="Ex: mouse, caneta touch, bolsa" value="' + esc(accText) + '"' + (isReceived ? ' readonly' : '') + '></div>' +
                '</div>' +

                '<div class="row-fluid">' +
                '<div class="span4"><label>Número de Série</label><input class="input-block-level wa-i-serial" maxlength="128" placeholder="Opcional" value="' + esc(receiving.serial_number || '') + '"' + (isReceived ? ' readonly' : '') + '></div>' +
                '<div class="span4"><label>IMEI / Chassi</label><input class="input-block-level wa-i-imei" maxlength="32" placeholder="Opcional" value="' + esc(receiving.imei || '') + '"' + (isReceived ? ' readonly' : '') + '></div>' +
                '<div class="span4"><label>Tag de serviço / Outro ID</label><input class="input-block-level wa-i-other-ids" placeholder="Opcional" value="' + esc(receiving.other_identifiers || '') + '"' + (isReceived ? ' readonly' : '') + '></div>' +
                '</div>' +

                '<label><strong>Observações integrais de recebimento</strong></label>' +
                '<textarea class="input-block-level wa-i-receiving-notes" rows="4" placeholder="Registre aqui detalhes da inspeção, condição das peças, bateria, periféricos e instruções especiais…"' + (isReceived ? ' readonly' : '') + '>' + esc(receiving.notes || '') + '</textarea>' +

                '<hr style="margin:16px 0 12px 0;">' +
                '<h5><i class="fas fa-camera"></i> Fotos e Documentos de Triagem (Anexos Privados)</h5>' +
                '<p class="muted" style="font-size:11px;">Arquivos são armazenados em volume privado e restritos à equipe. Formatos: JPEG, PNG, PDF (máx. 15 MiB por arquivo).</p>' +
                '<div id="wa-attachments-container">' + renderAttachmentsGallery(data.attachments, data.id) + '</div>' +
                '<div class="input-append" style="margin-bottom:16px;">' +
                '<input type="file" id="wa-file-input" accept=".jpg,.jpeg,.png,.pdf" style="display:none;">' +
                '<button type="button" class="btn btn-info" id="btn-trigger-upload"><i class="fas fa-paperclip"></i> Adicionar Foto / Anexo</button>' +
                '</div>' +

                '<div id="wa-readiness-widget-container">' + renderReadinessPlaceholder(data.id) + '</div>' +
                '<div class="wa-form-actions" style="margin-top:16px;padding-top:12px;border-top:1px solid #e5e5e5;">' + actions + '</div>' +
                '</div>';

            $('#wa-intake-detail').attr('class', '').html(html);
            fetchReadiness(data.id);
        });
    }

    function buildAccessoriesString(form) {
        var items = [];
        if (form.find('.wa-acc-charger').is(':checked')) { items.push('Carregador'); }
        if (form.find('.wa-acc-cable').is(':checked')) { items.push('Cabo'); }
        if (form.find('.wa-acc-case').is(':checked')) { items.push('Capa'); }
        if (form.find('.wa-acc-adapter').is(':checked')) { items.push('Adaptador'); }
        if (form.find('.wa-acc-media').is(':checked')) { items.push('Mídia'); }
        var other = form.find('.wa-i-other-acc').val().trim();
        if (other && items.indexOf(other) === -1) {
            items.push(other);
        }
        return items.join(', ');
    }

    function receivingFormData(form) {
        return {
            device_condition: form.find('.wa-i-condition').val(),
            accessories: buildAccessoriesString(form),
            serial_number: form.find('.wa-i-serial').val().trim(),
            imei: form.find('.wa-i-imei').val().trim(),
            other_identifiers: form.find('.wa-i-other-ids').val().trim(),
            notes: form.find('.wa-i-receiving-notes').val().trim()
        };
    }

    // Filter switches
    $(document).on('click', '.wa-history-filter button', function () {
        currentList = $(this).data('list');
        $('.wa-history-filter button').removeClass('btn-primary active');
        $(this).addClass('btn-primary active');
        emptyDetail(currentList === 'history' ? 'Selecione um registro do histórico' : 'Selecione um pré-atendimento');
        loadList();
    });

    $(document).on('click', '.wa-intake-open', function () { loadIntake(String($(this).data('id'))); });

    // Save preparation
    $(document).on('click', '.wa-receiving-save', function () {
        var form = $(this).closest('.wa-intake-form');
        var intakeId = String(form.data('id'));
        var data = receivingFormData(form);
        data.action = 'prepare';

        request('/tecnina/pre-atendimentos/' + encodeURIComponent(intakeId) + '/receiving', 'POST', data, function () {
            // Also save basic intake corrections to Bot
            var intakePayload = {
                review_version: form.data('version'),
                name: form.find('.wa-i-name').val(),
                city: form.find('.wa-i-city').val(),
                device_type: form.find('.wa-i-device').val(),
                brand: form.find('.wa-i-brand').val(),
                model: form.find('.wa-i-model').val(),
                problem_description: form.find('.wa-i-problem').val(),
                service_mode: form.find('.wa-i-mode').val(),
                notes: form.find('.wa-i-receiving-notes').val()
            };
            request('/pre_atendimento/' + encodeURIComponent(intakeId) + '/save', 'POST', intakePayload, function () {
                loadList();
                loadIntake(intakeId);
            });
        });
    });

    // Open confirmation modal
    $(document).on('click', '.wa-receiving-modal-open', function () {
        var form = $(this).closest('.wa-intake-form');
        var intakeId = String(form.data('id'));
        var name = form.find('.wa-i-name').val();
        var phone = form.find('input[readonly]').val();
        var device = form.find('.wa-i-device').val();
        var brand = form.find('.wa-i-brand').val();
        var model = form.find('.wa-i-model').val();
        var serial = form.find('.wa-i-serial').val().trim();
        var imei = form.find('.wa-i-imei').val().trim();

        var summaryHtml = '<p><strong>Cliente:</strong> ' + esc(name) + ' (' + esc(phone) + ')</p>' +
            '<p><strong>Equipamento:</strong> ' + esc(device) + ' ' + esc(brand) + ' ' + esc(model) + '</p>' +
            (serial ? '<p><strong>Nº Série:</strong> ' + esc(serial) + '</p>' : '') +
            (imei ? '<p><strong>IMEI:</strong> ' + esc(imei) + '</p>' : '') +
            '<p class="muted" style="font-size:11px;margin-bottom:0;">Identificador de intake: ' + esc(intakeId) + '</p>';

        $('#modal-receiving-summary').html(summaryHtml);
        $('#wa-confirm-possession-ack').prop('checked', false);
        $('#btn-submit-physical-receipt').prop('disabled', true);
        $('#modal-confirm-receiving').data('intake-id', intakeId).modal('show');
    });

    $(document).on('change', '#wa-confirm-possession-ack', function () {
        $('#btn-submit-physical-receipt').prop('disabled', !$(this).is(':checked'));
    });

    // Confirm physical receipt from modal
    $(document).on('click', '#btn-submit-physical-receipt', function () {
        if (!$('#wa-confirm-possession-ack').is(':checked')) { return; }
        var intakeId = $('#modal-confirm-receiving').data('intake-id');
        var form = $('.wa-intake-form[data-id="' + intakeId + '"]');
        var data = receivingFormData(form);
        data.action = 'confirm_received';
        var idempotencyKey = uuidv4();

        request('/tecnina/pre-atendimentos/' + encodeURIComponent(intakeId) + '/receiving', 'POST', data, function () {
            $('#modal-confirm-receiving').modal('hide');
            loadList();
            loadIntake(intakeId);
        }, false, {'Idempotency-Key': idempotencyKey});
    });

    // Trigger file input
    $(document).on('click', '#btn-trigger-upload', function () {
        $('#wa-file-input').click();
    });

    // File input changed -> upload
    $(document).on('change', '#wa-file-input', function () {
        var input = this;
        if (!input.files || !input.files[0]) { return; }
        var file = input.files[0];
        if (file.size > 15728640) {
            error('O arquivo excede o limite máximo permitido de 15 MiB.');
            $(input).val('');
            return;
        }

        var form = $('.wa-intake-form');
        var intakeId = String(form.data('id'));
        var fd = new FormData();
        fd.append('file', file);

        request('/tecnina/pre-atendimentos/' + encodeURIComponent(intakeId) + '/attachments', 'POST', fd, function () {
            $(input).val('');
            loadIntake(intakeId);
        });
    });

    // Delete attachment
    $(document).on('click', '.wa-attachment-delete', function () {
        if (!window.confirm('Deseja realmente excluir este anexo?')) { return; }
        var attachId = $(this).data('id');
        var form = $('.wa-intake-form');
        var intakeId = String(form.data('id'));

        request('/tecnina/pre-atendimentos/' + encodeURIComponent(intakeId) + '/attachments/' + encodeURIComponent(attachId) + '/delete', 'POST', {}, function () {
            loadIntake(intakeId);
        });
    });

    // Trigger deferred registration capability
    $(document).on('click', '.wa-trigger-deferred-reg', function () {
        var intakeId = $(this).data('id');
        request('/tecnina/pre-atendimentos/' + encodeURIComponent(intakeId) + '/deferred-registration', 'POST', {}, function (resp) {
            window.alert('Link de cadastro enviado com sucesso para o cliente no WhatsApp!');
        });
    });

    // Reject intake
    $(document).on('click', '.wa-intake-reject', function () {
        var form = $(this).closest('.wa-intake-form');
        var reason = window.prompt('Informe o motivo do descarte:');
        if (!reason) { return; }
        request('/pre_atendimento/' + encodeURIComponent(form.data('id')) + '/reject', 'POST', {review_version: form.data('version'), reason: reason}, function () { emptyDetail('Pré-atendimento descartado'); loadList(); });
    });

    // Offer pickup fee
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

    $(document).on('click', '#btn-recheck-readiness', function (e) {
        e.preventDefault();
        var intakeId = String($(this).data('id'));
        if (intakeId) {
            $('#wa-readiness-content').html('<i class="fas fa-spinner fa-spin"></i> Reavaliando prontidão…');
            fetchReadiness(intakeId);
        }
    });

    $(document).on('click', '#btn-materialize-os', function (e) {
        e.preventDefault();
        var btn = $(this);
        var intakeId = String(btn.data('id'));
        if (!intakeId) { return; }

        if (!window.confirm('Deseja converter este pré-atendimento em Ordem de Serviço (OS) definitiva no MapOS?')) {
            return;
        }

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Convertendo em OS…');
        clearError();

        request('/tecnina/pre-atendimentos/' + encodeURIComponent(intakeId) + '/materialize', 'POST', {}, function (res) {
            btn.remove();
            if (res.ok && res.os_id) {
                var successBox = '<div class="alert alert-success" style="margin-top:12px;">' +
                    '<h4><i class="fas fa-check-double"></i> OS #' + esc(res.os_id) + ' Materializada com Sucesso!</h4>' +
                    '<p>Cliente ID: #' + esc(res.client_id) + (res.client_created ? ' (Novo cadastro criado)' : ' (Vínculo a cadastro existente)') + '</p>' +
                    '<a href="' + esc(osEditBase + '/' + res.os_id) + '" class="btn btn-success" target="_blank"><i class="fas fa-external-link-alt"></i> Acessar OS #' + esc(res.os_id) + '</a>' +
                    '</div>';
                $('#wa-readiness-widget-container').append(successBox);
                loadList();
            }
        });
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
