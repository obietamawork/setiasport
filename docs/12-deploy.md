# 12 — Deploy ke Shared Hosting

Seluruh fitur sudah selesai dan teruji di lokal. Dokumen ini menutup jarak antara
"jalan di Docker" dan "toko bisa memakainya".

Prasyarat: hosting sudah dibeli dan memenuhi [10-spesifikasi-hosting.md](10-spesifikasi-hosting.md).

---

## 1. Siapkan berkas di komputer

Shared hosting tidak punya Node, dan sering `exec`/`proc_open` dimatikan sehingga
Composer tidak bisa jalan di sana. Keduanya dikerjakan di lokal:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

Hasil `public/build` memang sengaja ikut di-commit (lihat `.gitignore`).
Folder `vendor/` diunggah manual bila server tidak punya Composer.

---

## 2. Tata letak di server

Kode aplikasi berada **di luar** jangkauan browser — ini kebocoran paling umum
di setup Laravel di shared hosting:

```
/home/user/
├── setiasport/          ← seluruh kode, TIDAK bisa diakses browser
│   ├── app/ bootstrap/ config/ database/ resources/ routes/ storage/ vendor/
│   └── .env
└── public_html/         ← document root, isinya HANYA milik public/
    ├── index.php  .htaccess  build/  storage/
```

Kalau panel hosting bisa mengarahkan document root ke subfolder, arahkan ke
`setiasport/public` dan lewati pemisahan di atas.

Kalau tidak bisa, salin isi `public/` ke `public_html/`, lalu sesuaikan dua baris
di `public_html/index.php`:

```php
require __DIR__.'/../setiasport/vendor/autoload.php';
$app = require_once __DIR__.'/../setiasport/bootstrap/app.php';
```

---

## 3. Berkas `.env` produksi

```dotenv
APP_ENV=production
APP_DEBUG=false               # WAJIB false — kalau true, pesan galat membocorkan isi .env
APP_URL=https://setiasport.example
APP_KEY=                      # php artisan key:generate

DB_HOST=localhost             # bukan "db" seperti di Docker
DB_DATABASE=…
DB_USERNAME=…
DB_PASSWORD=…

MAIL_MAILER=resend
MAIL_FROM_ADDRESS="noreply@setiasport.example"
RESEND_API_KEY=re_…
RESEND_WEBHOOK_SECRET=whsec_…

BROADCAST_CONNECTION=pusher   # chat CS realtime (KEP-030)
PUSHER_APP_ID=…
PUSHER_APP_KEY=…
PUSHER_APP_SECRET=…
PUSHER_APP_CLUSTER=ap1        # Singapura, terdekat dari Indonesia
PUSHER_HOST=                  # KOSONGKAN di produksi; hanya untuk Reverb lokal
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_CLIENT_HOST=           # KOSONGKAN di produksi
PUSHER_CLIENT_PORT=
PUSHER_CLIENT_TLS=true
```

Tidak ada kunci payment gateway: pembayaran diverifikasi manual (KEP-023). Nomor
rekening, gambar QRIS, dan nomor WhatsApp diisi lewat **Panel → Pengaturan**, bukan
lewat `.env`, supaya pemilik toko bisa menggantinya sendiri tanpa deploy ulang.

### Kunci Pusher untuk chat CS

1. Daftar di pusher.com, buat aplikasi **Channels** baru dengan paket **Sandbox** (gratis),
   klaster `ap1`.
2. Salin `app_id`, `key`, dan `secret` dari tab *App Keys* ke `.env` di atas.
3. Di tab *App Settings*, pastikan **Enable client events dalam keadaan mati**. Kalau
   menyala, browser bisa saling mengirim pesan langsung lewat channel tanpa melewati
   server, dan pesan seperti itu tidak tersimpan maupun diperiksa.
4. Setelah `config:cache`, buka website, kirim satu chat, dan pastikan balasan dari panel
   muncul tanpa memuat ulang halaman.

Kalau kuncinya dikosongkan atau kuota gratisnya habis, chat **tetap berjalan** lewat
polling 15 detik selama jendela chat terbuka. Yang hilang hanya kecepatannya. Ketiadaan
kunci tidak pernah membuat pesan pelanggan hilang.

---

## 4. Perintah sekali jalan

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force        # peran, pengaturan, katalog contoh
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Lalu **ganti kata sandi kedua akun bawaan**, keduanya masih `password`:

```bash
php artisan staf:sandi owner@setiasport.test
php artisan staf:sandi admin@setiasport.test
```

Perintah yang sama dipakai kalau pemilik toko lupa kata sandinya. Pemulihan
lewat email sengaja tidak dibuat: ia bergantung pada cron dan Resend, dan
keduanya justru bisa ikut bermasalah saat dibutuhkan.

Izin tulis yang dibutuhkan: `storage/` dan `bootstrap/cache/`.

### Saat memperbarui aplikasi

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force        # aman diulang, lihat di bawah
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

`db:seed` **aman dijalankan ulang**: ia menambahkan pengaturan, izin, dan template yang
baru tanpa menimpa apa pun yang sudah diisi toko. Rekening dan QRIS tetap, harga katalog
yang sudah dikoreksi tetap, dan kata sandi owner serta admin tidak kembali ke `password`.
Ketiganya dulu ikut tertimpa dan kini dikunci oleh tes (`SeedUlangAmanTest`).

---

## 5. Cron — nyawa sistem

Satu baris ini menggantikan seluruh service `scheduler` di Docker. **Tanpa ini
email tidak pernah terkirim, order tidak pernah kadaluarsa, dan reservasi stok
tidak pernah dilepas** — stok tersedia akan menipis tanpa sebab yang terlihat.

```cron
* * * * * cd /home/user/setiasport && php artisan schedule:run >> /dev/null 2>&1
```

Verifikasi setelah beberapa menit: buka **Dashboard panel**. Kalau cron berjalan,
tidak ada peringatan apa pun. Kalau muncul kotak merah "Tugas otomatis berhenti
berjalan", cron belum jalan dan harus diurus sebelum situs dipakai.

Peringatan itu juga yang akan muncul kalau cron mati di kemudian hari, sehingga
pemilik toko tahu tanpa perlu memeriksa apa pun secara teknis.

---

## 6. Webhook

Satu endpoint perlu didaftarkan. Ia memeriksa tanda tangan dan **menolak permintaan
tanpa rahasia yang benar**, jadi aman dibuka ke publik.

| Penyedia | URL | Peristiwa |
|---|---|---|
| Resend | `https://setiasport.example/webhook/resend` | `email.delivered`, `email.opened`, `email.bounced`, `email.complained` |

Salin *signing secret* Resend ke `RESEND_WEBHOOK_SECRET`. Selama variabel itu
kosong, seluruh notifikasi ditolak — disengaja (KEP-016).

---

## 7. Verifikasi domain Resend

Tambahkan record SPF dan DKIM yang diberikan Resend ke DNS. Kalau DNS sudah di
Cloudflare, prosesnya beberapa menit. Sebelum domain terverifikasi, pengiriman
akan ditolak dan tercatat sebagai `GAGAL` di dashboard panel.

---

## 8. Cloudflare (dianjurkan)

Arahkan domain lewat Cloudflare gratis: SSL, cache aset statis, dan pengurangan
beban shared hosting. Yang perlu diperhatikan:

- **Jangan cache** `/panel/*`, `/lacak/*`, `/keranjang`, `/checkout`, `/webhook/*`
- Aset ber-hash di `/build/*` aman di-cache lama

---

## 9. Pemeriksaan setelah rilis

1. Buka beranda, katalog, dan satu halaman produk
2. Masuk panel, ubah alamat serta jam buka di **Pengaturan**
3. Buat satu pesanan uji ready stock sampai serah terima, lalu batalkan
4. Pastikan email pesanan masuk (cek status di detail pesanan panel)
5. Cetak worksheet dari antrian produksi
6. Pastikan `storage/logs/laravel.log` bersih

---

## 10. Cadangan

Shared hosting **akan** bermasalah suatu hari. Jadwalkan `mysqldump` harian dan
salin ke luar server:

```cron
0 2 * * * mysqldump -u USER -pPASS DBNAME | gzip > /home/user/backup/db-$(date +\%F).sql.gz
```

Simpan juga **dua** folder berkas, bukan hanya satu:

- `storage/app/public`: foto produk, gambar QRIS, preview desain, mockup, portofolio
- `storage/app/private`: **bukti pembayaran dan foto lampiran chat CS** (KEP-029,
  KEP-030). Ini catatan resmi untuk sengketa uang dan komplain; sengaja tidak bisa
  dibuka lewat URL, jadi mudah terlupa saat mencadangkan

File mentah siap cetak tidak ada di sini karena memang tidak pernah masuk sistem (KEP-002).
