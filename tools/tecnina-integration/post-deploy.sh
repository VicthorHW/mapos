#!/usr/bin/env sh

set -eu
umask 077

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
APP_ROOT=${MAPOS_ROOT:-$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)}
PHP_BIN=${PHP_BIN:-php}
MAX_ATTEMPTS=${MAPOS_POST_DEPLOY_ATTEMPTS:-12}
RETRY_DELAY=${MAPOS_POST_DEPLOY_RETRY_DELAY:-5}
MODE=${1:-install}

log()
{
    printf '%s\n' "[tecnina-integration] $*"
}

fail()
{
    log "ERRO: $*" >&2
    exit 1
}

case "$MODE" in
    install|--verify-only) ;;
    *) fail "Uso: post-deploy.sh [--verify-only]" ;;
esac

case "$MAX_ATTEMPTS:$RETRY_DELAY" in
    *[!0-9:]*|'':*) fail "Tentativas e intervalo devem ser inteiros positivos." ;;
esac
[ "$MAX_ATTEMPTS" -ge 1 ] || fail "MAPOS_POST_DEPLOY_ATTEMPTS deve ser positivo."
[ "$RETRY_DELAY" -ge 1 ] || fail "MAPOS_POST_DEPLOY_RETRY_DELAY deve ser positivo."

command -v "$PHP_BIN" >/dev/null 2>&1 || fail "PHP nao encontrado."
[ -f "$APP_ROOT/index.php" ] || fail "Raiz do MAP-OS invalida: $APP_ROOT"
[ -f "$APP_ROOT/application/.env" ] || fail "application/.env ausente."
[ -f "$APP_ROOT/application/vendor/autoload.php" ] || fail "Dependencias Composer ausentes."

if [ "$MODE" = "--verify-only" ]; then
    exec "$PHP_BIN" "$APP_ROOT/tools/tecnina-integration/install.php" --verify-only
fi

attempt=1
while [ "$attempt" -le "$MAX_ATTEMPTS" ]; do
    log "Instalando schema e trigger ($attempt/$MAX_ATTEMPTS)..."
    if "$PHP_BIN" "$APP_ROOT/tools/tecnina-integration/install.php"; then
        log "Instalacao concluida."
        exit 0
    fi
    [ "$attempt" -lt "$MAX_ATTEMPTS" ] || break
    sleep "$RETRY_DELAY"
    attempt=$((attempt + 1))
done

fail "Instalacao falhou apos $MAX_ATTEMPTS tentativa(s)."
