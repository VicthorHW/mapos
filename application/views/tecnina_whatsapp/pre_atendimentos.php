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



<div id="wa-panel-config" data-base="<?= html_escape(site_url('tecnina_whatsapp')); ?>" data-receiving-base="<?= html_escape(site_url('tecnina/pre-atendimentos')); ?>" data-client-add-base="<?= html_escape(site_url('clientes/adicionar')); ?>" data-client-edit-base="<?= html_escape(site_url('clientes/editar')); ?>" data-os-edit-base="<?= html_escape(site_url('os/editar')); ?>" data-csrf-name="<?= html_escape($csrfName); ?>" data-csrf-hash="<?= html_escape($csrfHash); ?>" style="display:none"></div>
<script src="<?= base_url(); ?>assets/tecnina/js/whatsapp-panel-diagnostics.js?v=<?= filemtime(FCPATH . 'assets/tecnina/js/whatsapp-panel-diagnostics.js'); ?>"></script>
<script src="<?= base_url(); ?>assets/tecnina/js/pre-attendance-panel.js?v=<?= filemtime(FCPATH . 'assets/tecnina/js/pre-attendance-panel.js'); ?>"></script>
