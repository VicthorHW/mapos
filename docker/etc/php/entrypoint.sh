#!/bin/sh

set -eu

CONFIG_DIR="/var/www/mapos-config"
CONFIG_ENV="${CONFIG_DIR}/.env"
APP_ENV="/var/www/html/application/.env"

echo "[MapOS] Preparando armazenamento persistente..."

# Diretório persistente para o .env
mkdir -p "${CONFIG_DIR}"
chown www-data:www-data "${CONFIG_DIR}"
chmod 770 "${CONFIG_DIR}"

# application/.env será um link para o .env persistente
if [ -e "${APP_ENV}" ] && [ ! -L "${APP_ENV}" ]; then
    rm -f "${APP_ENV}"
fi

if [ ! -L "${APP_ENV}" ]; then
    ln -s "${CONFIG_ENV}" "${APP_ENV}"
fi

# Diretórios em que o MapOS realmente precisa escrever
WRITABLE_DIRS="
/var/www/html/application/logs
/var/www/html/application/cache
/var/www/html/assets/anexos
/var/www/html/assets/arquivos
/var/www/html/assets/uploads
/var/www/html/assets/userImage
/var/www/html/updates
"

for DIR in ${WRITABLE_DIRS}; do
    mkdir -p "${DIR}"
    chown -R www-data:www-data "${DIR}"
    chmod -R u+rwX,g+rwX,o-rwx "${DIR}"
done

# Protege o .env quando já existir
if [ -f "${CONFIG_ENV}" ]; then
    chown www-data:www-data "${CONFIG_ENV}"
    chmod 660 "${CONFIG_ENV}"
fi

echo "[MapOS] Armazenamento pronto."

exec "$@"
