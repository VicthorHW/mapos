# S03-A identity and credential authority

Status: DONE / CANONICAL_CLOSURE_COMPLETE. This authority is merged to canonical master (commit `96bada83908f4caae364ff2b8b97c400b0267265`) and deployed in Coolify Deployment #267. It does not alter `Mine.php` active login/profile flows (deferred to CIAO-S03C).

## Required target configuration

`TECNINA_IDENTITY_HMAC_SECRET` is required and must contain at least 32 unpredictable bytes. It HMAC-digests verification codes and reset tokens independently of `MAPOS_BOT_TOKEN`; generate with the platform secret manager, rotate only through a planned credential-reset/verification invalidation procedure, and never place its value in source or logs. If absent, identity operations fail closed as unavailable; deployment can start, but S03-A authority calls must return controlled unavailability until configured.

## Responsibility split

MapOS owns credential hashing, identity persistence, authoritative client e-mail promotion, protected API authorization, digesting, UTC timestamps, reset consumption, relational legacy-phone ambiguity, and rate limits:
- Hash: 60/min/service
- Lookup: 300/min/service
- E-mail issue: 3/15 min plus 10/day per subject+e-mail
- Reset issue: 3/hour/identity
- Public reset token lifetime limit: strictly 10 attempts per token lifetime (tracked in `tecnina_password_resets.attempts`, attempt 11 returns HTTP 429 `rate_limited` independent of hourly rollover)
- Public reset IP limit: 30/hour/IP.

Bot per-intake capability, contextual proof, source-origin controls and end-to-end correlation remain **DEFERRED_TO_CIAO-S03B**.

## Technical Order 57 Closures

1. **Precedence Order in Public Reset**: Token existence and validity checks precede rate limit checks:
   - Non-existent token -> generic `invalid_or_expired_reset` (HTTP 409)
   - Non-PENDING or expired token -> generic `invalid_or_expired_reset` (HTTP 409)
   - Attempts >= 10 on still-valid PENDING token -> `rate_limited` (HTTP 429)
   - Valid submission: attempt 10 with valid password succeeds (state `CONSUMED`, attempts 10, version +1). Single-use replay returns HTTP 409 `invalid_or_expired_reset` (NOT 429) without mutating credentials. Expired PENDING token with attempts=10 returns HTTP 409 (NOT 429).
2. **Password Confirmation Enforcement**: Confirmation is strictly required in public reset consumption (`password_confirmation`). Mismatch, omission, or non-string rejected as invalid attempt (HTTP 409 `invalid_or_expired_reset`) and increments attempt counter. Exact byte-for-byte match accepted.
3. **Fail-Closed Attempt Accounting**: Both `verifyEmail()` and `consumePasswordReset()` attempt increments under row lock verify that affected rows equal 1 and transaction status is true; persistence write failure rolls back and returns HTTP 503 `unavailable`.
4. **Strict Client ID Validation**: Positive integer representation strictly required in `issuePasswordReset()`, non-positive or malformed returns HTTP 422 `invalid_payload`.
5. **Reverse Proxy & Log Redaction**: Upstream Traefik runs without `--accesslog`; downstream Nginx access-log redaction (`log_format tecnina_safe`) ensures reset tokens are never written to access logs (`[REDACTED]`).
6. **Test Runner Protection**: Nginx block `/tests/` (404), CLI guard, safety interlock, machine-readable output outside web root.

## Validation

The authoritative final validation target is the Orange Pi 5 Pro / Coolify production runtime. Production validation completed with 146/146 target assertions passing (100% EXECUTED_ON_TARGET, 0 failed), 13 composer regression tests passing (with 2 accepted historical debts), pristine DB baseline restored, and migration ledger strictly preserved at `20260916120000`.
