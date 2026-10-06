<?php

/**
 * TecninaNotificationsTest
 *
 * Governed by Technical Order 85, S10-INTEGRATION-SECURITY-REGRESSION.md,
 * and ADJ-020 (Ajustes §10.4):
 * "E-mail só recebe notificação quando real e confirmado.
 *  E-mail técnico ou não confirmado nunca recebe disparos."
 */

$assertions = 0;
$root = dirname(__DIR__);

function expectNotification($condition, $message)
{
    global $assertions;
    ++$assertions;

    if (! $condition) {
        fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
        exit(1);
    }
}

echo '=== S10: Tecnina Notifications & ADJ-020 Behavioral Test ===' . PHP_EOL;

// 1. Source code contract inspections
$notificationsLib = file_get_contents($root . '/application/libraries/Tecnina_notifications.php');
$welcomeLib = file_get_contents($root . '/application/libraries/Customer_welcome_email.php');

expectNotification(
    strpos($notificationsLib, 'isEmailNotificationAllowed') !== false,
    'Tecnina_notifications deve implementar isEmailNotificationAllowed'
);
expectNotification(
    strpos($notificationsLib, "'NONE', 'PENDING'") !== false,
    'Tecnina_notifications deve suprimir estados NONE e PENDING conforme ADJ-020'
);
expectNotification(
    strpos($notificationsLib, "'VERIFIED', 'LEGACY_EXISTING'") !== false,
    'Tecnina_notifications deve permitir estados VERIFIED e LEGACY_EXISTING'
);
expectNotification(
    strpos($notificationsLib, 'filterRecipients') !== false,
    'Tecnina_notifications deve fornecer utilitário filterRecipients'
);
expectNotification(
    strpos($welcomeLib, 'tecnina_notifications->isEmailNotificationAllowed') !== false,
    'Customer_welcome_email deve chamar isEmailNotificationAllowed antes de enfileirar'
);

// 2. Behavioral verification of library logic with mock CodeIgniter environment if standalone
class MockDbResult
{
    private $row;
    public function __construct($row) { $this->row = $row; }
    public function row() { return $this->row; }
}

class MockDb
{
    public $email_state = 'NONE';
    public function select($fields) { return $this; }
    public function where($field, $val) { return $this; }
    public function get($table)
    {
        if ($this->email_state === null) {
            return new MockDbResult(null);
        }
        $obj = new stdClass();
        $obj->email_state = $this->email_state;
        return new MockDbResult($obj);
    }
}

class MockCI
{
    public $db;
    public function __construct()
    {
        $this->db = new MockDb();
    }
}

// Emulate CI instance for testing library methods directly
if (! function_exists('get_instance')) {
    $GLOBALS['__mock_ci'] = new MockCI();
    function &get_instance() {
        return $GLOBALS['__mock_ci'];
    }
}

if (! function_exists('log_message')) {
    function log_message($level, $msg) {}
}

if (! defined('BASEPATH')) {
    define('BASEPATH', true);
}

require_once $root . '/application/libraries/Tecnina_notifications.php';
$notifier = new Tecnina_notifications();

// Case 1: Invalid email syntax -> FALSE
expectNotification(
    $notifier->isEmailNotificationAllowed(1, 'not-an-email') === false,
    'E-mail com sintaxe inválida deve ser rejeitado'
);

// Case 2: PENDING state -> FALSE (ADJ-020)
$GLOBALS['__mock_ci']->db->email_state = 'PENDING';
expectNotification(
    $notifier->isEmailNotificationAllowed(42, 'candidato@example.com') === false,
    'E-mail com estado PENDING deve ser BLOQUEADO de receber notificações (ADJ-020)'
);

// Case 3: NONE state -> FALSE (ADJ-020)
$GLOBALS['__mock_ci']->db->email_state = 'NONE';
expectNotification(
    $notifier->isEmailNotificationAllowed(42, 'vazio@example.com') === false,
    'E-mail com estado NONE deve ser BLOQUEADO de receber notificações (ADJ-020)'
);

// Case 4: VERIFIED state -> TRUE
$GLOBALS['__mock_ci']->db->email_state = 'VERIFIED';
expectNotification(
    $notifier->isEmailNotificationAllowed(42, 'confirmado@example.com') === true,
    'E-mail com estado VERIFIED deve ser AUTORIZADO a receber notificações'
);

// Case 5: LEGACY_EXISTING state -> TRUE
$GLOBALS['__mock_ci']->db->email_state = 'LEGACY_EXISTING';
expectNotification(
    $notifier->isEmailNotificationAllowed(42, 'legado@example.com') === true,
    'E-mail com estado LEGACY_EXISTING deve ser AUTORIZADO a receber notificações'
);

// Case 6: Pre-S03 client without identity record -> TRUE (if email valid)
$GLOBALS['__mock_ci']->db->email_state = null;
expectNotification(
    $notifier->isEmailNotificationAllowed(999, 'legado_sem_identity@example.com') === true,
    'Cliente sem linha em tecnina_client_identity com e-mail válido é compatível'
);

// Case 7: filterRecipients
$GLOBALS['__mock_ci']->db->email_state = 'VERIFIED';
$filtered = $notifier->filterRecipients(['a@example.com', 'b@example.com'], 42);
expectNotification(
    count($filtered) === 2,
    'filterRecipients deve retornar destinatários autorizados'
);

$GLOBALS['__mock_ci']->db->email_state = 'PENDING';
$filteredPending = $notifier->filterRecipients(['c@example.com'], 42);
expectNotification(
    count($filteredPending) === 0,
    'filterRecipients deve descartar destinatários PENDING (ADJ-020)'
);

echo "TecninaNotificationsTest: {$assertions} assertions passed successfully." . PHP_EOL;
