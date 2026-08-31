#!/bin/sh

set -eu

CONFIG_DIR="/var/www/mapos-config"
CONFIG_ENV="${CONFIG_DIR}/.env"
APP_ENV="/var/www/html/application/.env"

echo "[MapOS] Preparando armazenamento persistente..."

# Diretório onde o .env verdadeiro ficará guardado
mkdir -p "${CONFIG_DIR}"
chown www-data:www-data "${CONFIG_DIR}"
chmod 770 "${CONFIG_DIR}"

# application/.env será apenas um link para o arquivo persistente.
#
# Antes da primeira instalação o destino ainda não existe.
# O instalador vai criá-lo automaticamente através desse link.
if [ -e "${APP_ENV}" ] && [ ! -L "${APP_ENV}" ]; then
    rm -f "${APP_ENV}"
fi

if [ ! -L "${APP_ENV}" ]; then
    ln -s "${CONFIG_ENV}" "${APP_ENV}"
fi

# Diretórios que o MapOS realmente precisa escrever
WRITABLE_DIRS="
/var/www/html/application/logs
/var/www/html/application/cache
/var/www/html/assets/anexos
/var/www/html/assets/arquivos
/var/www/html/assets/uploads
/var/www/html/assets/userImage
"

for DIR in ${WRITABLE_DIRS}; do
    mkdir -p "${DIR}"
    chown -R www-data:www-data "${DIR}"
    chmod -R u+rwX,g+rwX,o-rwx "${DIR}"
done

# Se a instalação já ocorreu, protege o .env
if [ -f "${CONFIG_ENV}" ]; then
    chown www-data:www-data "${CONFIG_ENV}"
    chmod 660 "${CONFIG_ENV}"
fi

echo "[MapOS] Armazenamento pronto."

exec "$@"
