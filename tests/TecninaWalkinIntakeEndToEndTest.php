<?php

defined('BASEPATH') or require_once __DIR__ . '/cli/bootstrap.php';

class Tecnina_walkin_test_runner extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library('Tecnina_bot_gateway');
        $this->load->library('Tecnina_readiness_service');
        $this->load->model('Tecnina_receiving_model');
    }

    public function index()
    {
        echo "=== Walk-In Intake End-To-End Test ===" . PHP_EOL;

        // 1. Check Gateway
        $available = $this->tecnina_bot_gateway->available();
        if (! $available) {
            fwrite(STDERR, "[FAIL] Tecnina_bot_gateway is not available." . PHP_EOL);
            exit(1);
        }
        echo "[PASS] Tecnina_bot_gateway is configured and available." . PHP_EOL;

        // 2. Create walk-in intake via Bot API
        $payload = [
            'name' => 'Cliente Teste Presencial',
            'phone' => '11988887777',
            'device_type' => 'Notebook',
            'brand' => 'Lenovo',
            'model' => 'ThinkPad T14',
            'problem_description' => 'Cooler com ruído alto e desligando por superaquecimento',
            'service_mode' => 'DROP_OFF',
            'city' => 'São Paulo',
            'notes' => 'Aparelho deixado no balcão pelo próprio cliente',
            'possible_mapos_client_id' => null,
            'credential_type' => 'PASSWORD',
            'credential_value' => 'pin9876',
            'registration_choice' => 'DEFER_REGISTRATION',
            'operator_id' => 1,
        ];

        $result = $this->tecnina_bot_gateway->request('POST', '/admin/intakes', $payload);
        if (! ($result['ok'] ?? false) || empty($result['data']['id'])) {
            fwrite(STDERR, "[FAIL] Failed to create walk-in intake: " . json_encode($result) . PHP_EOL);
            exit(1);
        }

        $intakeId = $result['data']['id'];
        echo "[PASS] Walk-in intake created successfully. ID: {$intakeId}" . PHP_EOL;

        // 3. Confirm physical receiving in MapOS
        $receivingPayload = [
            'device_condition' => 'Marcas de uso normais',
            'accessories' => 'Carregador/Fonte, Cabo',
            'serial_number' => 'PF-2XYZ99',
            'notes' => 'Fonte original Lenovo 65W',
        ];
        $idempotencyKey = sprintf(
            '%04x%04x-%04x-4%03x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff),
            mt_rand(0x8000, 0xbfff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );

        $recResult = $this->Tecnina_receiving_model->confirmPhysicalReceipt($intakeId, 1, $receivingPayload, $idempotencyKey);
        if (! ($recResult['ok'] ?? false) || ($recResult['data']['state'] ?? '') !== 'RECEIVED') {
            fwrite(STDERR, "[FAIL] Failed to confirm physical receipt: " . json_encode($recResult) . PHP_EOL);
            exit(1);
        }
        echo "[PASS] Physical receipt confirmed. State: RECEIVED, Operator: #1" . PHP_EOL;

        // 4. Verify physical receiving recorded correctly
        $recRow = $this->Tecnina_receiving_model->getReceiving($intakeId);
        if (($recRow['device_condition'] ?? '') !== 'Marcas de uso normais') {
            fwrite(STDERR, "[FAIL] Device condition mismatch." . PHP_EOL);
            exit(1);
        }
        echo "[PASS] Device condition and physical receiving confirmed." . PHP_EOL;

        // 5. Evaluate Readiness Gate
        $readiness = $this->tecnina_readiness_service->evaluateReadiness($intakeId);
        if (($readiness['physical_receiving'] ?? '') !== 'RECEIVED' || ! ($readiness['ready'] ?? false)) {
            fwrite(STDERR, "[FAIL] Physical receiving not satisfied in readiness: " . json_encode($readiness) . PHP_EOL);
            exit(1);
        }
        echo "[PASS] Readiness gate physical_receiving is RECEIVED and ready=true." . PHP_EOL;

        // 6. Test deferred registration link trigger
        $defResult = $this->tecnina_bot_gateway->request('POST', "/admin/intakes/{$intakeId}/deferred-registration", ['purpose' => 'DEFERRED_AT_RECEIVING']);
        echo "[INFO] Deferred registration trigger response: " . json_encode($defResult) . PHP_EOL;
        if (($defResult['reason'] ?? '') === 'gateway_request_failed') {
            fwrite(STDERR, "[FAIL] Deferred registration returned masked gateway_request_failed!" . PHP_EOL);
            exit(1);
        }
        echo "[PASS] Deferred registration was processed cleanly without generic mask." . PHP_EOL;

        echo "SUCCESS: All Walk-In End-to-End steps verified successfully!" . PHP_EOL;
    }
}

if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $runner = new Tecnina_walkin_test_runner();
    $runner->index();
}
