#!/usr/bin/env sh

set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)

TECNINA_SKIP_INTEGRATION_CHAIN=1 sh "$SCRIPT_DIR/device-credential/post-deploy.sh" "${1:-install}"
sh "$SCRIPT_DIR/tecnina-integration/post-deploy.sh" "${1:-install}"

printf '%s\n' '[tecnina-post-deploy] Todas as customizacoes foram verificadas.'
