(function ($) {
    'use strict';

    $(function () {
        var container = $('#deviceCredentialDisplay');
        var revealButton = $('#revealDeviceCredential');
        if (!container.length || !revealButton.length || typeof DevicePattern === 'undefined') {
            return;
        }

        var csrfName = container.data('csrf-name');
        var csrfHash = container.data('csrf-hash');
        var endpoint = container.data('endpoint');
        var pattern = null;

        function clearDetails() {
            $('#deviceCredentialText').hide().text('');
            $('#deviceCredentialPatternWrapper').hide();
            $('#deviceCredentialReadonlySequence').text('');
            if (pattern) {
                pattern.destroy();
                pattern = null;
            }
            $('#deviceCredentialDetails').hide();
            revealButton.show().prop('disabled', false).text('Revelar credencial');
        }

        function showError(message) {
            if (typeof swal === 'function') {
                swal({type: 'error', title: 'Atenção', text: message});
            } else {
                window.alert(message);
            }
        }

        revealButton.on('click', function () {
            var payload = {};
            payload[csrfName] = csrfHash;
            revealButton.prop('disabled', true).text('Carregando...');

            $.ajax({
                type: 'POST',
                url: endpoint,
                dataType: 'json',
                data: payload
            }).done(function (response) {
                if (response.csrfHash) {
                    csrfHash = response.csrfHash;
                }
                if (!response.result || !response.credencial) {
                    showError(response.message || 'Não foi possível revelar a credencial.');
                    revealButton.prop('disabled', false).text('Revelar credencial');
                    return;
                }

                revealButton.hide();
                $('#deviceCredentialDetails').show();
                if (response.credencial.tipo === 'texto') {
                    $('#deviceCredentialText')
                        .text('Senha/PIN: ' + response.credencial.texto)
                        .show();
                    return;
                }

                if (response.credencial.tipo === 'padrao') {
                    $('#deviceCredentialPatternWrapper').show();
                    pattern = new DevicePattern(document.getElementById('deviceCredentialReadonlyPattern'), {
                        grid: Number(response.credencial.grade),
                        readOnly: true
                    });
                    pattern.setValue(response.credencial.sequencia);
                    $('#deviceCredentialReadonlySequence').text(response.credencial.descricao);
                }
            }).fail(function (xhr) {
                var response = xhr.responseJSON || {};
                if (response.csrfHash) {
                    csrfHash = response.csrfHash;
                }
                showError(response.message || 'Não foi possível revelar a credencial.');
                revealButton.prop('disabled', false).text('Revelar credencial');
            });
        });

        $('#playDeviceCredential').on('click', function () {
            if (pattern) {
                pattern.play({stepMs: 300});
            }
        });

        $('#hideDeviceCredential').on('click', clearDetails);
    });
}(jQuery));
