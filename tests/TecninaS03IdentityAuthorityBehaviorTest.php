<?php
/**
 * Behavioral S03-A test plan executable in a CodeIgniter/MySQL test bootstrap.
 * It intentionally uses the real Tecnina_identity_authority API; no source-text
 * assertions belong here. Set TECNINA_S03_TEST_BOOTSTRAP to a disposable CI DB
 * bootstrap before executing on an authorized runtime.
 */
$bootstrap = __DIR__ . '/support/s03_identity_authority_bootstrap.php';
require_once $bootstrap;
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
$assert(tecnina_s03_identity_row($unique['client_id'])->phone_confirmed_at === null, 'no fabricated confirmation');
tecnina_s03_add_conflict($fixture['unique'],$unique['client_id']);
$assert($authority->lookupCanonicalPhone($fixture['unique'])['match']==='AMBIGUOUS','conflict evidence overrides identity row');
tecnina_s03_remove_conflict($fixture['unique'],$unique['client_id']);
$ambiguous = $authority->lookupCanonicalPhone($fixture['ambiguous']);
$assert($ambiguous['match'] === 'AMBIGUOUS' && ! isset($ambiguous['client_id']), 'ambiguous does not select');
$assert(tecnina_s03_conflict_count($fixture['ambiguous']) === 2, 'ambiguity is relational');
$multipleId = tecnina_s03_client_id('S03 Multiple');
$assert(tecnina_s03_identity_row($multipleId)->canonical_phone === null, 'multiple candidates do not choose by iteration order');
$assert(tecnina_s03_conflict_count('5541998888888') === 1 && tecnina_s03_conflict_count('5541997777777') === 1, 'multiple candidates remain relational');

$issued = $authority->issueEmailVerification($fixture['email_issue']);
$assert($issued['ok'] && ! isset($issued['_delivery_code']), 'challenge is customer-safe');
$identityPending=tecnina_s03_identity_row($fixture['client_id']);
$assert($identityPending->email_state==='PENDING' && $identityPending->email_candidate===$fixture['email_issue']['email_candidate'],'client e-mail candidate becomes PENDING');
$assert(tecnina_s03_client_email($fixture['client_id'])==='old@example.test','trusted e-mail unchanged before verification');
$code = tecnina_s03_delivery_code();
$assert(is_string($code) && preg_match('/^\d{6}$/', $code) === 1, 'test delivery seam captures generated code');
$assert(! tecnina_s03_persisted_contains_plaintext('tecnina_email_verifications', $code), 'code digest only');
$replay = $authority->issueEmailVerification($fixture['email_issue']);
$assert($replay['replayed'] && $replay['challenge_id'] === $issued['challenge_id'], 'issue idempotency');
$assert($authority->issueEmailVerification(array_merge($fixture['email_issue'], ['email_candidate' => 'different@example.test']))['reason'] === 'idempotency_conflict', 'conflicting issue key');
$verified = $authority->verifyEmail(['challenge_id'=>$issued['challenge_id'], 'code'=>$code, 'idempotency_key'=>'verify-1']);
$assert($verified['ok'] && tecnina_s03_client_email($fixture['client_id']) === $fixture['email_issue']['email_candidate'], 'atomic email promotion');
$assert($authority->verifyEmail(['challenge_id'=>$issued['challenge_id'], 'code'=>$code, 'idempotency_key'=>'verify-1'])['replayed'], 'completed verify replay');
$conflictingVerify = $authority->verifyEmail(['challenge_id'=>$issued['challenge_id'], 'code'=>$code, 'idempotency_key'=>'verify-other']);
$assert(!$conflictingVerify['ok'] && $conflictingVerify['reason']==='idempotency_conflict', 'verify idempotency conflict');

// Cross-challenge idempotency key reuse must be controlled conflict, never 503 or duplicate promotion
$issueB = $authority->issueEmailVerification(['client_id'=>$fixture['client_id'],'email_candidate'=>'candidate-b@example.test','purpose'=>'PROFILE_CHANGE','idempotency_key'=>'s03-issue-b']);
$assert($issueB['ok'], 'issue challenge B');
$codeB = tecnina_s03_delivery_code();
$crossReuse = $authority->verifyEmail(['challenge_id'=>$issueB['challenge_id'], 'code'=>$codeB, 'idempotency_key'=>'verify-1']);
$assert(!$crossReuse['ok'] && $crossReuse['reason']==='idempotency_conflict', 'cross-challenge verify key reuse returns idempotency_conflict');
$assert(tecnina_s03_verification_state($issueB['challenge_id']) !== 'VERIFIED', 'challenge B remains unverified');
$assert(tecnina_s03_client_email($fixture['client_id']) === $fixture['email_issue']['email_candidate'], 'client email not promoted to B');
$assert(tecnina_s03_identity_row($fixture['client_id'])->email_candidate === $fixture['email_issue']['email_candidate'], 'identity candidate not mutated by B');

$GLOBALS['tecnina_s03_delivery_spy'] = static function () { return false; };
$deliveryFailure = $authority->issueEmailVerification(['client_id'=>$fixture['client_id'],'email_candidate'=>'delivery@example.test','purpose'=>'PROFILE_CHANGE','idempotency_key'=>'delivery-fail']);
$assert(!$deliveryFailure['ok'] && $deliveryFailure['reason']==='delivery_unavailable', 'delivery failure controlled');
$assert(tecnina_s03_identity_row($fixture['client_id'])->email_state==='VERIFIED' && tecnina_s03_client_email($fixture['client_id'])===$fixture['email_issue']['email_candidate'],'delivery failure preserves trusted e-mail state');
$GLOBALS['tecnina_s03_delivery_spy'] = static function ($email,$sentCode) { $GLOBALS['tecnina_s03_last_code']=$sentCode; return true; };

$expiredIssue=$authority->issueEmailVerification(['intake_id'=>'00000000-0000-4000-8000-000000000002','email_candidate'=>'expired@example.test','purpose'=>'ACCOUNT_CREATION','idempotency_key'=>'expired-issue']);$expiredCode=tecnina_s03_delivery_code();tecnina_s03_expire_verification($expiredIssue['challenge_id']);
$assert($authority->verifyEmail(['challenge_id'=>$expiredIssue['challenge_id'],'code'=>$expiredCode,'idempotency_key'=>'expired-verify'])['reason']==='invalid_or_expired_code','expired challenge rejected');
$failureIssue=$authority->issueEmailVerification(['intake_id'=>'00000000-0000-4000-8000-000000000003','email_candidate'=>'failures@example.test','purpose'=>'ACCOUNT_CREATION','idempotency_key'=>'failures-issue']);$validCode=tecnina_s03_delivery_code();$badCode=$validCode==='000000'?'000001':'000000';for($i=0;$i<5;$i++){$authority->verifyEmail(['challenge_id'=>$failureIssue['challenge_id'],'code'=>$badCode,'idempotency_key'=>'bad-'.$i]);}
$assert(tecnina_s03_verification_attempts($failureIssue['challenge_id'])===5,'exactly five wrong-code attempts are recorded');
$assert($authority->verifyEmail(['challenge_id'=>$failureIssue['challenge_id'],'code'=>$validCode,'idempotency_key'=>'after-five'])['reason']==='invalid_or_expired_code','five-failure boundary');
$malformedIssue=$authority->issueEmailVerification(['intake_id'=>'00000000-0000-4000-8000-000000000004','email_candidate'=>'malformed@example.test','purpose'=>'ACCOUNT_CREATION','idempotency_key'=>'malformed-issue']);$attemptsBefore=tecnina_s03_verification_attempts($malformedIssue['challenge_id']);$authority->verifyEmail(['challenge_id'=>$malformedIssue['challenge_id'],'code'=>'x','idempotency_key'=>'malformed-verify']);$assert(tecnina_s03_verification_attempts($malformedIssue['challenge_id'])===$attemptsBefore,'malformed code does not consume attempt');

$reset = $authority->issuePasswordReset($fixture['reset_issue']);
$resetReplay=$authority->issuePasswordReset($fixture['reset_issue']);
$assert($resetReplay['replayed'] && $resetReplay['reset_url']===$reset['reset_url'],'reset issuance replay returns stable URL');
$assert($reset['ok'] && ! tecnina_s03_persisted_contains_plaintext('tecnina_password_resets', tecnina_s03_token_from_url($reset['reset_url'])), 'reset digest only');
$before = tecnina_s03_credential_version($fixture['client_id']);
$assert($authority->consumePasswordReset(tecnina_s03_token_from_url($reset['reset_url']), ' reset ', ' reset ')['ok'], 'valid reset consumption');
$assert(tecnina_s03_credential_version($fixture['client_id']) === $before + 1, 'version increments once');
$assert(! $authority->consumePasswordReset(tecnina_s03_token_from_url($reset['reset_url']), 'changed', 'changed')['ok'], 'single use/replay rejection');
$assert(tecnina_s03_credential_version($fixture['client_id']) === $before + 1, 'replay cannot increment version');
$expiredReset=$authority->issuePasswordReset(array_merge($fixture['reset_issue'],['idempotency_key'=>'expired-reset']));tecnina_s03_expire_latest_reset();
$assert(!$authority->consumePasswordReset(tecnina_s03_token_from_url($expiredReset['reset_url']),'new-pass','new-pass')['ok'],'expired reset rejected');

$limiter=get_instance()->tecnina_identity_rate_limiter;
$assert($limiter->allow('behavior_limit','fixture',2,3600)===true, 'rate first');
$assert($limiter->allow('behavior_limit','fixture',2,3600)===true, 'rate second');
$assert($limiter->allow('behavior_limit','fixture',2,3600)===false, 'rate limit enforced');
$serviceSubject='behavior-service';for($i=0;$i<60;$i++){$assert($limiter->allow('credential_hash',$serviceSubject,60,60)===true,'hash service capacity');}$assert($limiter->allow('credential_hash',$serviceSubject,60,60)===false,'hash endpoint 60/min limit');
$lookupSubject='behavior-lookup';for($i=0;$i<300;$i++){$assert($limiter->allow('client_lookup',$lookupSubject,300,60)===true,'lookup service capacity');}$assert($limiter->allow('client_lookup',$lookupSubject,300,60)===false,'lookup endpoint 300/min limit');
$assert(tecnina_s03_rate_dependency_unavailable()===null,'limiter dependency failure is controlled unavailable');
echo "TecninaS03IdentityAuthorityBehaviorTest: PASS\n";
