<link rel="stylesheet" href="<?= base_url(); ?>assets/tecnina/css/whatsapp-panel.css?v=<?= filemtime(FCPATH . 'assets/tecnina/css/whatsapp-panel.css'); ?>">
<div class="row-fluid">
    <div class="span12">
        <div class="widget-box tecnina-wa-panel">
            <div class="widget-title"><span class="icon"><i class="bx bx-conversation"></i></span><h5>Pré-atendimentos</h5></div>
            <div class="widget-content">
                <div class="wa-page-heading">
                    <div><h3>Revisão de pré-atendimentos</h3><p class="muted">Revise as solicitações recebidas pelo WhatsApp e transforme-as em ordens de serviço.</p></div>
                    <div class="btn-group wa-history-filter" data-toggle="buttons-radio">
                        <button type="button" class="btn btn-primary active" data-list="pending">Pendentes</button>
                        <button type="button" class="btn" data-list="history">Histórico</button>
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
<div id="wa-panel-config" data-base="<?= html_escape(site_url('tecnina_whatsapp')); ?>" data-os-edit-base="<?= html_escape(site_url('os/editar')); ?>" data-csrf-name="<?= html_escape($csrfName); ?>" data-csrf-hash="<?= html_escape($csrfHash); ?>" style="display:none"></div>
<script src="<?= base_url(); ?>assets/tecnina/js/whatsapp-panel-diagnostics.js?v=<?= filemtime(FCPATH . 'assets/tecnina/js/whatsapp-panel-diagnostics.js'); ?>"></script>
<script src="<?= base_url(); ?>assets/tecnina/js/pre-attendance-panel.js?v=<?= filemtime(FCPATH . 'assets/tecnina/js/pre-attendance-panel.js'); ?>"></script>
