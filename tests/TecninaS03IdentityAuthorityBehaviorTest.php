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

// Strict subject validation
$assert(! $authority->issueEmailVerification(['client_id' => 'abc', 'email_candidate' => 'test@example.test', 'purpose' => 'PROFILE_CHANGE', 'idempotency_key' => 'k-sub-1'])['ok'], 'malformed client_id string rejected');
$assert(! $authority->issueEmailVerification(['client_id' => 0, 'email_candidate' => 'test@example.test', 'purpose' => 'PROFILE_CHANGE', 'idempotency_key' => 'k-sub-2'])['ok'], 'zero client_id rejected');
$assert(! $authority->issueEmailVerification(['intake_id' => 'not-uuid', 'email_candidate' => 'test@example.test', 'purpose' => 'ACCOUNT_CREATION', 'idempotency_key' => 'k-sub-3'])['ok'], 'malformed intake_id rejected');
$assert(! $authority->issueEmailVerification(['client_id' => $fixture['client_id'], 'intake_id' => '00000000-0000-4000-8000-000000000001', 'email_candidate' => 'test@example.test', 'purpose' => 'ACCOUNT_CREATION', 'idempotency_key' => 'k-sub-4'])['ok'], 'both subjects rejected');
$assert(! $authority->issueEmailVerification(['email_candidate' => 'test@example.test', 'purpose' => 'ACCOUNT_CREATION', 'idempotency_key' => 'k-sub-5'])['ok'], 'neither subject rejected');

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

$rateReset = $authority->issuePasswordReset(array_merge($fixture['reset_issue'], ['idempotency_key' => 'rate-reset-lifetime']));
$rateToken = tecnina_s03_token_from_url($rateReset['reset_url']);
tecnina_s03_set_reset_attempts($rateToken, 10);
$eleventhRes = $authority->consumePasswordReset($rateToken, 'pass123', 'pass123');
$assert(!$eleventhRes['ok'] && $eleventhRes['reason'] === 'rate_limited', 'eleventh reset attempt returns rate_limited');

// Same-candidate email reissue supersession without affected_rows error
$sameCand = 'same-cand@example.test';
$issueSame1 = $authority->issueEmailVerification(['client_id'=>$fixture['client_id'],'email_candidate'=>$sameCand,'purpose'=>'PROFILE_CHANGE','idempotency_key'=>'same-k1']);
$assert($issueSame1['ok'], 'issue same candidate first');
$issueSame2 = $authority->issueEmailVerification(['client_id'=>$fixture['client_id'],'email_candidate'=>$sameCand,'purpose'=>'PROFILE_CHANGE','idempotency_key'=>'same-k2']);
$assert($issueSame2['ok'], 'reissue same candidate succeeds without affected_rows error');
$assert(tecnina_s03_verification_state($issueSame1['challenge_id']) === 'SUPERSEDED', 'first same-candidate challenge superseded');
$assert(tecnina_s03_verification_state($issueSame2['challenge_id']) === 'PENDING', 'second same-candidate challenge pending');
$codeSame2 = tecnina_s03_delivery_code();
$verifiedSame = $authority->verifyEmail(['challenge_id'=>$issueSame2['challenge_id'], 'code'=>$codeSame2, 'idempotency_key'=>'verify-same-k2']);
$assert($verifiedSame['ok'] && tecnina_s03_client_email($fixture['client_id']) === $sameCand, 'verify same candidate promotes email');

// Syntactic validation of password reset requests
$assert(! $authority->issuePasswordReset(array_merge($fixture['reset_issue'], ['canonical_phone' => 'not-a-phone']))['ok'], 'malformed phone alpha rejected');
$assert(! $authority->issuePasswordReset(array_merge($fixture['reset_issue'], ['canonical_phone' => '123']))['ok'], 'malformed phone short rejected');
$assert(! $authority->issuePasswordReset(array_merge($fixture['reset_issue'], ['idempotency_key' => '']))['ok'], 'empty idempotency key rejected');
$assert(! $authority->issuePasswordReset(array_merge($fixture['reset_issue'], ['client_id' => 0]))['ok'], 'client_id 0 rejected 422');
$assert(! $authority->issuePasswordReset(array_merge($fixture['reset_issue'], ['client_id' => -1]))['ok'], 'client_id -1 rejected 422');
$dummyResetRes = $authority->issuePasswordReset(array_merge($fixture['reset_issue'], ['canonical_phone' => '5511999999999', 'client_id' => 999999]));
$assert($dummyResetRes['ok'] && ($dummyResetRes['state'] ?? '') === 'REQUEST_ACCEPTED' && !isset($dummyResetRes['reset_url']), 'dummy reset privacy accepted');

// Password confirmation required in reset consumption
$confReset = $authority->issuePasswordReset(array_merge($fixture['reset_issue'], ['idempotency_key' => 'conf-test-issue']));
$confToken = tecnina_s03_token_from_url($confReset['reset_url']);
$assert(! $authority->consumePasswordReset($confToken, 'validpass123', null)['ok'], 'missing confirmation rejected');
$assert(! $authority->consumePasswordReset($confToken, 'validpass123', 'mismatch123')['ok'], 'mismatched confirmation rejected');
$assert(! $authority->consumePasswordReset($confToken, 'validpass123', 123456)['ok'], 'non-string confirmation rejected');
$assert($authority->consumePasswordReset($confToken, 'validpass123', 'validpass123')['ok'], 'exact byte-for-byte confirmation accepted');

// Reset replay / rate-limit precedence edge case
$precReset = $authority->issuePasswordReset(array_merge($fixture['reset_issue'], ['idempotency_key' => 'prec-test-issue']));
$precToken = tecnina_s03_token_from_url($precReset['reset_url']);
tecnina_s03_set_reset_attempts($precToken, 9);
$vBeforePrec = tecnina_s03_credential_version($fixture['client_id']);
$tenthRes = $authority->consumePasswordReset($precToken, 'precpass123', 'precpass123');
$assert($tenthRes['ok'], 'valid password on attempt 10 succeeds');
$assert(tecnina_s03_reset_attempts($precToken) === 10, 'attempts becomes 10 on attempt 10');
$assert(tecnina_s03_credential_version($fixture['client_id']) === $vBeforePrec + 1, 'version increments exactly once on attempt 10');
$replayTenth = $authority->consumePasswordReset($precToken, 'precpass123', 'precpass123');
$assert(! $replayTenth['ok'] && $replayTenth['reason'] === 'invalid_or_expired_reset', 'replay of consumed token with attempts 10 returns invalid_or_expired_reset, not rate_limited');
$assert(tecnina_s03_credential_version($fixture['client_id']) === $vBeforePrec + 1, 'replay does not mutate credential version');

// Expired PENDING token with attempts = 10 returns invalid_or_expired_reset (not 429)
$expPrecReset = $authority->issuePasswordReset(array_merge($fixture['reset_issue'], ['idempotency_key' => 'exp-prec-test']));
$expPrecToken = tecnina_s03_token_from_url($expPrecReset['reset_url']);
tecnina_s03_set_reset_attempts($expPrecToken, 10);
tecnina_s03_expire_latest_reset();
$expTenthRes = $authority->consumePasswordReset($expPrecToken, 'exp123', 'exp123');
$assert(! $expTenthRes['ok'] && $expTenthRes['reason'] === 'invalid_or_expired_reset', 'expired token with attempts 10 returns invalid_or_expired_reset, not rate_limited');

// Attempts strictly capped at 5 under row lock
$afterFive = $authority->verifyEmail(['challenge_id'=>$failureIssue['challenge_id'],'code'=>$badCode,'idempotency_key'=>'after-five-wrong']);
$assert(!$afterFive['ok'] && $afterFive['reason'] === 'invalid_or_expired_code', 'attempt after five rejected');
$assert(tecnina_s03_verification_attempts($failureIssue['challenge_id']) === 5, 'attempts strictly capped at five');

$limiter=get_instance()->tecnina_identity_rate_limiter;
$assert($limiter->allow('behavior_limit','fixture',2,3600)===true, 'rate first');
$assert($limiter->allow('behavior_limit','fixture',2,3600)===true, 'rate second');
$assert($limiter->allow('behavior_limit','fixture',2,3600)===false, 'rate limit enforced');
$serviceSubject='behavior-service';for($i=0;$i<60;$i++){$assert($limiter->allow('credential_hash',$serviceSubject,60,60)===true,'hash service capacity');}$assert($limiter->allow('credential_hash',$serviceSubject,60,60)===false,'hash endpoint 60/min limit');
$lookupSubject='behavior-lookup';for($i=0;$i<300;$i++){$assert($limiter->allow('client_lookup',$lookupSubject,300,60)===true,'lookup service capacity');}$assert($limiter->allow('client_lookup',$lookupSubject,300,60)===false,'lookup endpoint 300/min limit');
$assert(tecnina_s03_rate_dependency_unavailable()===null,'limiter dependency failure is controlled unavailable');
echo "TecninaS03IdentityAuthorityBehaviorTest: PASS\n";
