#!/usr/bin/env bash
set -Eeuo pipefail

APP_NAME="ai-airbagszentrum"
REPOSITORY="/var/lib/${APP_NAME}/repository"
RELEASES="/var/www/${APP_NAME}-releases"
SHARED="/var/www/${APP_NAME}-shared"
CURRENT="/var/www/${APP_NAME}-current"
LEGACY="/var/www/${APP_NAME}"
REMOTE="https://github.com/marcosborg/AiZentrum.git"
BRANCH="main"
PHP_BIN="/usr/bin/php"
COMPOSER_BIN="/usr/bin/composer"

exec 9>"/run/lock/${APP_NAME}-deploy.lock"
flock -n 9 || exit 0

restore_application() {
    if [[ -f "${CURRENT}/artisan" ]]; then
        sudo -u www-data "${PHP_BIN}" "${CURRENT}/artisan" up >/dev/null 2>&1 || true
    fi
    if [[ -f "${LEGACY}/artisan" ]]; then
        sudo -u www-data "${PHP_BIN}" "${LEGACY}/artisan" up >/dev/null 2>&1 || true
    fi
}
trap restore_application EXIT

install -d -o www-data -g www-data "$(dirname "${REPOSITORY}")" "${RELEASES}" "${SHARED}"

if [[ ! -d "${REPOSITORY}/.git" ]]; then
    sudo -u www-data git clone --branch "${BRANCH}" "${REMOTE}" "${REPOSITORY}"
fi

sudo -u www-data git -C "${REPOSITORY}" fetch --quiet origin "${BRANCH}"
COMMIT="$(sudo -u www-data git -C "${REPOSITORY}" rev-parse "origin/${BRANCH}")"

if [[ -L "${CURRENT}" && -f "${CURRENT}/.deploy-commit" ]] && grep -qx "${COMMIT}" "${CURRENT}/.deploy-commit"; then
    exit 0
fi

if [[ ! -f "${SHARED}/.env" ]]; then
    install -m 640 -o www-data -g www-data "${LEGACY}/.env" "${SHARED}/.env"
fi

if [[ ! -d "${SHARED}/storage" ]]; then
    cp -a "${LEGACY}/storage" "${SHARED}/storage"
fi

install -d -o www-data -g www-data "${SHARED}/public/images" "${SHARED}/public/exports"
if [[ -d "${LEGACY}/public/images" ]]; then
    rsync -a "${LEGACY}/public/images/" "${SHARED}/public/images/"
fi
if [[ -d "${LEGACY}/public/exports" ]]; then
    rsync -a "${LEGACY}/public/exports/" "${SHARED}/public/exports/"
fi

RELEASE="${RELEASES}/${COMMIT}"
TEMP_RELEASE="${RELEASE}.tmp"
rm -rf "${TEMP_RELEASE}"
install -d -o www-data -g www-data "${TEMP_RELEASE}"

sudo -u www-data git -C "${REPOSITORY}" archive "${COMMIT}" | tar -x -C "${TEMP_RELEASE}"
rm -rf "${TEMP_RELEASE}/storage" "${TEMP_RELEASE}/public/images" "${TEMP_RELEASE}/public/exports"
ln -s "${SHARED}/.env" "${TEMP_RELEASE}/.env"
ln -s "${SHARED}/storage" "${TEMP_RELEASE}/storage"
ln -s "${SHARED}/storage/app/public" "${TEMP_RELEASE}/public/storage"
ln -s "${SHARED}/public/images" "${TEMP_RELEASE}/public/images"
ln -s "${SHARED}/public/exports" "${TEMP_RELEASE}/public/exports"

chown -R www-data:www-data "${TEMP_RELEASE}"
sudo -u www-data "${COMPOSER_BIN}" install \
    --working-dir="${TEMP_RELEASE}" \
    --no-dev --no-interaction --prefer-dist --optimize-autoloader

sudo -u www-data "${PHP_BIN}" "${TEMP_RELEASE}/artisan" config:clear
sudo -u www-data "${PHP_BIN}" "${TEMP_RELEASE}/artisan" migrate --force
printf '%s\n' "${COMMIT}" > "${TEMP_RELEASE}/.deploy-commit"
chown www-data:www-data "${TEMP_RELEASE}/.deploy-commit"

if [[ -f "${CURRENT}/artisan" ]]; then
    sudo -u www-data "${PHP_BIN}" "${CURRENT}/artisan" down --retry=30
elif [[ -f "${LEGACY}/artisan" ]]; then
    sudo -u www-data "${PHP_BIN}" "${LEGACY}/artisan" down --retry=30
fi

mv "${TEMP_RELEASE}" "${RELEASE}"
ln -sfn "${RELEASE}" "${CURRENT}.new"
mv -Tf "${CURRENT}.new" "${CURRENT}"

sudo -u www-data "${PHP_BIN}" "${CURRENT}/artisan" optimize:clear
sudo -u www-data "${PHP_BIN}" "${CURRENT}/artisan" config:cache
systemctl reload php8.3-fpm
sudo -u www-data "${PHP_BIN}" "${CURRENT}/artisan" up

find "${RELEASES}" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
    | sort -nr | tail -n +6 | cut -d' ' -f2- | xargs -r rm -rf

trap - EXIT
