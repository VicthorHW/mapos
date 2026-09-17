<?php

$assertions = 0;
$root = dirname(__DIR__);
function expectS03($condition, $message) { global $assertions; ++$assertions; if (! $condition) { fwrite(STDERR, $message . PHP_EOL); exit(1); } }
$migration = file_get_contents($root . '/application/database/migrations/20260916120000_add_s03_identity_credential_authority.php');
$authority = file_get_contents($root . '/application/libraries/Tecnina_identity_authority.php');
$controller = file_get_contents($root . '/application/controllers/api/bot/Identity.php');
$mine = file_get_contents($root . '/application/controllers/Mine.php');
foreach (['tecnina_client_identity', 'tecnina_client_profile', 'tecnina_email_verifications', 'tecnina_password_resets', 'canonical_phone', 'credential_version', 'LEGACY_EXISTING'] as $required) { expectS03(strpos($migration, $required) !== false, 'S03 persistence missing: ' . $required); }
expectS03(strpos($migration, 'DROP TABLE') === false && strpos($migration, 'TRUNCATE') === false, 'S03 migration must not have an automatic destructive down path.');
expectS03(strpos($authority, 'mb_strlen($password') !== false && preg_match('/strlen\(\$password\)\s*>\s*72/', $authority) === 1, 'Password policy boundary missing.');
expectS03(strpos($authority, 'trim($password)') === false, 'Passwords must not be normalized.');
expectS03(strpos($authority, "'AMBIGUOUS'") !== false && strpos($authority, "'UNIQUE'") !== false, 'Ambiguity contract missing.');
expectS03(strpos($controller, 'Tecnina_bot_auth') !== false && strpos($controller, "'algorithm_runtime'") !== false, 'Private opaque-hash contract missing.');
$routes = file_get_contents($root . '/application/config/routes.php');
expectS03(strpos($controller, 'canonical_phone') !== false, 'S01 canonical phone contract missing.');
foreach (['email-verification/issue', 'email-verification/verify', 'password-reset/issue', 'cliente/password-reset'] as $contract) { expectS03(strpos($routes, $contract) !== false, 'S01 route missing: ' . $contract); }
foreach (['SUPERSEDED', 'verified_at', 'resetToken', 'credential_version+1', 'idempotency_conflict'] as $lifecycle) { expectS03(strpos($authority, $lifecycle) !== false, 'S03 lifecycle behavior missing: ' . $lifecycle); }
foreach (["'credential_hash', 'service', 60, 60", "'client_lookup', 'service', 300, 60", 'HTTP_TOO_MANY_REQUESTS', 'HTTP_SERVICE_UNAVAILABLE'] as $limitContract) { expectS03(strpos($controller, $limitContract) !== false, 'S03 endpoint limit/status behavior missing: ' . $limitContract); }
expectS03(strpos($authority, "count(\$conflicts) > 0") !== false, 'Relational conflict evidence must take precedence over UNIQUE.');
expectS03(strpos($mine, 'function login') !== false, 'Legacy Mine flow unexpectedly absent.');
echo 'TecninaS03IdentityAuthorityTest: ' . $assertions . ' assertions passed.' . PHP_EOL;
