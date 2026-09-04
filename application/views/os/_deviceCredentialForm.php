<?php
$credentialHasStoredValue = isset($result) && Device_credential::hasStoredCredential($result);
$credentialStoredType = $credentialHasStoredValue ? $result->credencial_tipo : Device_credential::TYPE_UNKNOWN;
$credentialStoredLabels = [
    Device_credential::TYPE_NONE => 'Sem senha',
    Device_credential::TYPE_TEXT => 'Senha/PIN cadastrada',
    Device_credential::TYPE_PATTERN => 'Padrão de desenho cadastrado',
    Device_credential::TYPE_UNKNOWN => 'Não informada',
];
?>

<link rel="stylesheet" href="<?= base_url('assets/css/device-credential.css') ?>">
<script src="<?= base_url('assets/js/device-pattern.js') ?>"></script>

<div class="span12 device-credential" id="deviceCredentialForm" style="margin-left: 0">
    <div class="device-credential__header">
        <h4 style="margin: 0">Credencial do aparelho <span class="required">*</span></h4>
        <label class="checkbox" style="margin: 0">
            <input type="checkbox" id="credencial_sem_senha" name="credencial_sem_senha" value="1">
            Não tem senha
        </label>
    </div>

    <?php if ($credentialHasStoredValue) : ?>
        <div class="device-credential__stored" style="margin-top: 12px">
            Credencial atual: <strong><?= html_escape($credentialStoredLabels[$credentialStoredType]) ?></strong>.
            O valor permanece oculto durante a edição.
        </div>
        <div style="margin-top: 10px">
            <label class="radio inline">
                <input type="radio" name="credencial_acao" value="manter" checked> Manter atual
            </label>
            <label class="radio inline">
                <input type="radio" name="credencial_acao" value="substituir"> Substituir
            </label>
        </div>
    <?php else : ?>
        <input type="hidden" name="credencial_acao" value="substituir">
        <?php if (isset($result)) : ?>
            <div class="alert alert-warning" style="margin-top: 12px; margin-bottom: 0">
                Esta OS ainda não possui uma credencial registrada. Informe uma antes de atualizar.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div id="deviceCredentialEditor"<?= $credentialHasStoredValue ? ' style="display: none"' : '' ?>>
        <div class="device-credential__fields">
            <div class="device-credential__field">
                <label for="credencial_tipo">Tipo</label>
                <select class="span12" name="credencial_tipo" id="credencial_tipo">
                    <option value="texto">Texto / PIN</option>
                    <option value="padrao">Padrão de desenho</option>
                </select>
            </div>

            <div class="device-credential__field" id="deviceCredentialTextField">
                <label for="credencial_texto">Senha / PIN</label>
                <div style="display: flex; gap: 6px">
                    <input class="span12" type="password" name="credencial_texto" id="credencial_texto"
                        maxlength="255" autocomplete="new-password" aria-describedby="deviceCredentialHint">
                    <button type="button" class="btn" id="toggleDeviceCredentialText" aria-label="Mostrar senha">Mostrar</button>
                </div>
            </div>

            <div class="device-credential__field" id="deviceCredentialGridField" style="display: none">
                <label for="credencial_grade">Tamanho da grade</label>
                <select class="span12" name="credencial_grade" id="credencial_grade">
                    <option value="3">3x3</option>
                    <option value="4">4x4</option>
                    <option value="5">5x5</option>
                    <option value="6">6x6</option>
                </select>
            </div>
        </div>

        <p id="deviceCredentialHint" class="help-block">
            A credencial é criptografada antes de ser gravada e não será exibida na área do cliente.
        </p>

        <div id="deviceCredentialPatternField" style="display: none">
            <input type="hidden" name="credencial_padrao" id="credencial_padrao" value="[]">
            <div class="device-credential__pattern">
                <svg id="deviceCredentialPattern" role="img" aria-label="Grade para desenhar o padrão"></svg>
                <div class="device-credential__sequence" id="deviceCredentialSequence">Nenhum ponto selecionado.</div>
                <div style="text-align: center">
                    <button type="button" class="btn" id="clearDeviceCredentialPattern">Limpar padrão</button>
                </div>
            </div>
        </div>
    </div>

    <?php if (! empty($credential_error)) : ?>
        <div class="device-credential__error" role="alert"><?= html_escape($credential_error) ?></div>
    <?php endif; ?>
    <div class="device-credential__error" id="deviceCredentialClientError" role="alert" style="display: none"></div>
</div>

<script>
    $(function () {
        var container = $('#deviceCredentialForm');
        if (!container.length || typeof DevicePattern === 'undefined') {
            return;
        }

        var hasStoredValue = <?= $credentialHasStoredValue ? 'true' : 'false' ?>;
        var editor = $('#deviceCredentialEditor');
        var noPassword = $('#credencial_sem_senha');
        var type = $('#credencial_tipo');
        var text = $('#credencial_texto');
        var grid = $('#credencial_grade');
        var patternValue = $('#credencial_padrao');
        var error = $('#deviceCredentialClientError');
        var pattern = new DevicePattern(document.getElementById('deviceCredentialPattern'), {
            grid: Number(grid.val()),
            onChange: function (sequence) {
                patternValue.val(JSON.stringify(sequence));
                $('#deviceCredentialSequence').text(sequence.length
                    ? 'Sequência: ' + sequence.join(' -> ')
                    : 'Nenhum ponto selecionado.');
            }
        });

        function replacing() {
            return !hasStoredValue || $('input[name="credencial_acao"]:checked').val() === 'substituir';
        }

        function updateState(clearValues) {
            var active = replacing();
            var withoutPassword = noPassword.is(':checked');
            var patternType = type.val() === 'padrao';

            editor.toggle(active);
            noPassword.prop('disabled', !active);
            type.prop('disabled', !active || withoutPassword);
            $('#deviceCredentialTextField').toggle(!patternType);
            $('#deviceCredentialGridField, #deviceCredentialPatternField').toggle(patternType);
            text.prop('disabled', !active || withoutPassword || patternType);
            grid.prop('disabled', !active || withoutPassword || !patternType);
            patternValue.prop('disabled', !active || withoutPassword || !patternType);
            pattern.setDisabled(!active || withoutPassword || !patternType);

            text.prop('required', active && !withoutPassword && !patternType);
            patternValue.prop('required', active && !withoutPassword && patternType);

            if (clearValues) {
                text.val('');
                pattern.clear();
            }
            error.hide().text('');
        }

        $('input[name="credencial_acao"]').on('change', function () {
            noPassword.prop('checked', false);
            updateState(true);
        });

        noPassword.on('change', function () {
            updateState(true);
        });

        type.on('change', function () {
            updateState(true);
        });

        grid.on('change', function () {
            pattern.setGrid(Number(this.value));
        });

        $('#clearDeviceCredentialPattern').on('click', function () {
            pattern.clear();
        });

        $('#toggleDeviceCredentialText').on('click', function () {
            var showing = text.attr('type') === 'text';
            text.attr('type', showing ? 'password' : 'text');
            $(this).text(showing ? 'Mostrar' : 'Ocultar');
            $(this).attr('aria-label', showing ? 'Mostrar senha' : 'Ocultar senha');
        });

        $('#formOs').on('submit.deviceCredential', function (event) {
            if (!replacing() || noPassword.is(':checked')) {
                return;
            }

            if (type.val() === 'texto' && $.trim(text.val()) === '') {
                event.preventDefault();
                error.text('Informe a senha/PIN ou marque "Não tem senha".').show();
                text.focus();
                return;
            }

            if (type.val() === 'padrao' && pattern.getValue().length < 4) {
                event.preventDefault();
                error.text('O padrão deve conectar pelo menos quatro pontos.').show();
            }
        });

        updateState(false);
    });
</script>
