# S03-A identity and credential authority

Status: source-review candidate. This branch adds a dormant MapOS authority; it does not alter `Mine.php` active login/profile flows.

## Required target configuration

`TECNINA_IDENTITY_HMAC_SECRET` is required and must contain at least 32 unpredictable bytes. It HMAC-digests verification codes and reset tokens independently of `MAPOS_BOT_TOKEN`; generate with the platform secret manager, rotate only through a planned credential-reset/verification invalidation procedure, and never place its value in source or logs. If absent, identity operations fail closed as unavailable; deployment can start, but S03-A authority calls must return controlled unavailability until configured.

## Responsibility split

MapOS owns credential hashing, identity persistence, authoritative client e-mail promotion, protected API authorization, token/code digesting, UTC timestamps, reset consumption, and relational legacy-phone ambiguity. Bot-owned per-intake capability, WhatsApp contextual-proof, source-origin rate limit and correlation propagation are **DEFERRED_TO_CIAO-S03B**. MapOS must receive those contextual values but does not fabricate their verification in S03-A.

## Validation

`tests/TecninaS03IdentityAuthorityBehaviorTest.php` is an executable target-bootstrap suite using the actual authority API. It is intentionally not run on Windows because this checkout has no authorized PHP/MapOS/MySQL runtime.
