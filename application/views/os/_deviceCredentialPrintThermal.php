<?php if (! empty($deviceCredential) && ! empty($deviceCredential['valid'])) : ?>
    <tr>
        <td colspan="5">
            <strong>Credencial - via técnica: </strong>
            <?php if ($deviceCredential['data']['tipo'] === Device_credential::TYPE_NONE) : ?>
                SEM SENHA
            <?php elseif ($deviceCredential['data']['tipo'] === Device_credential::TYPE_TEXT) : ?>
                Senha/PIN: <?= html_escape($deviceCredential['data']['texto']) ?>
            <?php elseif ($deviceCredential['data']['tipo'] === Device_credential::TYPE_PATTERN) : ?>
                <?= html_escape($deviceCredential['data']['descricao']) ?>
            <?php else : ?>
                Não informada
            <?php endif; ?>
        </td>
    </tr>
<?php endif; ?>
