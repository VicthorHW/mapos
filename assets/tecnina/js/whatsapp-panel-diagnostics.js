(function (window, document) {
    'use strict';

    function show(message) {
        var target = document.getElementById('wa-error');
        if (!target) {
            return;
        }
        target.textContent = 'Painel WhatsApp: ' + message;
        target.style.display = '';
    }

    window.addEventListener('error', function (event) {
        var page = String(window.location.pathname || '');
        var source = String(event.filename || '');
        if (page.indexOf('tecnina_whatsapp') !== -1 && (!source || source.indexOf('tecnina_whatsapp') !== -1)) {
            show('erro de inicialização: ' + (event.message || 'erro JavaScript não identificado') + '.');
        }
    });

    window.addEventListener('unhandledrejection', function (event) {
        var reason = event.reason && event.reason.message ? event.reason.message : String(event.reason || 'promessa rejeitada');
        show('erro assíncrono: ' + reason + '.');
    });

    window.setTimeout(function () {
        var state = window.__tecninaWhatsappPanel;
        if (!state || !state.executed) {
            show('o script principal não foi executado pelo navegador.');
        } else if (!state.booted) {
            show('a inicialização do script principal não foi concluída.');
        }
    }, 3000);
}(window, document));
