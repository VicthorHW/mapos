<?php

$escapePasswordEmail = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$resetUrl = cliente_url('mine/verifyTokenSenha/token/' . rawurlencode($resets_de_senha->token));
$manualUrl = cliente_url('mine/tokenManual');

ob_start();
?>
<p style="margin:0 0 20px;">Olá, <strong><?= $escapePasswordEmail($cliente->nomeCliente) ?></strong>.</p>
<p style="margin:0 0 20px;">Recebemos uma solicitação para redefinir a senha da sua Área do Cliente TecNina.</p>
<p style="margin:0 0 20px;">Use o botão abaixo para escolher uma nova senha. Se você não solicitou essa alteração, pode ignorar este e-mail.</p>
<?php
$emailContent = ob_get_clean();

ob_start();
?>
<p style="margin:0 0 8px;">Se o botão não abrir, acesse <a href="<?= $escapePasswordEmail($manualUrl) ?>" target="_blank" style="color:#f26a21; text-decoration:underline;">a recuperação manual de senha</a> e informe este código:</p>
<p style="margin:0; padding:12px; border:1px solid #e5e7eb; background-color:#ffffff; color:#17191d; font-family:Courier New, Courier, monospace; font-size:14px; line-height:20px; overflow-wrap:anywhere;"><strong><?= $escapePasswordEmail($resets_de_senha->token) ?></strong></p>
<?php
$emailSecondaryContent = ob_get_clean();

$this->load->view('emails/layout', [
    'title' => 'Recuperação de senha',
    'preheader' => 'Use este link para redefinir sua senha da Área do Cliente.',
    'content' => $emailContent,
    'cta_label' => 'Redefinir minha senha',
    'cta_url' => $resetUrl,
    'secondary_content' => $emailSecondaryContent,
]);
