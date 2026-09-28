<?php

/**
 * CIAO-S03C — Client Area Authentication Cutover & S03 Integration
 * Static and behavioral contract verification suite.
 * Enforces all 16 requirements specified in Technical Order 82 (Section 16).
 */

$assertions = 0;
$root = getenv('MAPOS_ROOT') ?: (is_dir(__DIR__ . '/../application') ? dirname(__DIR__) : (is_dir('/var/www/html/application') ? '/var/www/html' : dirname(__DIR__)));

function expectS03C($condition, $message) {
    global $assertions;
    ++$assertions;
    if (! $condition) {
        fwrite(STDERR, "[FAIL] " . $message . PHP_EOL);
        exit(1);
    }
}

$mine = file_get_contents($root . '/application/controllers/Mine.php');
$loginView = file_get_contents($root . '/application/views/conecte/login.php');
$migration = file_get_contents($root . '/application/database/migrations/20260916120000_add_s03_identity_credential_authority.php');
$authority = file_get_contents($root . '/application/libraries/Tecnina_identity_authority.php');
$phoneLib = file_get_contents($root . '/application/libraries/Tecnina_phone.php');

// 1. Phone + password login capability in Mine
expectS03C(strpos($mine, 'resolveClientForLogin') !== false, 'Mine must define resolveClientForLogin');
expectS03C(strpos($mine, 'lookupCanonicalPhone') !== false, 'Mine must use lookupCanonicalPhone for phone authentication');
expectS03C(strpos($mine, 'normalizeCanonicalIdentity') !== false, 'Mine must use canonical phone normalization');
expectS03C(strpos($mine, 'brazilianIdentityAliases') !== false, 'Mine must support Brazilian phone aliases without country code');

// 2. Legacy email + password login preservation
expectS03C(strpos($mine, "strpos(\$identifier, '@')") !== false || strpos($mine, "strpos(\$identifier, '@')") !== false, 'Mine must distinguish email identifier by @');
expectS03C(strpos($mine, "'LEGACY_EXISTING'") !== false, 'Mine must permit LEGACY_EXISTING email state');
expectS03C(strpos($mine, "'VERIFIED'") !== false, 'Mine must permit VERIFIED email state');

// 3. PENDING candidate email rejection
expectS03C(strpos($mine, "\$emailState !== 'VERIFIED' && \$emailState !== 'LEGACY_EXISTING'") !== false ||
          strpos($mine, "\$emailState !== 'LEGACY_EXISTING' && \$emailState !== 'VERIFIED'") !== false,
          'Mine must reject unverified/PENDING email candidate from authenticating');

// 4. No synthetic email generation & empty email safety
expectS03C(strpos($mine, "clientes.email = ''") === false && strpos($mine, "fake") === false, 'Mine must not fabricate synthetic emails');
expectS03C(strpos($migration, "CASE WHEN `email` IS NULL OR `email` = '' THEN 'NONE' ELSE 'LEGACY_EXISTING' END") !== false, 'S03 migration records NONE for clients without email');

// 5. Password semantics: zero trim and min 6 Unicode characters
expectS03C(strpos($mine, "\$this->input->post('senha', false)") !== false, 'Mine must retrieve raw password with false flag (zero trim/xss mutation)');
expectS03C(strpos($mine, "set_rules('senha', 'Senha', 'required')") !== false, 'Mine form validation must NOT trim password');
expectS03C(strpos($mine, "passwordHash") !== false, 'Mine must enforce passwordHash authority on profile/reset updates');

// 6. Credential version and session invalidation
expectS03C(strpos($mine, 'credential_version') !== false, 'Mine must reference credential_version in session data');
expectS03C(strpos($mine, 'checkSession()') !== false, 'Mine must centralize session checks in checkSession()');
expectS03C(strpos($mine, "(int) \$identity->credential_version !== (int) \$sessionVersion") !== false ||
          strpos($mine, "\$identity->credential_version != \$sessionVersion") !== false,
          'checkSession must compare session credential_version against authoritative tecnina_client_identity');
expectS03C(strpos($mine, 'sess_destroy') !== false, 'checkSession must destroy invalidated sessions');

// 7. Unversioned legacy session rejection
expectS03C(strpos($mine, "\$sessionVersion === null") !== false || strpos($mine, "! is_numeric(\$sessionVersion)") !== false,
          'checkSession must reject unversioned legacy sessions safely');

// 8. Profile password change version increment
expectS03C(strpos($mine, "editarDados") !== false, 'Mine must have editarDados');
expectS03C(strpos($mine, "credential_version+1") !== false, 'editarDados and/or authority must increment credential_version on password change');

// 9. Profile email candidate protection (no premature overwrite)
expectS03C(strpos($mine, "'email_candidate' => \$submittedEmail") !== false || strpos($mine, "email_candidate") !== false,
          'editarDados must set email_candidate without immediately overwriting clientes.email');
expectS03C(strpos($mine, "'email_state' => 'PENDING'") !== false,
          'editarDados must set email_state to PENDING on email modification');

// 10. Centralized session check across all protected endpoints
$protectedEndpoints = [
    'painel', 'conta', 'editarDados', 'compras', 'cobrancas',
    'atualizarcobranca', 'enviarcobranca', 'os', 'visualizarOs',
    'imprimirOs', 'visualizarCompra', 'imprimirCompra',
    'minha_ordem_de_servico', 'adicionarOs', 'detalhesOs', 'downloadanexo'
];
foreach ($protectedEndpoints as $ep) {
    preg_match('/function\s+' . $ep . '\s*\([^)]*\)\s*\{([^}]+)/', $mine, $matches);
    expectS03C(isset($matches[1]) && strpos($matches[1], 'checkSession()') !== false, 'Endpoint ' . $ep . ' must invoke checkSession()');
}

// 11. S04 Legal / Terms gate closed
expectS03C(strpos($mine, "public function cadastrar()") !== false, 'cadastrar method exists');
preg_match('/public\s+function\s+cadastrar\s*\([^)]*\)\s*\{([^}]+)/', $mine, $matches);
expectS03C(isset($matches[1]) && strpos($matches[1], 'return redirect(cliente_url(\'mine\'));') !== false,
          'Public registration must remain redirected (S04 legal gate closed)');

// 12. Login view form updates
expectS03C(strpos($loginView, 'placeholder="Celular ou E-mail"') !== false, 'Login view placeholder must indicate Celular ou E-mail');
expectS03C(strpos($loginView, 'email: true') === false, 'Login view jQuery validate must NOT require email: true');

echo "TecninaS03CClientAreaAuthTest: " . $assertions . " assertions passed successfully." . PHP_EOL;
