<?php
/**
 * Behavioral S03-A test plan executable in a CodeIgniter/MySQL test bootstrap.
 * It intentionally uses the real Tecnina_identity_authority API; no source-text
 * assertions belong here. Set TECNINA_S03_TEST_BOOTSTRAP to a disposable CI DB
 * bootstrap before executing on an authorized runtime.
 */
$bootstrap = getenv('TECNINA_S03_TEST_BOOTSTRAP') ?: __DIR__ . '/support/s03_identity_authority_bootstrap.php';
require $bootstrap;
$authority = get_instance()->tecnina_identity_authority;
$assert = static function ($condition, $message) { if (! $condition) { throw new RuntimeException($message); } };

// Password bytes are passed to the actual authority unchanged.
foreach ([['12345', false], ['123456', true], ['áéíóúà', true], ['😀😀😀😀😀😀', true], [' senha ', true], [str_repeat('a', 73), false]] as [$password, $valid]) {
    $result = $authority->passwordHash($password, $password);
    $assert($result['ok'] === $valid, 'password boundary behavior');
    if ($valid) { $assert(password_verify($password, $result['hash']), 'exact password hash compatibility'); }
}
$assert(! $authority->passwordHash('123456', '123457')['ok'], 'confirmation mismatch');

// Bootstrap fixture creates one no-phone record, one unique legacy record and
// two rows sharing a canonical number. Fixtures are supplied by target bootstrap.
$fixture = tecnina_s03_identity_fixture();
$assert($authority->lookupCanonicalPhone($fixture['none'])['match'] === 'NONE', 'NONE lookup');
$unique = $authority->lookupCanonicalPhone($fixture['unique']);
$assert($unique['match'] === 'UNIQUE' && $unique['phone_state'] === 'LEGACY_EXISTING', 'legacy unique lookup');
$assert(tecnina_s03_identity_row($unique['client_id'])->phone_verified_at === null, 'no fabricated verification');
$ambiguous = $authority->lookupCanonicalPhone($fixture['ambiguous']);
$assert($ambiguous['match'] === 'AMBIGUOUS' && ! isset($ambiguous['client_id']), 'ambiguous does not select');
$assert(tecnina_s03_conflict_count($fixture['ambiguous']) === 2, 'ambiguity is relational');

$issued = $authority->issueEmailVerification($fixture['email_issue']);
$assert($issued['ok'] && ! isset($issued['_delivery_code']), 'challenge is customer-safe');
$assert(! tecnina_s03_persisted_contains_plaintext('tecnina_email_verifications', $fixture['email_code']), 'code digest only');
$replay = $authority->issueEmailVerification($fixture['email_issue']);
$assert($replay['replayed'] && $replay['challenge_id'] === $issued['challenge_id'], 'issue idempotency');
$assert($authority->issueEmailVerification(array_merge($fixture['email_issue'], ['email_candidate' => 'different@example.test']))['reason'] === 'idempotency_conflict', 'conflicting issue key');
$verified = $authority->verifyEmail(['challenge_id'=>$issued['challenge_id'], 'code'=>$fixture['email_code'], 'idempotency_key'=>'verify-1']);
$assert($verified['ok'] && tecnina_s03_client_email($fixture['client_id']) === $fixture['email_issue']['email_candidate'], 'atomic email promotion');
$assert($authority->verifyEmail(['challenge_id'=>$issued['challenge_id'], 'code'=>$fixture['email_code'], 'idempotency_key'=>'verify-1'])['replayed'], 'completed verify replay');

$reset = $authority->issuePasswordReset($fixture['reset_issue']);
$assert($reset['ok'] && ! tecnina_s03_persisted_contains_plaintext('tecnina_password_resets', tecnina_s03_token_from_url($reset['reset_url'])), 'reset digest only');
$before = tecnina_s03_credential_version($fixture['client_id']);
$assert($authority->consumePasswordReset(tecnina_s03_token_from_url($reset['reset_url']), ' reset ', ' reset ')['ok'], 'valid reset consumption');
$assert(tecnina_s03_credential_version($fixture['client_id']) === $before + 1, 'version increments once');
$assert(! $authority->consumePasswordReset(tecnina_s03_token_from_url($reset['reset_url']), 'changed', 'changed')['ok'], 'single use/replay rejection');
echo "TecninaS03IdentityAuthorityBehaviorTest: PASS\n";
