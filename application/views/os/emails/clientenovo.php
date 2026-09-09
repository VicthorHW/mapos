<?php

$escapeWelcomeEmail = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

ob_start();
?>
<p style="margin:0 0 20px;">Olá, <strong><?= $escapeWelcomeEmail($cliente->nomeCliente) ?></strong>. Seja bem-vindo(a) à TecNina.</p>
<p style="margin:0 0 20px;">Sua conta na Área do Cliente já está liberada. Por lá, você pode acompanhar o status dos seus reparos, serviços e cobranças.</p>
<p style="margin:0 0 20px;">Para acessar, use seu e-mail e a senha definida no cadastro.</p>
<p style="margin:0;">Se o cadastro foi iniciado pelo atendimento no WhatsApp e você ainda não definiu uma senha, use a opção <strong>Esqueceu a senha?</strong> para criar uma com segurança.</p>
<?php
$emailContent = ob_get_clean();

$this->load->view('emails/layout', [
    'title' => 'Seja bem-vindo à TecNina',
    'preheader' => 'Sua conta na Área do Cliente já está liberada.',
    'content' => $emailContent,
    'emitente' => $emitente,
    'cta_label' => 'Criar ou redefinir minha senha',
    'cta_url' => cliente_url('mine/resetarSenha'),
]);
