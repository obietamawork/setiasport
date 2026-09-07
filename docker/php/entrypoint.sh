#!/bin/bash
set -e

# Bind mount dari Windows membuat storage/ dan bootstrap/cache/ dimiliki root,
# sementara Apache berjalan sebagai www-data. Diperbaiki setiap container start
# agar tidak perlu diingat manual setiap kali `docker compose up`.
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

exec "$@"
