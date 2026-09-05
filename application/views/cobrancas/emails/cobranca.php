<?php

$escapeChargeEmail = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$paymentUrl = ! empty($cobranca->link) ? $cobranca->link : (! empty($cobranca->pdf) ? $cobranca->pdf : '');
$paymentLabel = ! empty($cobranca->link) ? 'Acessar pagamento' : 'Abrir cobrança';

ob_start();
?>
<p style="margin:0 0 20px;">Olá, <strong><?= $escapeChargeEmail($cobranca->nomeCliente) ?></strong>.</p>
<p style="margin:0 0 20px;">Há uma cobrança disponível para você. Confira os detalhes abaixo:</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:0 0 20px; border:1px solid #e5e7eb; border-collapse:collapse; color:#3f424a; font-size:14px; line-height:20px;">
    <tr>
        <td style="width:42%; padding:10px 12px; border-bottom:1px solid #e5e7eb; background-color:#fafafa;"><strong>Cobrança</strong></td>
        <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb;">#<?= $escapeChargeEmail($cobranca->idCobranca) ?></td>
    </tr>
    <?php if ($cobranca->expire_at) : ?>
        <tr>
            <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb; background-color:#fafafa;"><strong>Vencimento</strong></td>
            <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb;"><?= date('d/m/Y', strtotime($cobranca->expire_at)) ?></td>
        </tr>
    <?php endif; ?>
    <?php if ($cobranca->barcode) : ?>
        <tr>
            <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb; background-color:#fafafa;"><strong>Código de barras</strong></td>
            <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb; overflow-wrap:anywhere;"><?= $escapeChargeEmail($cobranca->barcode) ?></td>
        </tr>
    <?php endif; ?>
    <tr>
        <td style="padding:12px; background-color:#fff7ed;"><strong style="color:#9a3412;">Valor total</strong></td>
        <td style="padding:12px; background-color:#fff7ed; color:#9a3412; font-size:16px;"><strong>R$ <?= number_format($cobranca->total / 100, 2, ',', '.') ?></strong></td>
    </tr>
</table>
<?php if ($cobranca->pdf || $cobranca->link) : ?>
    <p style="margin:0; color:#6b7280; font-size:14px; line-height:21px;">
        <?php if ($cobranca->pdf) : ?><a href="<?= $escapeChargeEmail($cobranca->pdf) ?>" target="_blank" style="color:#f26a21; text-decoration:underline;">Abrir PDF</a><?php endif; ?>
        <?php if ($cobranca->pdf && $cobranca->link) : ?> · <?php endif; ?>
        <?php if ($cobranca->link) : ?><a href="<?= $escapeChargeEmail($cobranca->link) ?>" target="_blank" style="color:#f26a21; text-decoration:underline;">Abrir link de pagamento</a><?php endif; ?>
    </p>
<?php endif; ?>
<?php
$emailContent = ob_get_clean();

$this->load->view('emails/layout', [
    'title' => 'Cobrança disponível',
    'preheader' => 'Confira os dados e a forma de pagamento da sua cobrança.',
    'content' => $emailContent,
    'cta_label' => $paymentUrl ? $paymentLabel : '',
    'cta_url' => $paymentUrl,
    'emitente' => $emitente,
]);
