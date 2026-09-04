<?php
$credentialType = isset($result->credencial_tipo)
    ? $result->credencial_tipo
    : Device_credential::TYPE_UNKNOWN;
$credentialLabels = [
    Device_credential::TYPE_UNKNOWN => 'Não informada',
    Device_credential::TYPE_NONE => 'SEM SENHA',
    Device_credential::TYPE_TEXT => 'Senha/PIN cadastrada',
    Device_credential::TYPE_PATTERN => 'Padrão de desenho cadastrado',
];
$credentialCanReveal = in_array($credentialType, [
    Device_credential::TYPE_TEXT,
    Device_credential::TYPE_PATTERN,
], true);
?>

<tr id="deviceCredentialDisplay"
    data-endpoint="<?= html_escape(site_url('os/credencial/' . $result->idOs)) ?>"
    data-csrf-name="<?= html_escape($this->security->get_csrf_token_name()) ?>"
    data-csrf-hash="<?= html_escape($this->security->get_csrf_hash()) ?>">
    <td colspan="5">
        <b>CREDENCIAL DO APARELHO: </b>
        <span id="deviceCredentialStatus"><?= html_escape($credentialLabels[$credentialType] ?? 'Invalida') ?></span>

        <?php if ($credentialCanReveal) : ?>
            <div class="device-credential__reveal">
                <button type="button" class="btn btn-info" id="revealDeviceCredential">Revelar credencial</button>
            </div>
            <div class="device-credential" id="deviceCredentialDetails" style="display: none">
                <div id="deviceCredentialText" style="display: none"></div>
                <div id="deviceCredentialPatternWrapper" style="display: none">
                    <div class="device-credential__pattern">
                        <svg id="deviceCredentialReadonlyPattern" role="img" aria-label="Padrão de desbloqueio"></svg>
                        <div class="device-credential__sequence" id="deviceCredentialReadonlySequence"></div>
                        <div style="text-align: center">
                            <button type="button" class="btn btn-info" id="playDeviceCredential">Reproduzir</button>
                        </div>
                    </div>
                </div>
                <div style="margin-top: 10px">
                    <button type="button" class="btn" id="hideDeviceCredential">Ocultar</button>
                </div>
            </div>
        <?php endif; ?>
    </td>
</tr>
