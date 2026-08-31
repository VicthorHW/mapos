(function ($) {
    'use strict';

    function getCsrfTokenName() {
        return $('meta[name="csrf-token-name"]').attr('content');
    }

    function getCsrfToken() {
        return $('meta[name="csrf-token"]').attr('content');
    }

    function setCsrfTokenInAllForms() {
        var csrfTokenName = getCsrfTokenName();
        var csrfToken = getCsrfToken();

        if (!csrfTokenName || !csrfToken) {
            console.error('[CSRF] Token CSRF não encontrado na página.');
            return;
        }

        $('form').each(function () {
            var form = $(this);
            var method = (form.attr('method') || 'GET').toUpperCase();

            if (method !== 'POST') {
                return;
            }

            var input = form.find(
                'input[name="' + csrfTokenName + '"]'
            );

            if (input.length === 0) {
                input = $('<input>', {
                    type: 'hidden',
                    name: csrfTokenName
                });

                form.append(input);
            }

            input.val(csrfToken);
        });
    }

    $(document).ready(function () {
        setCsrfTokenInAllForms();

        /*
         * Garante o token novamente imediatamente antes
         * de qualquer formulário POST ser enviado.
         */
        $(document).on('submit', 'form', function () {
            var form = $(this);
            var method = (form.attr('method') || 'GET').toUpperCase();

            if (method !== 'POST') {
                return;
            }

            var csrfTokenName = getCsrfTokenName();
            var csrfToken = getCsrfToken();

            var input = form.find(
                'input[name="' + csrfTokenName + '"]'
            );

            if (input.length === 0) {
                input = $('<input>', {
                    type: 'hidden',
                    name: csrfTokenName
                });

                form.append(input);
            }

            input.val(csrfToken);
        });

        /*
         * Proteção global para AJAX jQuery.
         */
        $.ajaxPrefilter(function (options) {
            var method = (
                options.type ||
                options.method ||
                'GET'
            ).toUpperCase();

            if (method === 'GET' ||
                method === 'HEAD' ||
                method === 'OPTIONS') {
                return;
            }

            var csrfTokenName = getCsrfTokenName();
            var csrfToken = getCsrfToken();

            if (!csrfTokenName || !csrfToken) {
                return;
            }

            if (options.data instanceof FormData) {
                options.data.set(csrfTokenName, csrfToken);
                return;
            }

            if (typeof options.data === 'string') {
                var encodedName = encodeURIComponent(csrfTokenName);

                /*
                 * Remove token anterior, se houver.
                 */
                var parts = options.data
                    ? options.data.split('&').filter(function (part) {
                        return part.split('=')[0] !== encodedName;
                    })
                    : [];

                parts.push(
                    encodedName + '=' + encodeURIComponent(csrfToken)
                );

                options.data = parts.join('&');
                return;
            }

            options.data = options.data || {};
            options.data[csrfTokenName] = csrfToken;
        });
    });

})(jQuery);