<link rel="stylesheet" href="<?= base_url(); ?>assets/tecnina/css/bot-lab.css?v=<?= filemtime(FCPATH . 'assets/tecnina/css/bot-lab.css'); ?>">
<div class="row-fluid">
    <div class="span12">
        <div class="widget-box tecnina-wa-panel bot-lab-panel">
            <div class="widget-title">
                <span class="icon"><i class="bx bx-bot"></i></span>
                <h5>Bot Lab</h5>
            </div>
            <div class="widget-content">
                <p class="muted">Execute a FSM real do bot em um ambiente isolado, sem enviar mensagens ou alterar dados da operação.</p>

                <?php if (! $gatewayConfigured): ?>
                    <div class="alert alert-error">Gateway do Bot não configurado. O Bot Lab não pode ser utilizado.</div>
                <?php endif; ?>

                <div id="bot-lab-error" class="alert alert-error" style="display:none"></div>
                <div id="bot-lab-success" class="alert alert-success" style="display:none"></div>

                <div class="bot-lab-nav-header">
                    <ul class="nav nav-tabs" id="bot-lab-mode-tabs" style="margin-bottom: 0;">
                        <li class="active"><a href="#" id="tab-nav-interactive"><i class="bx bx-chat"></i> Sessão Interativa</a></li>
                        <li><a href="#" id="tab-nav-scenarios"><i class="bx bx-check-double"></i> Testes Automáticos de Cenários <span id="sc-nav-badge" class="badge badge-info" style="margin-left: 5px;">0</span></a></li>
                    </ul>
                </div>

                <!-- Panel: Interactive Mode -->
                <div id="panel-interactive-mode">
                    <!-- Mode A: Session Setup -->
                    <div id="bot-lab-setup" class="bot-lab-section">
                    <div class="bot-lab-setup-box">
                        <h4>Nova Sessão de Simulação</h4>
                        <p class="muted">Configure os parâmetros e fixtures iniciais para isolar a execução da FSM.</p>

                        <form id="form-create-session" onsubmit="return false;">
                            <div class="row-fluid">
                                <div class="span6">
                                    <div class="control-group">
                                        <label class="control-label" for="setup-phone"><strong>Telefone sintético</strong> <span class="text-error">*</span></label>
                                        <div class="controls">
                                            <input type="text" id="setup-phone" class="input-block-level" placeholder="Ex: 5541999990001" required>
                                            <span class="help-block muted">Use um número fictício para identificar esta simulação.</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="span6">
                                    <div class="control-group">
                                        <label class="control-label" for="setup-client-mode"><strong>Cliente no MapOS</strong></label>
                                        <div class="controls">
                                            <select id="setup-client-mode" class="input-block-level">
                                                <option value="NONE" selected>Cliente novo / não encontrado</option>
                                                <option value="UNIQUE">Cliente existente</option>
                                                <option value="AMBIGUOUS">Correspondência ambígua</option>
                                                <option value="UNCONFIGURED">Não configurado — falhar se consultado</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Unique Client Fields -->
                            <div id="setup-unique-fields" class="bot-lab-conditional-box" style="display:none;">
                                <h5>Dados do Cliente Existente</h5>
                                <div class="row-fluid">
                                    <div class="span4">
                                        <div class="control-group">
                                            <label class="control-label" for="setup-client-id"><strong>Client ID</strong> <span class="text-error">*</span></label>
                                            <div class="controls">
                                                <input type="number" id="setup-client-id" class="input-block-level" min="1" placeholder="Ex: 10">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="span8">
                                        <div class="control-group">
                                            <label class="control-label" for="setup-client-name"><strong>Nome do cliente</strong> <span class="text-error">*</span></label>
                                            <div class="controls">
                                                <input type="text" id="setup-client-name" class="input-block-level" placeholder="Ex: João da Silva">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Open Orders Fixture -->
                                <div id="setup-orders-section" class="bot-lab-sub-section">
                                    <div class="control-group">
                                        <label class="control-label" for="setup-orders-mode"><strong>Situação das OS abertas</strong></label>
                                        <div class="controls">
                                            <select id="setup-orders-mode" class="input-block-level">
                                                <option value="UNCONFIGURED" selected>Não configurado — falhar se consultado</option>
                                                <option value="NONE">Nenhuma OS aberta</option>
                                                <option value="CUSTOM">Configurar OS abertas</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div id="setup-orders-custom" style="display:none;">
                                        <div class="bot-lab-table-toolbar">
                                            <strong>Ordens de Serviço Simuladas</strong>
                                            <button type="button" class="btn btn-mini btn-info pull-right" id="btn-add-order"><i class="bx bx-plus"></i> Adicionar OS</button>
                                        </div>
                                        <table class="table table-bordered table-condensed wa-table" id="setup-orders-table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 80px;">OS ID</th>
                                                    <th>Status MapOS</th>
                                                    <th>Tipo</th>
                                                    <th>Marca</th>
                                                    <th>Modelo</th>
                                                    <th style="width: 50px;">Ação</th>
                                                </tr>
                                            </thead>
                                            <tbody id="setup-orders-tbody">
                                                <!-- Dynamic order rows -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Advanced Fixtures Collapse -->
                            <div class="bot-lab-advanced-accordion">
                                <a class="btn btn-link bot-lab-collapse-btn" data-toggle="collapse" href="#setup-advanced-collapse">
                                    <i class="bx bx-chevron-down"></i> Fixtures avançados
                                </a>
                                <div id="setup-advanced-collapse" class="collapse">
                                    <div class="bot-lab-advanced-body">
                                        <div class="row-fluid">
                                            <div class="span6">
                                                <div class="control-group">
                                                    <label class="control-label" for="setup-reg-delivery"><strong>Envio de código de cadastro</strong></label>
                                                    <div class="controls">
                                                        <select id="setup-reg-delivery" class="input-block-level">
                                                            <option value="UNCONFIGURED" selected>UNCONFIGURED (Não configurado)</option>
                                                            <option value="SUCCESS">SUCCESS (Código entregue)</option>
                                                            <option value="FAILURE">FAILURE (Falha na entrega)</option>
                                                        </select>
                                                        <span class="help-block muted">Simula o envio do código sem enviar e-mail real. O código aparecerá na aba Entregas.</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="span6">
                                                <div class="control-group">
                                                    <label class="control-label" for="setup-create-client-id"><strong>ID do cliente a ser criado</strong> (opcional)</label>
                                                    <div class="controls">
                                                        <input type="number" id="setup-create-client-id" class="input-block-level" min="1" placeholder="Ex: 99">
                                                        <span class="help-block muted">Para concluir o cadastro após validar o código, informe o ID fictício que o MapOS deverá retornar na criação.</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="bot-lab-postal-section">
                                            <div class="bot-lab-table-toolbar">
                                                <strong>Fixtures de CEP (Endereçamento)</strong>
                                                <button type="button" class="btn btn-mini btn-info pull-right" id="btn-add-cep"><i class="bx bx-plus"></i> Adicionar CEP</button>
                                            </div>
                                            <table class="table table-bordered table-condensed wa-table" id="setup-cep-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 100px;">CEP</th>
                                                        <th>Logradouro</th>
                                                        <th>Bairro</th>
                                                        <th>Cidade</th>
                                                        <th style="width: 50px;">UF</th>
                                                        <th style="width: 50px;">Ação</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="setup-cep-tbody">
                                                    <!-- Dynamic CEP rows -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions bot-lab-form-actions">
                                <button type="button" class="btn btn-primary" id="btn-create-session">
                                    <i class="bx bx-play"></i> Iniciar Sessão
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Mode B: Active Workbench -->
                <div id="bot-lab-workbench" class="bot-lab-section" style="display:none;">
                    <!-- Workbench Toolbar -->
                    <div class="bot-lab-toolbar" id="wb-toolbar">
                        <div class="bot-lab-toolbar-left">
                            <span class="bot-lab-brand"><i class="bx bx-bot"></i> Bot Lab</span>
                            <span id="wb-status-badge" class="label label-success">ACTIVE</span>
                            <span class="wb-meta-item"><i class="bx bx-phone"></i> <strong id="wb-phone">—</strong></span>
                            <span class="wb-meta-item muted"><i class="bx bx-hash"></i> <code id="wb-short-id">—</code></span>
                        </div>
                        <div class="bot-lab-toolbar-right">
                            <button type="button" class="btn btn-warning" id="btn-reset-session" title="Reinicia o FSM mantendo os fixtures"><i class="bx bx-refresh"></i> Resetar</button>
                            <button type="button" class="btn btn-danger" id="btn-delete-session" title="Encerra e descarta a simulação"><i class="bx bx-trash"></i> Encerrar</button>
                        </div>
                    </div>

                    <!-- Faulted / Closed Banners -->
                    <div id="wb-faulted-banner" class="alert alert-error" style="display:none;">
                        <i class="bx bx-error-circle"></i> <strong>A simulação encontrou uma falha e precisa ser resetada antes de continuar.</strong>
                    </div>
                    <div id="wb-closed-banner" class="alert alert-block" style="display:none;">
                        <i class="bx bx-lock"></i> <strong>Esta sessão de simulação foi encerrada. O workbench está em modo somente leitura.</strong>
                    </div>

                    <!-- Workspace Two-Column Grid -->
                    <div class="row-fluid bot-lab-workspace-grid">
                        <!-- Left Column: Conversation & Composer -->
                        <div class="span7 bot-lab-chat-column">
                            <div class="bot-lab-chat-container">
                                <div id="wb-chat-box" class="bot-lab-chat-box">
                                    <!-- Dynamic transcript bubbles -->
                                </div>
                            </div>

                            <!-- Quick Action Buttons -->
                            <div class="bot-lab-quick-actions" id="wb-quick-actions">
                                <span class="muted" style="font-size:11px; margin-right:6px;">Ações rápidas:</span>
                                <button type="button" class="btn btn-mini wb-quick-btn" data-text="Menu">Menu</button>
                                <button type="button" class="btn btn-mini wb-quick-btn" data-text="1">1</button>
                                <button type="button" class="btn btn-mini wb-quick-btn" data-text="2">2</button>
                                <button type="button" class="btn btn-mini wb-quick-btn" data-text="3">3</button>
                                <button type="button" class="btn btn-mini wb-quick-btn" data-text="Sim">Sim</button>
                                <button type="button" class="btn btn-mini wb-quick-btn" data-text="Não">Não</button>
                                <button type="button" class="btn btn-mini wb-quick-btn" data-text="Voltar">Voltar</button>
                            </div>

                            <!-- Message Composer -->
                            <div class="bot-lab-composer" id="wb-composer">
                                <div class="bot-lab-composer-row">
                                    <input type="text" id="wb-input-text" class="input-block-level" placeholder="Digite uma mensagem para o bot..." autocomplete="off">
                                    <button type="button" class="btn btn-primary" id="btn-send-message"><i class="bx bx-send"></i> Enviar</button>
                                </div>
                                <div class="bot-lab-composer-tools">
                                    <button type="button" class="btn btn-small" id="btn-open-location"><i class="bx bx-map-pin"></i> Enviar localização</button>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Inspector Tabs -->
                        <div class="span5 bot-lab-inspector-column">
                            <div class="widget-box" style="margin-top:0;">
                                <div class="widget-title">
                                    <ul class="nav nav-tabs" id="wb-inspector-tabs">
                                        <li class="active"><a href="#tab-state" data-toggle="tab">Estado</a></li>
                                        <li><a href="#tab-steps" data-toggle="tab">Etapas</a></li>
                                        <li><a href="#tab-effects" data-toggle="tab">Efeitos</a></li>
                                        <li><a href="#tab-deliveries" data-toggle="tab">Entregas</a></li>
                                    </ul>
                                </div>
                                <div class="widget-content tab-content bot-lab-inspector-body" id="wb-inspector-content">
                                    <!-- Tab 1: Estado -->
                                    <div class="tab-pane active" id="tab-state">
                                        <table class="table table-bordered table-striped table-condensed wa-table">
                                            <tbody>
                                                <tr><td style="width:40%;"><strong>Rascunho ativo</strong></td><td id="st-draft-exists">—</td></tr>
                                                <tr><td><strong>ID do rascunho</strong></td><td id="st-draft-id">—</td></tr>
                                                <tr><td><strong>Etapa</strong></td><td id="st-stage">—</td></tr>
                                                <tr><td><strong>Status</strong></td><td id="st-status">—</td></tr>
                                                <tr><td><strong>Modo de serviço</strong></td><td id="st-service-mode">—</td></tr>
                                                <tr><td><strong>Review version</strong></td><td id="st-review-version">—</td></tr>
                                                <tr><td><strong>Cliente MapOS possível</strong></td><td id="st-mapos-client">—</td></tr>
                                                <tr><td><strong>Status de credencial</strong></td><td id="st-credential-status">—</td></tr>
                                                <tr><td><strong>Status de taxa de coleta</strong></td><td id="st-pickup-fee-status">—</td></tr>
                                                <tr><td><strong>Human takeover</strong></td><td id="st-human-takeover">—</td></tr>
                                                <tr><td><strong>Capabilities pendentes</strong></td><td id="st-capability-count">—</td></tr>
                                                <tr><td><strong>Tipos de capability</strong></td><td id="st-capability-purposes">—</td></tr>
                                            </tbody>
                                        </table>
                                        <h5 style="margin-top:15px; margin-bottom:5px; font-size:12px; text-transform:uppercase; color:#666;">Configuração operacional da sessão</h5>
                                        <table class="table table-bordered table-striped table-condensed wa-table">
                                            <tbody>
                                                <tr><td style="width:40%;"><strong>Snapshot</strong></td><td id="st-op-captured-at">—</td></tr>
                                                <tr><td><strong>Cidades cadastradas</strong></td><td id="st-op-city-count">—</td></tr>
                                                <tr><td><strong>Cidades ativas</strong></td><td id="st-op-active-city-count">—</td></tr>
                                                <tr><td><strong>Taxas de bairro</strong></td><td id="st-op-rate-count">—</td></tr>
                                                <tr><td><strong>Endereço de entrega</strong></td><td id="st-op-dropoff-address">—</td></tr>
                                                <tr><td><strong>Dias de entrega habilitados</strong></td><td id="st-op-dropoff-days">—</td></tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Tab 2: Etapas -->
                                    <div class="tab-pane" id="tab-steps">
                                        <div id="wb-steps-list" class="bot-lab-steps-container">
                                            <p class="muted">Nenhuma etapa registrada nesta sessão.</p>
                                        </div>
                                    </div>

                                    <!-- Tab 3: Efeitos -->
                                    <div class="tab-pane" id="tab-effects">
                                        <div id="wb-effects-list" class="bot-lab-effects-container">
                                            <p class="muted">Nenhum efeito externo registrado nesta sessão.</p>
                                        </div>
                                    </div>

                                    <!-- Tab 4: Entregas -->
                                    <div class="tab-pane" id="tab-deliveries">
                                        <div id="wb-deliveries-list" class="bot-lab-deliveries-container">
                                            <p class="muted">Nenhuma entrega simulada nesta sessão.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> <!-- End #panel-interactive-mode -->

                <!-- Panel: Automated Scenarios Mode -->
                <div id="panel-scenarios-mode" style="display:none;">
                    <div class="bot-lab-setup-box">
                        <div class="row-fluid">
                            <div class="span8">
                                <h4>Testes Automáticos de Cenários (FSM Declarativa)</h4>
                                <p class="muted">Bancada de execução estrita dos cenários declarativos sobre o SimulationRuntime, com asserções completas e isolamento de banco.</p>
                            </div>
                            <div class="span4 text-right" style="text-align: right;">
                                <span class="badge badge-info" id="sc-header-count" style="font-size: 13px; padding: 6px 12px;">Carregando catálogo...</span>
                            </div>
                        </div>

                        <!-- Filter Controls -->
                        <div class="row-fluid" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                            <div class="span4">
                                <label for="sc-filter-tag"><strong>Filtrar por Tag:</strong></label>
                                <select id="sc-filter-tag" class="input-block-level">
                                    <option value="">Todas as tags</option>
                                </select>
                            </div>
                            <div class="span5">
                                <label for="sc-filter-scenario"><strong>Filtrar por Cenário:</strong></label>
                                <select id="sc-filter-scenario" class="input-block-level">
                                    <option value="">Todos os cenários</option>
                                </select>
                            </div>
                            <div class="span3" style="padding-top: 24px;">
                                <button type="button" class="btn btn-primary btn-block" id="btn-run-scenarios">
                                    <i class="bx bx-play"></i> Executar Filtro
                                </button>
                            </div>
                        </div>
                        <div class="row-fluid" style="margin-top: 10px;">
                            <div class="span12 text-right" style="text-align: right;">
                                <button type="button" class="btn btn-success" id="btn-run-all-scenarios">
                                    <i class="bx bx-play-circle"></i> Executar Todos os Cenários
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Running Progress Alert -->
                    <div id="sc-running-alert" class="alert alert-info" style="display: none; margin-top: 15px;">
                        <i class="bx bx-loader-alt bx-spin" style="margin-right: 8px;"></i>
                        <strong>Executando cenários automatizados...</strong>
                        <span id="sc-running-text" style="margin-left: 10px;">Aguarde a execução em lote da FSM.</span>
                    </div>

                    <!-- Suite Summary Card -->
                    <div id="sc-summary-box" class="bot-lab-summary-card" style="display: none; margin-top: 15px;">
                        <div class="row-fluid">
                            <div class="span3">
                                <div class="bot-lab-stat-box">
                                    <div class="stat-value" id="sc-stat-total">0</div>
                                    <div class="stat-label">Total de Casos</div>
                                </div>
                            </div>
                            <div class="span3">
                                <div class="bot-lab-stat-box stat-pass">
                                    <div class="stat-value text-success" id="sc-stat-passed">0</div>
                                    <div class="stat-label">Passaram</div>
                                </div>
                            </div>
                            <div class="span3">
                                <div class="bot-lab-stat-box stat-fail">
                                    <div class="stat-value text-error" id="sc-stat-failed">0</div>
                                    <div class="stat-label">Falharam</div>
                                </div>
                            </div>
                            <div class="span3">
                                <div class="bot-lab-stat-box">
                                    <div class="stat-value" id="sc-stat-time">0ms</div>
                                    <div class="stat-label">Tempo Total</div>
                                </div>
                            </div>
                        </div>
                        <div class="row-fluid" style="margin-top: 10px; font-size: 12px; color: #555;">
                            <div class="span6">
                                <strong>Tags Cobertas:</strong> <span id="sc-summary-tags">—</span>
                            </div>
                            <div class="span6 text-right" style="text-align: right;">
                                <strong>Estados FSM:</strong> <span id="sc-summary-states">0</span> |
                                <strong>Capabilities:</strong> <span id="sc-summary-caps">0</span>
                            </div>
                        </div>
                    </div>

                    <!-- Results List Container -->
                    <div id="sc-results-container" class="scenarios-results-container" style="margin-top: 20px;">
                        <!-- Dynamically populated -->
                    </div>
                </div> <!-- End #panel-scenarios-mode -->

            </div>
        </div>
    </div>
</div>

<!-- Modal: Location Input -->
<div id="modal-location" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="modal-location-title" aria-hidden="true">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="modal-location-title">Enviar Localização</h3>
    </div>
    <div class="modal-body">
        <div id="location-validation-error" class="bot-lab-location-error"></div>
        <div class="control-group">
            <label class="control-label" for="loc-lat"><strong>Latitude</strong> <span class="text-error">*</span></label>
            <div class="controls">
                <input type="number" step="any" id="loc-lat" class="input-block-level" placeholder="Ex: -25.4284" required>
                <span class="help-block muted">Valor decimal entre -90.0 e 90.0</span>
            </div>
        </div>
        <div class="control-group">
            <label class="control-label" for="loc-lng"><strong>Longitude</strong> <span class="text-error">*</span></label>
            <div class="controls">
                <input type="number" step="any" id="loc-lng" class="input-block-level" placeholder="Ex: -49.2733" required>
                <span class="help-block muted">Valor decimal entre -180.0 e 180.0</span>
            </div>
        </div>
        <div class="control-group">
            <label class="control-label" for="loc-acc"><strong>Precisão em metros</strong> (opcional)</label>
            <div class="controls">
                <input type="number" step="any" id="loc-acc" class="input-block-level" min="0" placeholder="Ex: 10.0">
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn" data-dismiss="modal" aria-hidden="true">Cancelar</button>
        <button class="btn btn-primary" id="btn-submit-location">Enviar localização</button>
    </div>
</div>

<!-- Hidden Configuration Container -->
<div id="bot-lab-config"
     data-base="<?= html_escape(site_url('tecnina_whatsapp')); ?>"
     data-csrf-name="<?= html_escape($csrfName); ?>"
     data-csrf-hash="<?= html_escape($csrfHash); ?>"
     style="display:none"></div>

<script src="<?= base_url(); ?>assets/tecnina/js/bot-lab.js?v=<?= filemtime(FCPATH . 'assets/tecnina/js/bot-lab.js'); ?>"></script>
