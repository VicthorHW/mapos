<?php

$escapeClientNoticeEmail = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

ob_start();
?>
<p style="margin:0 0 20px;">Olá, <strong><?= $escapeClientNoticeEmail($usuario->nome) ?></strong>.</p>
<p style="margin:0 0 20px;">Um novo cliente se cadastrou no sistema. Confira os dados informados:</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; border:1px solid #e5e7eb; border-collapse:collapse; color:#3f424a; font-size:14px; line-height:20px;">
    <tr>
        <td style="width:36%; padding:10px 12px; border-bottom:1px solid #e5e7eb; background-color:#fafafa;"><strong>Nome</strong></td>
        <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb;"><?= $escapeClientNoticeEmail($cliente->nomeCliente) ?></td>
    </tr>
    <tr>
        <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb; background-color:#fafafa;"><strong>CPF</strong></td>
        <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb;"><?= $escapeClientNoticeEmail($cliente->documento) ?></td>
    </tr>
    <tr>
        <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb; background-color:#fafafa;"><strong>Endereço</strong></td>
        <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb;"><?= $escapeClientNoticeEmail($cliente->rua) ?>, <?= $escapeClientNoticeEmail($cliente->numero) ?><?= $cliente->complemento ? ' — ' . $escapeClientNoticeEmail($cliente->complemento) : '' ?></td>
    </tr>
    <tr>
        <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb; background-color:#fafafa;"><strong>Bairro / cidade</strong></td>
        <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb;"><?= $escapeClientNoticeEmail($cliente->bairro) ?> — <?= $escapeClientNoticeEmail($cliente->cidade) ?>/<?= $escapeClientNoticeEmail($cliente->estado) ?></td>
    </tr>
    <tr>
        <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb; background-color:#fafafa;"><strong>CEP</strong></td>
        <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb;"><?= $escapeClientNoticeEmail($cliente->cep) ?></td>
    </tr>
    <tr>
        <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb; background-color:#fafafa;"><strong>E-mail</strong></td>
        <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb;"><?= $escapeClientNoticeEmail($cliente->email) ?></td>
    </tr>
    <tr>
        <td style="padding:10px 12px; background-color:#fafafa;"><strong>Celular</strong></td>
        <td style="padding:10px 12px;"><?= $escapeClientNoticeEmail($cliente->celular) ?></td>
    </tr>
</table>
<?php
$emailContent = ob_get_clean();

$this->load->view('emails/layout', [
    'title' => 'Novo cliente cadastrado',
    'preheader' => 'Um novo cliente realizou cadastro no sistema.',
    'content' => $emailContent,
]);
