#!/usr/bin/env bash
#
# Memasang satu rilis di server. Dipanggil deploy/rilis.sh setelah arsipnya
# diekstrak ke ~/setiasport/rilis/<id>; tidak untuk dijalankan di komputer lokal.
#
# Kembali ke rilis sebelumnya cukup dengan menjalankan skrip ini dari rilis itu:
#   bash ~/setiasport/rilis/<id-lama>/deploy/pasang.sh <id-lama>
#
# Tata letak dan alasannya: docs/12-deploy.md bagian 2.
set -euo pipefail

ID="$1"
AKAR="$HOME/setiasport"
RILIS="$AKAR/rilis/$ID"
BERSAMA="$AKAR/bersama"
PUBLIK="$HOME/public_html"
SIMPAN=3

cd "$RILIS"

if [ ! -f "$BERSAMA/.env" ]; then
    echo "Belum ada $BERSAMA/.env. Siapkan server dulu, lihat docs/12-deploy.md." >&2
    exit 1
fi
URL_APP="$(grep -E '^APP_URL=' "$BERSAMA/.env" | cut -d= -f2-)"

# .env dan storage dipakai bersama semua rilis: unggahan, bukti bayar, sesi,
# dan log tidak boleh ikut berganti setiap kali deploy.
rm -rf storage
ln -sfn "$BERSAMA/storage" storage
ln -sfn "$BERSAMA/.env" .env

# Dilewati Composer saat arsip disusun (--no-scripts).
php artisan package:discover --ansi
php artisan migrate --force
php artisan db:seed --force
php artisan optimize

# Aset statis. Document root domain utama cPanel tidak bisa dipindah ke public/,
# jadi isinya disalin ke public_html. Berkas build lama tidak dihapus: namanya
# ber-hash, dan halaman yang terlanjur terbuka di peramban masih memintanya.
for berkas in public/*; do
    nama="$(basename "$berkas")"
    case "$nama" in index.php | storage) continue ;; esac
    if [ -d "$berkas" ]; then
        mkdir -p "$PUBLIK/$nama"
        cp -a "$berkas/." "$PUBLIK/$nama/"
    else
        cp -a "$berkas" "$PUBLIK/$nama"
    fi
done
ln -sfn "$BERSAMA/storage/app/public" "$PUBLIK/storage"

# .htaccess = blok PHP buatan cPanel (dipertahankan apa adanya) + aturan Laravel.
blok_cpanel="$(sed -n '/# BEGIN cPanel-generated/,/# END cPanel-generated/p' "$PUBLIK/.htaccess" 2>/dev/null || true)"
{
    if [ -n "$blok_cpanel" ]; then
        printf '%s\n\n' "$blok_cpanel"
    fi
    # Satu alamat saja. HSTS baru berlaku setelah kunjungan https pertama, dan
    # www serta tanpa-www yang sama-sama hidup memecah peringkat di mesin pencari.
    # /.well-known/ dikecualikan: validasi SSL otomatis cPanel lewat http.
    cat <<HTACCESS
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/\.well-known/
    RewriteCond %{HTTPS} !=on [OR]
    RewriteCond %{HTTP_HOST} !^${URL_APP#https://}$ [NC]
    RewriteRule ^ ${URL_APP}%{REQUEST_URI} [L,R=301]
</IfModule>

HTACCESS
    cat <<'HTACCESS'
# Pengaturan PHP buatan cPanel di folder ini tidak untuk dibaca pengunjung.
<FilesMatch "^(php\.ini|\.user\.ini)$">
    Order allow,deny
    Deny from all
</FilesMatch>

HTACCESS
    cat public/.htaccess
} > "$PUBLIK/.htaccess.baru"
mv -f "$PUBLIK/.htaccess.baru" "$PUBLIK/.htaccess"

# index.php menunjuk langsung ke rilis ini, bukan lewat tautan "terkini": proses
# PHP web hidup lama dan menyimpan hasil resolusi tautan, sehingga bisa terus
# melayani rilis lama beberapa menit setelah tautannya dipindah.
cat > "$PUBLIK/index.php.baru" <<INDEX
<?php

// Dibuat deploy/pasang.sh untuk rilis $ID. Jangan diubah manual: ditimpa setiap deploy.

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

\$akar = '$RILIS';

if (file_exists(\$pemeliharaan = \$akar.'/storage/framework/maintenance.php')) {
    require \$pemeliharaan;
}

require \$akar.'/vendor/autoload.php';

/** @var Application \$app */
\$app = require_once \$akar.'/bootstrap/app.php';

\$app->handleRequest(Request::capture());
INDEX
mv -f "$PUBLIK/index.php.baru" "$PUBLIK/index.php"

# Untuk cron dan perintah artisan lewat SSH: cd ~/setiasport/terkini.
ln -sfn "rilis/$ID" "$AKAR/terkini"

cd "$AKAR/rilis"
ls -1 | sort -r | tail -n +$((SIMPAN + 1)) | xargs -r rm -rf

kode="$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 "$URL_APP")"
echo "Rilis $ID terpasang. Beranda menjawab HTTP $kode."
[ "$kode" = "200" ]
