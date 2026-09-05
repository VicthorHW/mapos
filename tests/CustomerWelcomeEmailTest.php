<?php

$assertions = 0;
$root = dirname(__DIR__);

function expectWelcomeContains($needle, $haystack, $message)
{
    global $assertions;
    ++$assertions;

    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function expectWelcomeNotContains($needle, $haystack, $message)
{
    global $assertions;
    ++$assertions;

    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$clientesController = file_get_contents($root . '/application/controllers/Clientes.php');
$mineController = file_get_contents($root . '/application/controllers/Mine.php');
$welcomeLibrary = file_get_contents($root . '/application/libraries/Customer_welcome_email.php');
$welcomeView = file_get_contents($root . '/application/views/os/emails/clientenovo.php');
$layout = file_get_contents($root . '/application/views/emails/layout.php');

expectWelcomeContains(
    "customer_welcome_email->queue(\$clienteId)",
    $clientesController,
    'O cadastro administrativo deve enfileirar o e-mail de boas-vindas.'
);
expectWelcomeContains(
    "! \$data['fornecedor']",
    $clientesController,
    'Fornecedores não devem receber o e-mail de boas-vindas de cliente.'
);
expectWelcomeContains(
    "load->library('customer_welcome_email')",
    $mineController,
    'O fluxo antigo deve reutilizar o serviço de boas-vindas.'
);
expectWelcomeContains(
    "'Subject' => 'Bem-vindo à TecNina'",
    $welcomeLibrary,
    'O serviço deve preservar um assunto de boas-vindas claro.'
);
expectWelcomeContains(
    "'email_queue'",
    $welcomeLibrary,
    'O serviço deve usar a fila de e-mails existente.'
);
expectWelcomeContains(
    'Sua conta na Área do Cliente já está liberada.',
    $welcomeView,
    'A boas-vindas deve informar que a conta já está liberada.'
);
expectWelcomeNotContains(
    'Endereço para envio',
    $welcomeView,
    'A boas-vindas não deve instruir envio de equipamento por correio.'
);
expectWelcomeContains(
    'https://wa.me/',
    $layout,
    'Todos os e-mails devem conter o link de WhatsApp centralizado no layout.'
);
expectWelcomeContains(
    '5541974035094',
    $layout,
    'O layout deve manter o WhatsApp de fallback.'
);

echo 'CustomerWelcomeEmailTest: ' . $assertions . ' assertions passed.' . PHP_EOL;
