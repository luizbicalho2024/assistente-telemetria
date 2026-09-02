#!/usr/bin/env bash
set -Eeuo pipefail

cd /var/www/html

mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

if [[ ! -f .env ]]; then
    cp .env.example .env
fi

if [[ -z "${APP_KEY:-}" ]]; then
    export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
    echo "AVISO: APP_KEY não estava definida; foi gerada uma chave para esta execução."
    echo "Defina APP_KEY no .env antes do uso em produção para manter sessões estáveis."
fi

echo "Aguardando MongoDB..."
attempt=0
until php artisan app:bootstrap --no-interaction; do
    attempt=$((attempt + 1))
    if [[ "$attempt" -ge 30 ]]; then
        echo "MongoDB não ficou disponível após 30 tentativas."
        exit 1
    fi
    sleep 2
done

php artisan config:clear --no-interaction || true
php artisan view:clear --no-interaction || true

exec "$@"
