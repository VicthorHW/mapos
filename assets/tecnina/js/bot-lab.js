(function ($) {
    'use strict';

    var config = $('#bot-lab-config');
    var base = String(config.attr('data-base') || '');
    var csrfName = String(config.attr('data-csrf-name') || '');
    var csrfHash = String(config.attr('data-csrf-hash') || '');

    var currentSimulationId = null;
    var currentSession = null;
    var inFlight = false;
    var STORAGE_KEY = 'tecnina.botLab.simulationId';

    function esc(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function showError(message) {
        $('#bot-lab-error').text(message).show();
        $('#bot-lab-success').hide();
    }

    function clearError() {
        $('#bot-lab-error').hide().text('');
    }

    function showSuccess(message) {
        $('#bot-lab-success').text(message).show();
        $('#bot-lab-error').hide();
        window.setTimeout(function () {
            $('#bot-lab-success').fadeOut();
        }, 4000);
    }

    function formatErrorMessage(reason, status) {
        if (status === 404 && (reason === 'gateway_request_failed' || reason === 'not_found')) {
            return 'O Simulator API não está disponível no Bot. Verifique se SIMULATOR_ENABLED está habilitado.';
        }
        var messages = {
            simulation_not_found: 'A simulação não existe mais. Crie uma nova sessão.',
            simulation_fixture_incomplete: 'Fixture incompleto: o bot tentou acessar um comportamento externo que não foi configurado para esta simulação.',
            simulation_runtime_state: 'A simulação não pode continuar no estado atual. Consulte a execução e resete a sessão.',
            simulation_execution_failed: 'A execução do simulador falhou inesperadamente. Consulte a última etapa registrada e resete a sessão.',
            simulation_manager_unavailable: 'O gerenciador de simulações está indisponível no Bot.',
            invalid_simulator_payload: 'Payload de simulação inválido.',
            invalid_simulator_message: 'Mensagem de simulação inválida.',
            invalid_simulator_location: 'Localização de simulação inválida.',
            invalid_simulation_id: 'ID de simulação inválido.',
            gateway_not_configured: 'Gateway do Bot não configurado no MapOS.',
            gateway_unavailable: 'Gateway do Bot indisponível no momento.',
            forbidden: 'Acesso não autorizado ao módulo Bot Lab.'
        };
        return messages[reason] || ('Erro na operação (' + (reason || status || 'desconhecido') + ').');
    }

    function getInitialSimulationId() {
        var queryMatch = window.location.search.match(/[?&]simulation_id=([a-f0-9\-]{36})/i);
        if (queryMatch && queryMatch[1]) {
            return queryMatch[1];
        }
        var stored = window.sessionStorage ? window.sessionStorage.getItem(STORAGE_KEY) : null;
        if (stored && /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i.test(stored)) {
            return stored;
        }
        return null;
    }

    function updateUrlSimulationId(id) {
        if (!window.history || !window.history.replaceState) {
            return;
        }
        var url = new URL(window.location.href);
        if (id) {
            url.searchParams.set('simulation_id', id);
        } else {
            url.searchParams.delete('simulation_id');
        }
        window.history.replaceState({}, '', url.toString());
    }

    function persistSessionId(id) {
        currentSimulationId = id;
        if (window.sessionStorage) {
            if (id) {
                window.sessionStorage.setItem(STORAGE_KEY, id);
            } else {
                window.sessionStorage.removeItem(STORAGE_KEY);
            }
        }
        updateUrlSimulationId(id);
    }

    function setInFlight(busy) {
        inFlight = busy;
        $('#btn-create-session').prop('disabled', busy);
        $('#btn-send-message').prop('disabled', busy);
        $('#wb-input-text').prop('disabled', busy);
        $('.wb-quick-btn').prop('disabled', busy);
        $('#btn-open-location').prop('disabled', busy);
        $('#btn-submit-location').prop('disabled', busy);
        $('#btn-reset-session').prop('disabled', busy);
        $('#btn-delete-session').prop('disabled', busy);
    }

    function request(path, method, data, done, fail, retryAttempt) {
        data = data || {};
        if (method !== 'GET') {
            data[csrfName] = csrfHash;
        }
        return $.ajax({
            url: base + path,
            method: method,
            data: data,
            dataType: 'json',
            timeout: 15000
        }).done(function (response) {
            if (response && response.csrf) {
                csrfHash = response.csrf;
            }
            if (!response || !response.ok) {
                var reason = (response && response.reason) ? response.reason : 'unknown';
                var status = (response && response.status) ? response.status : 500;
                if (fail) {
                    fail(reason, status, response);
                } else {
                    showError(formatErrorMessage(reason, status));
                }
                return;
            }
            if (done) {
                done(response.data);
            }
        }).fail(function (xhr) {
            var response = xhr.responseJSON || {};
            if (response.csrf) {
                csrfHash = response.csrf;
            }
            var reason = response.reason || 'network_error';
            var status = xhr.status || response.status || 500;

            if (method === 'GET' && !retryAttempt) {
                window.setTimeout(function () {
                    request(path, method, data, done, fail, true);
                }, 800);
                return;
            }

            if (fail) {
                fail(reason, status, response);
            } else {
                showError(formatErrorMessage(reason, status));
            }
        });
    }

    function switchMode(mode) {
        if (mode === 'WORKBENCH') {
            $('#bot-lab-setup').hide();
            $('#bot-lab-workbench').show();
        } else {
            $('#bot-lab-workbench').hide();
            $('#bot-lab-setup').show();
        }
    }

    function renderSession(sessionView) {
        currentSession = sessionView;
        currentSimulationId = sessionView.simulation_id;
        switchMode('WORKBENCH');

        // Toolbar
        var status = String(sessionView.runtime_status || 'UNKNOWN');
        var badge = $('#wb-status-badge');
        badge.text(status);
        badge.removeClass('label-success label-important label-inverse');
        if (status === 'ACTIVE') {
            badge.addClass('label-success');
            $('#wb-faulted-banner').hide();
            $('#wb-closed-banner').hide();
            $('#wb-composer').show();
            $('#wb-quick-actions').show();
        } else if (status === 'FAULTED') {
            badge.addClass('label-important');
            $('#wb-faulted-banner').show();
            $('#wb-closed-banner').hide();
            $('#wb-composer').hide();
            $('#wb-quick-actions').hide();
        } else if (status === 'CLOSED') {
            badge.addClass('label-inverse');
            $('#wb-faulted-banner').hide();
            $('#wb-closed-banner').show();
            $('#wb-composer').hide();
            $('#wb-quick-actions').hide();
        }

        $('#wb-phone').text(sessionView.synthetic_phone || '—');
        $('#wb-short-id').text(sessionView.simulation_id ? sessionView.simulation_id.substring(0, 8) : '—');

        // Transcript
        var chatBox = $('#wb-chat-box');
        chatBox.empty();
        var transcript = sessionView.transcript || [];
        if (transcript.length === 0) {
            chatBox.append('<div class="muted text-center" style="padding:40px 0;">Conversa vazia. Envie uma mensagem para iniciar o atendimento.</div>');
        } else {
            for (var i = 0; i < transcript.length; i++) {
                var entry = transcript[i];
                var isCustomer = (entry.actor === 'CUSTOMER');
                var row = $('<div class="bot-lab-msg-row ' + (isCustomer ? 'is-customer' : 'is-bot') + '"></div>');
                var bubble = $('<div class="bot-lab-bubble"></div>');

                if (entry.kind === 'LOCATION') {
                    var card = $('<div class="bot-lab-location-card"></div>');
                    card.append('<strong>Localização simulada</strong>');
                    card.append('<div>Latitude: ' + esc(entry.latitude) + '</div>');
                    card.append('<div>Longitude: ' + esc(entry.longitude) + '</div>');
                    if (entry.accuracy_meters != null) {
                        card.append('<div>Precisão: ' + esc(entry.accuracy_meters) + ' m</div>');
                    }
                    bubble.append(card);
                } else {
                    var textHtml = esc(entry.text || '').replace(/\n/g, '<br>');
                    bubble.html(textHtml);
                }
                row.append(bubble);
                chatBox.append(row);
            }
        }
        chatBox.scrollTop(chatBox[0].scrollHeight);

        // State Tab
        var st = sessionView.state || {};
        $('#st-draft-exists').text(st.active_draft_exists ? 'Sim' : 'Não');
        $('#st-draft-id').text(st.draft_id || '—');
        $('#st-stage').text(st.stage || '—');
        $('#st-status').text(st.status || '—');
        $('#st-service-mode').text(st.service_mode || '—');
        $('#st-review-version').text(st.review_version != null ? st.review_version : '—');
        $('#st-mapos-client').text(st.possible_mapos_client_id != null ? st.possible_mapos_client_id : '—');
        $('#st-credential-status').text(st.credential_status || '—');
        $('#st-pickup-fee-status').text(st.pickup_fee_status || '—');
        $('#st-human-takeover').text(st.human_takeover_state || '—');
        $('#st-capability-count').text(st.pending_capability_count != null ? st.pending_capability_count : '0');
        var caps = (st.capability_purposes && st.capability_purposes.length) ? st.capability_purposes.join(', ') : '—';
        $('#st-capability-purposes').text(caps);

        // Steps Tab
        var stepsContainer = $('#wb-steps-list');
        stepsContainer.empty();
        var steps = sessionView.steps || [];
        if (steps.length === 0) {
            stepsContainer.append('<p class="muted">Nenhuma etapa registrada nesta sessão.</p>');
        } else {
            for (var s = 0; s < steps.length; s++) {
                var step = steps[s];
                var isOk = (step.outcome === 'SUCCESS');
                var card = $('<div class="bot-lab-step-card"></div>');
                var header = $('<div class="bot-lab-card-header"></div>');
                header.append('<strong>Etapa #' + esc(step.step_index) + '</strong>');
                header.append('<span class="label ' + (isOk ? 'label-success' : 'label-important') + '">' + esc(step.outcome) + '</span>');
                card.append(header);

                var inputDesc = (step.input && step.input.kind === 'LOCATION')
                    ? ('Localização (' + esc(step.input.latitude) + ', ' + esc(step.input.longitude) + ')')
                    : ('Texto: "' + esc(step.input ? step.input.text : '') + '"');
                var meta = $('<div class="bot-lab-card-meta"></div>');
                meta.append('<div><strong>Entrada:</strong> ' + inputDesc + '</div>');

                var stageBefore = (step.state_before && step.state_before.stage) ? step.state_before.stage : '—';
                var stageAfter = (step.state_after && step.state_after.stage) ? step.state_after.stage : '—';
                meta.append('<div><strong>Transição:</strong> ' + esc(stageBefore) + ' &rarr; ' + esc(stageAfter) + '</div>');
                if (!isOk) {
                    meta.append('<div class="text-error"><strong>Erro:</strong> ' + esc(step.error_code || '—') + ' (' + esc(step.error_type || '—') + ')</div>');
                }
                card.append(meta);

                var msgs = step.outbound_messages || [];
                var effects = step.external_effects || [];
                if (msgs.length > 0 || effects.length > 0) {
                    var details = $('<div class="bot-lab-card-details"></div>');
                    if (msgs.length > 0) {
                        details.append('<div><strong>Mensagens geradas (' + msgs.length + '):</strong></div>');
                        for (var m = 0; m < msgs.length; m++) {
                            details.append('<div class="muted" style="margin-left:8px;">&bull; ' + esc(msgs[m].text) + '</div>');
                        }
                    }
                    if (effects.length > 0) {
                        details.append('<div style="margin-top:4px;"><strong>Efeitos gerados (' + effects.length + '):</strong> ' +
                            esc(effects.map(function (e) { return e.operation; }).join(', ')) + '</div>');
                    }
                    card.append(details);
                }
                stepsContainer.append(card);
            }
        }

        // Effects Tab
        var effectsContainer = $('#wb-effects-list');
        effectsContainer.empty();
        var allEffects = sessionView.external_effects || [];
        if (allEffects.length === 0) {
            effectsContainer.append('<p class="muted">Nenhum efeito externo registrado nesta sessão.</p>');
        } else {
            for (var e = 0; e < allEffects.length; e++) {
                var eff = allEffects[e];
                var effCard = $('<div class="bot-lab-effect-card"></div>');
                var effHeader = $('<div class="bot-lab-card-header"></div>');
                effHeader.append('<strong>#' + esc(eff.sequence) + ' ' + esc(eff.operation) + '</strong>');
                effHeader.append('<span class="label">' + esc(eff.category) + '</span>');
                effCard.append(effHeader);

                if (eff.details && typeof eff.details === 'object') {
                    var jsonText = JSON.stringify(eff.details, null, 2);
                    effCard.append('<pre class="bot-lab-json-snippet">' + esc(jsonText) + '</pre>');
                }
                effectsContainer.append(effCard);
            }
        }
    }

    function refreshCurrentSession(doneCallback) {
        if (!currentSimulationId) {
            return;
        }
        request('/simulador_sessao/' + encodeURIComponent(currentSimulationId), 'GET', null, function (sessionView) {
            renderSession(sessionView);
            if (doneCallback) {
                doneCallback(sessionView);
            }
        }, function (reason, status) {
            if (reason === 'simulation_not_found') {
                persistSessionId(null);
                switchMode('SETUP');
                showError('A simulação não existe mais. Crie uma nova sessão.');
            } else {
                showError(formatErrorMessage(reason, status));
            }
        });
    }

    // UI Event: Toggle client mode fields
    $('#setup-client-mode').on('change', function () {
        var mode = $(this).val();
        if (mode === 'UNIQUE') {
            $('#setup-unique-fields').slideDown(200);
        } else {
            $('#setup-unique-fields').slideUp(200);
        }
    });

    // UI Event: Toggle orders custom table
    $('#setup-orders-mode').on('change', function () {
        var mode = $(this).val();
        if (mode === 'CUSTOM') {
            $('#setup-orders-custom').slideDown(200);
            if ($('#setup-orders-tbody tr').length === 0) {
                $('#btn-add-order').trigger('click');
            }
        } else {
            $('#setup-orders-custom').slideUp(200);
        }
    });

    // UI Event: Add order row
    $('#btn-add-order').on('click', function () {
        var row = $('<tr>' +
            '<td><input type="number" class="input-block-level ord-id" min="1" placeholder="100" style="margin:0;"></td>' +
            '<td><input type="text" class="input-block-level ord-status" placeholder="Aberto" style="margin:0;"></td>' +
            '<td><input type="text" class="input-block-level ord-type" placeholder="Notebook" style="margin:0;"></td>' +
            '<td><input type="text" class="input-block-level ord-brand" placeholder="Dell" style="margin:0;"></td>' +
            '<td><input type="text" class="input-block-level ord-model" placeholder="Inspiron" style="margin:0;"></td>' +
            '<td class="text-center"><button type="button" class="btn btn-mini btn-danger btn-remove-row"><i class="bx bx-trash"></i></button></td>' +
            '</tr>');
        $('#setup-orders-tbody').append(row);
    });

    // UI Event: Add CEP row
    $('#btn-add-cep').on('click', function () {
        var row = $('<tr>' +
            '<td><input type="text" class="input-block-level cep-code" placeholder="80010000" style="margin:0;"></td>' +
            '<td><input type="text" class="input-block-level cep-street" placeholder="Rua XV de Novembro" style="margin:0;"></td>' +
            '<td><input type="text" class="input-block-level cep-neigh" placeholder="Centro" style="margin:0;"></td>' +
            '<td><input type="text" class="input-block-level cep-city" placeholder="Curitiba" style="margin:0;"></td>' +
            '<td><input type="text" class="input-block-level cep-uf" placeholder="PR" maxlength="2" style="margin:0;"></td>' +
            '<td class="text-center"><button type="button" class="btn btn-mini btn-danger btn-remove-row"><i class="bx bx-trash"></i></button></td>' +
            '</tr>');
        $('#setup-cep-tbody').append(row);
    });

    // UI Event: Remove row in dynamic tables
    $(document).on('click', '.btn-remove-row', function () {
        $(this).closest('tr').remove();
    });

    // UI Event: Create Session
    $('#btn-create-session').on('click', function () {
        clearError();
        var phone = $.trim($('#setup-phone').val());
        if (!phone) {
            showError('Informe o telefone sintético para a simulação.');
            $('#setup-phone').focus();
            return;
        }

        var clientMode = $('#setup-client-mode').val();
        var maposObj = {
            client_match_mode: clientMode
        };

        if (clientMode === 'UNIQUE') {
            var clientId = parseInt($('#setup-client-id').val(), 10);
            var clientName = $.trim($('#setup-client-name').val());
            if (!clientId || clientId < 1) {
                showError('Para cliente existente, informe um Client ID válido (maior que 0).');
                $('#setup-client-id').focus();
                return;
            }
            if (!clientName) {
                showError('Para cliente existente, informe o nome do cliente.');
                $('#setup-client-name').focus();
                return;
            }
            maposObj.client_id = clientId;
            maposObj.client_name = clientName;

            var ordersMode = $('#setup-orders-mode').val();
            if (ordersMode === 'NONE') {
                maposObj.open_orders = [];
            } else if (ordersMode === 'CUSTOM') {
                var orderRows = [];
                var validOrders = true;
                $('#setup-orders-tbody tr').each(function () {
                    var tr = $(this);
                    var oid = parseInt(tr.find('.ord-id').val(), 10);
                    var ost = $.trim(tr.find('.ord-status').val());
                    var otype = $.trim(tr.find('.ord-type').val());
                    var obrand = $.trim(tr.find('.ord-brand').val());
                    var omodel = $.trim(tr.find('.ord-model').val());
                    if (!oid || !ost || !otype || !obrand || !omodel) {
                        validOrders = false;
                        return false;
                    }
                    orderRows.push({
                        os_id: oid,
                        mapos_status: ost,
                        device_type: otype,
                        brand: obrand,
                        model: omodel
                    });
                });
                if (!validOrders || orderRows.length === 0) {
                    showError('Preencha todos os campos das ordens de serviço adicionadas.');
                    return;
                }
                maposObj.open_orders = orderRows;
            }
        }

        var regDelivery = $('#setup-reg-delivery').val();
        if (regDelivery && regDelivery !== 'UNCONFIGURED') {
            maposObj.registration_code_delivery = regDelivery;
        }

        var createClientId = parseInt($('#setup-create-client-id').val(), 10);
        if (createClientId && createClientId > 0) {
            maposObj.create_client_id = createClientId;
        }

        var postalCodes = [];
        $('#setup-cep-tbody tr').each(function () {
            var tr = $(this);
            var cep = $.trim(tr.find('.cep-code').val());
            if (cep) {
                postalCodes.push({
                    postal_code: cep,
                    street: $.trim(tr.find('.cep-street').val()) || null,
                    neighborhood: $.trim(tr.find('.cep-neigh').val()) || null,
                    city: $.trim(tr.find('.cep-city').val()) || null,
                    state: $.trim(tr.find('.cep-uf').val()) || null
                });
            }
        });

        var payload = {
            synthetic_phone: phone,
            mapos: maposObj
        };
        if (postalCodes.length > 0) {
            payload.postal_codes = postalCodes;
        }

        setInFlight(true);
        request('/simulador_criar', 'POST', { payload: JSON.stringify(payload) }, function (sessionView) {
            setInFlight(false);
            persistSessionId(sessionView.simulation_id);
            renderSession(sessionView);
            showSuccess('Sessão de simulação iniciada com sucesso.');
        }, function (reason, status) {
            setInFlight(false);
            showError(formatErrorMessage(reason, status));
        });
    });

    // Helper: Send message string
    function sendMessageText(text) {
        if (!currentSimulationId || inFlight) {
            return;
        }
        if (!text || !$.trim(text)) {
            return;
        }
        clearError();
        setInFlight(true);
        request('/simulador_mensagem/' + encodeURIComponent(currentSimulationId), 'POST', { text: text }, function (stepResponse) {
            setInFlight(false);
            $('#wb-input-text').val('').focus();
            if (stepResponse && stepResponse.session) {
                renderSession(stepResponse.session);
            }
        }, function (reason, status) {
            setInFlight(false);
            showError(formatErrorMessage(reason, status));
            if (reason === 'simulation_fixture_incomplete' || reason === 'simulation_runtime_state' || reason === 'simulation_execution_failed') {
                refreshCurrentSession();
            } else if (reason === 'simulation_not_found') {
                persistSessionId(null);
                switchMode('SETUP');
            }
        });
    }

    // UI Event: Send Message
    $('#btn-send-message').on('click', function () {
        var text = $('#wb-input-text').val();
        sendMessageText(text);
    });

    $('#wb-input-text').on('keypress', function (e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#btn-send-message').trigger('click');
        }
    });

    // UI Event: Quick action buttons
    $(document).on('click', '.wb-quick-btn', function () {
        var text = $(this).data('text');
        sendMessageText(String(text));
    });

    // UI Event: Open location modal
    $('#btn-open-location').on('click', function () {
        clearError();
        $('#loc-lat').val('');
        $('#loc-lng').val('');
        $('#loc-acc').val('');
        $('#modal-location').modal('show');
    });

    // UI Event: Submit Location
    $('#btn-submit-location').on('click', function () {
        if (!currentSimulationId || inFlight) {
            return;
        }
        var lat = $('#loc-lat').val();
        var lng = $('#loc-lng').val();
        var acc = $('#loc-acc').val();

        if (!lat || isNaN(parseFloat(lat)) || parseFloat(lat) < -90 || parseFloat(lat) > 90) {
            alert('Informe uma latitude válida entre -90.0 e 90.0');
            $('#loc-lat').focus();
            return;
        }
        if (!lng || isNaN(parseFloat(lng)) || parseFloat(lng) < -180 || parseFloat(lng) > 180) {
            alert('Informe uma longitude válida entre -180.0 e 180.0');
            $('#loc-lng').focus();
            return;
        }

        var data = {
            latitude: lat,
            longitude: lng,
            accuracy_meters: acc ? acc : ''
        };

        setInFlight(true);
        request('/simulador_localizacao/' + encodeURIComponent(currentSimulationId), 'POST', data, function (stepResponse) {
            setInFlight(false);
            $('#modal-location').modal('hide');
            if (stepResponse && stepResponse.session) {
                renderSession(stepResponse.session);
            }
        }, function (reason, status) {
            setInFlight(false);
            $('#modal-location').modal('hide');
            showError(formatErrorMessage(reason, status));
            if (reason === 'simulation_fixture_incomplete' || reason === 'simulation_runtime_state' || reason === 'simulation_execution_failed') {
                refreshCurrentSession();
            } else if (reason === 'simulation_not_found') {
                persistSessionId(null);
                switchMode('SETUP');
            }
        });
    });

    // UI Event: Reset Session
    $('#btn-reset-session').on('click', function () {
        if (!currentSimulationId || inFlight) {
            return;
        }
        if (!window.confirm('Tem certeza que deseja resetar a simulação? O histórico de mensagens será reiniciado mantendo os fixtures.')) {
            return;
        }
        clearError();
        setInFlight(true);
        request('/simulador_reset/' + encodeURIComponent(currentSimulationId), 'POST', {}, function (sessionView) {
            setInFlight(false);
            renderSession(sessionView);
            showSuccess('Sessão resetada com sucesso.');
        }, function (reason, status) {
            setInFlight(false);
            showError(formatErrorMessage(reason, status));
            if (reason === 'simulation_runtime_state' || reason === 'simulation_execution_failed') {
                refreshCurrentSession();
            } else if (reason === 'simulation_not_found') {
                persistSessionId(null);
                switchMode('SETUP');
            }
        });
    });

    // UI Event: Delete / End Session
    $('#btn-delete-session').on('click', function () {
        if (!currentSimulationId || inFlight) {
            return;
        }
        if (!window.confirm('Encerrar e descartar esta sessão de simulação?')) {
            return;
        }
        clearError();
        setInFlight(true);
        request('/simulador_excluir/' + encodeURIComponent(currentSimulationId), 'POST', {}, function () {
            setInFlight(false);
            persistSessionId(null);
            currentSession = null;
            switchMode('SETUP');
            showSuccess('Sessão de simulação encerrada.');
        }, function (reason, status) {
            setInFlight(false);
            if (reason === 'simulation_not_found') {
                persistSessionId(null);
                currentSession = null;
                switchMode('SETUP');
                showSuccess('Sessão descartada.');
            } else {
                showError(formatErrorMessage(reason, status));
            }
        });
    });

    // Initialization: Check for restored session
    var initialId = getInitialSimulationId();
    if (initialId) {
        currentSimulationId = initialId;
        refreshCurrentSession();
    }

})(jQuery);
