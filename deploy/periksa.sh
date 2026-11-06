#!/usr/bin/env bash
# CI lokal (KEP-109): empat pemeriksaan yang sama dengan .github/workflows/ci.yml,
# dijalankan di komputer developer sebelum deploy. GitHub Actions tidak lagi
# berjalan otomatis di setiap push; akunnya terkunci tagihan, dan setiap push
# berakhir dengan email "CI gagal" yang tidak ada hubungannya dengan kodenya.
#
# Pemakaian: bash deploy/periksa.sh   (tesnya sekitar 10 menit)
set -euo pipefail
cd "$(dirname "$0")/.."

echo "1/4 Build aset dan pastikan public/build sinkron dengan kode..."
npm run build --silent >/dev/null
if [ -n "$(git status --porcelain public/build)" ]; then
    git status --porcelain public/build
    echo "public/build tidak sinkron dengan kode. Commit hasil build di atas." >&2
    exit 1
fi

echo "2/4 Analisis statis (Larastan)..."
docker compose exec -T app vendor/bin/phpstan analyse --memory-limit=1G --no-progress

echo "3/4 Audit kerentanan dependensi..."
docker compose exec -T app composer audit --no-interaction
npm audit --audit-level=high

echo "4/4 Tes..."
docker compose exec -T app php artisan test --compact

echo "Semua pemeriksaan lulus."
