<?php

$target = $argv[1] ?? 'help';

$suites = [
    's03' => 'S03_identity_test_runner.php',
    's04' => 'S04_legal_test_runner.php',
    's06a' => 'S06a_receiving_test_runner.php',
    's06b' => 'S06b_readiness_test_runner.php',
    's07' => 'S07_materialization_test_runner.php',
];

if ($target === 'all') {
    echo "Running all behavioral test runners...\n\n";
    $php = PHP_BINARY ?: 'php';
    foreach (['s04', 's06a', 's06b', 's07'] as $key) {
        echo "=== RUNNING [{$key}] ===\n";
        $file = escapeshellarg(__DIR__ . '/' . $suites[$key]);
        $cmd = "{$php} {$file} index";
        passthru($cmd, $returnCode);
        if ($returnCode !== 0) {
            echo "FAILED [{$key}] with code {$returnCode}\n";
            exit($returnCode);
        }
        echo "\n";
    }
    echo "=== ALL TEST SUITES PASSED SUCCESSFULLY ===\n";
    exit(0);
}

if (isset($suites[strtolower($target)])) {
    $php = PHP_BINARY ?: 'php';
    $file = escapeshellarg(__DIR__ . '/' . $suites[strtolower($target)]);
    $method = escapeshellarg($argv[2] ?? 'index');
    passthru("{$php} {$file} {$method}", $returnCode);
    exit($returnCode);
}

echo "Usage: php tests/cli/run.php [s03|s04|s06a|s06b|s07|all] [method]\n";
echo "Or run runners directly: php tests/cli/S07_materialization_test_runner.php\n";
exit(1);
