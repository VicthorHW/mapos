#!/usr/bin/env sh

# Executado pelo Coolify dentro do container php-fpm depois do deploy.
# `pipefail` nao e usado porque o /bin/sh da imagem Debian e o dash; o script
# nao possui pipelines e permanece estritamente POSIX.
set -eu
umask 077

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
DEFAULT_APP_ROOT=$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)
APP_ROOT=${MAPOS_ROOT:-$DEFAULT_APP_ROOT}
PHP_BIN=${PHP_BIN:-php}
MAX_ATTEMPTS=${MAPOS_POST_DEPLOY_ATTEMPTS:-12}
RETRY_DELAY=${MAPOS_POST_DEPLOY_RETRY_DELAY:-5}
INSTALLER="$APP_ROOT/tools/device-credential/install.php"
ENV_FILE="$APP_ROOT/application/.env"
AUTOLOAD_FILE="$APP_ROOT/application/vendor/autoload.php"
LOCK_DIR="${TMPDIR:-/tmp}/mapos-device-credential-post-deploy.lock"
CURRENT_STEP="inicializacao"
LOCK_ACQUIRED=0

log()
{
    printf '%s\n' "[mapos-post-deploy] $*"
}

fail()
{
    log "ERRO: $*" >&2
    exit 1
}

cleanup()
{
    status=$?

    if [ "$LOCK_ACQUIRED" -eq 1 ]; then
        rm -f "$LOCK_DIR/pid"
        rmdir "$LOCK_DIR" 2>/dev/null || true
    fi

    if [ "$status" -ne 0 ]; then
        log "Falha na etapa '$CURRENT_STEP' (codigo $status). Consulte o log do deploy no Coolify." >&2
    fi
}

trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
trap 'exit 129' HUP

validate_positive_integer()
{
    value=$1
    name=$2

    case "$value" in
        ''|*[!0-9]*) fail "$name deve ser um numero inteiro positivo." ;;
    esac

    if [ "$value" -lt 1 ]; then
        fail "$name deve ser maior que zero."
    fi
}

wait_for_file()
{
    file=$1
    description=$2
    attempt=1

    while [ ! -f "$file" ]; do
        if [ "$attempt" -ge "$MAX_ATTEMPTS" ]; then
            fail "$description nao ficou disponivel em $file."
        fi

        log "Aguardando $description ($attempt/$MAX_ATTEMPTS)..."
        sleep "$RETRY_DELAY"
        attempt=$((attempt + 1))
    done
}

if [ "$#" -gt 1 ]; then
    fail "Uso: post-deploy.sh [--verify-only]"
fi

MODE=${1:-install}
case "$MODE" in
    install)
        ;;
    --verify-only)
        ;;
    *)
        fail "Uso: post-deploy.sh [--verify-only]"
        ;;
esac

validate_positive_integer "$MAX_ATTEMPTS" MAPOS_POST_DEPLOY_ATTEMPTS
validate_positive_integer "$RETRY_DELAY" MAPOS_POST_DEPLOY_RETRY_DELAY

CURRENT_STEP="obtencao do lock"
if ! mkdir "$LOCK_DIR" 2>/dev/null; then
    fail "outra execucao parece estar ativa. Se nao estiver, remova somente $LOCK_DIR e tente novamente."
fi
LOCK_ACQUIRED=1
printf '%s\n' "$$" > "$LOCK_DIR/pid"

CURRENT_STEP="pre-requisitos"
command -v "$PHP_BIN" >/dev/null 2>&1 || fail "PHP nao foi encontrado no container. Selecione o servico php-fpm."
[ -d "$APP_ROOT" ] || fail "Raiz do MAP-OS nao encontrada: $APP_ROOT"
[ -f "$APP_ROOT/index.php" ] || fail "index.php nao encontrado em $APP_ROOT"
[ -f "$INSTALLER" ] || fail "Instalador da feature nao encontrado: $INSTALLER"

if ! "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);'; then
    fail "PHP 8.4 ou superior e obrigatorio."
fi

if ! "$PHP_BIN" -r 'exit(extension_loaded("mysqli") && extension_loaded("openssl") ? 0 : 1);'; then
    fail "As extensoes PHP mysqli e openssl sao obrigatorias."
fi

cd "$APP_ROOT"
log "Raiz da aplicacao: $APP_ROOT"
log "PHP: $($PHP_BIN -r 'echo PHP_VERSION;')"

if [ "$MODE" = "--verify-only" ]; then
    CURRENT_STEP="verificacao sem alteracoes"
    "$PHP_BIN" "$INSTALLER" --verify-only
    log "Verificacao concluida sem alterar chave ou banco."
    exit 0
fi

CURRENT_STEP="espera da aplicacao"
wait_for_file "$ENV_FILE" "application/.env persistente"
wait_for_file "$AUTOLOAD_FILE" "dependencias do Composer"
[ -r "$ENV_FILE" ] || fail "$ENV_FILE nao pode ser lido pelo usuario atual."
[ -w "$ENV_FILE" ] || fail "$ENV_FILE nao pode ser alterado pelo usuario atual."

CURRENT_STEP="instalacao e banco de dados"
attempt=1
last_status=1

while [ "$attempt" -le "$MAX_ATTEMPTS" ]; do
    log "Executando instalador da feature ($attempt/$MAX_ATTEMPTS)..."

    if "$PHP_BIN" "$INSTALLER"; then
        last_status=0
        break
    else
        last_status=$?
    fi

    if [ "$attempt" -ge "$MAX_ATTEMPTS" ]; then
        break
    fi

    log "Instalador retornou codigo $last_status; nova tentativa em ${RETRY_DELAY}s."
    sleep "$RETRY_DELAY"
    attempt=$((attempt + 1))
done

if [ "$last_status" -ne 0 ]; then
    fail "o instalador continuou falhando apos $MAX_ATTEMPTS tentativa(s)."
fi

CURRENT_STEP="finalizacao"
log "Post-deploy concluido com sucesso."
