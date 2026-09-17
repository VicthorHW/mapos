# S03-A identity and credential authority

Status: TL_ACCEPTANCE. This branch adds a dormant MapOS authority; it does not alter `Mine.php` active login/profile flows.

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

Strict subject validation enforces positive integer `client_id` and canonical UUID `intake_id` returning HTTP 422 `invalid_payload` before rate limiting, persistence, or delivery. Unresolved relational phone-conflict evidence always wins over an identity row and returns `AMBIGUOUS`. A delivered client challenge records the candidate as `PENDING` without replacing `clientes.email`; only successful verification promotes the authoritative e-mail. Malformed verification requests are rejected before attempt accounting. Concurrency in `verifyEmail()` is strictly serialized via row-level locking (`SELECT ... FOR UPDATE`); the attempts counter is capped at 5 and released concurrent valid requests cannot bypass the fifth failure. Limiter/database dependency failures return controlled unavailability, and request fingerprints that include limited proof are keyed rather than stored as guessable raw derivatives.

The public reset controller is POST-only and relies on the existing CodeIgniter CSRF middleware when enabled. Upstream reverse proxy (Traefik in Coolify) access logging is confirmed disabled; downstream Nginx access-log token redaction (`log_format tecnina_safe`) ensures reset tokens are never written in plaintext to access logs (`[REDACTED]`). Test runners are protected via Nginx `location ^~ /tests/ { deny all; return 404; }`, CLI-only guard, and safety interlock.

## Validation

The authoritative final validation target is the Orange Pi 5 Pro / Coolify production runtime. Production validation completed with 129/129 target assertions passing, 0 failures, 13 composer regression tests passing (with 2 accepted historical debts), pristine DB baseline restored, and migration ledger strictly preserved at `20260916120000`.
