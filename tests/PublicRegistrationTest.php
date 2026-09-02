<?php

define('BASEPATH', __DIR__);

class CI_Controller
{
}

class RegistrationRedirect extends RuntimeException
{
}

function cliente_url($uri = '')
{
    return 'https://cliente.tecnina.com/index.php/' . ltrim($uri, '/');
}

function redirect($uri = '')
{
    throw new RegistrationRedirect($uri);
}

require_once dirname(__DIR__) . '/application/controllers/Mine.php';

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

function expectContains($needle, $haystack, $message)
{
    expectSame(true, strpos($haystack, $needle) !== false, $message);
}

function expectNotContains($needle, $haystack, $message)
{
    expectSame(false, strpos($haystack, $needle) !== false, $message);
}

$controller = (new ReflectionClass(Mine::class))->newInstanceWithoutConstructor();
$loginUrl = 'https://cliente.tecnina.com/index.php/mine';

foreach (['GET', 'POST'] as $method) {
    $_SERVER['REQUEST_METHOD'] = $method;

    try {
        $controller->cadastrar();
        fwrite(STDERR, $method . ' /mine/cadastrar não foi redirecionado.' . PHP_EOL);
        exit(1);
    } catch (RegistrationRedirect $redirect) {
        expectSame($loginUrl, $redirect->getMessage(), $method . ' /mine/cadastrar deve voltar ao login do cliente.');
    }
}

$loginView = file_get_contents(dirname(__DIR__) . '/application/views/conecte/login.php');

expectNotContains('mine/cadastrar', $loginView, 'A tela de login não deve apontar para o autocadastro.');
expectNotContains('Cadastrar-me', $loginView, 'A tela de login não deve exibir o CTA de autocadastro.');
expectContains('mine/login', $loginView, 'O login do cliente deve permanecer disponível.');
expectContains('mine/resetarSenha', $loginView, 'A recuperação de senha deve permanecer disponível.');

echo 'Public registration tests passed (' . $assertions . ' assertions).' . PHP_EOL;
