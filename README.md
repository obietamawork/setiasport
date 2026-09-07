# Setia Sport

Toko alat olahraga + workshop kustom. Satu aplikasi web, dua alur pesanan
(Ready Stock & PreOrder), tracking pesanan sampai **siap diambil di toko**.
Tanpa pengiriman.

**Siap deploy** — langkahnya di [docs/12-deploy.md](docs/12-deploy.md).

**Rancangan lengkap ada di [docs/](docs/).** Baca [docs/08-log-keputusan.md](docs/08-log-keputusan.md)
lebih dulu — semua keputusan fondasi beserta alasannya ada di sana, dan kode ini
merujuk ke nomor KEP-xxx.

## Stack

| | |
|---|---|
| Laravel 12 · PHP 8.2 · MySQL 8 | Target akhir: shared hosting |
| Blade + Alpine.js | Tanpa SPA — katalog butuh SEO |
| Queue driver `database` | Dijalankan cron, bukan daemon |
| Resend (HTTP API) | Notifikasi email; tanpa SMTP, tanpa WhatsApp API |
| Midtrans Snap | Pembayaran online; nonaktif sampai kuncinya diisi |

## Menjalankan di lokal

Butuh Docker saja — PHP, Composer, dan MySQL tidak perlu terpasang di komputer.

```bash
docker compose up -d
docker compose exec app php artisan migrate --seed
npm install && npm run build
```

Buka:

| | |
|---|---|
| Aplikasi | http://localhost:8080 |
| Adminer (DB) | http://localhost:8081 — server `db`, user `setiasport`, pass `secret` |

Akun internal contoh (kata sandi semua: `password`):

| Email | Peran |
|---|---|
| owner@setiasport.test | Owner |
| admin@setiasport.test | Admin / CS |
| desainer@setiasport.test | Desainer |
| produksi@setiasport.test | Operator Printing **dan** Jahit |
| gudang@setiasport.test | Gudang |

## Perintah yang sering dipakai

```bash
docker compose exec app php artisan migrate:fresh --seed   # bangun ulang DB
docker compose exec app php artisan test                   # jalankan test
docker compose exec app php artisan tinker                 # REPL
docker compose logs -f app                                 # log Apache
docker compose exec app php artisan schedule:run           # jalankan tugas terjadwal
docker compose exec app php artisan queue:work             # proses antrian email
docker compose down                                        # matikan
```

## Kenapa setup-nya seperti ini

Beberapa pilihan yang terlihat aneh sebenarnya disengaja agar lokal semirip
mungkin dengan shared hosting — supaya masalah ketahuan sekarang, bukan setelah
deploy:

- **Apache, bukan nginx.** Shared hosting memakai Apache, jadi `.htaccess` dan
  `mod_rewrite` berperilaku sama di kedua tempat.
- **Document root diarahkan ke `public/`.** Kode aplikasi, `.env`, dan `storage/`
  berada di luar jangkauan browser — persis penataan yang ditargetkan di produksi.
- **`php.ini` menyalin batas hosting** (`upload_max_filesize`, `memory_limit`).
  Upload preview desain 10 MB yang gagal akan gagal di lokal juga.
- **Service `scheduler` menggantikan cron.** Di produksi ia diganti satu baris
  crontab; tidak ada Supervisor atau daemon yang perlu diandalkan.
- **Test berjalan di MySQL, bukan SQLite in-memory.** Skema ini memakai kolom
  JSON dan unique index bernilai NULL yang perilakunya berbeda antar engine.

## Struktur

```
app/Enums/          Status, tipe, dan basis harga — nilai domain, bukan string lepas
app/Models/         Eloquent; Order sengaja TIDAK menyimpan kolom total
app/Services/       PriceCalculator (KEP-009), OrderStatusResolver (KEP-001),
                    Keranjang, Checkout, ReservasiStok, AlurPesanan, PengirimEmail,
                    AlurDesain (gerbang approve), RosterPemain
app/Services/Pembayaran/  Gerbang pembayaran; Midtrans, bisa ditukar Xendit
app/Mail/Transport/ Transport Resend lewat HTTP API, bukan SMTP
app/Http/           Controller publik & panel, middleware izin RBAC
resources/views/    publik/ (etalase) dan panel/ (alat kerja) — dua register visual
resources/views/emails/  Email pesanan; gaya inline karena klien email mengabaikan CSS
database/seeders/   Peran bawaan, pengaturan, dan 5 produk penguji model data
docker/             Image PHP-Apache dan konfigurasi vhost
docs/               Rancangan, log keputusan, panduan visual
tests/Feature/      Penjaga rumus harga, aturan uang, akses panel, dan alur pesanan
```
