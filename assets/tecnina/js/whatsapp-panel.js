(function ($) {
    'use strict';
    window.__tecninaWhatsappPanel = {executed: true, booted: false};
    var panelConfig = $('#wa-panel-config');
    var base = String(panelConfig.attr('data-base') || '');
    var osEditBase = String(panelConfig.attr('data-os-edit-base') || '');
    var csrfName = String(panelConfig.attr('data-csrf-name') || '');
    var csrfHash = String(panelConfig.attr('data-csrf-hash') || '');
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
            ,flow_not_found: 'O fluxo não existe mais.'
            ,flow_version_not_found: 'A versão selecionada não existe mais.'
            ,flow_version_missing: 'O fluxo ainda não possui uma versão disponível.'
            ,draft_not_found: 'Crie um rascunho antes de editar ou validar.'
            ,stale_flow_revision: 'Este fluxo foi alterado. Reabra-o antes de salvar novamente.'
            ,invalid_flow_definition: 'O JSON do fluxo é inválido ou excede o limite permitido.'
        };
        return messages[reason] || 'Não foi possível concluir a operação.';
    }
    function request(path, method, data, done, retryAttempt) {
        data = data || {}; if (method !== 'GET') { data[csrfName] = csrfHash; }
        return $.ajax({url: base + path, method: method, data: data, dataType: 'json', timeout: 12000})
            .done(function (response) { if (response.csrf) { csrfHash = response.csrf; } if (!response.ok) { error(reasonMessage(response.reason)); return; } $('#wa-error').hide(); done(response.data); })
            .fail(function (xhr) {
                var response=xhr.responseJSON || {}; if (response.csrf) { csrfHash=response.csrf; }
                // The gateway can still be warming up immediately after a deploy. Retry only
                // idempotent reads once, without retrying actions that could change state.
                if (method === 'GET' && !retryAttempt) {
                    window.setTimeout(function () { request(path, method, data, done, true); }, 800);
                    return;
                }
                error(reasonMessage(response.reason));
            });
    }
    function loadOverview() { request('/dados/overview', 'GET', null, function (d) {
        var c = d.components || {}, q = d.queue || {};
        runtimeNotifications = d.runtime_notifications_enabled === true;
        $('#wa-overview').html('<div class="span3"><strong>Gateway</strong><br>' + (c.gateway && c.gateway.ok ? 'Online' : 'Indisponível') + '</div>' +
            '<div class="span3"><strong>MapOS</strong><br>' + (c.mapos && c.mapos.ok ? 'Online' : 'Indisponível') + '</div>' +
            '<div class="span3"><strong>Evolution</strong><br>' + (c.evolution && c.evolution.ok ? 'Online' : 'Indisponível') + '</div>' +
            '<div class="span3"><strong>Fila pendente</strong><br>' + esc((q.PENDING || 0) + (q.RETRY || 0) + (q.DEFERRED || 0)) + '</div>');
    }); }
    function loadConversations() { request('/dados/conversations', 'GET', null, function (rows) {
        var h = '<table class="table table-bordered"><thead><tr><th>Contato</th><th>Estado</th><th>Até</th><th>Ação</th></tr></thead><tbody>';
        $.each(rows, function(_, r) { h += '<tr><td>' + esc(r.phone_tail) + '</td><td>' + esc(r.state) + '</td><td>' + esc(r.human_until || '—') + '</td><td><button class="btn btn-mini wa-lock" data-id="' + r.id + '">Pausar bot</button> <button class="btn btn-mini wa-resume" data-id="' + r.id + '">Retomar</button> <button class="btn btn-mini wa-flow-observe" data-id="' + r.id + '">Fluxo</button></td></tr>'; });
        $('#wa-conversations').html(h + '</tbody></table>');
    }); }
    var flowDrafts = {}, flowDraftRevisions = {};
    function loadFlows() { request('/dados/flows', 'GET', null, function(rows) {
        var h='<table class="table table-bordered"><thead><tr><th>Fluxo</th><th>Tipo</th><th>Estado</th><th></th></tr></thead><tbody>';
        $.each(rows, function(_, r) {
            var state=r.enabled ? esc(r.mode) : 'PLANEJADO';
            var versions='Publicada: v' + esc(r.version || '—') + (r.draft_version ? '<br>Rascunho: v' + esc(r.draft_version) : '');
            h += '<tr><td><strong>' + esc(r.name) + '</strong><br><small>' + esc(r.description) + '</small></td><td>' + esc(r.flow_type) + '</td><td>' + state + '<br><small>' + versions + '</small></td><td><button class="btn btn-mini wa-flow-open" data-key="' + esc(r.key) + '">Abrir</button></td></tr>';
        });
        $('#wa-flows-list').html(h + '</tbody></table>');
    }); }
    function shortFlowText(value, limit) {
        value=String(value || '');
        return value.length > limit ? value.substring(0, limit - 1) + '…' : value;
    }
    function flowLayout(nodes, edges) {
        var byKey={}, levels={}, queue=[], groups={}, maxLevel=0, nodeWidth=220, nodeHeight=72, gap=42, rowGap=94;
        $.each(nodes, function(_, node) { byKey[node.key]=node; if (node.type === 'START') { levels[node.key]=0; queue.push(node.key); } });
        while (queue.length) {
            var source=queue.shift(), sourceLevel=levels[source];
            $.each(edges, function(_, edge) {
                if (edge.from === source && byKey[edge.to] && levels[edge.to] === undefined) {
                    levels[edge.to]=sourceLevel + 1;
                    maxLevel=Math.max(maxLevel, levels[edge.to]);
                    queue.push(edge.to);
                }
            });
        }
        $.each(nodes, function(_, node) { if (levels[node.key] === undefined) { levels[node.key]=++maxLevel; } });
        $.each(nodes, function(_, node) { var level=levels[node.key]; groups[level]=groups[level] || []; groups[level].push(node); maxLevel=Math.max(maxLevel, level); });
        var maxInLevel=1;
        $.each(groups, function(_, group) { maxInLevel=Math.max(maxInLevel, group.length); });
        var width=Math.max(900, maxInLevel * nodeWidth + (maxInLevel - 1) * gap + 100), positions={};
        $.each(groups, function(level, group) {
            var total=group.length * nodeWidth + (group.length - 1) * gap, start=(width-total)/2;
            $.each(group, function(index, node) { positions[node.key]={x:start + index*(nodeWidth+gap), y:38 + Number(level)*(nodeHeight+rowGap)}; });
        });
        return {positions:positions,width:width,height:Math.max(220, 38+(maxLevel+1)*(nodeHeight+rowGap)),nodeWidth:nodeWidth,nodeHeight:nodeHeight};
    }
    function flowDiagram(d, currentNode) {
        var nodes=d.nodes || [], edges=d.edges || [], layout=flowLayout(nodes,edges), p=layout.positions, marker='wa-flow-arrow-' + String(d.key || 'flow').replace(/[^a-z0-9_-]/gi,''), svg='';
        svg += '<div class="wa-flow-tools" style="margin-bottom:6px"><button class="btn btn-mini wa-flow-zoom" data-scale="75">−</button> <button class="btn btn-mini wa-flow-zoom" data-scale="100">Ajustar</button> <button class="btn btn-mini wa-flow-zoom" data-scale="135">+</button></div>';
        svg += '<div class="wa-flow-canvas" style="overflow:auto;background:#fff;border:1px solid #ddd"><svg viewBox="0 0 '+layout.width+' '+layout.height+'" role="img" aria-label="Diagrama do fluxo '+esc(d.name)+'" style="display:block;width:100%;min-width:700px;transition:width .15s ease"><defs><marker id="'+marker+'" markerWidth="9" markerHeight="9" refX="8" refY="3" orient="auto"><path d="M0,0 L0,6 L9,3 z" fill="#667085"></path></marker></defs>';
        $.each(edges,function(index,e){
            var a=p[e.from], b=p[e.to]; if(!a||!b){return;}
            var sx=a.x+layout.nodeWidth/2, sy=a.y+layout.nodeHeight, tx=b.x+layout.nodeWidth/2, ty=b.y, path, lx, ly;
            if (ty > sy) {
                var mid=sy+(ty-sy)/2;
                path='M'+sx+' '+sy+' C'+sx+' '+mid+' '+tx+' '+mid+' '+tx+' '+ty;
                lx=(sx+tx)/2; ly=mid-6;
            } else {
                var lane=layout.width-28-(index%5)*14;
                sx=a.x+layout.nodeWidth; sy=a.y+layout.nodeHeight/2; tx=b.x+layout.nodeWidth; ty=b.y+layout.nodeHeight/2;
                path='M'+sx+' '+sy+' C'+lane+' '+sy+' '+lane+' '+ty+' '+tx+' '+ty;
                lx=lane-8; ly=(sy+ty)/2-6;
            }
            svg+='<path d="'+path+'" stroke="#667085" stroke-width="1.8" fill="none" marker-end="url(#'+marker+')"></path>';
            if(e.label){svg+='<text x="'+lx+'" y="'+ly+'" text-anchor="middle" font-size="12" fill="#475467" style="paint-order:stroke;stroke:#fff;stroke-width:6px;stroke-linejoin:round">'+esc(shortFlowText(e.label,34))+'</text>';}
        });
        $.each(nodes,function(_,n){
            var point=p[n.key], active=currentNode===n.key, planned=n.implementation_status && n.implementation_status !== 'ACTIVE';
            var fill=active?'#d9edf7':(planned?'#fff7e6':'#f8fafc'), stroke=active?'#31708f':(planned?'#b7791f':'#98a2b3');
            svg+='<g class="wa-flow-node" data-node="'+esc(n.key)+'"><rect x="'+point.x+'" y="'+point.y+'" width="'+layout.nodeWidth+'" height="'+layout.nodeHeight+'" rx="9" fill="'+fill+'" stroke="'+stroke+'" stroke-width="'+(active?'3':'1.5')+'"></rect><text x="'+(point.x+layout.nodeWidth/2)+'" y="'+(point.y+27)+'" text-anchor="middle" font-size="13" font-weight="bold">'+esc(n.type)+'</text><text x="'+(point.x+layout.nodeWidth/2)+'" y="'+(point.y+51)+'" text-anchor="middle" font-size="12">'+esc(shortFlowText(n.label,31))+'</text></g>';
        });
        return svg+'</svg></div>';
    }
    function flowNotes(d) { var notes=[]; $.each(d.nodes || [], function(_, n) { if (n.implementation_status && n.implementation_status !== 'ACTIVE') { notes.push('<li><strong>' + esc(n.label) + ':</strong> ' + esc(n.implementation_status) + (n.note ? ' — ' + esc(n.note) : '') + '</li>'); } }); return notes.length ? '<div class="alert alert-info"><strong>Estado dos nós:</strong><ul>' + notes.join('') + '</ul></div>' : ''; }
    function cleanFlowDefinition(value) {
        var result=$.extend(true, {}, value);
        $.each(['published_version','revision','draft_version','state'], function(_, key) { delete result[key]; });
        return result;
    }
    function syncDraftJson(key) { $('.wa-flow-json').val(JSON.stringify(cleanFlowDefinition(flowDrafts[key]), null, 2)); }
    function renderDraftEditor(key) {
        var d=flowDrafts[key], nodes='', edges=''; if(!d){return;}
        $.each(d.nodes || [], function(index,node) { nodes+='<label><code>'+esc(node.key)+'</code><input class="input-block-level wa-flow-node-label" data-key="'+esc(key)+'" data-index="'+index+'" maxlength="160" value="'+esc(node.label)+'"></label>'; });
        $.each(d.edges || [], function(index,edge) { edges+='<label><code>'+esc(edge.from)+' → '+esc(edge.to)+'</code><input class="input-block-level wa-flow-edge-label" data-key="'+esc(key)+'" data-index="'+index+'" maxlength="160" value="'+esc(edge.label || '')+'" placeholder="Descrição da transição"></label>'; });
        var html='<hr><h5>Editor visual do rascunho v'+esc(d.version)+'</h5><p class="muted">Edite nomes dos passos e transições. A estrutura completa também pode ser revisada no JSON; salvar não publica e não altera conversas em andamento.</p><div class="row-fluid"><div class="span6"><strong>Passos</strong>'+nodes+'</div><div class="span6"><strong>Transições</strong>'+edges+'</div></div><label><strong>Formato para edição por IA</strong></label><textarea class="input-block-level wa-flow-json" rows="18" spellcheck="false" style="font-family:monospace"></textarea><button class="btn btn-primary wa-flow-save-draft" data-key="'+esc(key)+'">Salvar rascunho</button> <button class="btn wa-flow-copy-ai" data-key="'+esc(key)+'">Copiar pacote para IA</button> <button class="btn wa-flow-import-ai" data-key="'+esc(key)+'">Aplicar JSON no editor</button><div class="wa-flow-editor-status" style="margin-top:8px"></div>';
        $('#wa-flow-editor').html(html); syncDraftJson(key); $('#wa-flow-diagram').html(flowDiagram(d));
    }
    function loadFlowDraft(key) { request('/fluxo/' + encodeURIComponent(key) + '/draft', 'GET', null, function(d) { flowDrafts[key]=d; flowDraftRevisions[key]=d.revision; renderDraftEditor(key); }); }
    function flowSimulation(key) {
        return '<hr><h5>Simulador seguro</h5><p class="muted">Executa decisões com dados fictícios somente dentro do Gateway. Não consulta clientes reais, não cria OS, não gera link e não envia WhatsApp.</p><select class="wa-flow-scenario"><option value="NEW_CUSTOMER">Novo cliente</option><option value="EXISTING_CUSTOMER">Cliente existente</option><option value="AMBIGUOUS_CUSTOMER">Telefone ambíguo</option><option value="HUMAN_LOCK">Atendimento em pausa</option><option value="MAPOS_OFFLINE">MapOS indisponível</option><option value="PICKUP">Pré-atendimento com coleta</option><option value="DROP_OFF">Cliente traz o equipamento</option><option value="LOCATION_PENDING">Localização pendente</option><option value="FALLBACK">Mensagem não compreendida</option></select><label>Mensagens fictícias opcionais (uma por linha)</label><textarea class="input-block-level wa-flow-messages" rows="3" maxlength="5000" placeholder="oi&#10;preciso de assistência"></textarea><button class="btn btn-primary btn-mini wa-flow-simulate" data-key="'+esc(key)+'">Executar simulação segura</button><div class="wa-flow-trace" style="display:none;margin-top:10px"></div>';
    }
    function renderFlowTrace(d) {
        var rows=''; $.each(d.trace || [], function(index,step) { rows+='<tr><td>'+(index+1)+'</td><td>'+esc(step.event)+'</td><td><code>'+esc(step.node_key)+'</code></td><td>'+esc(step.reason)+'</td></tr>'; });
        return '<div class="alert alert-success"><strong>'+esc(d.scenario_label || d.scenario)+'</strong><br>'+esc(d.output_preview || '')+'<br><small>Modo seguro: '+(d.side_effects ? 'há efeitos externos' : 'nenhum efeito externo')+'.</small></div><table class="table table-bordered table-condensed"><thead><tr><th>#</th><th>Decisão</th><th>Passo</th><th>Motivo</th></tr></thead><tbody>'+rows+'</tbody></table>';
    }
    function loadFlow(key, currentNode) { request('/fluxo/' + encodeURIComponent(key), 'GET', null, function(d) {
        var versioning=d.draft_version ? '<strong>Rascunho v' + esc(d.draft_version) + '</strong> <button class="btn btn-mini wa-flow-validate" data-key="' + esc(d.key) + '">Validar</button> <button class="btn btn-warning btn-mini wa-flow-publish" data-key="' + esc(d.key) + '" data-revision="' + esc(d.revision) + '">Publicar</button>' : '<button class="btn btn-mini wa-flow-draft" data-key="' + esc(d.key) + '" data-revision="' + esc(d.revision) + '">Criar rascunho editável</button>';
        var simulation=d.enabled ? flowSimulation(d.key) : '<div class="alert alert-info">Este fluxo está planejado; o NLU ainda não participa do atendimento real.</div>';
        var h='<hr><h4>' + esc(d.name) + ' <small>publicada v' + esc(d.version) + ' · ' + esc(d.mode) + '</small></h4><p>' + esc(d.description) + '</p><div class="well"><div id="wa-flow-diagram">'+flowDiagram(d,currentNode)+'</div><p class="muted">O desenho é organizado automaticamente por etapas. Use −/Ajustar/+ para controlar o tamanho.</p>'+flowNotes(d)+'<hr>'+versioning+'<div id="wa-flow-editor"></div>'+simulation+'<div id="wa-flow-history"></div></div>';
        $('#wa-flow-detail').html(h); loadFlowHistory(key, d.revision); if(d.draft_version){loadFlowDraft(key);}
    }); }
    function loadFlowHistory(key, revision) { request('/fluxo/' + encodeURIComponent(key) + '/versoes', 'GET', null, function(rows) { var h='<hr><strong>Histórico de versões</strong><br><select class="wa-flow-rollback-version">'; $.each(rows,function(_,row){ h+='<option value="'+esc(row.version)+'">v'+esc(row.version)+' — '+esc(row.state)+' — '+esc(row.checksum)+'</option>'; }); h+='</select> <button class="btn btn-mini wa-flow-rollback" data-key="'+esc(key)+'" data-revision="'+esc(revision)+'">Restaurar como nova versão</button>'; $('#wa-flow-history').html(h); }); }
    function observeFlow(id) { request('/conversa/' + id + '/flow-observer', 'GET', null, function(d) { $('#wa-flow-observer').html('<div class="alert alert-info"><strong>Conversa observada:</strong> ' + esc(d.flow_key) + ' · nó atual <code>' + esc(d.current_node) + '</code><br><small>' + esc((d.trace && d.trace[0] && d.trace[0].reason) || '') + '</small></div>'); loadFlow(d.flow_key, d.current_node); }); }
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
    $(document).on('click', '.wa-flow-open', function () { loadFlow($(this).data('key')); });
    $(document).on('click', '.wa-flow-observe', function () { observeFlow($(this).data('id')); });
    $(document).on('click', '.wa-flow-zoom', function () { $(this).closest('.wa-flow-tools').next('.wa-flow-canvas').find('svg').css('width', String($(this).data('scale')) + '%'); });
    $(document).on('click', '.wa-flow-simulate', function () { var button=$(this), box=button.closest('.well'), messages=lines(box.find('.wa-flow-messages').val()); request('/fluxo/' + encodeURIComponent(button.data('key')) + '/simular', 'POST', {scenario: box.find('.wa-flow-scenario').val(), messages:messages}, function (d) { box.find('.wa-flow-trace').html(renderFlowTrace(d)).show(); }); });
    $(document).on('click', '.wa-flow-draft', function () { var button=$(this); request('/fluxo/' + encodeURIComponent(button.data('key')) + '/draft', 'POST', {expected_revision:button.data('revision')}, function () { loadFlows(); loadFlow(button.data('key')); }); });
    $(document).on('input', '.wa-flow-node-label', function () { var input=$(this), key=input.data('key'), index=Number(input.data('index')); flowDrafts[key].nodes[index].label=input.val(); syncDraftJson(key); $('#wa-flow-diagram').html(flowDiagram(flowDrafts[key])); });
    $(document).on('input', '.wa-flow-edge-label', function () { var input=$(this), key=input.data('key'), index=Number(input.data('index')), value=$.trim(input.val()); if(value){flowDrafts[key].edges[index].label=value;}else{delete flowDrafts[key].edges[index].label;} syncDraftJson(key); $('#wa-flow-diagram').html(flowDiagram(flowDrafts[key])); });
    $(document).on('click', '.wa-flow-import-ai', function () { var key=$(this).data('key'), raw=$('.wa-flow-json').val(), parsed; try { parsed=JSON.parse(raw); parsed=parsed.flow || parsed; if(!parsed || !$.isArray(parsed.nodes) || !$.isArray(parsed.edges)){throw new Error('invalid');} flowDrafts[key]=$.extend(true, {}, parsed, {revision:flowDraftRevisions[key], draft_version:flowDrafts[key].draft_version, state:'DRAFT'}); renderDraftEditor(key); $('.wa-flow-editor-status').html('<div class="alert alert-info">JSON aplicado somente ao editor. Clique em Salvar rascunho para persistir.</div>'); } catch(exception) { $('.wa-flow-editor-status').html('<div class="alert alert-error">JSON inválido ou sem nodes/edges.</div>'); } });
    $(document).on('click', '.wa-flow-save-draft', function () { var key=$(this).data('key'), raw=$('.wa-flow-json').val(), parsed; try { parsed=JSON.parse(raw); parsed=parsed.flow || parsed; } catch(exception) { $('.wa-flow-editor-status').html('<div class="alert alert-error">Corrija o JSON antes de salvar.</div>'); return; } request('/fluxo/' + encodeURIComponent(key) + '/salvar-draft', 'POST', {expected_revision:flowDraftRevisions[key], definition:JSON.stringify(parsed)}, function (d) { flowDrafts[key]=d; flowDraftRevisions[key]=d.revision; renderDraftEditor(key); $('.wa-flow-publish,.wa-flow-rollback').data('revision',d.revision).attr('data-revision',d.revision); $('.wa-flow-editor-status').html('<div class="alert alert-success">Rascunho salvo e auditado. Valide antes de publicar.</div>'); loadFlows(); loadFlowHistory(key,d.revision); }); });
    $(document).on('click', '.wa-flow-copy-ai', function () { var key=$(this).data('key'); request('/fluxo/' + encodeURIComponent(key) + '/exportar-ia', 'GET', null, function (d) { var value=JSON.stringify(d,null,2); $('.wa-flow-json').val(value).focus().select(); if(navigator.clipboard && navigator.clipboard.writeText){navigator.clipboard.writeText(value);} $('.wa-flow-editor-status').html('<div class="alert alert-success">Pacote com instruções e limites preparado. Se a cópia automática falhar, use Ctrl+C no campo selecionado.</div>'); }); });
    $(document).on('click', '.wa-flow-validate', function () { var button=$(this); request('/fluxo/' + encodeURIComponent(button.data('key')) + '/validar', 'POST', {}, function (d) { var warnings=(d.warnings || []).length ? '<br><small>Avisos: '+esc(d.warnings.join(', '))+'</small>' : ''; $('#wa-flow-editor').prepend('<div class="alert '+(d.valid?'alert-success':'alert-error')+'">Validação: '+(d.valid?'aprovada':esc((d.errors || []).join(', ')))+warnings+'</div>'); }); });
    $(document).on('click', '.wa-flow-publish', function () { var button=$(this); if(!window.confirm('Publicar este rascunho validado? A FSM atual continuará soberana até a ativação controlada do runtime visual.')){return;} request('/fluxo/' + encodeURIComponent(button.data('key')) + '/publicar', 'POST', {expected_revision:button.data('revision')}, function () { loadFlows(); loadFlow(button.data('key')); }); });
    $(document).on('click', '.wa-flow-rollback', function () { var button=$(this), source=button.closest('.well').find('.wa-flow-rollback-version').val(); if(!window.confirm('Restaurar esta versão como uma nova versão publicada?')){return;} request('/fluxo/' + encodeURIComponent(button.data('key')) + '/rollback', 'POST', {expected_revision:button.data('revision'),source_version:source}, function () { loadFlows(); loadFlow(button.data('key')); }); });
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
    // Fetch only what is visible initially. The logistics screen alone performs several
    // dependent requests, so eager-loading every tab made the first visit unnecessarily
    // slow and susceptible to a transient Gateway warm-up failure.
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (event) {
        var target = $(event.target).attr('href');
        if (target === '#wa-intakes') { loadIntakes(); }
        else if (target === '#wa-logistica') { loadLogistics(); }
        else if (target === '#wa-fluxos') { loadFlows(); }
        else if (target === '#wa-fila') { loadQueue(); }
        else if (target === '#wa-logs') { loadLogs(); }
        else if (target === '#wa-regras') { loadRules(); }
        else if (target === '#wa-templates') { loadTemplates(); }
        else if (target === '#wa-config') { loadSettings(); }
    });
    // This view is rendered inside the legacy MapOS layout. Start only after the
    // document is ready so the first data requests cannot be lost while the
    // layout scripts are still initializing. The short fallback also covers a
    // browser that has already passed jQuery's ready event.
    var panelBooted = false;
    function bootPanel() {
        if (panelBooted) { return; }
        panelBooted = true;
        window.__tecninaWhatsappPanel.booted = true;
        try {
            loadOverview();
            loadConversations();
        } catch (exception) {
            error('Não foi possível iniciar o painel do WhatsApp. Atualize a página e tente novamente.');
            if (window.console && window.console.error) {
                window.console.error('TecNina WhatsApp panel initialization failed.', exception);
            }
        }
    }
    $(bootPanel);
    window.setTimeout(bootPanel, 500);
}(jQuery));
