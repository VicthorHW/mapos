<?php

define('BASEPATH', __DIR__);

class DeviceCredentialFakeConfig
{
    public function item($name)
    {
        return $name === 'encryption_key' ? '0123456789abcdef0123456789abcdef' : null;
    }
}

class DeviceCredentialFakeLoader
{
    public function library($name)
    {
    }
}

class DeviceCredentialFakeEncryption
{
    public function initialize(array $configuration)
    {
        return $this;
    }

    public function encrypt($value)
    {
        return base64_encode('test:' . $value);
    }

    public function decrypt($value)
    {
        $decoded = base64_decode($value, true);
        return is_string($decoded) && strpos($decoded, 'test:') === 0
            ? substr($decoded, 5)
            : false;
    }
}

class DeviceCredentialFakeDatabase
{
    public $missing = [];

    public function field_exists($column, $table)
    {
        return $table === 'os' && ! in_array($column, $this->missing, true);
    }
}

$deviceCredentialFakeCI = (object) [
    'config' => new DeviceCredentialFakeConfig(),
    'load' => new DeviceCredentialFakeLoader(),
    'encryption' => new DeviceCredentialFakeEncryption(),
    'db' => new DeviceCredentialFakeDatabase(),
];

function &get_instance()
{
    global $deviceCredentialFakeCI;
    return $deviceCredentialFakeCI;
}

require_once dirname(__DIR__) . '/application/libraries/Device_credential.php';

$assertions = 0;

function expectSameCredential($expected, $actual, $message)
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

$classic = Device_credential::normalizePatternSequence(3, [1, 3, 9, 7]);
expectSameCredential(true, $classic['valid'], 'Um padrao 3x3 valido deve ser aceito.');
expectSameCredential(
    [1, 2, 3, 6, 9, 8, 7],
    $classic['sequence'],
    'Pontos intermediarios devem ser incluidos na ordem correta.'
);

$largeGrid = Device_credential::normalizePatternSequence(4, [1, 4, 16, 13]);
expectSameCredential(true, $largeGrid['valid'], 'Um padrao 4x4 valido deve ser aceito.');
expectSameCredential(
    [1, 2, 3, 4, 8, 12, 16, 15, 14, 13],
    $largeGrid['sequence'],
    'A interpolacao deve funcionar em grades maiores.'
);

$duplicate = Device_credential::normalizePatternSequence(3, [1, 2, 5, 2]);
expectSameCredential(false, $duplicate['valid'], 'Pontos repetidos devem ser rejeitados.');

$outside = Device_credential::normalizePatternSequence(3, [1, 2, 5, 10]);
expectSameCredential(false, $outside['valid'], 'Pontos fora da grade devem ser rejeitados.');

$tooShort = Device_credential::normalizePatternSequence(3, [1, 2, 5]);
expectSameCredential(false, $tooShort['valid'], 'Padroes com menos de quatro pontos devem ser rejeitados.');

$invalidGrid = Device_credential::normalizePatternSequence(7, [1, 2, 3, 4]);
expectSameCredential(false, $invalidGrid['valid'], 'Grades maiores que 6x6 devem ser rejeitadas.');

$service = new Device_credential();

$schemaReady = $service->databaseSchemaStatus();
expectSameCredential(true, $schemaReady['ready'], 'O schema completo deve ser reconhecido.');
$deviceCredentialFakeCI->db->missing = ['credencial_grade'];
$schemaMissing = $service->databaseSchemaStatus();
expectSameCredential(false, $schemaMissing['ready'], 'Uma coluna ausente deve bloquear o cadastro.');
expectSameCredential(['credencial_grade'], $schemaMissing['missing'], 'A coluna ausente deve ser identificada.');
$deviceCredentialFakeCI->db->missing = [];

$none = $service->prepareForStorage([
    'credencial_sem_senha' => '1',
    'credencial_tipo' => 'texto',
    'credencial_texto' => 'nao deve ser salvo',
], true, false);
expectSameCredential(true, $none['valid'], 'A opcao sem senha deve ser valida.');
expectSameCredential(Device_credential::TYPE_NONE, $none['data']['credencial_tipo'], 'Sem senha deve prevalecer sobre os demais campos.');
expectSameCredential(null, $none['data']['credencial_dados'], 'Sem senha deve limpar o segredo.');

$text = $service->prepareForStorage([
    'credencial_tipo' => 'texto',
    'credencial_texto' => 'PIN 0123',
], true, false);
expectSameCredential(true, $text['valid'], 'Uma senha textual deve ser aceita.');
expectSameCredential(false, strpos($text['data']['credencial_dados'], 'PIN 0123') !== false, 'O banco nao deve receber texto puro.');

$decodedText = $service->decodeRecord((object) $text['data']);
expectSameCredential(true, $decodedText['valid'], 'Uma senha textual protegida deve ser recuperavel.');
expectSameCredential('PIN 0123', $decodedText['data']['texto'], 'A descriptografia deve preservar o valor exato.');

$pattern = $service->prepareForStorage([
    'credencial_tipo' => 'padrao',
    'credencial_grade' => '3',
    'credencial_padrao' => '[1,3,9,7]',
], true, false);
expectSameCredential(true, $pattern['valid'], 'Um padrao enviado como JSON deve ser aceito.');
$decodedPattern = $service->decodeRecord((object) $pattern['data']);
expectSameCredential(
    [1, 2, 3, 6, 9, 8, 7],
    $decodedPattern['data']['sequencia'],
    'O padrao armazenado deve permanecer canonico.'
);

$keep = $service->prepareForStorage([], true, true);
expectSameCredential(true, $keep['keep'], 'A API pode manter uma credencial existente sem reenvia-la.');

echo "DeviceCredentialTest: {$assertions} assertions passed." . PHP_EOL;
