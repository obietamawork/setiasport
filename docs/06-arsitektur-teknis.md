# 06 — Arsitektur Teknis

## Batasan yang menentukan segalanya

Dua batasan yang kamu sebutkan — **shared hosting** dan **CDN gratis** — bukan detail
kecil. Keduanya menghapus sebagian besar pilihan stack modern:

| Tidak tersedia di shared hosting | Akibatnya |
|---|---|
| Proses daemon / Supervisor | Tidak ada queue worker permanen, tidak ada WebSocket |
| Node.js runtime | Tidak ada Next.js / Nuxt / SSR JavaScript |
| Docker, akses root | Tidak ada Redis, tidak ada layanan tambahan |
| Memori & CPU longgar | Build asset harus dilakukan di lokal, bukan di server |
| Disk besar | Ruang biasanya 5–20 GB, dan **file desain sangat rakus** |

Yang biasanya **ada**: PHP 8.2+, MySQL/MariaDB, cron job (cPanel), HTTPS gratis.

## Rekomendasi stack

**PHP monolith. Laravel 12 + MySQL + Blade.**

- **Laravel** — RBAC, migrasi, queue database, scheduler, validasi, storage: semuanya
  sudah ada. **Dipilih dan sudah dipasang** (Laravel 12 + PHP 8.2), karena hosting
  tujuan dipastikan mendukung PHP 8.2. CodeIgniter 4 tidak lagi diperlukan sebagai
  cadangan.
- **Blade + Tailwind (dikompilasi di lokal, hasil build di-commit) + Alpine.js**
  untuk interaktivitas ringan. Konfigurator produk & form roster cukup dengan Alpine —
  tidak perlu React.
- **Hindari SPA.** Katalog butuh SEO, dan API terpisah + frontend terpisah berarti dua
  deployment di lingkungan yang bahkan tidak nyaman untuk satu.

### Pekerjaan latar (queue)
Driver `database`, dijalankan cron tiap menit:
```
* * * * * cd /home/user/app && php artisan schedule:run >> /dev/null 2>&1
```
Cukup untuk: kadaluarsa order & pelepasan reservasi stok, pengingat belum diambil,
optimasi gambar, rekap harian.

### Realtime
Tidak ada WebSocket. Halaman tracking dan layar antrian pakai **polling** (mis. 30 detik)
atau cukup refresh manual. Untuk skala toko ini, itu lebih dari cukup.

## Soal "CDN gratis" — perlu diluruskan

CDN gratis (Cloudflare free) **tidak menyimpan file-mu**. Dia hanya cache di depan
hosting-mu. Gambar tetap tinggal dan memakan disk shared hosting.

### Layanan pihak ketiga yang dipakai

- **Resend** untuk email transaksional — dipanggil lewat **HTTP API port 443**, bukan SMTP.
  Ini penting: shared hosting sering memblokir port SMTP 25/465/587, tapi hampir tidak
  pernah memblokir HTTPS keluar. Butuh verifikasi domain (SPF/DKIM di DNS).
  Webhook status disimpan ke tabel `email_logs`.

Yang layak dipakai gratis:
- **Cloudflare (free)** di depan domain — SSL, cache aset statis, proteksi dasar,
  mengurangi beban shared hosting secara signifikan. Sangat direkomendasikan.
- **cdnjs / jsDelivr** untuk pustaka pihak ketiga (Alpine, dll).
- Google Fonts (atau self-host font, lebih cepat & lebih privat).

### Masalah nyata: penyimpanan file desain

Ini risiko teknis terbesar di project ini, dan sering baru disadari setelah 3 bulan jalan.

- File mentah desain (AI, PSD, CDR, PDF cetak) bisa **50–300 MB per order**.
- 50 order PreOrder/bulan × 100 MB = **5 GB/bulan**. Disk shared hosting habis dalam
  hitungan bulan, dan backup jadi mustahil.

**Rekomendasi pemisahan yang tegas:**

| Jenis file | Disimpan di mana |
|---|---|
| Foto produk, template desain, mockup preview (JPG/PNG/WebP, dikompres, ≤ 1 MB) | Disk hosting, di-cache Cloudflare |
| Upload desain pelanggan — **hanya preview** (JPG/PNG/PDF, ≤ 10 MB) | Disk hosting |
| File mentah siap cetak (AI/PSD/CDR, ratusan MB) | **DI LUAR sistem** — Google Drive / WA; sistem cukup menyimpan link-nya |

Ini keputusan yang harus disetujui pemilik toko, bukan keputusan teknis murni.
Kalau semua file mentah harus masuk sistem, shared hosting **bukan pilihan yang layak**
dan harus naik ke VPS + object storage.

## Deployment

- Repositori Git; build asset (`npm run build`) di **lokal**, folder `public/build`
  di-commit.
- Deploy via cPanel Git Version Control, atau upload zip. `composer install` dijalankan
  di lokal, folder `vendor` ikut diunggah kalau composer tidak tersedia di server.
- Laravel di shared hosting: root domain diarahkan ke `public/`, kode aplikasi
  diletakkan **di luar** `public_html`. Kalau tidak bisa, `.env` dan `storage/`
  **wajib** diproteksi — ini kebocoran keamanan paling umum di setup shared hosting.
- Backup database terjadwal (cron `mysqldump`) — shared hosting **akan** rusak suatu hari.

## Rencana bertahap

| Fase | Isi | Hasil yang bisa dipakai |
|---|---|---|
| 0 | Keputusan di dokumen 07, skema DB, RBAC, seeder | **SELESAI** — 26 tabel, model, kalkulator harga, 14 test |
| 1 | Katalog publik + panel produk/kategori/stok | **SELESAI** — etalase online + panel katalog |
| 2 | Keranjang, checkout, order, pembayaran manual, tracking + email Resend | **SELESAI** — pesanan masuk lewat web |
| 3 | Konfigurator kustom, roster, alur desain + approval, antrian produksi | **SELESAI** — alur PreOrder penuh |
| 4 | Serah terima (kode ambil), worksheet cetak, dashboard harian | **SELESAI** — operasional harian di sistem |
| 5 | Verifikasi pembayaran manual + webhook Resend | **SELESAI** — bukti diunggah di web dan disimpan privat, status email otomatis |

Email (Resend) masuk di fase 2, bukan fase 5 — tanpa notifikasi, halaman tracking tidak
akan pernah dibuka pelanggan dan seluruh nilainya hilang.

Fase 2 dan 3 adalah inti nilainya. Fase 5 sengaja terakhir, sesuai permintaanmu — dan
memang secara teknis itu urutan yang benar, karena `payments` sudah disiapkan sejak fase 2.
