<?php
/** Authorized disposable-target bootstrap contract; never points to production data. */
$ciBootstrap = getenv('TECNINA_S03_CI_BOOTSTRAP');
if (! $ciBootstrap || ! is_file($ciBootstrap)) {
    fwrite(STDERR, "S03 bootstrap: SKIPPED / ENVIRONMENT_NOT_AVAILABLE (missing isolated CI bootstrap)\n");
    exit(77);
}
require $ciBootstrap;
foreach (['tecnina_s03_identity_fixture','tecnina_s03_identity_row','tecnina_s03_conflict_count','tecnina_s03_persisted_contains_plaintext','tecnina_s03_client_email','tecnina_s03_token_from_url','tecnina_s03_credential_version'] as $helper) {
    if (! function_exists($helper)) { throw new RuntimeException('S03 test bootstrap missing helper: ' . $helper); }
}
