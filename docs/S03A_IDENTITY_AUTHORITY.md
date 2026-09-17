# S03-A identity and credential authority

Status: target validation. This branch adds a dormant MapOS authority; it does not alter `Mine.php` active login/profile flows.

## Required target configuration

`TECNINA_IDENTITY_HMAC_SECRET` is required and must contain at least 32 unpredictable bytes. It HMAC-digests verification codes and reset tokens independently of `MAPOS_BOT_TOKEN`; generate with the platform secret manager, rotate only through a planned credential-reset/verification invalidation procedure, and never place its value in source or logs. If absent, identity operations fail closed as unavailable; deployment can start, but S03-A authority calls must return controlled unavailability until configured.

## Responsibility split

MapOS owns credential hashing, identity persistence, authoritative client e-mail promotion, protected API authorization, digesting, UTC timestamps, reset consumption, relational legacy-phone ambiguity, and fixed-window limits: hash 60/min/service, lookup 300/min/service, e-mail issue 3/15 min plus 10/day per subject+e-mail, reset issue 3/hour/identity, and public reset 10/hour/token plus 30/hour/IP. Bot per-intake capability, contextual proof, source-origin controls and end-to-end correlation remain **DEFERRED_TO_CIAO-S03B**.

Unresolved relational phone-conflict evidence always wins over an identity row and returns `AMBIGUOUS`. A delivered client challenge records the candidate as `PENDING` without replacing `clientes.email`; only successful verification promotes the authoritative e-mail. Malformed verification requests are rejected before attempt accounting. Limiter/database dependency failures return controlled unavailability, and request fingerprints that include limited proof are keyed rather than stored as guessable raw derivatives.

The public reset controller is POST-only and relies on the existing CodeIgniter CSRF middleware when enabled. Nginx/Coolify access-log token redaction and TLS/SameSite deployment verification are **TARGET_VALIDATION / INFRA** requirements, not asserted by this source branch.

## Validation

The repository-owned runner remains available for an inexpensive representative database with `APP_ENVIRONMENT=testing php index.php s03_identity_test_runner`, but a disposable replica is optional rather than a mandatory gate. The authoritative final validation target is the Orange Pi / Coolify production runtime. Production validation still requires completed source review, feasible static/automated checks, a fresh verified backup, controlled CodeIgniter migration/deployment, forward-recovery discipline and post-rollout regression/security evidence. Windows execution remains prohibited.
