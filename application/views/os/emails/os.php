<?php

$totalServico = 0;
$totalProdutos = 0;
$escapeOsEmail = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

ob_start();
?>
<p style="margin:0 0 20px;">Olá, <strong><?= $escapeOsEmail($result->nomeCliente) ?></strong>.</p>
<p style="margin:0 0 20px;">Confira as informações atualizadas do seu reparo.</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:0 0 20px; border:1px solid #e5e7eb; border-collapse:collapse; color:#3f424a; font-size:14px; line-height:20px;">
    <tr>
        <td style="width:42%; padding:10px 12px; border-bottom:1px solid #e5e7eb; background-color:#fafafa;"><strong>Atendimento</strong></td>
        <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb;">#<?= $escapeOsEmail($result->idOs) ?></td>
    </tr>
    <tr>
        <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb; background-color:#fafafa;"><strong>Status</strong></td>
        <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb;"><strong style="color:#f26a21;"><?= $escapeOsEmail($result->status) ?></strong></td>
    </tr>
    <tr>
        <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb; background-color:#fafafa;"><strong>Data de entrada</strong></td>
        <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb;"><?= date('d/m/Y', strtotime($result->dataInicial)) ?></td>
    </tr>
    <?php if ($result->dataFinal) : ?>
        <tr>
            <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb; background-color:#fafafa;"><strong>Previsão / conclusão</strong></td>
            <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb;"><?= date('d/m/Y', strtotime($result->dataFinal)) ?></td>
        </tr>
    <?php endif; ?>
    <?php if ($result->garantia) : ?>
        <tr>
            <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb; background-color:#fafafa;"><strong>Garantia</strong></td>
            <td style="padding:10px 12px; border-bottom:1px solid #e5e7eb;"><?= $escapeOsEmail($result->garantia) ?></td>
        </tr>
    <?php endif; ?>
    <?php if ($result->nome) : ?>
        <tr>
            <td style="padding:10px 12px; background-color:#fafafa;"><strong>Responsável</strong></td>
            <td style="padding:10px 12px;"><?= $escapeOsEmail($result->nome) ?></td>
        </tr>
    <?php endif; ?>
</table>

<?php foreach ([
    'Descrição do equipamento' => $result->descricaoProduto,
    'Defeito informado' => $result->defeito,
    'Observações' => $result->observacoes,
    'Laudo técnico' => $result->laudoTecnico,
] as $sectionTitle => $sectionContent) : ?>
    <?php if ($sectionContent) : ?>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:0 0 16px; border:1px solid #e5e7eb; border-collapse:collapse;">
            <tr>
                <td style="padding:10px 12px; background-color:#fafafa; color:#17191d; font-size:14px;"><strong><?= $escapeOsEmail($sectionTitle) ?></strong></td>
            </tr>
            <tr>
                <td style="padding:12px; color:#3f424a; font-size:14px; line-height:21px;"><?= printSafeHtml($sectionContent) ?></td>
            </tr>
        </table>
    <?php endif; ?>
<?php endforeach; ?>

<?php if ($produtos) : ?>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:0 0 16px; border:1px solid #e5e7eb; border-collapse:collapse; color:#3f424a; font-size:13px; line-height:19px;">
        <tr>
            <td colspan="4" style="padding:10px 12px; background-color:#fafafa; color:#17191d;"><strong>Produtos</strong></td>
        </tr>
        <tr>
            <td style="padding:9px 10px; border-top:1px solid #e5e7eb;"><strong>Produto</strong></td>
            <td align="center" style="padding:9px 10px; border-top:1px solid #e5e7eb;"><strong>Qtd.</strong></td>
            <td align="right" style="padding:9px 10px; border-top:1px solid #e5e7eb;"><strong>Unitário</strong></td>
            <td align="right" style="padding:9px 10px; border-top:1px solid #e5e7eb;"><strong>Subtotal</strong></td>
        </tr>
        <?php foreach ($produtos as $p) : ?>
            <?php
            $totalProdutos += $p->subTotal;
            $precoProduto = $p->preco ?: $p->precoVenda;
            ?>
            <tr>
                <td style="padding:9px 10px; border-top:1px solid #e5e7eb;"><?= $escapeOsEmail($p->descricao) ?></td>
                <td align="center" style="padding:9px 10px; border-top:1px solid #e5e7eb;"><?= $escapeOsEmail($p->quantidade) ?></td>
                <td align="right" style="padding:9px 10px; border-top:1px solid #e5e7eb;">R$ <?= number_format($precoProduto, 2, ',', '.') ?></td>
                <td align="right" style="padding:9px 10px; border-top:1px solid #e5e7eb;">R$ <?= number_format($p->subTotal, 2, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <td colspan="3" align="right" style="padding:10px; border-top:1px solid #e5e7eb;"><strong>Total em produtos</strong></td>
            <td align="right" style="padding:10px; border-top:1px solid #e5e7eb;"><strong>R$ <?= number_format($totalProdutos, 2, ',', '.') ?></strong></td>
        </tr>
    </table>
<?php endif; ?>

<?php if ($servicos) : ?>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:0 0 16px; border:1px solid #e5e7eb; border-collapse:collapse; color:#3f424a; font-size:13px; line-height:19px;">
        <tr>
            <td colspan="4" style="padding:10px 12px; background-color:#fafafa; color:#17191d;"><strong>Serviços</strong></td>
        </tr>
        <tr>
            <td style="padding:9px 10px; border-top:1px solid #e5e7eb;"><strong>Serviço</strong></td>
            <td align="center" style="padding:9px 10px; border-top:1px solid #e5e7eb;"><strong>Qtd.</strong></td>
            <td align="right" style="padding:9px 10px; border-top:1px solid #e5e7eb;"><strong>Unitário</strong></td>
            <td align="right" style="padding:9px 10px; border-top:1px solid #e5e7eb;"><strong>Subtotal</strong></td>
        </tr>
        <?php foreach ($servicos as $s) : ?>
            <?php
            $precoServico = $s->preco ?: $s->precoVenda;
            $quantidadeServico = $s->quantidade ?: 1;
            $subtotalServico = $precoServico * $quantidadeServico;
            $totalServico += $subtotalServico;
            ?>
            <tr>
                <td style="padding:9px 10px; border-top:1px solid #e5e7eb;"><?= $escapeOsEmail($s->nome) ?></td>
                <td align="center" style="padding:9px 10px; border-top:1px solid #e5e7eb;"><?= $escapeOsEmail($quantidadeServico) ?></td>
                <td align="right" style="padding:9px 10px; border-top:1px solid #e5e7eb;">R$ <?= number_format($precoServico, 2, ',', '.') ?></td>
                <td align="right" style="padding:9px 10px; border-top:1px solid #e5e7eb;">R$ <?= number_format($subtotalServico, 2, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <td colspan="3" align="right" style="padding:10px; border-top:1px solid #e5e7eb;"><strong>Total em serviços</strong></td>
            <td align="right" style="padding:10px; border-top:1px solid #e5e7eb;"><strong>R$ <?= number_format($totalServico, 2, ',', '.') ?></strong></td>
        </tr>
    </table>
<?php endif; ?>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; margin:0 0 4px; border-collapse:collapse; color:#3f424a; font-size:14px; line-height:20px;">
    <?php if ($result->desconto != 0 && $result->valor_desconto != 0) : ?>
        <tr>
            <td align="right" style="padding:4px 0;">Desconto: R$ <?= number_format($result->valor_desconto - ($totalProdutos + $totalServico), 2, ',', '.') ?></td>
        </tr>
        <tr>
            <td align="right" style="padding:8px 0; color:#9a3412; font-size:16px;"><strong>Total com desconto: R$ <?= number_format($result->valor_desconto, 2, ',', '.') ?></strong></td>
        </tr>
    <?php else : ?>
        <tr>
            <td align="right" style="padding:8px 0; color:#9a3412; font-size:16px;"><strong>Total: R$ <?= number_format($totalProdutos + $totalServico, 2, ',', '.') ?></strong></td>
        </tr>
    <?php endif; ?>
</table>
<?php
$emailContent = ob_get_clean();

$this->load->view('emails/layout', [
    'title' => 'Atualização do seu reparo',
    'preheader' => 'Confira o status e os detalhes atualizados do seu atendimento.',
    'content' => $emailContent,
]);
