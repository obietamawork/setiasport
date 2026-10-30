#!/usr/bin/env bash
#
# Deploy ke hosting lewat SSH, dijalankan dari komputer pengembang (Git Bash):
#   bash deploy/rilis.sh
#
# Yang dikirim adalah commit HEAD, bukan isi folder kerja. Hosting tidak punya
# Node dan mematikan proc_open, jadi build aset (sudah ikut di-commit) dan
# Composer dikerjakan di sini, di dalam container Docker. Lihat docs/12-deploy.md.
set -euo pipefail

SERVER="${SERVER:-setp2739@talang.iixcp.rumahweb.net}"
PORT="${PORT:-2223}"
KUNCI="${KUNCI:-$HOME/.ssh/setiasport_deploy}"
SSH_OPSI=(-i "$KUNCI" -o BatchMode=yes)

# Git Bash mengubah argumen seperti /tmp/... menjadi path Windows saat memanggil
# program Windows (docker), padahal path itu milik container. ssh dan scp
# bawaan Git Bash tidak terpengaruh; di Linux/macOS variabel ini diabaikan.
export MSYS_NO_PATHCONV=1

cd "$(dirname "$0")/.."

if [ -n "$(git status --porcelain)" ]; then
    echo "Ada perubahan yang belum di-commit. Yang dikirim hanya HEAD, jadi commit dulu." >&2
    exit 1
fi

ID="$(date +%Y%m%d-%H%M%S)-$(git rev-parse --short HEAD)"
ARSIP="$(mktemp -d)/rilis.tar.gz"

echo "Menyusun rilis $ID tanpa paket pengembangan..."
git archive --format=tar HEAD | docker compose exec -T app sh -c '
    set -e
    rm -rf /tmp/rilis /tmp/rilis.tar.gz && mkdir /tmp/rilis && tar -x -C /tmp/rilis
    cd /tmp/rilis && rm -rf tests docs docker .github
    composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts --quiet
    tar -czf /tmp/rilis.tar.gz .
'
docker compose exec -T app cat /tmp/rilis.tar.gz > "$ARSIP"
SIDIK="$(docker compose exec -T app sha256sum /tmp/rilis.tar.gz | cut -d' ' -f1)"
[ "$(sha256sum "$ARSIP" | cut -d' ' -f1)" = "$SIDIK" ] || { echo "Arsip rusak saat disalin dari container." >&2; exit 1; }

echo "Mengunggah $(du -h "$ARSIP" | cut -f1)..."
scp "${SSH_OPSI[@]}" -P "$PORT" -q "$ARSIP" "$SERVER:setiasport/rilis/$ID.tar.gz"
rm -rf "$(dirname "$ARSIP")"

ssh "${SSH_OPSI[@]}" -p "$PORT" "$SERVER" "
    set -e
    cd ~/setiasport/rilis
    [ \"\$(sha256sum $ID.tar.gz | cut -d' ' -f1)\" = $SIDIK ] || { echo 'Arsip rusak saat diunggah.' >&2; exit 1; }
    mkdir $ID && tar -xzf $ID.tar.gz -C $ID && rm $ID.tar.gz
    chmod 700 $ID
    bash $ID/deploy/pasang.sh $ID
"
