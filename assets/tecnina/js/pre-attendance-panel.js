(function ($) {
    'use strict';

    window.__tecninaWhatsappPanel = {executed: true, booted: false};
    var config = $('#wa-panel-config');
    var base = String(config.attr('data-base') || '');
    var receivingBase = String(config.attr('data-receiving-base') || '/tecnina/pre-atendimentos');
    var osEditBase = String(config.attr('data-os-edit-base') || '');
    var clientAddBase = String(config.attr('data-client-add-base') || '');
    var clientEditBase = String(config.attr('data-client-edit-base') || '');
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
            gateway_request_failed: 'Falha na comunicação com o assistente/bot (gateway_request_failed). Verifique se o serviço está ativo.',
            whatsapp_send_failed: 'Não foi possível enviar a mensagem no WhatsApp. Verifique a conexão com a Evolution API.',
            whatsapp_delivery_unknown: 'Envio no WhatsApp em estado incerto. Verifique a conversa no WhatsApp.',
            phone_not_available: 'Este pré-atendimento não possui número de telefone/WhatsApp válido para envio.',
            intake_not_found: 'Pré-atendimento não encontrado no sistema.',
            intake_not_active: 'O pré-atendimento não está ativo para gerar novo link.',
            intake_creation_failed: 'Falha ao registrar pré-atendimento no assistente. Tente novamente.',
            invalid_phone: 'Número de telefone/WhatsApp inválido. Informe DDD + número.',
            whatsapp_instance_disconnected: 'A instância do WhatsApp está desconectada no momento.',
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
        var msg = messages[parsedReason] || ('Operação não concluída: ' + (detail ? detail : parsedReason));
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
                if (!response || !response.ok) {
                    var rReason = response ? response.reason : 'unknown';
                    error(reasonMessage(rReason));
                    $('#wa-save-status-msg').show().html('<div class="alert alert-error" style="margin-bottom:8px;">' + esc(reasonMessage(rReason)) + '</div>');
                    $('.wa-btn-save-draft').prop('disabled', false).html('<i class="fas fa-save"></i> Salvar Rascunho');
                    $('.wa-trigger-deferred-reg').prop('disabled', false);
                    return;
                }
                clearError();
                $('#wa-save-status-msg').hide().empty();
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
                $('#wa-save-status-msg').show().html('<div class="alert alert-error" style="margin-bottom:8px;">' + esc(reasonMessage(errReason)) + '</div>');
                $('.wa-btn-save-draft').prop('disabled', false).html('<i class="fas fa-save"></i> Salvar Rascunho');
                $('.wa-trigger-deferred-reg').prop('disabled', false);
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

    function formatCurrencyMask(value) {
        var digits = String(value || '').replace(/\D/g, '');
        if (!digits) { return '0,00'; }
        var cents = parseInt(digits, 10);
        var floatVal = (cents / 100).toFixed(2);
        var parts = floatVal.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return parts.join(',');
    }

    function pickupFeeAction(data, actionable) {
        if (!actionable || data.service_mode !== 'PICKUP_REQUESTED') { return ''; }
        if (data.pickup_fee_status !== 'PENDING_TEAM' && data.stage !== 'WAITING_PICKUP_FEE_TEAM') { return ''; }
        return '<div class="alert alert-warning wa-pickup-fee-action" style="padding:14px;border-radius:6px;border:1px solid #faebcc;">' +
            '<h5 style="margin:0 0 6px;color:#8a6d3b;"><i class="fas fa-motorcycle"></i> Taxa de Coleta Aguardando Definição</h5>' +
            '<p style="margin:0 0 10px;font-size:12px;line-height:1.4;">Informe o valor calculado pela equipe para realizar a coleta no endereço informado. O cliente receberá a proposta no WhatsApp e deverá confirmar antes da triagem.</p>' +
            '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">' +
            '<div class="input-prepend" style="margin-bottom:0;">' +
            '<span class="add-on" style="font-weight:bold;background:#fff8e7;color:#8a6d3b;">R$</span>' +
            '<input class="input-medium wa-i-pickup-fee" inputmode="numeric" style="text-align:right;font-weight:bold;font-size:15px;color:#333;" placeholder="0,00">' +
            '</div>' +
            '<button class="btn btn-warning wa-intake-offer-fee" type="button" style="font-weight:bold;"><i class="fas fa-paper-plane"></i> Enviar Proposta de Taxa</button>' +
            '</div>' +
            '</div>';
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
            : (report.identity_resolution === 'AMBIGUOUS' ? '<span class="label label-warning"><i class="fas fa-exclamation-triangle"></i> AMBÍGUO (PENDENTE)</span>' : '<span class="label label-warning"><i class="fas fa-clock"></i> PENDENTE</span>');

        var regBadge = (report.registration === 'READY_TO_MATERIALIZE' || report.registration === 'EXISTING_ACCOUNT')
            ? '<span class="label label-success"><i class="fas fa-check"></i> ' + esc(report.registration) + '</span>'
            : '<span class="label label-warning"><i class="fas fa-clock"></i> CADASTRO PENDENTE</span>';

        var credBadge = (report.credential === 'PRESENT' || report.credential === 'EXISTING_ACCOUNT')
            ? '<span class="label label-success"><i class="fas fa-check"></i> ' + esc(report.credential) + '</span>'
            : '<span class="label label-warning"><i class="fas fa-key"></i> AUSENTE</span>';

        var legalBadge = (report.legal === 'SATISFIED')
            ? '<span class="label label-success"><i class="fas fa-check"></i> SATISFEITO</span>'
            : '<span class="label label-warning"><i class="fas fa-clock"></i> PENDENTE</span>';

        var snapshotBadge = (report.intake_snapshot === 'READY')
            ? '<span class="label label-success"><i class="fas fa-check"></i> SELADO</span>'
            : '<span class="label label-warning"><i class="fas fa-clock"></i> ' + esc(report.intake_snapshot || 'PENDENTE') + '</span>';

        var items = '<table class="table table-condensed table-bordered" style="margin-top:8px;font-size:11px;background:#fff;">' +
            '<thead><tr><th>Dimensão</th><th>Status</th></tr></thead>' +
            '<tbody>' +
            '<tr><td><strong>1. Posse Física (Obrigatório)</strong></td><td>' + physicalBadge + '</td></tr>' +
            '<tr><td><strong>2. Identificação do Cliente</strong></td><td>' + identityBadge + '</td></tr>' +
            '<tr><td><strong>3. Cadastro de Conta</strong></td><td>' + regBadge + '</td></tr>' +
            '<tr><td><strong>4. Credencial de Acesso</strong></td><td>' + credBadge + '</td></tr>' +
            '<tr><td><strong>5. Manifestações Legais</strong></td><td>' + legalBadge + '</td></tr>' +
            '<tr><td><strong>6. Snapshot Autoritativo</strong></td><td>' + snapshotBadge + '</td></tr>' +
            '</tbody></table>';

        var statusAlert = '';
        if (report.ready) {
            var pendingNote = '';
            if (report.pending_items && report.pending_items.length > 0) {
                pendingNote = '<div style="margin-top:6px;font-size:11px;" class="muted"><strong>Itens não impeditivos pendentes:</strong> ' + esc(report.pending_items.join(', ')) + '</div>';
            }
            statusAlert = '<div class="alert alert-success" style="margin-bottom:0;"><i class="fas fa-check-circle"></i> <strong>Gate de Recebimento Satisfeito:</strong> Posse física confirmada. Pronto para conversão em Ordem de Serviço (OS).' + pendingNote + '</div>';
        } else {
            statusAlert = '<div class="alert alert-block alert-warning" style="margin-bottom:0;"><i class="fas fa-lock"></i> <strong>Abertura de OS Bloqueada:</strong> O recebimento físico do equipamento ainda não foi confirmado na TecNina. Confirme o recebimento físico para liberar a conversão em OS.</div>';
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

    function buildClientAddUrl(data, name) {
        if (!clientAddBase || !data) {
            return '';
        }
        var cleanPhone = function (val) {
            if (!val) return '';
            var digits = String(val).replace(/\D/g, '');
            if (digits.length >= 12 && digits.indexOf('55') === 0) {
                digits = digits.substring(2);
            }
            return digits;
        };

        var params = {
            intake_id: data.id || '',
            nomeCliente: name || data.name || '',
            contato: name || data.name || '',
            documento: data.registration_cpf || '',
            celular: cleanPhone(data.phone_canonical || data.phone_display || ''),
            email: data.registration_email || '',
            cep: data.registration_postal_code || data.postal_code || '',
            rua: data.registration_street || data.street || '',
            numero: data.registration_street_number || data.street_number || '',
            complemento: data.registration_complement || data.complement || '',
            bairro: data.registration_neighborhood || data.neighborhood || '',
            cidade: data.registration_city || data.city || '',
            estado: data.registration_address_state || data.address_state || 'PR'
        };

        var query = [];
        for (var key in params) {
            if (params.hasOwnProperty(key) && params[key] !== '') {
                query.push(encodeURIComponent(key) + '=' + encodeURIComponent(params[key]));
            }
        }
        return clientAddBase + (clientAddBase.indexOf('?') === -1 ? '?' : '&') + query.join('&');
    }

    function loadIntake(id) {
        request('/pre_atendimento/' + encodeURIComponent(id), 'GET', null, function (data) {
            currentIntakeData = data;
            var pickup = data.service_mode === 'PICKUP_REQUESTED';
            var actionable = data.status === 'READY' || data.status === 'UNDER_REVIEW';
            var feeActionable = pickup && (data.pickup_fee_status === 'PENDING_TEAM' || data.stage === 'WAITING_PICKUP_FEE_TEAM');
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
            var addUrl = buildClientAddUrl(data, name);
            if (existingId) {
                var editUrl = clientEditBase ? (clientEditBase.replace(/\/+$/, '') + '/' + encodeURIComponent(existingId)) : '';
                clientResolutionCard = '<div class="well well-small" style="background:#f4fbf4;border-color:#bce8f1;">' +
                    '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">' +
                    '<div><strong><i class="fas fa-user-check text-success"></i> Cliente Cadastrado no MapOS</strong><br>' +
                    '<span>Nome: <strong>' + esc(name) + '</strong> · ID MapOS: <strong>#' + esc(existingId) + '</strong></span></div>' +
                    (editUrl ? '<a href="' + esc(editUrl) + '" target="_blank" class="btn btn-mini btn-info"><i class="fas fa-user-edit"></i> Ver/Editar Cadastro no MapOS</a>' : '') +
                    '</div>' +
                    '<small class="muted" style="display:block;margin-top:6px;"><i class="fas fa-info-circle"></i> Cadastro existente vinculado a este pré-atendimento.</small>' +
                    '</div>';
            } else if (data.registration_choice === 'DEFER_REGISTRATION') {
                clientResolutionCard = '<div class="well well-small" style="background:#fcf8e3;border-color:#faebcc;">' +
                    '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">' +
                    '<div><strong><i class="fas fa-user-clock text-warning"></i> Cadastro Adiado pelo Cliente</strong><br>' +
                    '<span class="muted">O cliente optou por adiar o cadastro web de conta.</span></div>' +
                    '<div style="display:flex;gap:6px;flex-wrap:wrap;">' +
                    (addUrl ? '<a href="' + esc(addUrl) + '" target="_blank" class="btn btn-mini btn-success"><i class="fas fa-user-plus"></i> Cadastrar Cliente no MapOS</a>' : '') +
                    '<button type="button" class="btn btn-mini btn-info wa-trigger-deferred-reg" data-id="' + esc(data.id) + '">' +
                    '<i class="fas fa-paper-plane"></i> Enviar link ao cliente' +
                    '</button>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
            } else {
                clientResolutionCard = '<div class="well well-small">' +
                    '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">' +
                    '<div><strong><i class="fas fa-user-plus text-info"></i> Novo Cliente</strong><br>' +
                    '<span>Nome informado: <strong>' + esc(name) + '</strong></span></div>' +
                    (addUrl ? '<a href="' + esc(addUrl) + '" target="_blank" class="btn btn-mini btn-success"><i class="fas fa-user-plus"></i> Cadastrar Cliente no MapOS</a>' : '') +
                    '</div>' +
                    '<small class="muted" style="display:block;margin-top:6px;"><i class="fas fa-info-circle"></i> Você pode cadastrar o cliente antecipadamente no MapOS ou aguardar a materialização com a OS.</small>' +
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
                '<div class="input-append" style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap;">' +
                '<input type="file" id="wa-file-input" accept=".jpg,.jpeg,.png,.pdf" style="display:none;">' +
                '<input type="file" id="wa-camera-input" accept="image/*" capture="environment" style="display:none;">' +
                '<button type="button" class="btn btn-primary" id="btn-trigger-camera"><i class="fas fa-camera"></i> Tirar Foto (Câmera)</button>' +
                '<button type="button" class="btn btn-info" id="btn-trigger-upload"><i class="fas fa-paperclip"></i> Anexar Arquivo</button>' +
                '</div>' +

                '<div id="wa-readiness-widget-container">' + renderReadinessPlaceholder(data.id) + '</div>' +
                '<div class="wa-form-actions" style="margin-top:16px;padding-top:12px;border-top:1px solid #e5e5e5;">' + actions + '</div>' +
                '</div>';

            $('#wa-intake-detail').attr('class', '').html(html);
            fetchReadiness(data.id);
            if (window.innerWidth <= 768) {
                var detailEl = document.getElementById('wa-intake-detail');
                if (detailEl) {
                    detailEl.scrollIntoView({behavior: 'smooth', block: 'start'});
                }
            }
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

    // Trigger camera input
    $(document).on('click', '#btn-trigger-camera', function () {
        $('#wa-camera-input').click();
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

    // Camera input changed -> upload
    $(document).on('change', '#wa-camera-input', function () {
        var input = this;
        if (!input.files || !input.files[0]) { return; }
        var file = input.files[0];
        if (file.size > 15728640) {
            error('A foto excede o limite máximo permitido de 15 MiB.');
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

    // Currency input mask (right-to-left cents entry)
    $(document).on('input', '.wa-i-pickup-fee', function () {
        $(this).val(formatCurrencyMask($(this).val()));
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
        var btn = $(this);
        var intakeId = btn.data('id');
        var origHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Enviando…');
        clearError();

        request('/tecnina/pre-atendimentos/' + encodeURIComponent(intakeId) + '/deferred-registration', 'POST', {}, function (resp) {
            btn.prop('disabled', false).html(origHtml);
            window.alert('Link de cadastro enviado com sucesso para o cliente no WhatsApp!');
        });
    });

    // Workspace de Novo Pré-atendimento (Sem Modal)
    var draftAutoSaveTimer = null;
    var draftIsSaving = false;

    function renderNewIntakeWorkspace() {
        currentIntakeData = null;
        var html = '<div class="well wa-intake-form" data-id="" data-version="0" data-is-new="true">' +
            '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">' +
            '<h4><i class="fas fa-plus-circle text-success"></i> Novo Pré-atendimento Presencial (Balcão)</h4>' +
            '<span class="wa-state-badge wa-status-review" id="wa-draft-badge"><i class="fas fa-pencil-alt"></i> Novo Rascunho</span>' +
            '</div>' +
            '<div class="alert alert-info wa-receiving-status-banner" style="margin-top:8px;">' +
            '<h4 style="margin:0 0 4px 0;"><i class="fas fa-user-edit"></i> Cadastro e Triagem no Balcão</h4>' +
            '<small class="muted">Preencha os dados do atendimento. O sistema salva automaticamente como rascunho (Draft) assim que o nome e telefone forem preenchidos, aparecendo na lista à esquerda.</small>' +
            '</div>' +

            '<!-- Autocomplete de Clientes do MapOS -->' +
            '<div class="well well-small" style="background:#f9fbfd;border:1px solid #d0e2ec;margin-bottom:12px;">' +
            '<label for="wa-intake-client-autocomplete" style="font-size:12px;font-weight:bold;margin-bottom:4px;color:#2c3e50;">' +
            '<i class="fas fa-search text-info"></i> Localizar Cliente Cadastrado (Opcional - preenche automático)' +
            '</label>' +
            '<input type="text" id="wa-intake-client-autocomplete" class="input-block-level" placeholder="Digite nome, telefone ou CPF para buscar cliente no MapOS…" autocomplete="off">' +
            '<input type="hidden" id="wa-intake-client-id" class="wa-i-client-id" value="">' +
            '<div id="wa-intake-client-match-badge" style="display:none;margin-top:4px;"></div>' +
            '</div>' +

            '<div class="row-fluid">' +
            '<div class="span7">' +
            '<label><strong>Nome do Cliente</strong> <span class="text-error">*</span></label>' +
            '<input class="input-block-level wa-i-name" maxlength="120" placeholder="Nome completo do cliente" required>' +
            '</div>' +
            '<div class="span5">' +
            '<label><strong>WhatsApp / Telefone</strong> <span class="text-error">*</span></label>' +
            '<input type="text" class="input-block-level wa-i-phone" placeholder="(11) 99999-9999" required>' +
            '</div>' +
            '</div>' +

            '<div class="row-fluid">' +
            '<div class="span4"><label>CPF (Opcional)</label><input type="text" class="input-block-level wa-i-cpf" placeholder="000.000.000-00"></div>' +
            '<div class="span5"><label>E-mail (Opcional)</label><input type="email" class="input-block-level wa-i-email" placeholder="cliente@exemplo.com"></div>' +
            '<div class="span3"><label>Cidade</label><input type="text" class="input-block-level wa-i-city" maxlength="80" value="São Paulo" placeholder="Cidade"></div>' +
            '</div>' +

            '<div class="row-fluid">' +
            '<div class="span4"><label><strong>Equipamento</strong></label><input class="input-block-level wa-i-device" maxlength="80" placeholder="Ex: Notebook, Celular, TV…" value="Notebook"></div>' +
            '<div class="span4"><label>Marca</label><input class="input-block-level wa-i-brand" maxlength="80" placeholder="Ex: Dell, Samsung, Apple…"></div>' +
            '<div class="span4"><label>Modelo</label><input class="input-block-level wa-i-model" maxlength="120" placeholder="Ex: Inspiron 15, Galaxy S21…"></div>' +
            '</div>' +

            '<label><strong>Problema informado</strong></label>' +
            '<textarea class="input-block-level wa-i-problem" maxlength="2000" rows="3" placeholder="Descreva detalhadamente o defeito ou solicitação do cliente…"></textarea>' +

            '<!-- Senha / Desbloqueio -->' +
            '<div class="well well-small" style="background:#fdfdfd;margin-bottom:12px;">' +
            '<label><strong><i class="fas fa-key text-info"></i> Senha / Desbloqueio do Aparelho (Opcional)</strong></label>' +
            '<div class="row-fluid">' +
            '<div class="span5">' +
            '<select class="input-block-level wa-i-cred-type">' +
            '<option value="NONE">Sem senha / Não informado</option>' +
            '<option value="PASSWORD">Senha numérica ou PIN/Texto</option>' +
            '<option value="PATTERN">Padrão / Desenho</option>' +
            '</select>' +
            '</div>' +
            '<div class="span7 wa-i-cred-val-wrap" style="display:none;">' +
            '<input type="text" class="input-block-level wa-i-cred-value" placeholder="Digite a senha ou sequência de pontos">' +
            '</div>' +
            '</div>' +
            '</div>' +

            '<label>Forma de atendimento</label>' +
            '<select class="input-block-level wa-i-mode">' +
            '<option value="DROP_OFF" selected>Cliente traz o equipamento à loja (Balcão)</option>' +
            '<option value="PICKUP_REQUESTED">Coleta no endereço do cliente</option>' +
            '</select>' +

            '<hr style="margin:16px 0 12px 0;">' +
            '<h5><i class="fas fa-search"></i> Triagem e Inspeção Física do Equipamento</h5>' +

            '<label><strong>Condição física geral do equipamento</strong></label>' +
            '<select class="input-block-level wa-i-condition">' +
            '<option value="">— Selecione a condição física —</option>' +
            '<option value="EXCELENTE">Intacto / Excelente (sem marcas de uso)</option>' +
            '<option value="BOM" selected>Bom estado (marcas de uso leves)</option>' +
            '<option value="RISCOS_VISIVEIS">Arranhões / desgastes visíveis na carcaça</option>' +
            '<option value="TELA_TRINCADA">Tela ou vidro quebrado / trincado</option>' +
            '<option value="CARCACA_DANIFICADA">Carcaça quebrada, amassada ou com peças faltando</option>' +
            '<option value="DANOS_LIQUIDO">Sinais de oxidação ou contato com líquido</option>' +
            '<option value="NAO_LIGA">Equipamento não liga / dano severo</option>' +
            '<option value="OUTRO">Outra condição (detalhar nas notas)</option>' +
            '</select>' +

            '<label><strong>Acessórios entregues junto ao equipamento</strong></label>' +
            '<div class="well well-small" style="padding:6px 12px;margin-bottom:10px;">' +
            '<label class="checkbox inline" style="margin-right:12px;"><input type="checkbox" class="wa-acc-charger"> Carregador / Fonte</label>' +
            '<label class="checkbox inline" style="margin-right:12px;"><input type="checkbox" class="wa-acc-cable"> Cabo de força/USB</label>' +
            '<label class="checkbox inline" style="margin-right:12px;"><input type="checkbox" class="wa-acc-case"> Capa / Case</label>' +
            '<label class="checkbox inline" style="margin-right:12px;"><input type="checkbox" class="wa-acc-adapter"> Adaptador</label>' +
            '<label class="checkbox inline" style="margin-right:12px;"><input type="checkbox" class="wa-acc-media"> Cartão de memória / Mídia</label>' +
            '<div style="margin-top:6px;"><label style="font-size:12px;">Outros acessórios entregues:</label><input class="input-block-level wa-i-other-acc" placeholder="Ex: mouse, caneta touch, bolsa"></div>' +
            '</div>' +

            '<div class="row-fluid">' +
            '<div class="span4"><label>Número de Série</label><input class="input-block-level wa-i-serial" maxlength="128" placeholder="Opcional"></div>' +
            '<div class="span4"><label>IMEI / Chassi</label><input class="input-block-level wa-i-imei" maxlength="32" placeholder="Opcional"></div>' +
            '<div class="span4"><label>Localização / Tag</label><input class="input-block-level wa-i-other-ids" placeholder="Ex: Balcão, Bancada 1"></div>' +
            '</div>' +

            '<label><strong>Observações integrais de recebimento</strong></label>' +
            '<textarea class="input-block-level wa-i-receiving-notes" rows="3" placeholder="Registre aqui detalhes da inspeção, condição das peças, bateria, periféricos e instruções especiais…"></textarea>' +

            '<div class="alert alert-success" style="margin-top:10px;padding:8px 12px;">' +
            '<label class="checkbox" style="margin-bottom:0;font-weight:bold;color:#155724;">' +
            '<input type="checkbox" class="wa-i-immediate-possession" checked> Confirmar posse física do equipamento no balcão imediatamente' +
            '</label>' +
            '<small class="muted" style="display:block;margin-top:2px;">O recebimento físico será registrado em nome do operador atual assim que o rascunho for salvo.</small>' +
            '</div>' +

            '<div id="wa-save-status-msg" style="display:none;margin-top:10px;"></div>' +

            '<div class="wa-form-actions" style="margin-top:16px;padding-top:12px;border-top:1px solid #e5e5e5;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">' +
            '<button type="button" class="btn btn-primary wa-btn-save-draft"><i class="fas fa-save"></i> Salvar Rascunho</button> ' +
            '<button type="button" class="btn btn-danger wa-btn-cancel-new">Cancelar</button> ' +
            '<span id="wa-autosave-indicator" class="muted" style="font-size:12px;margin-left:auto;"></span>' +
            '</div>' +
            '</div>';

        $('#wa-intake-detail').attr('class', '').html(html);
        if (window.innerWidth <= 768) {
            var detailEl = document.getElementById('wa-intake-detail');
            if (detailEl) {
                detailEl.scrollIntoView({behavior: 'smooth', block: 'start'});
            }
        }
        $('#wa-intake-detail .wa-i-name').focus();
    }

    function saveDraft(manual) {
        var form = $('#wa-intake-detail .wa-intake-form');
        if (!form.length) { return; }
        var intakeId = String(form.data('id') || form.attr('data-id') || '');
        var isNew = form.attr('data-is-new') === 'true' || !intakeId;

        var name = $.trim(form.find('.wa-i-name').val());
        var phone = $.trim(form.find('.wa-i-phone').val());
        var rawDigits = phone.replace(/\D/g, '');

        if (!name || rawDigits.length < 8) {
            if (manual) {
                $('#wa-save-status-msg').show().html('<div class="alert alert-error" style="margin-bottom:8px;">Por favor, preencha o Nome e o Telefone/WhatsApp do cliente para salvar o rascunho.</div>');
                if (!name) { form.find('.wa-i-name').focus(); }
                else { form.find('.wa-i-phone').focus(); }
            }
            return;
        }

        if (draftIsSaving) { return; }
        draftIsSaving = true;

        var indicator = $('#wa-autosave-indicator');
        indicator.html('<i class="fas fa-spinner fa-spin"></i> Salvando rascunho…');

        var deviceType = $.trim(form.find('.wa-i-device').val()) || 'Equipamento';
        var brand = $.trim(form.find('.wa-i-brand').val());
        var model = $.trim(form.find('.wa-i-model').val());
        var problem = $.trim(form.find('.wa-i-problem').val()) || 'Em avaliação no balcão';
        var city = $.trim(form.find('.wa-i-city').val()) || 'São Paulo';
        var notes = $.trim(form.find('.wa-i-receiving-notes').val());
        var clientId = form.find('.wa-i-client-id').val() || null;
        var cpf = $.trim(form.find('.wa-i-cpf').val());
        var email = $.trim(form.find('.wa-i-email').val());
        var credType = form.find('.wa-i-cred-type').val() || 'NONE';
        var credVal = $.trim(form.find('.wa-i-cred-value').val());
        var condition = form.find('.wa-i-condition').val() || 'BOM';
        var serial = $.trim(form.find('.wa-i-serial').val());
        var imei = $.trim(form.find('.wa-i-imei').val());
        var storage = $.trim(form.find('.wa-i-other-ids').val());
        var confirmReceipt = form.find('.wa-i-immediate-possession').is(':checked');

        var accList = [];
        if (form.find('.wa-acc-charger').is(':checked')) { accList.push('Carregador'); }
        if (form.find('.wa-acc-cable').is(':checked')) { accList.push('Cabo'); }
        if (form.find('.wa-acc-case').is(':checked')) { accList.push('Capa'); }
        if (form.find('.wa-acc-adapter').is(':checked')) { accList.push('Adaptador'); }
        if (form.find('.wa-acc-media').is(':checked')) { accList.push('Mídia'); }
        var otherAcc = $.trim(form.find('.wa-i-other-acc').val());
        if (otherAcc) { accList.push(otherAcc); }

        if (isNew) {
            var payload = {
                name: name,
                phone: phone,
                device_type: deviceType,
                brand: brand,
                model: model,
                problem_description: problem,
                notes: notes,
                city: city,
                possible_mapos_client_id: clientId,
                registration_cpf: cpf,
                registration_email: email,
                credential_type: credType,
                credential_value: credVal,
                confirm_physical_receipt: confirmReceipt,
                device_condition: condition,
                accessories: accList,
                serial_number: serial,
                imei: imei,
                storage_location: storage
            };

            request('/tecnina/pre-atendimentos/novo', 'POST', payload, function (res) {
                draftIsSaving = false;
                if (res && res.intake_id) {
                    var newId = res.intake_id;
                    form.attr('data-id', newId).data('id', newId);
                    form.removeAttr('data-is-new').data('is-new', false);
                    $('#wa-draft-badge').removeClass('wa-status-review').addClass('wa-status-ready').html('<i class="fas fa-check"></i> Rascunho #' + esc(newId.substring(0, 8)));
                    indicator.html('<span class="text-success"><i class="fas fa-check"></i> Rascunho salvo</span>');
                    $('#wa-save-status-msg').hide().empty();
                    loadList();
                    if (manual) {
                        loadIntake(newId);
                    }
                } else {
                    indicator.html('<span class="text-error">Erro ao salvar rascunho</span>');
                }
            });
        } else {
            var receivingData = receivingFormData(form);
            receivingData.action = 'prepare';
            request('/tecnina/pre-atendimentos/' + encodeURIComponent(intakeId) + '/receiving', 'POST', receivingData, function () {
                var intakePayload = {
                    review_version: form.data('version') || 0,
                    name: name,
                    city: city,
                    device_type: deviceType,
                    brand: brand,
                    model: model,
                    problem_description: problem,
                    service_mode: form.find('.wa-i-mode').val() || 'DROP_OFF',
                    notes: notes
                };
                request('/pre_atendimento/' + encodeURIComponent(intakeId) + '/save', 'POST', intakePayload, function () {
                    draftIsSaving = false;
                    indicator.html('<span class="text-success"><i class="fas fa-check"></i> Rascunho atualizado</span>');
                    loadList();
                    if (manual) {
                        loadIntake(intakeId);
                    }
                });
            });
        }
    }

    function scheduleDraftAutoSave() {
        if (draftAutoSaveTimer) { clearTimeout(draftAutoSaveTimer); }
        draftAutoSaveTimer = setTimeout(function () {
            saveDraft(false);
        }, 1200);
    }

    // Auto-save debounced em alterações
    $(document).on('input change', '#wa-intake-detail .wa-intake-form[data-is-new="true"] input, #wa-intake-detail .wa-intake-form[data-is-new="true"] textarea, #wa-intake-detail .wa-intake-form[data-is-new="true"] select', function () {
        scheduleDraftAutoSave();
    });

    // Salvar Rascunho manual
    $(document).on('click', '.wa-btn-save-draft', function (e) {
        e.preventDefault();
        if (draftAutoSaveTimer) { clearTimeout(draftAutoSaveTimer); }
        saveDraft(true);
    });

    // Cancelar novo rascunho
    $(document).on('click', '.wa-btn-cancel-new', function (e) {
        e.preventDefault();
        emptyDetail('Novo pré-atendimento cancelado');
    });

    // Abrir Workspace de Novo Pré-atendimento ao clicar no botão
    $(document).on('click', '#wa-btn-open-new-intake', function () {
        renderNewIntakeWorkspace();
    });

    // Alternar campo de senha conforme tipo
    $(document).on('change', '.wa-i-cred-type', function () {
        if ($(this).val() === 'NONE') {
            $('.wa-i-cred-val-wrap').hide();
            $('.wa-i-cred-value').val('');
        } else {
            $('.wa-i-cred-val-wrap').show();
            $('.wa-i-cred-value').focus();
        }
    });

    // Autocomplete de clientes do MapOS no workspace
    $(document).on('focus', '#wa-intake-client-autocomplete', function () {
        var $input = $(this);
        if ($input.data('ui-autocomplete') || !$.fn.autocomplete) { return; }
        $input.autocomplete({
            source: base.replace(/\/+$/, '') + '/index.php/os/autoCompleteCliente',
            minLength: 2,
            select: function (event, ui) {
                if (!ui || !ui.item) { return; }
                $('#wa-intake-client-id').val(ui.item.id);
                var label = ui.item.label || '';
                var parts = label.split('|');
                if (parts.length > 0) {
                    $('.wa-i-name').val($.trim(parts[0]));
                }
                for (var i = 1; i < parts.length; i++) {
                    var p = parts[i];
                    if (p.indexOf('Telefone:') !== -1 || p.indexOf('Celular:') !== -1) {
                        var rawPhone = p.split(':')[1] ? $.trim(p.split(':')[1]) : '';
                        if (rawPhone && rawPhone !== 'null') {
                            $('.wa-i-phone').val(rawPhone).trigger('input');
                        }
                    }
                    if (p.indexOf('Documento:') !== -1) {
                        var rawDoc = p.split(':')[1] ? $.trim(p.split(':')[1]) : '';
                        if (rawDoc && rawDoc !== 'null') {
                            $('.wa-i-cpf').val(rawDoc);
                        }
                    }
                }
                $('#wa-intake-client-match-badge').show().html('<span class="label label-success"><i class="fas fa-check"></i> Cliente #' + esc(ui.item.id) + ' selecionado do MapOS</span>');
                scheduleDraftAutoSave();
            }
        });
    });

    // Formatação de telefone
    $(document).on('input', '.wa-i-phone', function () {
        var v = $(this).val().replace(/\D/g, '');
        if (v.length > 11) { v = v.substring(0, 11); }
        if (v.length > 10) {
            $(this).val('(' + v.substring(0, 2) + ') ' + v.substring(2, 7) + '-' + v.substring(7));
        } else if (v.length > 6) {
            $(this).val('(' + v.substring(0, 2) + ') ' + v.substring(2, 6) + '-' + v.substring(6));
        } else if (v.length > 2) {
            $(this).val('(' + v.substring(0, 2) + ') ' + v.substring(2));
        } else if (v.length > 0) {
            $(this).val('(' + v);
        }
    });

    // Reject intake
    $(document).on('click', '.wa-intake-reject', function () {
        var form = $(this).closest('.wa-intake-form');
        var reason = window.prompt('Informe o motivo do descarte:');
        if (!reason) { return; }
        request('/pre_atendimento/' + encodeURIComponent(form.data('id')) + '/reject', 'POST', {review_version: form.data('version'), reason: reason}, function () { emptyDetail('Pré-atendimento descartado'); loadList(); });
    });

    // Offer pickup fee (with masked value parsing)
    $(document).on('click', '.wa-intake-offer-fee', function () {
        var form = $(this).closest('.wa-intake-form');
        var feeStr = String(form.find('.wa-i-pickup-fee').val() || '').trim();
        var cleanFee = feeStr.replace(/\./g, '').replace(',', '.');
        if (!cleanFee || isNaN(parseFloat(cleanFee)) || parseFloat(cleanFee) <= 0) {
            error('Informe um valor válido para a taxa de coleta.');
            return;
        }
        request('/pre_atendimento/' + encodeURIComponent(form.data('id')) + '/pickup-fee', 'POST', {
            review_version: form.data('version'), fee: cleanFee
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

    $(document).on('click', '#wa-btn-refresh-list', function () {
        loadList();
    });

    var autoRefreshTimer = null;
    function startAutoRefresh() {
        if (autoRefreshTimer) { clearInterval(autoRefreshTimer); }
        autoRefreshTimer = setInterval(function () {
            if (currentList === 'pending' && !$('#modal-confirm-receiving').is(':visible')) {
                request('/dados/intakes', 'GET', null, function (rows) {
                    if (currentList === 'pending') {
                        $('#wa-intakes-list').html('<h4>Aguardando recebimento e revisão</h4>' + intakeTable(rows, false));
                    }
                });
            }
        }, 12000);
    }

    var booted = false;
    function bootPanel() {
        if (booted) { return; }
        booted = true;
        window.__tecninaWhatsappPanel.booted = true;
        loadList();
        startAutoRefresh();

        try {
            var urlParams = new URLSearchParams(window.location.search);
            var initialIntakeId = urlParams.get('intake_id');
            if (initialIntakeId) {
                loadIntake(initialIntakeId);
            }
        } catch (e) {}
    }
    $(bootPanel);
    window.setTimeout(bootPanel, 500);
}(jQuery));
