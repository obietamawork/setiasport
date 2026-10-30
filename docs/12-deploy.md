# 12 — Deploy ke Shared Hosting

Seluruh fitur sudah selesai dan teruji di lokal. Dokumen ini menutup jarak antara
"jalan di Docker" dan "toko bisa memakainya".

Prasyarat: hosting sudah dibeli dan memenuhi [10-spesifikasi-hosting.md](10-spesifikasi-hosting.md).

---

## 1. Hosting yang dipakai

`setiasport.com` di Rumahweb (cPanel, server `talang.iixcp.rumahweb.net`, SSH port
2223, pengguna `setp2739`). Yang perlu diingat dari hosting ini (KEP-093):

| Keadaan | Akibatnya |
|---|---|
| LiteSpeed, PHP 8.3, MariaDB 10.11 (bukan MySQL 8) | Seluruh tes juga dijalankan di MariaDB 10.11 sebelum rilis pertama |
| `proc_open`, `exec`, `symlink` dimatikan, juga untuk PHP di SSH | Composer dan `storage:link` tidak bisa jalan di server; tugas terjadwal wajib `Schedule::call`, bukan `Schedule::command` (dikunci `PenjadwalHostingTest`) |
| Tidak ada Node | Aset dibangun di lokal; `public/build` sengaja ikut di-commit |
| Document root domain utama tetap `public_html` | Isi `public/` disalin ke sana oleh `deploy/pasang.sh` |

---

## 2. Tata letak di server

Kode aplikasi berada **di luar** jangkauan browser — ini kebocoran paling umum
di setup Laravel di shared hosting:

```
/home/setp2739/
├── setiasport/
│   ├── rilis/<id>/       ← kode tiap rilis, 3 terakhir disimpan; tidak bisa diakses browser
│   ├── terkini → rilis/<id>      untuk cron dan artisan lewat SSH
│   ├── bersama/.env      ← rahasia produksi (chmod 600), dipakai semua rilis
│   ├── bersama/storage/  ← unggahan, bukti bayar, sesi, log; tidak ikut berganti saat deploy
│   └── cadangan/         ← halaman bawaan hosting yang semula ada di public_html
└── public_html/          ← document root, isinya HANYA salinan public/
    ├── index.php         ← dibuat deploy/pasang.sh, menunjuk langsung ke rilis aktif
    ├── .htaccess         ← blok PHP buatan cPanel + pengalihan ke https://setiasport.com + aturan Laravel
    ├── build/  logo-*.webp  favicon.*
    └── storage → ~/setiasport/bersama/storage/app/public
```

`index.php` sengaja menunjuk ke folder rilisnya sendiri, bukan lewat `terkini`:
proses PHP web hidup lama dan mengingat hasil resolusi tautan, sehingga bisa
terus melayani rilis lama beberapa menit setelah tautannya dipindah.

---

## Deploy lewat SSH

Dari komputer pengembang, di Git Bash, dengan container Docker menyala:

```bash
bash deploy/rilis.sh
```

Yang dikirim adalah **commit HEAD**, bukan isi folder kerja; skrip menolak
berjalan kalau masih ada perubahan yang belum di-commit. Jadi jalankan
`npm run build` dan commit hasilnya lebih dulu. Urutannya:

1. Di lokal: `git archive HEAD`, lalu `composer install --no-dev` di dalam container
   (server tidak bisa menjalankan Composer), dikemas dan diperiksa sidik SHA-256-nya
2. Diunggah lewat `scp` ke `~/setiasport/rilis/<tanggal-jam-commit>/`
3. Di server, `deploy/pasang.sh`: menautkan `.env` dan `storage` bersama, `migrate`,
   `db:seed`, `optimize`, menyalin aset ke `public_html`, mengalihkan `index.php`
   ke rilis baru, menghapus rilis lama selain 3 terakhir, lalu memeriksa beranda
   menjawab HTTP 200

Kunci SSH-nya `~/.ssh/setiasport_deploy`. Server, port, dan kunci bisa diganti lewat
variabel `SERVER`, `PORT`, dan `KUNCI`.

**Kembali ke rilis sebelumnya:**

```bash
ls ~/setiasport/rilis
bash ~/setiasport/rilis/<id-lama>/deploy/pasang.sh <id-lama>
```

Database tidak ikut mundur. Migrasi yang sudah berjalan tetap ada, jadi mundur
hanya aman kalau rilis yang dibatalkan tidak menghapus atau mengganti nama kolom.

---

## 3. Berkas `.env` produksi

Letaknya `~/setiasport/bersama/.env`, dengan izin 600. Sandi database dan `APP_KEY`
dibuat acak langsung di server saat persiapan, jadi tidak pernah tersalin ke komputer
lain. Isinya mengikuti `.env.example`, dengan perbedaan berikut:

```dotenv
APP_ENV=production
APP_DEBUG=false               # WAJIB false — kalau true, pesan galat membocorkan isi .env
APP_URL=https://setiasport.com       # https: cookie sesi otomatis bertanda Secure
APP_KEY=                      # base64 dari 32 bait acak
LOG_STACK=daily               # satu berkas per hari, 14 hari disimpan; berkas tunggal tumbuh tanpa batas
LOG_LEVEL=warning             # bukan debug: log tingkat debug bisa memuat data pelanggan

DB_HOST=localhost             # bukan "db" seperti di Docker
DB_DATABASE=…
DB_USERNAME=…
DB_PASSWORD=…

MAIL_MAILER=resend           # jangan "log": dengan LOG_LEVEL=warning email dibuang, padahal panel menulis "Terkirim"
MAIL_FROM_ADDRESS="noreply@setiasport.com"
RESEND_API_KEY=re_…
RESEND_WEBHOOK_SECRET=whsec_…

# Chat CS sedang dimatikan klien (KEP-034): biarkan blok Pusher ini KOSONG.
# Ongkir dan pelacakan paket (KEP-057). Biarkan "mati" sampai akun Biteship
# dibuat; selama itu ongkir dihitung admin seperti sebelumnya. Area asal toko
# BUKAN di sini (KEP-067): diisi staf lewat Pengaturan > Pengiriman di panel,
# dicari lewat kotak pencarian karena kodenya tidak bisa ditebak.
ONGKIR_PENYEDIA=biteship      # mati | biteship
BITESHIP_KUNCI=biteship_test… # sementara kunci TEST: tarifnya nyata (KEP-094); tukar ke kunci live setelah aktivasi disetujui
ONGKIR_KURIR=jne,jnt

FITUR_CS=false                # true hanya kalau chat CS dinyalakan kembali
# Isi hanya kalau chat CS dinyalakan kembali, lihat "Menyalakan chat CS kembali".
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
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

### Menyalakan chat CS kembali

Klien membatalkan chat CS untuk sementara (KEP-034). Kodenya **tidak dihapus**: ia tetap
ada, tetap ikut diuji setiap ada perubahan, dan mati sebagai bawaan. Kalau suatu hari
dibutuhkan lagi:

1. Di `.env` server, ubah `FITUR_CS=false` menjadi `FITUR_CS=true`, lalu
   `php artisan config:cache`. Ini memasang fiturnya: tab **Chat CS** muncul di
   Pengaturan (KEP-036). Selama `false`, tab itu disembunyikan, supaya pemilik toko tidak
   melihat saklar untuk fitur yang tidak dipakai
2. **Panel → Pengaturan → Chat CS → Tampilkan Chat CS**, nyalakan, simpan. Jendela chat
   langsung muncul di website, teks "hubungi kami" beralih dari WhatsApp ke chat, dan
   menu Chat CS muncul di panel. Tanpa kunci Pusher, chat sudah berfungsi, hanya dengan
   jeda sampai 15 detik
3. Isi jam operasional dan template balasan di Pengaturan dan di Ruang CS
4. Opsional, supaya pesan muncul seketika: isi kunci Pusher (langkah di bawah), lalu
   `php artisan config:cache` lagi

Mematikannya sementara cukup lewat saklar di Pengaturan. Riwayat chat tidak ikut hilang;
ia tetap bisa dibaca di menu Arsip chat.

#### Kunci Pusher

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

## 4. Persiapan server (sekali saja)

Sudah dikerjakan untuk `setiasport.com` pada 30 Oktober 2026. Dicatat di sini
untuk pindah hosting atau membangun ulang:

1. Buat database dan penggunanya di cPanel (atau lewat SSH: `uapi Mysql create_database`,
   `create_user`, `set_privileges_on_database`). Nama wajib berawalan `setp2739_`
2. Buat folder `~/setiasport/{rilis,cadangan}` dan `~/setiasport/bersama/storage/`
   beserta isinya (`app/public`, `app/private`, `framework/{cache/data,sessions,views}`,
   `logs`). Folder yang dilalui gambar unggahan (`setiasport`, `bersama`, `storage`,
   `storage/app`) diberi izin 711 dan `app/public` 755, supaya web server bisa
   menyajikan foto produk. Sisanya 700
3. Tulis `~/setiasport/bersama/.env` (bagian 3), `chmod 600`
4. Jalankan `bash deploy/rilis.sh` dari lokal. `migrate`, `db:seed` (peran, pengaturan,
   katalog), dan `optimize` dijalankan olehnya. `key:generate` dan `storage:link` tidak
   dipakai: kuncinya sudah ditulis di langkah 3, dan tautan storage dibuat `pasang.sh`
   dengan `ln -s` karena fungsi `symlink()` PHP dimatikan
5. Pasang cron (bagian 5)

Lalu **ganti kata sandi kedua akun bawaan**. Di rilis pertama keduanya sudah diberi
sandi sementara yang wajib diganti saat pertama masuk, bukan `password`. Cara
termudah: masuk panel, buka menu akun di kanan atas, pilih **Akun saya**. Akun
staf lain (kasir, penjahit) dibuat Pemilik di menu **Pengguna** dengan sandi
sementara yang wajib diganti sendiri saat pertama masuk (KEP-084). Lewat SSH
juga bisa:

```bash
cd ~/setiasport/terkini
php artisan staf:sandi owner@setiasport.test
php artisan staf:sandi admin@setiasport.test
```

Perintah SSH itu yang dipakai kalau pemilik toko lupa kata sandinya dan tidak
ada Pemilik lain yang bisa mengatur ulang dari menu Pengguna. Pemulihan lewat
email sengaja tidak dibuat: ia bergantung pada cron dan Resend, dan keduanya
justru bisa ikut bermasalah saat dibutuhkan.

Izin tulis yang dibutuhkan: `storage/` dan `bootstrap/cache/`.

### Saat memperbarui aplikasi

Cukup `bash deploy/rilis.sh` (lihat "Deploy lewat SSH"). `db:seed` yang ikut dijalankannya
**aman diulang**: ia menambahkan pengaturan, izin, dan template yang
baru tanpa menimpa apa pun yang sudah diisi toko. Rekening dan QRIS tetap, harga katalog
yang sudah dikoreksi tetap, dan kata sandi owner serta admin tidak kembali ke `password`.
Ketiganya dulu ikut tertimpa dan kini dikunci oleh tes (`SeedUlangAmanTest`).

---

## 5. Cron — nyawa sistem

Satu baris ini menggantikan seluruh service `scheduler` di Docker. **Tanpa ini
email tidak pernah terkirim, order tidak pernah kadaluarsa, dan reservasi stok
tidak pernah dilepas** — stok tersedia akan menipis tanpa sebab yang terlihat.

```cron
* * * * * cd /home/setp2739/setiasport/terkini && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Karena `proc_open` dimatikan, `schedule:run` tidak bisa membuka proses baru. Setiap
tugas terjadwal, termasuk antrian email, berjalan di dalam proses itu sendiri lewat
`Schedule::call` (KEP-093).

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
| Resend | `https://setiasport.com/webhook/resend` | `email.delivered`, `email.opened`, `email.bounced`, `email.complained` |

Salin *signing secret* Resend ke `RESEND_WEBHOOK_SECRET`. Selama variabel itu
kosong, seluruh notifikasi ditolak — disengaja (KEP-016).

Setiap kali `bersama/.env` diubah, jalankan `cd ~/setiasport/terkini && php artisan
config:cache`. Tanpa itu aplikasi tetap memakai isi lama yang tersimpan di cache.

---

## 7. Verifikasi domain Resend

Kunci API yang dipasang di server cukup berizin **Sending access**: kunci itu
hanya bisa mengirim, tidak bisa membaca atau mengubah apa pun di akun Resend.
Akibatnya, menambah domain dan webhook dikerjakan di dasbor resend.com, bukan
lewat kunci tersebut.

1. resend.com → **Domains** → **Add Domain**: `setiasport.com`, region **Tokyo
   (ap-northeast-1)**, yang terdekat dari Indonesia.
2. Resend menampilkan record DNS berikut. Isinya disalin persis dari halaman
   Resend, karena kunci DKIM berbeda untuk setiap akun:

   | Jenis | Nama | Isi (yang ditampilkan untuk `setiasport.com`, Oktober 2026) |
   |---|---|---|
   | TXT | `resend._domainkey` | kunci DKIM (`p=MIGfMA0…`) |
   | CNAME | `send` | `send.forge.rmta.net` |
   | CNAME | `rsend` | `rsend-apne1.forge.rmta.net` |

   Nameserver `setiasport.com` milik Rumahweb, jadi record ini ditambahkan di
   cPanel → **Zone Editor** (lewat SSH: `uapi DNS mass_edit_zone`). Semuanya di
   subdomain, sehingga SPF utama `setiasport.com` milik email cPanel tidak disentuh.
3. Tekan **Verify** di Resend. Biasanya selesai dalam beberapa menit.

Sebelum domain terverifikasi, Resend menolak setiap kiriman. Penolakan itu
tercatat **Gagal Kirim** di detail pesanan dan muncul di kartu "Email tidak
sampai" pada dashboard, supaya admin mengabari pelanggannya lewat WhatsApp.

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
7. **Dashboard tidak menampilkan peringatan "Halaman depan masih memuat data contoh".**
   Data contoh portofolio dan testimoni (KEP-035) hanya untuk demo di lokal; jangan
   jalankan `ContohProfilSeeder` di server. Seeder itu menolak berjalan di produksi,
   tapi database lokal yang disalin ke server akan membawa contohnya ikut serta
8. Pemilik toko membaca dan menyesuaikan tab **Pengaturan → Kebijakan & ketentuan**.
   Isinya draf dari developer; selama penanda drafnya belum dihapus, halaman
   **Kesiapan tayang** tetap menahannya (KEP-085)
9. Periksa header keamanan di [securityheaders.com](https://securityheaders.com) atau
   [Mozilla Observatory](https://developer.mozilla.org/en-US/observatory). Hasil yang
   diharapkan minimal A. Kalau ada skrip pihak ketiga baru (misalnya analitik), domainnya
   perlu ditambahkan ke `HeaderKeamanan` lebih dulu, atau peramban akan memblokirnya
   (KEP-086). `<script>` sebaris yang baru wajib membawa `nonce="{{ Vite::cspNonce() }}"`,
   dan `onclick`/`onsubmit` sebaris tidak akan dijalankan sama sekali (KEP-088)
10. Tempel alamat situs di WhatsApp dan pastikan pratinjaunya memuat judul dan gambar toko
11. Buka `/robots.txt`: harus menyebut `Sitemap:` dengan domain asli, bukan `Disallow: /`
    (yang itu tanda `APP_ENV` belum `production`). Daftarkan `/sitemap.xml` di Google
    Search Console (KEP-089)

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
