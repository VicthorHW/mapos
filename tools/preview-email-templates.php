<?php

/**
 * Gera prévias locais dos e-mails transacionais sem inicializar o MapOS,
 * consultar o banco ou enviar mensagens.
 *
 * Uso: php tools/preview-email-templates.php [diretorio-de-saida]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$outputDirectory = $argv[1] ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mapos-email-preview');

function base_url($uri = '')
{
    return 'https://gestao.exemplo.test/' . ltrim((string) $uri, '/');
}

function cliente_url($uri = '')
{
    return 'https://cliente.exemplo.test/index.php/' . ltrim((string) $uri, '/');
}

function cliente_asset_url($url)
{
    return (string) $url;
}

function printSafeHtml($value)
{
    return nl2br(htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'));
}

final class EmailPreviewLoader
{
    private $renderer;

    public function __construct(EmailPreviewRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function view($view, array $data = [], $return = false)
    {
        $html = $this->renderer->render($view, $data);

        if ($return) {
            return $html;
        }

        echo $html;

        return null;
    }
}

final class EmailPreviewRenderer
{
    public $load;
    private $root;

    public function __construct($root)
    {
        $this->root = $root;
        $this->load = new EmailPreviewLoader($this);
    }

    public function render($view, array $data)
    {
        $file = $this->root . '/application/views/' . trim($view, '/') . '.php';
        if (! is_file($file)) {
            throw new RuntimeException('View não encontrada: ' . $view);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $file;

        return ob_get_clean();
    }
}

function object(array $properties)
{
    return (object) $properties;
}

function assertPreview($condition, $message)
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    $logoPath = $root . '/assets/tecnina/img/email/tecnina-logo-email.png';
    assertPreview(is_file($logoPath), 'Logo de e-mail não encontrada: ' . $logoPath);

    if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0700, true) && ! is_dir($outputDirectory)) {
        throw new RuntimeException('Não foi possível criar o diretório de prévias: ' . $outputDirectory);
    }

    $renderer = new EmailPreviewRenderer($root);
    $emitente = object([
        'nome' => 'TecNina Assistência Técnica',
        'telefone' => '(41) 99999-0000',
        'rua' => 'Rua Exemplo',
        'numero' => '123',
        'bairro' => 'Centro',
        'cidade' => 'Antonina',
        'uf' => 'PR',
        'cep' => '83370-000',
    ]);
    $cliente = object([
        'nomeCliente' => 'Ana Cliente',
        'documento' => '123.456.789-00',
        'rua' => 'Rua da Cliente',
        'numero' => '45',
        'complemento' => 'Apto. 2',
        'bairro' => 'Batel',
        'cidade' => 'Curitiba',
        'estado' => 'PR',
        'cep' => '80000-000',
        'email' => 'ana@example.test',
        'celular' => '(41) 98888-0000',
    ]);
    $result = object([
        'idOs' => 42,
        'nomeCliente' => $cliente->nomeCliente,
        'status' => 'Em andamento',
        'dataInicial' => '2026-09-04',
        'dataFinal' => '2026-09-11',
        'garantia' => '90 dias',
        'nome' => 'Técnico TecNina',
        'descricaoProduto' => 'Smartphone modelo de demonstração',
        'defeito' => 'Não liga após atualização.',
        'observacoes' => 'Cliente autorizou avaliação.',
        'laudoTecnico' => 'Bateria em diagnóstico.',
        'desconto' => 0,
        'valor_desconto' => 0,
    ]);
    $cobranca = object([
        'idCobranca' => 17,
        'nomeCliente' => $cliente->nomeCliente,
        'expire_at' => '2026-09-20',
        'barcode' => '00190.00009 01234.567890 12345.678901 2 12340000010000',
        'total' => 10000,
        'pdf' => 'https://pagamentos.exemplo.test/cobranca/17.pdf',
        'link' => 'https://pagamentos.exemplo.test/cobranca/17',
    ]);

    $templates = [
        'recuperacao-de-senha' => [
            'view' => 'conecte/emails/clientenovasenha',
            'data' => [
                'cliente' => $cliente,
                'emitente' => $emitente,
                'resets_de_senha' => object(['token' => 'token-de-teste-sem-validade-real']),
            ],
        ],
        'boas-vindas' => [
            'view' => 'os/emails/clientenovo',
            'data' => ['cliente' => $cliente, 'emitente' => $emitente],
        ],
        'novo-cliente-interno' => [
            'view' => 'os/emails/clientenovonotifica',
            'data' => ['cliente' => $cliente, 'emitente' => $emitente, 'usuario' => object(['nome' => 'Administradora'])],
        ],
        'atualizacao-reparo' => [
            'view' => 'os/emails/os',
            'data' => [
                'result' => $result,
                'emitente' => $emitente,
                'produtos' => [object(['descricao' => 'Peça de teste', 'quantidade' => 1, 'preco' => 25, 'precoVenda' => 25, 'subTotal' => 25])],
                'servicos' => [object(['nome' => 'Diagnóstico', 'quantidade' => 1, 'preco' => 75, 'precoVenda' => 75])],
            ],
        ],
        'cobranca' => [
            'view' => 'cobrancas/emails/cobranca',
            'data' => ['cobranca' => $cobranca, 'emitente' => $emitente],
        ],
    ];

    foreach ($templates as $name => $template) {
        $html = $renderer->render($template['view'], $template['data']);

        assertPreview(stripos($html, '<!doctype html>') !== false, $name . ': documento HTML não foi renderizado.');
        assertPreview(strpos($html, 'https://gestao.exemplo.test/assets/tecnina/img/email/tecnina-logo-email.png') !== false, $name . ': logo absoluta não foi encontrada.');
        assertPreview(strpos($html, 'assets/tecnina/img/email/tecnina-logo-email.png') !== false, $name . ': caminho da logo não foi encontrado.');
        assertPreview(strpos($html, 'https://wa.me/5541999990000') !== false, $name . ': link do WhatsApp do emitente não foi encontrado.');
        assertPreview(strpos($html, '(41) 99999-0000') !== false, $name . ': telefone do emitente não foi encontrado no rodapé.');

        preg_match_all('/\\s(?:src|href)="([^"]+)"/i', $html, $urls);
        foreach ($urls[1] as $url) {
            $absoluteUrl = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
            assertPreview(
                filter_var($absoluteUrl, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $absoluteUrl),
                $name . ': URL não absoluta no HTML: ' . $absoluteUrl
            );
        }

        $filename = $outputDirectory . DIRECTORY_SEPARATOR . $name . '.html';
        if (file_put_contents($filename, $html) === false) {
            throw new RuntimeException('Não foi possível gravar a prévia: ' . $filename);
        }
    }

    $fallbackFooter = $renderer->render('emails/layout', [
        'title' => 'Teste de contato',
        'content' => '<p>Prévia do contato padrão.</p>',
    ]);
    assertPreview(strpos($fallbackFooter, 'https://wa.me/5541974035094') !== false, 'Fallback do WhatsApp não foi aplicado.');
    assertPreview(strpos($fallbackFooter, '41 97403-5094') !== false, 'Telefone de fallback não foi aplicado.');

    echo 'Prévia gerada em: ' . realpath($outputDirectory) . PHP_EOL;
    echo count($templates) . ' templates renderizados sem envio de e-mail.' . PHP_EOL;
} finally {
    restore_error_handler();
}
