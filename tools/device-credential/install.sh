#!/usr/bin/env sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)

if ! command -v php >/dev/null 2>&1; then
    echo "PHP nao foi encontrado no PATH." >&2
    exit 1
fi

exec php "$SCRIPT_DIR/install.php" "$@"
