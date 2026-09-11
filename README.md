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
| Pusher Channels | Chat CS realtime; Reverb menggantikannya di lokal (KEP-030) |
| Plus Jakarta Sans | Tipografi; palet diturunkan dari logo (KEP-019) |

## Menjalankan di lokal

Butuh Docker saja — PHP, Composer, dan MySQL tidak perlu terpasang di komputer.

```bash
docker compose up -d
docker compose exec app php artisan migrate --seed
npm install && npm run build
```

`docker compose up -d` yang pertama memakan waktu lebih lama: container memasang
dependensi PHP-nya sendiri ke volume terpisah (lihat "Kenapa setup-nya seperti
ini"). Setelah itu ia langsung siap.

Buka:

| | |
|---|---|
| Aplikasi | http://localhost:8080 |
| Adminer (DB) | http://localhost:8081 — server `db`, user `setiasport`, pass `secret` |
| Reverb (websocket chat CS) | localhost:8082, dipakai widget chat secara otomatis |

Chat CS realtime di lokal berjalan lewat container `reverb`, pengganti Pusher yang
berbicara dengan protokol yang sama. Buka website di satu tab dan `/panel/cs` di tab
lain untuk mencobanya dua arah.

Pelanggan punya dua jalur (KEP-020): tautan bertoken di email seperti biasa,
atau akun dengan kata sandi lewat `/masuk`. Keduanya berlaku penuh.

Akun internal (kata sandi keduanya `password`, ganti sebelum deploy):

| Email | Peran | Memegang |
|---|---|---|
| owner@setiasport.test | Pemilik | Semuanya, termasuk pengaturan dan pengguna |
| admin@setiasport.test | Admin | Seluruh operasi harian |

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
- **GD dikompilasi dengan WebP.** Gambar produk dikonversi ke WebP, dengan jalan
  mundur ke JPEG bila server tujuan tidak mendukungnya.
- **`vendor/` dan `node_modules/` tidak ikut mount Windows.** Ini satu-satunya
  penyimpangan yang alasannya kecepatan, bukan kemiripan dengan produksi. OPcache
  memeriksa tanggal setiap berkas yang di-cache, dan satu pemeriksaan lewat mount
  Windows berbiaya milidetik; dengan ~600 berkas vendor, satu halaman butuh **5-7
  detik**. Dengan vendor di volume Linux, halaman yang sama terbuka dalam **~180
  ms**, dan karena kode aplikasi tetap di mount, hasil edit tetap langsung terlihat
  tanpa membersihkan cache apa pun.

  Konsekuensinya satu: **`composer` dijalankan di dalam container.**

  ```bash
  docker compose exec app composer require paket/baru
  composer install          # di host, hanya supaya IDE tetap bisa autocomplete
  ```

  Keduanya membaca `composer.lock` yang sama, jadi isinya identik. `node_modules`
  di dalam container sengaja dibiarkan kosong: Vite dijalankan di host.

## Struktur

```
app/Enums/          Status, tipe, dan basis harga — nilai domain, bukan string lepas
app/Models/         Eloquent; Order sengaja TIDAK menyimpan kolom total
app/Services/       PriceCalculator (KEP-009), OrderStatusResolver (KEP-001),
                    Keranjang, Checkout, ReservasiStok, AlurPesanan, PengirimEmail,
                    AlurDesain (gerbang approve), RosterPemain
app/Mail/Transport/ Transport Resend lewat HTTP API, bukan SMTP
resources/views/components/  <x-logo/>, memakai public/logo.svg bila ada
app/Http/           Controller publik & panel, middleware izin RBAC
resources/views/    publik/ (etalase) dan panel/ (alat kerja) — dua register visual
resources/views/emails/  Email pesanan; gaya inline karena klien email mengabaikan CSS
database/seeders/   Peran bawaan, pengaturan, dan 5 produk penguji model data
docker/             Image PHP-Apache dan konfigurasi vhost
docs/               Rancangan, log keputusan, panduan visual
tests/Feature/      Penjaga rumus harga, aturan uang, akses panel, dan alur pesanan
```
