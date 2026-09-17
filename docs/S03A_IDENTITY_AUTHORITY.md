# S03-A identity and credential authority

Status: implementation in progress. This branch adds a dormant MapOS authority; it does not alter `Mine.php` active login/profile flows.

## Required target configuration

`TECNINA_IDENTITY_HMAC_SECRET` is required and must contain at least 32 unpredictable bytes. It HMAC-digests verification codes and reset tokens independently of `MAPOS_BOT_TOKEN`; generate with the platform secret manager, rotate only through a planned credential-reset/verification invalidation procedure, and never place its value in source or logs. If absent, identity operations fail closed as unavailable; deployment can start, but S03-A authority calls must return controlled unavailability until configured.

## Responsibility split

MapOS owns credential hashing, identity persistence, authoritative client e-mail promotion, protected API authorization, digesting, UTC timestamps, reset consumption, relational legacy-phone ambiguity, and identity-operation fixed-window limits (email issue 3/15 min per subject+email; reset issue 3/hour per identity). Bot-owned per-intake capability, WhatsApp contextual-proof validation, source-origin rate limit and end-to-end correlation propagation are **DEFERRED_TO_CIAO-S03B**. MapOS must receive contextual values but does not fabricate their verification in S03-A.

The public reset controller is POST-only and relies on the existing CodeIgniter CSRF middleware when enabled. Nginx/Coolify access-log token redaction and TLS/SameSite deployment verification are **TARGET_VALIDATION / INFRA** requirements, not asserted by this source branch.

## Validation

`tests/TecninaS03IdentityAuthorityBehaviorTest.php` is an executable target-bootstrap suite using the actual authority API. It is intentionally not run on Windows because this checkout has no authorized PHP/MapOS/MySQL runtime.
