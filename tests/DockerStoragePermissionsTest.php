<?php

$assertions = 0;
$root = dirname(__DIR__);
$entrypoint = file_get_contents($root . '/docker/etc/php/entrypoint.sh');
$dockerfile = file_get_contents($root . '/docker/etc/php/Dockerfile');

function expectStorageContains($needle, $haystack, $message)
{
    global $assertions;
    ++$assertions;

    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function expectStorageNotContains($needle, $haystack, $message)
{
    global $assertions;
    ++$assertions;

    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

expectStorageContains(
    'PRIVATE_WRITABLE_DIRS=',
    $entrypoint,
    'O entrypoint deve separar os diretórios privados.'
);
expectStorageContains(
    'set -eu',
    $entrypoint,
    'O entrypoint deve interromper ao encontrar erros ou variáveis ausentes.'
);
expectStorageContains(
    'COPY ./entrypoint.sh /usr/local/bin/mapos-entrypoint',
    $dockerfile,
    'A imagem do PHP deve instalar o entrypoint validado.'
);
expectStorageContains(
    'PUBLIC_WRITABLE_DIRS=',
    $entrypoint,
    'O entrypoint deve separar os diretórios servidos pelo nginx.'
);

foreach (['anexos', 'arquivos', 'uploads', 'userImage'] as $directory) {
    expectStorageContains(
        '/var/www/html/assets/' . $directory,
        $entrypoint,
        'O diretório público ' . $directory . ' deve ser preparado pelo entrypoint.'
    );
}

expectStorageContains(
    'find "${DIR}" -type d -exec chmod 0755 {} \;',
    $entrypoint,
    'Diretórios públicos devem permitir leitura e travessia pelo nginx.'
);
expectStorageContains(
    'find "${DIR}" -type f -exec chmod 0644 {} \;',
    $entrypoint,
    'Arquivos públicos devem permitir leitura pelo nginx.'
);
expectStorageContains(
    'umask 0022',
    $entrypoint,
    'Novos uploads devem permanecer legíveis pelo nginx.'
);
expectStorageNotContains(
    'for DIR in ${WRITABLE_DIRS}; do',
    $entrypoint,
    'Uploads não podem voltar a receber permissões privadas recursivamente.'
);

echo 'DockerStoragePermissionsTest: ' . $assertions . ' assertions passed.' . PHP_EOL;
