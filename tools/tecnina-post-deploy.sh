#!/usr/bin/env sh

set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)

"$SCRIPT_DIR/device-credential/post-deploy.sh" "${1:-install}"
"$SCRIPT_DIR/tecnina-integration/post-deploy.sh" "${1:-install}"

printf '%s\n' '[tecnina-post-deploy] Todas as customizacoes foram verificadas.'
