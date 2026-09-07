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
                    <li><a href="#wa-logistica" data-toggle="tab">Logística</a></li>
                    <li><a href="#wa-fluxos" data-toggle="tab">Fluxos</a></li>
                    <li><a href="#wa-fila" data-toggle="tab">Fila</a></li>
                    <li><a href="#wa-logs" data-toggle="tab">Logs</a></li>
                    <li><a href="#wa-regras" data-toggle="tab">Regras de status</a></li>
                    <li><a href="#wa-templates" data-toggle="tab">Templates</a></li>
                    <li><a href="#wa-config" data-toggle="tab">Configuração</a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane active" id="wa-conversas"><div id="wa-conversations">Carregando…</div></div>
                    <div class="tab-pane" id="wa-intakes"><div id="wa-intakes-list">Carregando…</div><div id="wa-intake-detail"></div></div>
                    <div class="tab-pane" id="wa-logistica">
                        <div id="wa-logistics-overview">Carregando…</div>
                        <div id="wa-logistics-location-link"></div>
                        <div id="wa-logistics-appointments"></div>
                        <hr>
                        <div id="wa-logistics-zones"></div>
                        <div id="wa-logistics-routes"></div>
                        <div id="wa-logistics-capacity"></div>
                        <div id="wa-logistics-profiles"></div>
                    </div>
                    <div class="tab-pane" id="wa-fluxos"><div id="wa-flows-list">Carregando…</div><div id="wa-flow-detail"></div><div id="wa-flow-observer"></div></div>
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
<div
    id="wa-panel-config"
    data-base="<?= html_escape(site_url('tecnina_whatsapp')); ?>"
    data-os-edit-base="<?= html_escape(site_url('os/editar')); ?>"
    data-csrf-name="<?= html_escape($csrfName); ?>"
    data-csrf-hash="<?= html_escape($csrfHash); ?>"
    style="display:none"
></div>
<script src="<?= base_url(); ?>assets/tecnina/js/whatsapp-panel-diagnostics.js?v=<?= filemtime(FCPATH . 'assets/tecnina/js/whatsapp-panel-diagnostics.js'); ?>"></script>
<script src="<?= base_url(); ?>assets/tecnina/js/whatsapp-panel.js?v=<?= filemtime(FCPATH . 'assets/tecnina/js/whatsapp-panel.js'); ?>"></script>
