<?php

define('BASEPATH', __DIR__);
require_once dirname(__DIR__) . '/application/libraries/Portal.php';

$assertions = 0;

function expectSame($expected, $actual, $message)
{
    global $assertions;
    ++$assertions;

    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function expectInvalidConfiguration(array $environment, $message)
{
    global $assertions;
    ++$assertions;

    try {
        Portal::configuration($environment, ['HTTP_HOST' => 'gestao.tecnina.com'], 'production');
    } catch (InvalidArgumentException $exception) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$environment = [
    'APP_BASEURL' => 'http://localhost:8000/',
    'APP_BASEURL_GESTAO' => 'https://gestao.tecnina.com/',
    'APP_BASEURL_CLIENTE' => 'https://cliente.tecnina.com/',
];

$management = Portal::configuration($environment, ['HTTP_HOST' => 'GESTAO.TECNINA.COM:443'], 'production');
expectSame('management', $management['current'], 'O hostname de gestão deve selecionar o portal administrativo.');
expectSame('https://gestao.tecnina.com/', $management['base_url'], 'A base URL de gestão deve ser explícita.');

$client = Portal::configuration($environment, ['HTTP_HOST' => 'cliente.tecnina.com'], 'production');
expectSame('client', $client['current'], 'O hostname do cliente deve selecionar a Área do Cliente.');
expectSame('https://cliente.tecnina.com/', $client['base_url'], 'A base URL do cliente deve ser explícita.');

$unknown = Portal::configuration($environment, ['HTTP_HOST' => 'gestao.tecnina.com.evil.example'], 'production');
expectSame('unknown', $unknown['current'], 'Hostnames inesperados não podem casar parcialmente com a whitelist.');
expectSame('https://gestao.tecnina.com/', $unknown['base_url'], 'Um hostname inesperado deve usar o fallback seguro de gestão.');

$injected = Portal::configuration($environment, ['HTTP_HOST' => 'cliente.tecnina.com@evil.example'], 'production');
expectSame('unknown', $injected['current'], 'Um Host malformado deve ser rejeitado.');

$local = Portal::configuration($environment, ['HTTP_HOST' => 'localhost:8000'], 'development');
expectSame('local', $local['current'], 'Localhost deve continuar disponível fora de produção.');
expectSame('http://localhost:8000/', $local['base_url'], 'Desenvolvimento local deve manter APP_BASEURL.');

$legacy = Portal::configuration(['APP_BASEURL' => 'http://localhost:8000/'], ['HTTP_HOST' => 'localhost:8000'], 'development');
expectSame(false, $legacy['split_enabled'], 'Sem as duas novas variáveis, o modo legado deve ser preservado.');
expectInvalidConfiguration(
    ['APP_BASEURL' => 'http://localhost:8000/', 'APP_BASEURL_GESTAO' => 'https://gestao.tecnina.com/'],
    'A configuração parcial dos portais deve falhar de forma segura.'
);
expectInvalidConfiguration(
    [
        'APP_BASEURL' => 'http://localhost:8000/',
        'APP_BASEURL_GESTAO' => 'https://usuario@gestao.tecnina.com/',
        'APP_BASEURL_CLIENTE' => 'https://cliente.tecnina.com/',
    ],
    'URLs de portal com credenciais devem ser rejeitadas.'
);

expectSame(
    ['portal' => 'client', 'path' => 'mine', 'query' => '', 'status' => 302],
    Portal::redirectInstruction('client', ''),
    'A raiz do cliente deve direcionar ao login Mine.'
);
expectSame(null, Portal::redirectInstruction('client', 'mine'), '/mine deve ser permitido no portal do cliente.');
expectSame(null, Portal::redirectInstruction('client', 'api/v1/client/os'), 'A API do cliente deve ser permitida no portal do cliente.');
expectSame(
    ['portal' => 'management', 'path' => '', 'query' => '', 'status' => 302],
    Portal::redirectInstruction('client', 'mapos'),
    'Rotas administrativas no host do cliente devem voltar para gestão.'
);
expectSame(
    ['portal' => 'management', 'path' => '', 'query' => '', 'status' => 302],
    Portal::redirectInstruction('client', 'login'),
    'O login administrativo não deve ser exposto no host do cliente.'
);
expectSame(
    ['portal' => 'client', 'path' => 'mine/resetarSenha', 'query' => 'token=abc', 'status' => 302],
    Portal::redirectInstruction('management', 'mine/resetarSenha', 'GET', 'token=abc'),
    'Rotas Mine no host de gestão devem preservar caminho e query string.'
);
expectSame(
    ['portal' => 'client', 'path' => 'mine/login', 'query' => '', 'status' => 307],
    Portal::redirectInstruction('management', 'mine/login', 'POST'),
    'POSTs Mine redirecionados devem preservar o método.'
);
expectSame(null, Portal::redirectInstruction('management', 'mapos'), '/mapos deve ser permitido em gestão.');
expectSame(
    ['portal' => 'management', 'path' => '', 'query' => '', 'status' => 302],
    Portal::redirectInstruction('unknown', 'mine'),
    'Um hostname inesperado deve redirecionar para a raiz de gestão.'
);
expectSame(
    'https://cliente.tecnina.com/index.php/mine/painel',
    Portal::siteUrl('https://cliente.tecnina.com/', 'index.php', 'mine/painel'),
    'URLs do cliente devem incluir o front controller configurado.'
);

echo 'Portal tests passed (' . $assertions . ' assertions).' . PHP_EOL;
