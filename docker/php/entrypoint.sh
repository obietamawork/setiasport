#!/bin/bash
set -e

# Bind mount dari Windows membuat storage/ dan bootstrap/cache/ dimiliki root,
# sementara Apache berjalan sebagai www-data. Diperbaiki setiap container start
# agar tidak perlu diingat manual setiap kali `docker compose up`.
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

# vendor/ tinggal di volume Linux, bukan di mount Windows (lihat compose.yaml),
# jadi volume yang baru dibuat isinya kosong. Tanpa ini, `docker compose up`
# pertama kali berakhir dengan layar putih dan pesan autoload.php tidak ada,
# yang tidak menjelaskan apa pun ke orang yang baru meng-clone repo ini.
if [ ! -f vendor/autoload.php ]; then
    echo "vendor/ kosong, memasang dependensi PHP lebih dulu..."
    composer install --no-interaction --optimize-autoloader
    chown -R www-data:www-data vendor 2>/dev/null || true
fi

exec "$@"
