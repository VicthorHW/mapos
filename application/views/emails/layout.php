<?php

$emailTitle = isset($title) ? (string) $title : '';
$emailSubtitle = isset($subtitle) ? (string) $subtitle : '';
$emailContent = isset($content) ? (string) $content : '';
$emailCtaLabel = isset($cta_label) ? (string) $cta_label : '';
$emailCtaUrl = isset($cta_url) ? (string) $cta_url : '';
$emailSecondaryContent = isset($secondary_content) ? (string) $secondary_content : '';
$emailPreheader = isset($preheader) ? (string) $preheader : $emailTitle;
$emailLogoUrl = isset($logo_url) && $logo_url !== ''
    ? (string) $logo_url
    : base_url('assets/tecnina/img/email/tecnina-logo-email.png');

$escapeEmailLayout = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title><?= $escapeEmailLayout($emailTitle) ?></title>
    <style>
        @media only screen and (max-width: 640px) {
            .email-shell { width: 100% !important; }
            .email-card { border-radius: 0 !important; }
            .email-padding { padding-left: 24px !important; padding-right: 24px !important; }
            .email-title { font-size: 26px !important; line-height: 32px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; width:100%; background-color:#f3f4f6; color:#26282d; font-family:Arial, Helvetica, sans-serif;">
    <span style="display:none !important; font-size:1px; color:#f3f4f6; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
        <?= $escapeEmailLayout($emailPreheader) ?>
    </span>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%; background-color:#f3f4f6;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" class="email-shell" width="620" cellpadding="0" cellspacing="0" border="0" style="width:620px; max-width:620px; margin:0 auto;">
                    <tr>
                        <td class="email-card" style="background-color:#ffffff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                                <tr>
                                    <td class="email-padding" style="padding:32px 40px 24px; border-bottom:3px solid #f26a21;">
                                        <img src="<?= $escapeEmailLayout($emailLogoUrl) ?>" width="190" alt="TecNina Assistência Técnica" style="display:block; width:190px; max-width:100%; height:auto; border:0; outline:none; text-decoration:none;">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="email-padding" style="padding:32px 40px 12px;">
                                        <h1 class="email-title" style="margin:0; color:#17191d; font-family:Arial, Helvetica, sans-serif; font-size:30px; font-weight:700; line-height:36px;">
                                            <?= $escapeEmailLayout($emailTitle) ?>
                                        </h1>
                                        <?php if ($emailSubtitle !== '') : ?>
                                            <p style="margin:10px 0 0; color:#6b7280; font-size:16px; line-height:24px;">
                                                <?= $escapeEmailLayout($emailSubtitle) ?>
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="email-padding" style="padding:12px 40px 12px; color:#3f424a; font-size:16px; line-height:24px;">
                                        <?= $emailContent ?>
                                    </td>
                                </tr>
                                <?php if ($emailCtaLabel !== '' && $emailCtaUrl !== '') : ?>
                                    <tr>
                                        <td class="email-padding" style="padding:16px 40px 24px;">
                                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                <tr>
                                                    <td align="center" bgcolor="#f26a21" style="border-radius:6px; background-color:#f26a21;">
                                                        <a href="<?= $escapeEmailLayout($emailCtaUrl) ?>" target="_blank" style="display:inline-block; padding:13px 22px; color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:16px; font-weight:700; line-height:20px; text-decoration:none;">
                                                            <?= $escapeEmailLayout($emailCtaLabel) ?>
                                                        </a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ($emailSecondaryContent !== '') : ?>
                                    <tr>
                                        <td class="email-padding" style="padding:4px 40px 28px; color:#6b7280; font-size:13px; line-height:20px;">
                                            <?= $emailSecondaryContent ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="email-padding" style="padding:24px 40px 28px; background-color:#fafafa; border-top:1px solid #e5e7eb; color:#6b7280; font-size:13px; line-height:20px;">
                                        <strong style="color:#17191d;">TecNina Assistência Técnica</strong><br>
                                        A gente busca, resolve e devolve.<br>
                                        Antonina-PR e região · <a href="https://tecnina.com" target="_blank" style="color:#f26a21; text-decoration:underline;">tecnina.com</a><br>
                                        <span style="color:#9ca3af;">Esta é uma mensagem automática. Por favor, não responda diretamente a este e-mail.</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
