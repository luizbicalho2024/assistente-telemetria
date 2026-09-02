#!/usr/bin/env bash
set -Eeuo pipefail

cd /var/www/html

fail() {
    echo "ERRO DE SEGURANÇA: $*" >&2
    exit 1
}

require_env() {
    local name="$1"
    [[ -n "${!name:-}" ]] || fail "variável obrigatória ${name} não foi definida."
}

require_env APP_KEY
require_env MONGODB_URI
require_env MONGODB_DATABASE
require_env ADMIN_USERNAME
require_env ADMIN_EMAIL
require_env ADMIN_PASSWORD

case "${ADMIN_PASSWORD}" in
    "Troque""EstaSenha123!"|"admin"|"password"|"12345678"|"123456789")
        fail "ADMIN_PASSWORD usa valor inseguro/proibido."
        ;;
esac

if [[ "${#ADMIN_PASSWORD}" -lt 12 ]]; then
    fail "ADMIN_PASSWORD deve possuir pelo menos 12 caracteres."
fi

if [[ "${APP_ENV:-production}" == "production" && "${APP_DEBUG:-false}" == "true" ]]; then
    fail "APP_DEBUG=true é proibido em produção."
fi

if [[ ! "${APP_KEY}" =~ ^base64:[A-Za-z0-9+/=]+$ ]] && [[ "${#APP_KEY}" -lt 32 ]]; then
    fail "APP_KEY inválida ou curta demais."
fi

mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

echo "Aguardando MongoDB e executando bootstrap seguro..."
attempt=0
until php artisan app:bootstrap --no-interaction; do
    attempt=$((attempt + 1))
    if [[ "$attempt" -ge 30 ]]; then
        echo "MongoDB/bootstrap não ficou disponível após 30 tentativas." >&2
        exit 1
    fi
    sleep 2
done

php artisan config:clear --no-interaction || true
php artisan view:clear --no-interaction || true

if [[ "${APP_ENV:-production}" == "production" ]]; then
    php artisan config:cache --no-interaction
    php artisan view:cache --no-interaction
fi

exec "$@"
