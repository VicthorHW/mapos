<link rel="stylesheet" href="<?= base_url(); ?>assets/tecnina/css/whatsapp-panel.css?v=<?= filemtime(FCPATH . 'assets/tecnina/css/whatsapp-panel.css'); ?>">
<div class="row-fluid">
    <div class="span12">
        <div class="widget-box tecnina-wa-panel">
            <div class="widget-title"><span class="icon"><i class="bx bxl-whatsapp"></i></span><h5>Configurações → WhatsApp</h5></div>
            <div class="widget-content">
                <p class="muted">Controle da integração, automações e diagnóstico. Pré-atendimentos ficam na área operacional própria.</p>
                <?php if (! $gatewayConfigured): ?>
                    <div class="alert alert-error">Gateway não configurado. Defina TECNINA_BOT_BASE_URL e MAPOS_BOT_TOKEN no ambiente do MapOS.</div>
                <?php endif; ?>
                <div id="wa-error" class="alert alert-error" style="display:none"></div>
                <ul class="nav nav-tabs wa-main-tabs">
                    <li class="active"><a href="#wa-status" data-toggle="tab">Visão geral</a></li>
                    <li><a href="#wa-conversas" data-toggle="tab">Conversas</a></li>
                    <li><a href="#wa-cidades" data-toggle="tab">Cidades com coleta</a></li>
                    <li><a href="#wa-conexao" data-toggle="tab">Conexão e envios</a></li>
                    <li><a href="#wa-automaticas" data-toggle="tab">Mensagens automáticas</a></li>
                    <li><a href="#wa-logs" data-toggle="tab">Logs / Diagnóstico</a></li>
                </ul>
                <div class="tab-content wa-tab-content">
                    <div class="tab-pane active" id="wa-status">
                        <h4>Saúde dos serviços</h4>
                        <p class="muted">Resumo técnico da integração neste momento.</p>
                        <div id="wa-overview" class="wa-health-grid"><div class="wa-loading"><i class="fas fa-spinner fa-spin"></i> Carregando visão geral…</div></div>
                    </div>
                    <div class="tab-pane" id="wa-conversas"><div id="wa-conversations" class="wa-loading">Carregando…</div></div>
                    <div class="tab-pane" id="wa-cidades"><div id="wa-pickup-cities" class="wa-loading">Carregando…</div></div>
                    <div class="tab-pane" id="wa-conexao">
                        <div class="row-fluid">
                            <div class="span8"><h4>Fila de envios</h4><div id="wa-queue" class="wa-loading">Carregando…</div></div>
                            <div class="span4"><h4>Conexão</h4><div id="wa-connection" class="wa-loading">Carregando…</div></div>
                        </div>
                    </div>
                    <div class="tab-pane" id="wa-automaticas">
                        <section class="wa-section"><h4>Ativação geral</h4><div id="wa-settings" class="wa-loading">Carregando…</div></section>
                        <section class="wa-section"><h4>Regras de disparo</h4><p class="muted">Definem quais mudanças de status geram mensagens e com qual prioridade.</p><div id="wa-rules" class="wa-loading">Carregando…</div></section>
                        <section class="wa-section"><h4>Templates das mensagens</h4><p class="muted">Textos usados pelas notificações transacionais; não alteram a conversa normal do bot.</p><div id="wa-templates-list" class="wa-loading">Carregando…</div></section>
                    </div>
                    <div class="tab-pane" id="wa-logs"><div id="wa-logs-list" class="wa-loading">Carregando…</div></div>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="wa-panel-config" data-base="<?= html_escape(site_url('tecnina_whatsapp')); ?>" data-csrf-name="<?= html_escape($csrfName); ?>" data-csrf-hash="<?= html_escape($csrfHash); ?>" style="display:none"></div>
<script src="<?= base_url(); ?>assets/tecnina/js/whatsapp-panel-diagnostics.js?v=<?= filemtime(FCPATH . 'assets/tecnina/js/whatsapp-panel-diagnostics.js'); ?>"></script>
<script src="<?= base_url(); ?>assets/tecnina/js/whatsapp-panel.js?v=<?= filemtime(FCPATH . 'assets/tecnina/js/whatsapp-panel.js'); ?>"></script>
