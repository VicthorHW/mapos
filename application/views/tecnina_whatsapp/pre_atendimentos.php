<link rel="stylesheet" href="<?= base_url(); ?>assets/tecnina/css/whatsapp-panel.css?v=<?= filemtime(FCPATH . 'assets/tecnina/css/whatsapp-panel.css'); ?>">
<div class="row-fluid">
    <div class="span12">
        <div class="widget-box tecnina-wa-panel">
            <div class="widget-title"><span class="icon"><i class="bx bx-conversation"></i></span><h5>Pré-atendimentos</h5></div>
            <div class="widget-content">
                <div class="wa-page-heading">
                    <div><h3>Revisão e Recebimento Físico</h3><p class="muted">Inspecione a condição física, registre acessórios e identificadores, anexe fotos de triagem e confirme a posse física do equipamento.</p></div>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <button type="button" class="btn btn-small btn-success" id="wa-btn-open-new-intake" title="Abrir novo pré-atendimento presencial"><i class="fas fa-plus"></i> Novo Pré-atendimento</button>
                        <button type="button" class="btn btn-small" id="wa-btn-refresh-list" title="Atualizar lista"><i class="fas fa-sync"></i> Atualizar</button>
                        <div class="btn-group wa-history-filter" data-toggle="buttons-radio">
                            <button type="button" class="btn btn-primary active" data-list="pending">Pendentes</button>
                            <button type="button" class="btn" data-list="history">Histórico</button>
                        </div>
                    </div>
                </div>
                <?php if (! $gatewayConfigured): ?><div class="alert alert-error">Gateway não configurado. A listagem não poderá ser carregada.</div><?php endif; ?>
                <div id="wa-error" class="alert alert-error" style="display:none"></div>
                <div class="row-fluid wa-intake-workspace">
                    <div class="span7 wa-intake-list-column"><div id="wa-intakes-list" class="wa-loading"><i class="fas fa-spinner fa-spin"></i> Carregando pré-atendimentos…</div></div>
                    <div class="span5 wa-intake-detail-column">
                        <div id="wa-intake-detail" class="wa-intake-empty"><i class="bx bx-list-check"></i><strong>Selecione um pré-atendimento</strong><span>Os dados para revisão aparecerão aqui.</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="modal-confirm-receiving" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="modalReceivingLabel" aria-hidden="true">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h4 id="modalReceivingLabel"><i class="fas fa-boxes"></i> Confirmar Recebimento Físico</h4>
    </div>
    <div class="modal-body">
        <div class="alert alert-info">
            <strong>Atestação de Posse Física:</strong> Esta ação confirma que a <strong>TecNina recebeu fisicamente o equipamento</strong>.<br>
            <small>Nenhuma Ordem de Serviço (OS) será criada nesta etapa (fronteira estrita S06A).</small>
        </div>
        <div id="modal-receiving-summary" class="well well-small"></div>
        <div class="control-group" style="margin-top:10px;">
            <label class="checkbox">
                <input type="checkbox" id="wa-confirm-possession-ack"> <strong>Confirmo que o equipamento acima foi fisicamente entregue e está em posse da equipe TecNina.</strong>
            </label>
        </div>
    </div>
    <div class="modal-footer" style="display:flex;justify-content:flex-end;gap:8px;">
        <button class="btn btn-warning" data-dismiss="modal" aria-hidden="true">Cancelar</button>
        <button class="btn btn-success" id="btn-submit-physical-receipt" disabled><i class="fas fa-check"></i> Confirmar Posse Física</button>
    </div>
</div>

<div id="modal-new-intake" class="modal hide fade" tabindex="-1" role="dialog" aria-labelledby="modalNewIntakeLabel" aria-hidden="true" style="width:720px;max-width:96vw;margin-left:-360px;">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h4 id="modalNewIntakeLabel"><i class="fas fa-plus-circle text-success"></i> Novo Pré-atendimento Presencial (Balcão)</h4>
    </div>
    <div class="modal-body" style="max-height:75vh;overflow-y:auto;padding:15px;">
        <div id="modal-new-intake-error" class="alert alert-error" style="display:none;"></div>

        <!-- Seção 1: Cliente -->
        <div class="well well-small" style="background:#fdfdfd;margin-bottom:12px;">
            <h5 style="margin-top:0;margin-bottom:8px;border-bottom:1px solid #eee;padding-bottom:4px;">
                <i class="fas fa-user text-info"></i> Dados do Cliente
            </h5>
            <div class="row-fluid">
                <div class="span12" style="margin-bottom:8px;">
                    <label for="new-intake-client-autocomplete" style="font-size:11px;font-weight:bold;margin-bottom:2px;">
                        <i class="fas fa-search"></i> Localizar Cliente Cadastrado (Opcional - preenche automático)
                    </label>
                    <input type="text" id="new-intake-client-autocomplete" class="span12" placeholder="Digite nome, telefone ou CPF para buscar no MapOS…" autocomplete="off">
                    <input type="hidden" id="new-intake-client-id" value="">
                </div>
            </div>
            <div class="row-fluid">
                <div class="span7">
                    <label for="new-intake-name" style="font-size:11px;font-weight:bold;margin-bottom:2px;">Nome do Cliente <span class="text-error">*</span></label>
                    <input type="text" id="new-intake-name" class="span12" placeholder="Nome completo do cliente" required>
                </div>
                <div class="span5">
                    <label for="new-intake-phone" style="font-size:11px;font-weight:bold;margin-bottom:2px;">WhatsApp / Telefone <span class="text-error">*</span></label>
                    <input type="text" id="new-intake-phone" class="span12" placeholder="(11) 99999-9999" required>
                </div>
            </div>
            <div class="row-fluid">
                <div class="span4">
                    <label for="new-intake-cpf" style="font-size:11px;font-weight:bold;margin-bottom:2px;">CPF (Opcional)</label>
                    <input type="text" id="new-intake-cpf" class="span12" placeholder="000.000.000-00">
                </div>
                <div class="span5">
                    <label for="new-intake-email" style="font-size:11px;font-weight:bold;margin-bottom:2px;">E-mail (Opcional)</label>
                    <input type="email" id="new-intake-email" class="span12" placeholder="cliente@exemplo.com">
                </div>
                <div class="span3">
                    <label for="new-intake-birth-date" style="font-size:11px;font-weight:bold;margin-bottom:2px;">Nascimento</label>
                    <input type="date" id="new-intake-birth-date" class="span12">
                </div>
            </div>
        </div>

        <!-- Seção 2: Equipamento & Defeito -->
        <div class="well well-small" style="background:#fdfdfd;margin-bottom:12px;">
            <h5 style="margin-top:0;margin-bottom:8px;border-bottom:1px solid #eee;padding-bottom:4px;">
                <i class="fas fa-laptop text-info"></i> Equipamento & Defeito Relatado
            </h5>
            <div class="row-fluid">
                <div class="span4">
                    <label for="new-intake-device-type" style="font-size:11px;font-weight:bold;margin-bottom:2px;">Tipo de Equipamento <span class="text-error">*</span></label>
                    <select id="new-intake-device-type" class="span12">
                        <option value="Notebook">Notebook</option>
                        <option value="Computador">Computador / Desktop</option>
                        <option value="Celular">Celular / Smartphone</option>
                        <option value="Tablet">Tablet</option>
                        <option value="Impressora">Impressora</option>
                        <option value="Monitor">Monitor</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>
                <div class="span4">
                    <label for="new-intake-brand" style="font-size:11px;font-weight:bold;margin-bottom:2px;">Marca</label>
                    <input type="text" id="new-intake-brand" class="span12" placeholder="Ex: Dell, Apple, Samsung, Lenovo…">
                </div>
                <div class="span4">
                    <label for="new-intake-model" style="font-size:11px;font-weight:bold;margin-bottom:2px;">Modelo</label>
                    <input type="text" id="new-intake-model" class="span12" placeholder="Ex: Inspiron 15, iPhone 13…">
                </div>
            </div>
            <div class="row-fluid">
                <div class="span12">
                    <label for="new-intake-problem" style="font-size:11px;font-weight:bold;margin-bottom:2px;">Defeito / Problema Relatado <span class="text-error">*</span></label>
                    <textarea id="new-intake-problem" class="span12" rows="3" placeholder="Descreva detalhadamente o problema relatado pelo cliente…" required></textarea>
                </div>
            </div>
            <div class="row-fluid">
                <div class="span12">
                    <label for="new-intake-notes" style="font-size:11px;font-weight:bold;margin-bottom:2px;">Observações Internas (Opcional)</label>
                    <textarea id="new-intake-notes" class="span12" rows="2" placeholder="Observações operacionais ou do cliente…"></textarea>
                </div>
            </div>
        </div>

        <!-- Seção 3: Senha / Desbloqueio (Segurança) -->
        <div class="well well-small" style="background:#fdfdfd;margin-bottom:12px;">
            <h5 style="margin-top:0;margin-bottom:8px;border-bottom:1px solid #eee;padding-bottom:4px;">
                <i class="fas fa-key text-info"></i> Senha / Desbloqueio do Aparelho (Opcional)
            </h5>
            <div class="row-fluid">
                <div class="span5">
                    <label for="new-intake-cred-type" style="font-size:11px;font-weight:bold;margin-bottom:2px;">Tipo de Desbloqueio</label>
                    <select id="new-intake-cred-type" class="span12">
                        <option value="NONE">Sem senha / Não informado</option>
                        <option value="PASSWORD">Senha numérica ou alfanumérica (PIN/Texto)</option>
                        <option value="PATTERN">Padrão / Desenho</option>
                    </select>
                </div>
                <div class="span7" id="new-intake-cred-val-container" style="display:none;">
                    <label for="new-intake-cred-value" style="font-size:11px;font-weight:bold;margin-bottom:2px;">Senha / Código / Padrão</label>
                    <input type="text" id="new-intake-cred-value" class="span12" placeholder="Digite a senha ou sequência de pontos">
                </div>
            </div>
        </div>

        <!-- Seção 4: Triagem e Recebimento Físico no Balcão -->
        <div class="well well-small" style="background:#f0f9ff;border-color:#bce8f1;margin-bottom:0;">
            <h5 style="margin-top:0;margin-bottom:8px;border-bottom:1px solid #d9edf7;padding-bottom:4px;color:#31708f;">
                <i class="fas fa-boxes"></i> Triagem Física & Recebimento no Balcão
            </h5>
            <div class="row-fluid">
                <div class="span6">
                    <label for="new-intake-condition" style="font-size:11px;font-weight:bold;margin-bottom:2px;">Condição Física Aparente</label>
                    <select id="new-intake-condition" class="span12">
                        <option value="Aparelho em bom estado / Conservado">Aparelho em bom estado / Conservado</option>
                        <option value="Marcas de uso normais">Marcas de uso normais</option>
                        <option value="Arranhões leves">Arranhões leves</option>
                        <option value="Riscos profundos / Marcas de queda">Riscos profundos / Marcas de queda</option>
                        <option value="Tela trincada / danificada">Tela trincada / danificada</option>
                        <option value="Não liga / Sem sinal de vida">Não liga / Sem sinal de vida</option>
                    </select>
                </div>
                <div class="span6">
                    <label for="new-intake-storage" style="font-size:11px;font-weight:bold;margin-bottom:2px;">Localização Física Inicial</label>
                    <input type="text" id="new-intake-storage" class="span12" placeholder="Ex: Balcão, Bancada 1, Armário A">
                </div>
            </div>
            <div class="row-fluid">
                <div class="span6">
                    <label for="new-intake-serial" style="font-size:11px;font-weight:bold;margin-bottom:2px;">Número de Série</label>
                    <input type="text" id="new-intake-serial" class="span12" placeholder="Número de Série (S/N)">
                </div>
                <div class="span6">
                    <label for="new-intake-imei" style="font-size:11px;font-weight:bold;margin-bottom:2px;">IMEI (Celulares/Tablets)</label>
                    <input type="text" id="new-intake-imei" class="span12" placeholder="IMEI do aparelho">
                </div>
            </div>
            <div class="row-fluid" style="margin-top:6px;">
                <div class="span12">
                    <label style="font-size:11px;font-weight:bold;margin-bottom:4px;">Acessórios Deixados:</label>
                    <div style="display:flex;gap:12px;flex-wrap:wrap;">
                        <label class="checkbox inline"><input type="checkbox" class="new-intake-acc" value="Carregador/Fonte"> Carregador/Fonte</label>
                        <label class="checkbox inline"><input type="checkbox" class="new-intake-acc" value="Cabo"> Cabo</label>
                        <label class="checkbox inline"><input type="checkbox" class="new-intake-acc" value="Capa/Case"> Capa/Case</label>
                        <label class="checkbox inline"><input type="checkbox" class="new-intake-acc" value="Adaptador"> Adaptador</label>
                    </div>
                    <input type="text" id="new-intake-acc-other" class="span12" style="margin-top:6px;" placeholder="Outros acessórios deixados…">
                </div>
            </div>
            <div class="row-fluid" style="margin-top:10px;">
                <div class="span12">
                    <label class="checkbox" style="font-weight:bold;color:#155724;background:#d4edda;border:1px solid #c3e6cb;padding:8px 10px 8px 30px;border-radius:4px;">
                        <input type="checkbox" id="new-intake-confirm-receipt" checked> Confirmar posse física do equipamento no balcão imediatamente
                    </label>
                    <small class="muted" style="display:block;margin-top:4px;">
                        <i class="fas fa-info-circle"></i> O pré-atendimento será criado e o recebimento físico registrado em nome do operador atual. Nenhuma OS será gerada até que você decida materializar.
                    </small>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer" style="display:flex;justify-content:flex-end;gap:8px;">
        <button class="btn btn-warning" data-dismiss="modal" aria-hidden="true">Cancelar</button>
        <button class="btn btn-success" id="btn-submit-new-intake"><i class="fas fa-save"></i> Criar Pré-atendimento</button>
    </div>
</div>

<div id="wa-panel-config" data-base="<?= html_escape(site_url('tecnina_whatsapp')); ?>" data-receiving-base="<?= html_escape(site_url('tecnina/pre-atendimentos')); ?>" data-client-add-base="<?= html_escape(site_url('clientes/adicionar')); ?>" data-client-edit-base="<?= html_escape(site_url('clientes/editar')); ?>" data-os-edit-base="<?= html_escape(site_url('os/editar')); ?>" data-csrf-name="<?= html_escape($csrfName); ?>" data-csrf-hash="<?= html_escape($csrfHash); ?>" style="display:none"></div>
<script src="<?= base_url(); ?>assets/tecnina/js/whatsapp-panel-diagnostics.js?v=<?= filemtime(FCPATH . 'assets/tecnina/js/whatsapp-panel-diagnostics.js'); ?>"></script>
<script src="<?= base_url(); ?>assets/tecnina/js/pre-attendance-panel.js?v=<?= filemtime(FCPATH . 'assets/tecnina/js/pre-attendance-panel.js'); ?>"></script>
