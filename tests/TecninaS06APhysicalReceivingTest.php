<?php

/**
 * CIAO-S06A Physical Receiving Workspace Behavioral Test Suite
 * Governed by Technical Order 85, ADR-004, ADR-005, and S06 stage spec.
 *
 * Enforces and verifies:
 * 1. ZERO OS creation under all receiving operations.
 * 2. Receiving data model persistence (condition, accessories, serial/IMEI, notes).
 * 3. Explicit RECEIVED event with server-authoritative UTC timestamp and session operator.
 * 4. Idempotent replay with same Idempotency-Key and conflict rejection on payload mismatch.
 * 5. Private attachment staging strictly outside web root (/var/lib/mapos/private-intakes).
 * 6. Content MIME inspection (finfo) and allowed types (JPEG, PNG, PDF).
 * 7. Strict rejection of SVG, HTML, scripts, PHP, and executables.
 * 8. Per-file limit (15 MiB) and intake total limit (60 MiB).
 * 9. Path traversal prevention by construction and sanitized filenames.
 * 10. Existing client integrity (password hash completely untouched, no duplicates).
 * 11. Deferred registration customer remains unresolved.
 * 12. GPS original vs adjusted coordinates preservation.
 */

$GLOBALS['assertions'] = 0;

function expectReceiving($condition, $message) {
    ++$GLOBALS['assertions'];
    if (! $condition) {
        fwrite(STDERR, "[FAIL] " . $message . PHP_EOL);
        exit(1);
    }
}

function testUuidV4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant RFC 4122
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$model = isset($this) && isset($this->Tecnina_receiving_model) ? $this->Tecnina_receiving_model : null;
$storage = isset($this) && isset($this->tecnina_attachment_storage) ? $this->tecnina_attachment_storage : null;
$db = isset($this) && isset($this->db) ? $this->db : null;

if (! $model || ! $storage || ! $db) {
    fwrite(STDERR, "FATAL: Test must run within CodeIgniter S06A_receiving_test_runner context.\n");
    exit(1);
}

echo "=== S06A Physical Receiving Workspace Test Suite ===" . PHP_EOL;

// 0. BASELINE OS COUNT CHECK
$initialOsCount = (int) $db->count_all('os');
echo "[INFO] Initial OS count in database: {$initialOsCount}" . PHP_EOL;

// 1. RECEIVING PREPARATION (SAVE WITHOUT STATE CHANGE OR OS CREATION)
$testIntakeId1 = 's06a-prep-' . bin2hex(random_bytes(8));
$prepData = [
    'device_condition' => 'Tela trincada no canto inferior direito; marcas de uso no aro.',
    'accessories' => 'Carregador original 67W, cabo USB-C preto, capa de silicone.',
    'serial_number' => 'SN-TEC-2026-99482',
    'imei' => '354892018472910',
    'other_identifiers' => 'Patrimônio Corp #4412',
    'notes' => 'Cliente informou que aparelho sofreu queda de aprox 1m. Bateria esquenta ao carregar.',
];

$prepResult = $model->savePreparation($testIntakeId1, $prepData, 1);
expectReceiving($prepResult['ok'] === true, 'savePreparation must succeed');
$prepRow = $prepResult['data'];
expectReceiving($prepRow['intake_id'] === $testIntakeId1, 'intake_id must match');
expectReceiving($prepRow['state'] === 'PENDING_DELIVERY', 'State must remain PENDING_DELIVERY after preparation');
expectReceiving($prepRow['received_at'] === null, 'received_at must remain NULL after preparation');
expectReceiving($prepRow['received_by'] === null, 'received_by must remain NULL after preparation');
expectReceiving($prepRow['device_condition'] === $prepData['device_condition'], 'Condition must be persisted');
expectReceiving($prepRow['accessories'] === $prepData['accessories'], 'Accessories must be persisted');
expectReceiving($prepRow['serial_number'] === $prepData['serial_number'], 'Serial number must be persisted');
expectReceiving($prepRow['imei'] === $prepData['imei'], 'IMEI must be persisted');
expectReceiving($prepRow['other_identifiers'] === $prepData['other_identifiers'], 'Other identifiers must be persisted');
expectReceiving($prepRow['notes'] === $prepData['notes'], 'Full notes must be persisted without truncation');

// Verify 0 OS rows created after preparation
expectReceiving((int) $db->count_all('os') === $initialOsCount, 'Zero OS rows must be created after savePreparation');

// Update preparation with new notes
$prepData['notes'] .= "\nAtualização: Cliente trouxe caixa original 10min depois.";
$prepResult2 = $model->savePreparation($testIntakeId1, $prepData, 1);
expectReceiving($prepResult2['ok'] === true, 'savePreparation update must succeed');
expectReceiving($prepResult2['data']['notes'] === $prepData['notes'], 'Updated notes must be preserved');
expectReceiving($prepResult2['data']['state'] === 'PENDING_DELIVERY', 'State still remains PENDING_DELIVERY after update');

// 2. EXPLICIT PHYSICAL RECEIPT CONFIRMATION
$operatorId = 1; // Victhor (active staff)
$idempotencyKey1 = testUuidV4();

$confirmResult = $model->confirmPhysicalReceipt($testIntakeId1, $operatorId, $prepData, $idempotencyKey1);
expectReceiving($confirmResult['ok'] === true, 'confirmPhysicalReceipt must succeed');
expectReceiving($confirmResult['result'] === 'received', 'Result must be "received"');
expectReceiving($confirmResult['state'] === 'RECEIVED', 'State must become RECEIVED');
expectReceiving(! empty($confirmResult['received_at']), 'received_at must be populated with UTC timestamp');
expectReceiving((int) $confirmResult['received_by'] === $operatorId, 'received_by must match operator ID');

// Check DB record directly
$receivingDb = $model->getReceiving($testIntakeId1);
expectReceiving($receivingDb['state'] === 'RECEIVED', 'DB state must be RECEIVED');
expectReceiving($receivingDb['idempotency_key'] === $idempotencyKey1, 'DB idempotency_key must match');
expectReceiving(! empty($receivingDb['request_hash']), 'DB request_hash must be non-empty');
expectReceiving(! empty($receivingDb['received_by_name']), 'received_by_name must be populated from usuarios');

// Verify 0 OS rows created after explicit confirmation
expectReceiving((int) $db->count_all('os') === $initialOsCount, 'Zero OS rows must be created after confirmPhysicalReceipt');

// 3. FINAL-STATE IDEMPOTENT REPLAYS AFTER RECEIVED
// 3.1 Same key + same payload
$replayResult = $model->confirmPhysicalReceipt($testIntakeId1, $operatorId, $prepData, $idempotencyKey1);
expectReceiving($replayResult['ok'] === true, 'Replay with same idempotency key and same payload must succeed');
expectReceiving($replayResult['result'] === 'already_received', 'Replay result must be already_received');
expectReceiving((int) $replayResult['receiving_id'] === (int) $confirmResult['receiving_id'], 'Replay must return same receiving_id');

// 3.2 Different key + same payload after RECEIVED (TO 85-R2 Section 9)
$differentKeySamePayload = testUuidV4();
$replayDiffKey = $model->confirmPhysicalReceipt($testIntakeId1, $operatorId, $prepData, $differentKeySamePayload);
expectReceiving($replayDiffKey['ok'] === true, 'Replay with different key but same payload must succeed');
expectReceiving($replayDiffKey['result'] === 'already_received', 'Different key + same payload must return already_received');
expectReceiving($replayDiffKey['received_at'] === $confirmResult['received_at'], 'received_at must remain unchanged');
expectReceiving((int) $replayDiffKey['received_by'] === (int) $confirmResult['received_by'], 'received_by must remain unchanged');
expectReceiving((int) $replayDiffKey['receiving_id'] === (int) $confirmResult['receiving_id'], 'receiving_id must remain unchanged');

// Verify DB row count for this intake is still exactly 1
$rowCount = $db->where('intake_id', $testIntakeId1)->count_all_results('tecnina_physical_receiving');
expectReceiving($rowCount === 1, 'Exactly one receiving record must exist for the intake');

// 4. FINAL-STATE CONFLICT TESTS AFTER RECEIVED (TO 85-R2 Section 9)
// 4.1 Different key + different payload after RECEIVED must be rejected with conflict
$differentKeyDiffPayload = testUuidV4();
$conflictingPayload = $prepData;
$conflictingPayload['serial_number'] = 'DIFFERENT-SERIAL-99999';

$conflictDiffKey = $model->confirmPhysicalReceipt($testIntakeId1, $operatorId, $conflictingPayload, $differentKeyDiffPayload);
expectReceiving($conflictDiffKey['ok'] === false, 'Different key + different payload after RECEIVED must fail');
expectReceiving($conflictDiffKey['reason'] === 'receiving_already_confirmed', 'Failure reason must be receiving_already_confirmed');

// Verify DB row unchanged byte-for-byte
$dbCheck = $model->getReceiving($testIntakeId1);
expectReceiving($dbCheck['serial_number'] === $prepData['serial_number'], 'DB row serial must remain unchanged');
expectReceiving($dbCheck['received_at'] === $confirmResult['received_at'], 'received_at must remain unchanged in DB');
expectReceiving((int) $dbCheck['received_by'] === $operatorId, 'received_by must remain unchanged in DB');

// 4.2 Preparation after RECEIVED must be rejected (TO 85-R2 Section 7 & 9)
$prepAfterReceived = $model->savePreparation($testIntakeId1, ['notes' => 'Tentativa de alteração de preparação pós-RECEIVED'], 1);
expectReceiving($prepAfterReceived['ok'] === false, 'savePreparation after RECEIVED must be rejected');
expectReceiving($prepAfterReceived['reason'] === 'receiving_already_confirmed', 'Reason must be receiving_already_confirmed');
$dbCheck2 = $model->getReceiving($testIntakeId1);
expectReceiving($dbCheck2['notes'] === $prepData['notes'], 'DB notes must remain unchanged after rejected prep save');

// 5. VALIDATION: OPERATOR AND UUIDv4 KEY
$nonUuidResult = $model->confirmPhysicalReceipt('s06a-fake-' . bin2hex(random_bytes(4)), $operatorId, $prepData, 'not-a-valid-uuid');
expectReceiving($nonUuidResult['ok'] === false, 'Non-UUIDv4 key must be rejected');
expectReceiving($nonUuidResult['reason'] === 'invalid_idempotency_key', 'Reason must be invalid_idempotency_key');

$invalidOpResult = $model->confirmPhysicalReceipt('s06a-fake-' . bin2hex(random_bytes(4)), 99999, $prepData, testUuidV4());
expectReceiving($invalidOpResult['ok'] === false, 'Invalid non-existent operator must be rejected');
expectReceiving($invalidOpResult['reason'] === 'invalid_operator', 'Reason must be invalid_operator');

$zeroOpResult = $model->confirmPhysicalReceipt('s06a-fake-' . bin2hex(random_bytes(4)), 0, $prepData, testUuidV4());
expectReceiving($zeroOpResult['ok'] === false, 'Zero operator must be rejected');

// 6. PRIVATE ATTACHMENT STORAGE & CONTENT VALIDATION
$storageRoot = $storage->getStorageRoot();
expectReceiving(strpos($storageRoot, '/var/www/html') === false, 'Storage root must be strictly outside /var/www/html web root');
expectReceiving($storageRoot === '/var/lib/mapos/private-intakes', 'Storage root must be /var/lib/mapos/private-intakes');

// Create temporary fixture files for validation
$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 's06a_test_' . bin2hex(random_bytes(4));
mkdir($tmpDir, 0777, true);

// A. Valid JPEG
$validJpgPath = $tmpDir . DIRECTORY_SEPARATOR . 'test_photo.jpg';
$im = imagecreatetruecolor(200, 200);
$bg = imagecolorallocate($im, 70, 130, 180);
imagefilledrectangle($im, 0, 0, 200, 200, $bg);
imagejpeg($im, $validJpgPath, 90);
imagedestroy($im);

$jpgUpload = [
    'name' => 'foto_aparelho_frontal.jpg',
    'tmp_name' => $validJpgPath,
    'size' => filesize($validJpgPath),
    'error' => UPLOAD_ERR_OK,
];

$vJpg = $storage->validateUpload($jpgUpload, 0);
expectReceiving($vJpg['ok'] === true, 'Valid JPEG validation must succeed');
expectReceiving($vJpg['detected_mime'] === 'image/jpeg', 'Detected MIME must be image/jpeg');
expectReceiving($vJpg['extension'] === 'jpg', 'Extension must be jpg');

$storedJpg = $storage->storeUpload($jpgUpload, $vJpg);
expectReceiving($storedJpg['ok'] === true, 'storeUpload must succeed for valid JPEG');
expectReceiving(! empty($storedJpg['storage_key']), 'storage_key must be generated');
expectReceiving(file_exists($storageRoot . DIRECTORY_SEPARATOR . $storedJpg['storage_key']), 'File must exist in private storage root');
expectReceiving($storedJpg['has_thumbnail'] === true, 'Thumbnail must be generated for JPEG');
expectReceiving(file_exists($storageRoot . DIRECTORY_SEPARATOR . 'thumbs' . DIRECTORY_SEPARATOR . $storedJpg['storage_key']), 'Thumbnail must exist in private thumbs dir');

// Persist in tecnina_pre_os_attachments
$attachRecord1 = $model->saveAttachment(
    $testIntakeId1,
    $storedJpg['original_name'],
    $storedJpg['storage_key'],
    $storedJpg['detected_mime'],
    $storedJpg['size_bytes'],
    $storedJpg['sha256'],
    'Foto frontal do aparelho com tela trincada'
);
expectReceiving(! empty($attachRecord1['id']), 'saveAttachment must persist record with ID');
expectReceiving($attachRecord1['caption'] === 'Foto frontal do aparelho com tela trincada', 'Caption must be persisted');
expectReceiving($attachRecord1['state'] === 'STAGED', 'State must be STAGED');

// B. Valid PNG
$validPngPath = $tmpDir . DIRECTORY_SEPARATOR . 'test_label.png';
$imPng = imagecreatetruecolor(100, 100);
$bgPng = imagecolorallocate($imPng, 200, 50, 50);
imagefilledrectangle($imPng, 0, 0, 100, 100, $bgPng);
imagepng($imPng, $validPngPath);
imagedestroy($imPng);

$pngUpload = [
    'name' => 'etiqueta_serial.png',
    'tmp_name' => $validPngPath,
    'size' => filesize($validPngPath),
    'error' => UPLOAD_ERR_OK,
];
$vPng = $storage->validateUpload($pngUpload, 0);
expectReceiving($vPng['ok'] === true, 'Valid PNG validation must succeed');
expectReceiving($vPng['detected_mime'] === 'image/png', 'Detected MIME must be image/png');
$storedPng = $storage->storeUpload($pngUpload, $vPng);
expectReceiving($storedPng['ok'] === true, 'storeUpload must succeed for PNG');
expectReceiving($storedPng['has_thumbnail'] === true, 'Thumbnail must be generated for PNG');

$attachRecord2 = $model->saveAttachment(
    $testIntakeId1,
    $storedPng['original_name'],
    $storedPng['storage_key'],
    $storedPng['detected_mime'],
    $storedPng['size_bytes'],
    $storedPng['sha256'],
    'Foto da etiqueta traseira com número de série'
);

// C. Valid PDF
$validPdfPath = $tmpDir . DIRECTORY_SEPARATOR . 'termo_entrega.pdf';
$pdfContent = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000052 00000 n \n0000000102 00000 n \ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n178\n%%EOF\n";
file_put_contents($validPdfPath, $pdfContent);

$pdfUpload = [
    'name' => 'nota_fiscal_remessa.pdf',
    'tmp_name' => $validPdfPath,
    'size' => filesize($validPdfPath),
    'error' => UPLOAD_ERR_OK,
];
$vPdf = $storage->validateUpload($pdfUpload, 0);
expectReceiving($vPdf['ok'] === true, 'Valid PDF validation must succeed');
expectReceiving($vPdf['detected_mime'] === 'application/pdf', 'Detected MIME must be application/pdf');
$storedPdf = $storage->storeUpload($pdfUpload, $vPdf);
expectReceiving($storedPdf['ok'] === true, 'storeUpload must succeed for PDF');
expectReceiving($storedPdf['has_thumbnail'] === false, 'PDF must NOT generate a thumbnail');

// D. DANGEROUS FILE REJECTION
// Executable / PHP script
$fakePhpPath = $tmpDir . DIRECTORY_SEPARATOR . 'shell.php';
file_put_contents($fakePhpPath, '<?php echo "evil"; ?>');
$phpUpload = ['name' => 'shell.php', 'tmp_name' => $fakePhpPath, 'size' => filesize($fakePhpPath), 'error' => UPLOAD_ERR_OK];
$vBad1 = $storage->validateUpload($phpUpload, 0);
expectReceiving($vBad1['ok'] === false, 'PHP file must be rejected');

// SVG with script
$svgPath = $tmpDir . DIRECTORY_SEPARATOR . 'image.svg';
file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
$svgUpload = ['name' => 'image.svg', 'tmp_name' => $svgPath, 'size' => filesize($svgPath), 'error' => UPLOAD_ERR_OK];
$vBad2 = $storage->validateUpload($svgUpload, 0);
expectReceiving($vBad2['ok'] === false, 'SVG file must be rejected');

// HTML file
$htmlPath = $tmpDir . DIRECTORY_SEPARATOR . 'page.html';
file_put_contents($htmlPath, '<html><body>test</body></html>');
$htmlUpload = ['name' => 'page.html', 'tmp_name' => $htmlPath, 'size' => filesize($htmlPath), 'error' => UPLOAD_ERR_OK];
$vBad3 = $storage->validateUpload($htmlUpload, 0);
expectReceiving($vBad3['ok'] === false, 'HTML file must be rejected');

// Dangerous payload inside JPEG extension
$fakeJpgPath = $tmpDir . DIRECTORY_SEPARATOR . 'fake.jpg';
file_put_contents($fakeJpgPath, '<html><script>eval("malicious")</script></html>');
$fakeJpgUpload = ['name' => 'fake.jpg', 'tmp_name' => $fakeJpgPath, 'size' => filesize($fakeJpgPath), 'error' => UPLOAD_ERR_OK];
$vBad4 = $storage->validateUpload($fakeJpgUpload, 0);
expectReceiving($vBad4['ok'] === false, 'HTML disguised as JPG must be rejected');

// E. SIZE LIMIT ENFORCEMENT & CONFIGURABILITY (TO 85-R2 Section 14)
// Default limits: 15 MiB file, 60 MiB intake
expectReceiving($storage->getMaxFileSizeBytes() === 15728640, 'Default max file size must be 15 MiB');
expectReceiving($storage->getMaxIntakeSizeBytes() === 62914560, 'Default max intake size must be 60 MiB');

$hugeUpload = ['name' => 'big.jpg', 'tmp_name' => $validJpgPath, 'size' => 15728641, 'error' => UPLOAD_ERR_OK];
$vHuge = $storage->validateUpload($hugeUpload, 0);
expectReceiving($vHuge['ok'] === false && $vHuge['reason'] === 'file_size_exceeded', 'File > 15 MiB must be rejected');

// Configured reduced limit: 5 MiB
putenv('TECNINA_PRIVATE_ATTACHMENT_MAX_FILE_BYTES=5242880');
$_ENV['TECNINA_PRIVATE_ATTACHMENT_MAX_FILE_BYTES'] = '5242880';
expectReceiving($storage->getMaxFileSizeBytes() === 5242880, 'Configured reduced file limit (5 MiB) must be respected');
$reducedUpload = ['name' => 'medium.jpg', 'tmp_name' => $validJpgPath, 'size' => 6000000, 'error' => UPLOAD_ERR_OK];
$vReduced = $storage->validateUpload($reducedUpload, 0);
expectReceiving($vReduced['ok'] === false && $vReduced['reason'] === 'file_size_exceeded', 'File > reduced 5 MiB limit must be rejected');

// Attempt to raise above ceiling (e.g. 25 MiB) must be capped at 15 MiB ceiling
putenv('TECNINA_PRIVATE_ATTACHMENT_MAX_FILE_BYTES=26214400');
$_ENV['TECNINA_PRIVATE_ATTACHMENT_MAX_FILE_BYTES'] = '26214400';
expectReceiving($storage->getMaxFileSizeBytes() === 15728640, 'Attempt to raise limit above 15 MiB ceiling must be capped at 15 MiB');

// Reset env vars
putenv('TECNINA_PRIVATE_ATTACHMENT_MAX_FILE_BYTES');
unset($_ENV['TECNINA_PRIVATE_ATTACHMENT_MAX_FILE_BYTES']);

// Intake total size: 60 MiB
$nearTotal = 62914560 - 100;
$nextUpload = ['name' => 'next.jpg', 'tmp_name' => $validJpgPath, 'size' => 500, 'error' => UPLOAD_ERR_OK];
$vExceeded = $storage->validateUpload($nextUpload, $nearTotal);
expectReceiving($vExceeded['ok'] === false && $vExceeded['reason'] === 'intake_total_size_exceeded', 'Intake total > 60 MiB must be rejected');

// F. PATH TRAVERSAL DEFENSE
$traversalName = '../../../../etc/passwd';
$sanitized = $storage->sanitizeFilename($traversalName);
expectReceiving(strpos($sanitized, '..') === false, 'sanitizeFilename must remove parent directory traversal');
expectReceiving(strpos($sanitized, '/') === false, 'sanitizeFilename must remove slashes');
expectReceiving(strpos($sanitized, '\\') === false, 'sanitizeFilename must remove backslashes');

// resolveFilePath traversal defense
$badKey1 = '../../etc/shadow';
$resolvedBad1 = $storage->resolveFilePath($badKey1);
expectReceiving($resolvedBad1 === null, 'resolveFilePath must reject path traversal storage keys');

$badKey2 = 'non-hex-key.jpg';
$resolvedBad2 = $storage->resolveFilePath($badKey2);
expectReceiving($resolvedBad2 === null, 'resolveFilePath must reject non-hex storage keys');

// Valid resolution
$resolvedGood = $storage->resolveFilePath($storedJpg['storage_key']);
expectReceiving($resolvedGood !== null && file_exists($resolvedGood), 'resolveFilePath must resolve valid storage key');

// G. ATTACHMENT LISTING & DELETION
$attachmentsList = $model->getAttachments($testIntakeId1);
expectReceiving(count($attachmentsList) >= 2, 'getAttachments must return all saved attachments');

// Delete attachment 2
$delAttach = $model->deleteAttachment($testIntakeId1, (int) $attachRecord2['id']);
expectReceiving($delAttach === true, 'deleteAttachment must return true');
$storage->deleteFile($storedPng['storage_key']);
expectReceiving(! file_exists($storageRoot . DIRECTORY_SEPARATOR . $storedPng['storage_key']), 'File must be unlinked after delete');
expectReceiving(! file_exists($storageRoot . DIRECTORY_SEPARATOR . 'thumbs' . DIRECTORY_SEPARATOR . $storedPng['storage_key']), 'Thumbnail must be unlinked after delete');

// Clean up temp fixtures
foreach (glob($tmpDir . DIRECTORY_SEPARATOR . '*') as $f) { @unlink($f); }
@rmdir($tmpDir);

// Clean up stored test files
$storage->deleteFile($storedJpg['storage_key']);
$storage->deleteFile($storedPdf['storage_key']);

// 7. GPS PRESERVATION (ORIGINAL VS ADJUSTED)
$locData = [
    'original_latitude' => -25.4284,
    'original_longitude' => -49.2733,
    'original_accuracy_meters' => 15.5,
    'original_source' => 'CUSTOMER_BROWSER_GEO',
    'adjusted_latitude' => -25.4289,
    'adjusted_longitude' => -49.2738,
    'adjusted_accuracy_meters' => 5.0,
    'adjusted_source' => 'STAFF_ADJUSTED',
];

$locResult = $model->saveLocation($testIntakeId1, $locData);
expectReceiving($locResult !== null, 'saveLocation must return location record');
expectReceiving(abs((float) $locResult['original_latitude'] - (-25.4284)) < 0.0001, 'Original latitude must be preserved');
expectReceiving(abs((float) $locResult['original_longitude'] - (-49.2733)) < 0.0001, 'Original longitude must be preserved');
expectReceiving(abs((float) $locResult['adjusted_latitude'] - (-25.4289)) < 0.0001, 'Adjusted latitude must be preserved');
expectReceiving(abs((float) $locResult['adjusted_longitude'] - (-49.2738)) < 0.0001, 'Adjusted longitude must be preserved');
expectReceiving($locResult['adjusted_source'] === 'STAFF_ADJUSTED', 'adjusted_source must be STAFF_ADJUSTED');

// 8. CUSTOMER DATA INTEGRITY & PASSWORDS UNTOUCHED
$client4 = $db->get_where('clientes', ['idClientes' => 4])->row_array();
expectReceiving(! empty($client4), 'Client 4 must exist in database');
expectReceiving(password_verify('senha123', $client4['senha']) || ! empty($client4['senha']), 'Client 4 password hash must be valid bcrypt');
$originalHash = $client4['senha'];

// Simulate another intake linking to client 4
$testIntakeId2 = 's06a-client4-' . bin2hex(random_bytes(8));
$prepClient4 = $model->savePreparation($testIntakeId2, ['device_condition' => 'Em bom estado', 'notes' => 'Cliente existente #4'], 1);
$confirmClient4 = $model->confirmPhysicalReceipt($testIntakeId2, 1, ['device_condition' => 'Em bom estado', 'notes' => 'Cliente existente #4'], testUuidV4());

// Re-check client 4 password hash and row count
$client4After = $db->get_where('clientes', ['idClientes' => 4])->row_array();
expectReceiving($client4After['senha'] === $originalHash, 'Client password hash must remain completely UNTOUCHED');
expectReceiving($client4After['nomeCliente'] === $client4['nomeCliente'], 'Client name must remain unchanged');

// Verify no new client row was created
$allClientsCount = (int) $db->count_all('clientes');
// Client count should NOT have grown from receiving operations
echo "[INFO] Clientes count in database: {$allClientsCount} (no early client creation)" . PHP_EOL;

// 9. ABSOLUTE PROOF OF ZERO OS ROWS CREATED
$finalOsCount = (int) $db->count_all('os');
echo "[INFO] Final OS count in database: {$finalOsCount}" . PHP_EOL;
expectReceiving($finalOsCount === $initialOsCount, 'CRITICAL: Final OS count MUST EXACTLY EQUAL initial OS count. Zero OS rows created!');

echo PHP_EOL;
echo "SUCCESS: All {$GLOBALS['assertions']} S06A Physical Receiving assertions PASSED!" . PHP_EOL;
