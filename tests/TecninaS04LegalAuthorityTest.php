<?php

$assertions = 0;
$root = dirname(__DIR__);

function expectS04($condition, $message) {
    global $assertions;
    ++$assertions;
    if (! $condition) {
        fwrite(STDERR, 'FAILED: ' . $message . PHP_EOL);
        exit(1);
    }
}

// 1. Verify canonical files and hashes
$termsFile = $root . '/application/database/legal/terms-of-use-v1.0.md';
$privacyFile = $root . '/application/database/legal/privacy-policy-v1.0.md';

expectS04(file_exists($termsFile), 'Terms canonical file missing');
expectS04(file_exists($privacyFile), 'Privacy canonical file missing');

$termsBytes = file_get_contents($termsFile);
$privacyBytes = file_get_contents($privacyFile);

expectS04(hash('sha256', $termsBytes) === 'de0936f1e54fc9f005202752e66c1ec659035a57fa2dd7e859acd28b458ebd82', 'Terms SHA-256 hash mismatch');
expectS04(hash('sha256', $privacyBytes) === '4b15348c1a7b30ddb102e44f18b8c72a5b21be9072c3e9a09c3d9c1ab0b30079', 'Privacy SHA-256 hash mismatch');
expectS04(strpos($termsBytes, "\r") === false, 'Terms file must use LF line breaks only');
expectS04(strpos($privacyBytes, "\r") === false, 'Privacy file must use LF line breaks only');

// 2. Verify migration
$migrationFile = $root . '/application/database/migrations/20260928150000_add_s04_legal_catalog_and_ledger.php';
expectS04(file_exists($migrationFile), 'S04 migration file missing');
$migration = file_get_contents($migrationFile);

expectS04(strpos($migration, 'tecnina_legal_document_versions') !== false, 'Migration missing tecnina_legal_document_versions table');
expectS04(strpos($migration, 'tecnina_legal_acceptances') !== false, 'Migration missing tecnina_legal_acceptances table');
expectS04(strpos($migration, 'trg_tecnina_legal_acceptances_no_update') !== false, 'Migration missing BEFORE UPDATE trigger');
expectS04(strpos($migration, 'trg_tecnina_legal_acceptances_no_delete') !== false, 'Migration missing BEFORE DELETE trigger');
expectS04(strpos($migration, 'tecnina_legal_acceptances is append-only') !== false, 'Trigger missing append-only signal message');
expectS04(strpos($migration, 'de0936f1e54fc9f005202752e66c1ec659035a57fa2dd7e859acd28b458ebd82') !== false, 'Migration missing Terms hash seed check');
expectS04(strpos($migration, '4b15348c1a7b30ddb102e44f18b8c72a5b21be9072c3e9a09c3d9c1ab0b30079') !== false, 'Migration missing Privacy hash seed check');
expectS04(strpos($migration, 'DROP TABLE') === false && strpos($migration, 'TRUNCATE') === false, 'Migration must not have destructive down path');

// 3. Verify Model
$modelFile = $root . '/application/models/Tecnina_legal_model.php';
expectS04(file_exists($modelFile), 'Tecnina_legal_model.php missing');
$model = file_get_contents($modelFile);

expectS04(strpos($model, 'getCurrentVersions') !== false, 'Model missing getCurrentVersions');
expectS04(strpos($model, 'recordManifestations') !== false, 'Model missing recordManifestations');
expectS04(strpos($model, 'legal_catalog_inconsistent') !== false, 'Model missing legal_catalog_inconsistent handling');
expectS04(strpos($model, 'legal_version_changed') !== false, 'Model missing legal_version_changed check');
expectS04(strpos($model, 'idempotency_conflict') !== false, 'Model missing idempotency_conflict check');
expectS04(strpos($model, 'invalid_legal_action') !== false, 'Model missing invalid_legal_action check');
expectS04(strpos($model, 'ACCEPTED') !== false && strpos($model, 'ACKNOWLEDGED') !== false, 'Model missing action enums');
expectS04(strpos($model, 'document_type_snapshot') !== false, 'Model missing document_type_snapshot');
expectS04(strpos($model, 'document_version_snapshot') !== false, 'Model missing document_version_snapshot');
expectS04(strpos($model, 'document_hash_snapshot') !== false, 'Model missing document_hash_snapshot');

// 4. Verify Controller
$controllerFile = $root . '/application/controllers/api/bot/Legal.php';
expectS04(file_exists($controllerFile), 'Legal controller missing');
$controller = file_get_contents($controllerFile);

expectS04(strpos($controller, 'Tecnina_bot_auth') !== false, 'Controller missing Tecnina_bot_auth');
expectS04(strpos($controller, 'current_get') !== false, 'Controller missing current_get');
expectS04(strpos($controller, 'manifestations_post') !== false, 'Controller missing manifestations_post');
expectS04(strpos($controller, 'HTTP_CONFLICT') !== false, 'Controller missing HTTP_CONFLICT (409) status');
expectS04(strpos($controller, 'HTTP_CREATED') !== false, 'Controller missing HTTP_CREATED (201) status');
expectS04(strpos($controller, 'legal_version_changed') !== false, 'Controller missing legal_version_changed response');

// 5. Verify Routes
$routesFile = $root . '/application/config/routes.php';
$routes = file_get_contents($routesFile);

expectS04(strpos($routes, "api/bot/legal/current") !== false, 'Route api/bot/legal/current missing');
expectS04(strpos($routes, "api/bot/legal/manifestations") !== false, 'Route api/bot/legal/manifestations missing');

echo "TecninaS04LegalAuthorityTest: {$assertions} assertions passed." . PHP_EOL;
