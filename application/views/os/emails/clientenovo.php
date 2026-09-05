<?php

$escapeWelcomeEmail = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

ob_start();
?>
<p style="margin:0 0 20px;">Olá, <strong><?= $escapeWelcomeEmail($cliente->nomeCliente) ?></strong>. Seja bem-vindo(a) à Área do Cliente TecNina.</p>
<p style="margin:0 0 16px;">Para enviar seu equipamento para avaliação, siga estes passos:</p>
<ol style="margin:0 0 20px; padding-left:22px;">
    <li style="margin:0 0 10px;">Fale conosco pelo WhatsApp <?= $escapeWelcomeEmail($emitente->telefone) ?> e avise que enviará o equipamento.</li>
    <li style="margin:0 0 10px;">Na Área do Cliente, cadastre um reparo e descreva o defeito ou serviço desejado.</li>
    <li style="margin:0 0 10px;">Use seu e-mail para acessar a conta e seu CPF como senha inicial.</li>
    <li style="margin:0;">Após o cadastro, você receberá a confirmação e poderá enviar o equipamento.</li>
</ol>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:0 0 8px; border:1px solid #e5e7eb; border-collapse:collapse;">
    <tr>
        <td style="padding:14px 16px; background-color:#fafafa; color:#17191d; font-size:14px; line-height:21px;">
            <strong>Endereço para envio</strong><br>
            <?= $escapeWelcomeEmail($emitente->rua) ?>, <?= $escapeWelcomeEmail($emitente->numero) ?> — <?= $escapeWelcomeEmail($emitente->bairro) ?><br>
            <?= $escapeWelcomeEmail($emitente->cidade) ?> - <?= $escapeWelcomeEmail($emitente->uf) ?> · CEP <?= $escapeWelcomeEmail($emitente->cep) ?>
        </td>
    </tr>
</table>
<?php
$emailContent = ob_get_clean();

$this->load->view('emails/layout', [
    'title' => 'Boas-vindas à Área do Cliente',
    'preheader' => 'Sua conta TecNina foi criada. Veja como enviar seu equipamento.',
    'content' => $emailContent,
]);
