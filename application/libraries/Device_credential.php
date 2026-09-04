<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Valida, normaliza e protege a credencial de desbloqueio de um aparelho.
 *
 * A descriptografia fica deliberadamente fora do model de OS. Dessa forma,
 * consultas usadas pelo portal do cliente nunca transformam o dado cifrado em
 * texto legivel por acidente.
 */
class Device_credential
{
    public const TYPE_UNKNOWN = 'nao_informada';
    public const TYPE_NONE = 'sem_senha';
    public const TYPE_TEXT = 'texto';
    public const TYPE_PATTERN = 'padrao';

    private const MIN_GRID = 3;
    private const MAX_GRID = 6;
    private const MIN_PATTERN_POINTS = 4;
    private const MAX_TEXT_LENGTH = 255;

    /** @var CI_Controller */
    private $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    /**
     * Prepara os campos que podem ser enviados diretamente ao banco.
     *
     * @param array $input Entrada do formulario/API
     * @param bool  $required Exige uma escolha explicita
     * @param bool  $allowKeep Permite manter a credencial ja cadastrada
     */
    public function prepareForStorage(array $input, $required = true, $allowKeep = false)
    {
        $hasCredentialInput = array_key_exists('credencial_sem_senha', $input)
            || array_key_exists('credencial_tipo', $input)
            || array_key_exists('credencial_texto', $input)
            || array_key_exists('credencial_padrao', $input)
            || array_key_exists('credencial_grade', $input)
            || array_key_exists('credencial_acao', $input);

        $action = isset($input['credencial_acao']) ? (string) $input['credencial_acao'] : '';
        if ($allowKeep && ($action === 'manter' || ! $hasCredentialInput)) {
            return $this->success([], true);
        }

        if ($this->isTruthy($input['credencial_sem_senha'] ?? false)) {
            return $this->success([
                'credencial_tipo' => self::TYPE_NONE,
                'credencial_dados' => null,
                'credencial_grade' => null,
                'credencial_atualizada_em' => date('Y-m-d H:i:s'),
            ]);
        }

        $type = isset($input['credencial_tipo']) ? (string) $input['credencial_tipo'] : '';
        if (! in_array($type, [self::TYPE_TEXT, self::TYPE_PATTERN], true)) {
            return $this->failure($required
                ? 'Informe uma senha/PIN, desenhe um padrão ou marque "Não tem senha".'
                : 'Tipo de credencial inválido.');
        }

        if ($type === self::TYPE_TEXT) {
            $text = isset($input['credencial_texto']) ? (string) $input['credencial_texto'] : '';
            if (trim($text) === '') {
                return $this->failure('Informe a senha ou o PIN do aparelho.');
            }

            $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
            if ($length > self::MAX_TEXT_LENGTH) {
                return $this->failure('A senha/PIN deve ter no máximo 255 caracteres.');
            }

            return $this->encryptPayload($type, null, [
                'v' => 1,
                'texto' => $text,
            ]);
        }

        $grid = filter_var($input['credencial_grade'] ?? null, FILTER_VALIDATE_INT);
        if ($grid === false || $grid < self::MIN_GRID || $grid > self::MAX_GRID) {
            return $this->failure('A grade do padrão deve estar entre 3x3 e 6x6.');
        }

        $sequence = $input['credencial_padrao'] ?? null;
        if (is_string($sequence)) {
            $sequence = json_decode($sequence, true);
        }

        $normalized = self::normalizePatternSequence((int) $grid, $sequence);
        if (! $normalized['valid']) {
            return $this->failure($normalized['error']);
        }

        return $this->encryptPayload($type, (int) $grid, [
            'v' => 1,
            'sequencia' => $normalized['sequence'],
        ]);
    }

    /**
     * Retorna uma representacao pronta para a view interna.
     */
    public function decodeRecord($record)
    {
        $type = isset($record->credencial_tipo)
            ? (string) $record->credencial_tipo
            : self::TYPE_UNKNOWN;

        if ($type === self::TYPE_UNKNOWN || $type === '') {
            return $this->success([
                'tipo' => self::TYPE_UNKNOWN,
                'descricao' => 'Não informada',
            ]);
        }

        if ($type === self::TYPE_NONE) {
            return $this->success([
                'tipo' => self::TYPE_NONE,
                'descricao' => 'SEM SENHA',
            ]);
        }

        if (! in_array($type, [self::TYPE_TEXT, self::TYPE_PATTERN], true)
            || empty($record->credencial_dados)) {
            return $this->failure('A credencial armazenada está incompleta ou inválida.');
        }

        $encryptionError = $this->initializeEncryption();
        if ($encryptionError !== null) {
            return $this->failure($encryptionError);
        }

        $plain = $this->CI->encryption->decrypt($record->credencial_dados);
        if ($plain === false) {
            return $this->failure('Não foi possível descriptografar a credencial do aparelho.');
        }

        $payload = json_decode($plain, true);
        if (! is_array($payload) || ($payload['v'] ?? null) !== 1) {
            return $this->failure('O formato da credencial armazenada não é reconhecido.');
        }

        if ($type === self::TYPE_TEXT) {
            if (! isset($payload['texto']) || ! is_string($payload['texto'])) {
                return $this->failure('A senha/PIN armazenada está inválida.');
            }

            return $this->success([
                'tipo' => self::TYPE_TEXT,
                'descricao' => 'Senha/PIN',
                'texto' => $payload['texto'],
            ]);
        }

        $grid = isset($record->credencial_grade) ? (int) $record->credencial_grade : 0;
        $normalized = self::normalizePatternSequence($grid, $payload['sequencia'] ?? null);
        if (! $normalized['valid']) {
            return $this->failure('O padrão armazenado está inválido.');
        }

        return $this->success([
            'tipo' => self::TYPE_PATTERN,
            'descricao' => sprintf(
                'Padrão %dx%d: %s',
                $grid,
                $grid,
                implode(' -> ', $normalized['sequence'])
            ),
            'grade' => $grid,
            'sequencia' => $normalized['sequence'],
        ]);
    }

    public static function hasStoredCredential($record)
    {
        return isset($record->credencial_tipo)
            && in_array((string) $record->credencial_tipo, [
                self::TYPE_NONE,
                self::TYPE_TEXT,
                self::TYPE_PATTERN,
            ], true);
    }

    /**
     * Confere se a instalacao no banco esta pronta sem alterar o schema.
     */
    public function databaseSchemaStatus()
    {
        $requiredColumns = [
            'credencial_tipo',
            'credencial_dados',
            'credencial_grade',
            'credencial_atualizada_em',
        ];
        $missingColumns = [];

        foreach ($requiredColumns as $column) {
            if (! $this->CI->db->field_exists($column, 'os')) {
                $missingColumns[] = $column;
            }
        }

        return [
            'ready' => empty($missingColumns),
            'missing' => $missingColumns,
        ];
    }

    /**
     * Remove todos os metadados da credencial de um objeto devolvido por API.
     */
    public static function withoutCredentialFields($record)
    {
        if (! is_object($record)) {
            return $record;
        }

        $copy = clone $record;
        unset(
            $copy->credencial_tipo,
            $copy->credencial_dados,
            $copy->credencial_grade,
            $copy->credencial_atualizada_em
        );

        return $copy;
    }

    /**
     * Aplica a regra de pontos intermediarios e valida a sequencia row-major.
     */
    public static function normalizePatternSequence($grid, $sequence)
    {
        $grid = (int) $grid;
        if ($grid < self::MIN_GRID || $grid > self::MAX_GRID || ! is_array($sequence)) {
            return ['valid' => false, 'error' => 'Informe um padrão de desbloqueio válido.'];
        }

        $requested = [];
        $requestedSeen = [];
        $maxPoint = $grid * $grid;

        foreach ($sequence as $point) {
            if (is_string($point) && ctype_digit($point)) {
                $point = (int) $point;
            }
            if (! is_int($point) || $point < 1 || $point > $maxPoint) {
                return ['valid' => false, 'error' => 'O padrão contém um ponto fora da grade.'];
            }
            if (isset($requestedSeen[$point])) {
                return ['valid' => false, 'error' => 'O padrão não pode repetir pontos.'];
            }
            $requestedSeen[$point] = true;
            $requested[] = $point;
        }

        $expanded = [];
        $seen = [];
        $previous = null;

        foreach ($requested as $point) {
            if ($previous !== null) {
                $fromRow = intdiv($previous - 1, $grid);
                $fromColumn = ($previous - 1) % $grid;
                $toRow = intdiv($point - 1, $grid);
                $toColumn = ($point - 1) % $grid;
                $rowDelta = $toRow - $fromRow;
                $columnDelta = $toColumn - $fromColumn;
                $steps = self::greatestCommonDivisor(abs($rowDelta), abs($columnDelta));

                if ($steps > 1) {
                    for ($step = 1; $step < $steps; $step++) {
                        $row = $fromRow + (int) (($rowDelta / $steps) * $step);
                        $column = $fromColumn + (int) (($columnDelta / $steps) * $step);
                        $intermediate = ($row * $grid) + $column + 1;
                        if (! isset($seen[$intermediate])) {
                            $seen[$intermediate] = true;
                            $expanded[] = $intermediate;
                        }
                    }
                }
            }

            if (! isset($seen[$point])) {
                $seen[$point] = true;
                $expanded[] = $point;
            }
            $previous = $point;
        }

        if (count($expanded) < self::MIN_PATTERN_POINTS) {
            return [
                'valid' => false,
                'error' => 'O padrão deve conectar pelo menos quatro pontos.',
            ];
        }

        return ['valid' => true, 'sequence' => $expanded];
    }

    private function encryptPayload($type, $grid, array $payload)
    {
        $encryptionError = $this->initializeEncryption();
        if ($encryptionError !== null) {
            return $this->failure($encryptionError);
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ciphertext = $json === false ? false : $this->CI->encryption->encrypt($json);
        if ($ciphertext === false) {
            return $this->failure('Não foi possível proteger a credencial do aparelho.');
        }

        return $this->success([
            'credencial_tipo' => $type,
            'credencial_dados' => $ciphertext,
            'credencial_grade' => $grid,
            'credencial_atualizada_em' => date('Y-m-d H:i:s'),
        ]);
    }

    private function initializeEncryption()
    {
        $key = trim((string) ($_ENV['DEVICE_CREDENTIAL_KEY'] ?? ''));
        if ($key === '') {
            $key = trim((string) $this->CI->config->item('encryption_key'));
        }

        if ($key === '' || $key === 'enter_encryption_key' || strlen($key) < 16) {
            return 'Configure DEVICE_CREDENTIAL_KEY com uma chave aleatória antes de salvar credenciais.';
        }

        $this->CI->load->library('encryption');
        $this->CI->encryption->initialize(['key' => $key]);

        return null;
    }

    private function success(array $data, $keep = false)
    {
        return [
            'valid' => true,
            'error' => null,
            'keep' => (bool) $keep,
            'data' => $data,
        ];
    }

    private function failure($message)
    {
        return [
            'valid' => false,
            'error' => (string) $message,
            'keep' => false,
            'data' => [],
        ];
    }

    private function isTruthy($value)
    {
        return in_array($value, [1, '1', true, 'true', 'on', 'sim'], true);
    }

    private static function greatestCommonDivisor($a, $b)
    {
        $a = (int) $a;
        $b = (int) $b;
        while ($b !== 0) {
            $remainder = $a % $b;
            $a = $b;
            $b = $remainder;
        }

        return $a;
    }
}
