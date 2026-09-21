# 08 — Log Keputusan

Catatan keputusan yang sudah diambil, beserta alasannya. Ditambah seiring diskusi.

---

## KEP-001 — Keranjang campuran: satu order, progres per item
**Status:** TERKUNCI · 2026-09-07

Ready stock dan PreOrder boleh dicampur dalam satu checkout. Pelanggan mendapat **satu
kode order**. Setiap `order_item` punya progresnya sendiri; status order adalah agregat.
Order baru berstatus `Siap Diambil` ketika **semua** item siap — pelanggan datang sekali.

**Konsekuensi teknis:**
- `order_items.status_item` adalah sumber kebenaran; `orders.status` diturunkan darinya
- `order_items.fulfillment_type` di-snapshot saat checkout
- Halaman tracking menampilkan timeline per item, bukan satu timeline tunggal

---

## KEP-002 — File desain: hanya preview yang masuk sistem
**Status:** TERKUNCI · 2026-09-07

Sistem hanya menyimpan file preview: JPG/PNG/PDF, maksimum 10 MB per file.
File mentah siap cetak (AI/PSD/CDR) **tidak diunggah ke sistem** — ditukar lewat
Google Drive atau WhatsApp, dan sistem hanya menyimpan **link**-nya di field khusus.

**Konsekuensi teknis:**
- Shared hosting tetap layak dipakai; tidak perlu VPS/object storage
- `design_assets` dapat kolom `external_url` (nullable) selain `path`
- Validasi upload wajib ketat: whitelist MIME, batas ukuran, nama file di-sanitasi
- Gambar dikompres & dibuat turunan WebP saat unggah (via queue cron)

---

## KEP-003 — Pembayaran fleksibel: Lunas atau DP, pelunasan bisa tunai di toko
**Status:** ARAH DISEPAKATI, MEKANISME MASIH DIBAHAS · 2026-09-07

Pelanggan boleh memilih **bayar lunas** (digital) atau **bayar DP** (digital), dengan
sisa dilunasi **di toko** — tunai maupun digital. Artinya sistem harus menangani
pembayaran online dan offline dalam satu order secara setara.

**Konsekuensi teknis (sudah pasti):**
- Tabel `payments` many-to-one ke `orders` — **wajib, bukan opsional**
- Tidak boleh ada kolom `paid_at` / `payment_status` tunggal di `orders`
- Angka yang selalu dihitung ulang dari `payments`, tidak pernah disimpan sebagai
  kolom yang bisa basi: `total`, `sudah_dibayar`, `sisa_tagihan`
- Pembayaran offline dicatat kasir dengan `dicatat_oleh` + waktu → wajib masuk audit log,
  karena ini titik rawan penyelewengan uang tunai

**Masih dibahas:** besaran & aturan DP, kapan pelunasan ditagih, kebijakan pembatalan,
dan penanganan perubahan pesanan setelah DP masuk.

---

## KEP-004 — Kanal notifikasi: email via Resend, TANPA WhatsApp API
**Status:** ARAH DISEPAKATI, MEKANISME MASIH DIBAHAS · 2026-09-07

Notifikasi otomatis dikirim lewat **email menggunakan Resend**. Tidak ada WhatsApp
Business API (berbayar & butuh verifikasi bisnis).

**Konsekuensi teknis (sudah pasti):**
- Resend dipanggil lewat **HTTP API (port 443)**, bukan SMTP. Ini justru menguntungkan:
  shared hosting sering memblokir port SMTP 25/465/587, tapi hampir tidak pernah
  memblokir HTTPS keluar
- Butuh verifikasi domain di Resend (record DNS SPF/DKIM) — mudah kalau DNS di Cloudflare
- **Email menjadi wajib diisi saat checkout**, tidak lagi opsional
- Pengiriman email harus lewat queue (cron), jangan blocking saat request checkout
- Batas gratis Resend perlu dicek terhadap volume order (umumnya cukup untuk skala ini)

**Risiko yang diakui:** di segmen pelanggan ini (tim futsal, sekolah), email sering tidak
dibaca atau masuk folder promosi. Mitigasi dibahas di bawah.

**Masih dibahas:** bentuk akses pelanggan (magic link vs akun berpassword), dan cadangan
kalau email tidak dibaca.

---

## KEP-005 — DP minimum 50%, pelanggan boleh membayar lebih
**Status:** TERKUNCI · 2026-09-07

Saat checkout PreOrder, pelanggan memilih nominal bayar dengan batas bawah **50% dari
total**. Boleh menaikkan sampai lunas. Persentase minimum disimpan di `settings`
(`dp_minimum_persen`) agar pemilik toko bisa mengubahnya tanpa deploy ulang.

**Alasan:** 50% bukan angka acak — itu ambang yang menutup biaya produksi. Jersey kustom
bernama orang tidak bisa dijual ke siapa pun kalau tidak diambil, jadi DP harus menanggung
HPP. Membolehkan bayar lebih tidak menambah risiko dan memberi keleluasaan pelanggan.

**Konsekuensi teknis:**
- Validasi sisi server: `nominal_dp >= ceil(total * dp_minimum_persen / 100)`
- Nominal DP dikunci saat pembayaran dibuat; kalau total berubah kemudian (lihat KEP-007),
  yang menyesuaikan adalah **sisa tagihan**, bukan DP yang sudah dibayar
- Ready stock tetap lunas di muka — tidak ada opsi DP

---

## KEP-006 — Pelunasan dibayar saat pengambilan di toko
**Status:** DIREVISI · 2026-09-07, direvisi KEP-028 pada 2026-09-10

> Untuk barang kustom, pelunasan kini jadi gerbang **sebelum** Siap Diambil: barang
> selesai diproduksi, lalu ditahan sampai lunas, dan kode ambil baru terbit setelah itu.
> Membayar di kasir saat datang tetap bisa. Catatan di bawah dipertahankan sebagai sejarah.

Tidak ada tahap "Menunggu Pelunasan" yang menahan produksi. Barang diproduksi sampai
selesai dengan modal DP, lalu sisa dibayar saat serah terima — tunai atau digital.

**Konsekuensi teknis:**
- Alur PreOrder di dokumen 02 **dipangkas**: tahap `Menunggu Pelunasan` dihapus.
  Setelah Packing langsung `Siap Diambil`.
- Layar serah terima menggabungkan dua aksi jadi satu: **catat pelunasan + tandai diambil**.
  Nominal terisi otomatis dari sisa tagihan; kasir memilih metode dan menyimpan.
- Order tidak boleh berstatus `Selesai` kalau `sisa_tagihan > 0` — dijaga di level aplikasi
- Halaman tracking menampilkan "Sisa yang dibayar saat ambil: Rp X" secara menonjol
- Risiko yang diterima: barang siap tapi tidak diambil. Ditangani lewat pengingat
  terjadwal dan laporan "siap > 7 hari" di dashboard, bukan lewat penahanan barang.

---

## KEP-007 — Perubahan pesanan dicatat sebagai penyesuaian
**Status:** TERKUNCI · 2026-09-07

Tambah pemain, ganti nama punggung berbayar, tambah patch, diskon nego — semuanya menjadi
baris `order_adjustments`, bukan hasil mengedit item yang sudah ada.

```
order_adjustments   id, order_id, order_item_id (nullable), 
                    deskripsi, jumlah (boleh negatif), 
                    dibuat_oleh, dibuat_pada
```

**Alasan:** total dan sisa tagihan menjadi **transparan dan bisa ditelusuri**. Pelanggan
melihat "Tambah 2 pemain — Rp 240.000" di halaman tracking-nya **sebelum** datang ke toko.
Tidak ada kejutan angka di kasir — ini inti dari "DP yang nyaman".

**Konsekuensi teknis:**
- `total = subtotal_items + sum(adjustments) - diskon`, selalu dihitung, tidak disimpan
- Penyesuaian bernilai negatif menangani diskon dan kompensasi kesalahan toko
- Penyesuaian setelah desain disetujui tetap diperbolehkan (misal tambah pemain), tapi
  **tidak boleh** mengubah roster yang sudah dicetak — perlu aturan operasional terpisah
- Setiap penyesuaian masuk audit log; nominal besar sebaiknya butuh peran Owner/Admin

---

## KEP-008 — Akses pelanggan: magic link tanpa password, approve butuh konfirmasi
**Status:** TERKUNCI · 2026-09-07

Tidak ada pendaftaran akun dan tidak ada password. Setiap order punya **token akses**
yang dikirim lewat email sebagai tautan sekali-klik. Halaman tracking juga bisa dibuka
manual dengan **kode order + email**.

Tombol **Setujui Desain** meminta konfirmasi tambahan sebelum aktif: pelanggan mengetik
namanya dan mencentang pernyataan bahwa desain tidak dapat diubah setelah disetujui.

**Alasan:** password tidak menambah keamanan nyata di sini — pemulihannya lewat email
yang sama, jadi keamanannya toh bersandar pada email. Yang ditambahkannya hanya friksi
checkout. Sementara tautan yang bisa dibagikan justru berguna untuk order tim: satu
pemesan, banyak anggota yang ingin memantau. Perlindungan diletakkan tepat di titik
yang berkonsekuensi, bukan di pintu masuk.

**Konsekuensi teknis:**
- `orders.access_token` — acak, panjang (≥32 byte), unik, dibuat saat order dibuat
- Halaman tracking bersifat **publik-bertoken**. Karena tautan bisa beredar di grup,
  **jangan menampilkan data sensitif**: no HP disamarkan, email disamarkan,
  tidak ada data pelanggan lain
- Approval mencatat: `approved_by_name` (yang diketik), `approved_at`, `approved_ip`,
  dan `approved_design_asset_id` (versi mockup yang disetujui)
- Rate limit pada form akses manual (kode order + email) untuk mencegah penebakan
- `customers.user_id` tetap nullable — akun pelanggan bisa ditambahkan di masa depan
  tanpa mengubah apa pun yang sudah berjalan

---

## KEP-009 — Harga bertingkat per jumlah, dihitung per baris item
**Status:** TERKUNCI · 2026-09-07

Produk kustom punya tabel tier harga. Tier ditentukan oleh **qty pada satu baris item**,
bukan gabungan seluruh keranjang.

```
product_price_tiers  id, product_id, min_qty, max_qty (nullable = tak terbatas), 
                     harga_satuan
```

Keringanan lintas item (misal 12 model A + 12 model B ingin dihargai seperti 24 pcs)
ditangani admin lewat `order_adjustments` bernilai negatif — bukan lewat aturan otomatis.
Aturan penggabungan di konveksi terlalu bergantung konteks (bahan sama? warna sama?)
untuk dikodekan tanpa sering salah.

### ★ Basis biaya: PER_PCS vs PER_ITEM

Ini jebakan yang hampir selalu kena di sistem konveksi. Sebagian opsi dikenakan per potong
(bahan, lengan, nameset), sebagian **sekali saja** per baris pesanan (setup desain kustom).

`option_values` **wajib** punya kolom `basis`:

| Nilai | Arti | Contoh |
|---|---|---|
| `PER_PCS` | dikali qty | Bahan Milano +7.000/pcs |
| `PER_ITEM` | sekali per baris pesanan | Setup desain kustom +75.000 |

Disebut `PER_ITEM`, **bukan** `PER_ORDER`: kalau pelanggan memesan dua model jersey
berbeda, setup desainnya memang dua kali. Penamaan `PER_ORDER` akan menyesatkan sejak
KEP-001 membolehkan banyak item dalam satu order.

### Rumus resmi

```
harga_satuan   = tier(product, qty) + Σ(opsi dengan basis PER_PCS)
subtotal_item  = harga_satuan × qty + Σ(opsi dengan basis PER_ITEM)
total_order    = Σ(subtotal_item) + Σ(adjustments) − diskon
sisa_tagihan   = total_order − Σ(payments berstatus LUNAS)
```

**Contoh (18 jersey, Milano, lengan panjang, nameset, setup kustom):**
```
tier 12-23 pcs                 95.000
+ Milano                        7.000
+ lengan panjang                8.000
+ nameset                      10.000
                              ────────
harga_satuan                  120.000  × 18 = 2.160.000
+ setup desain (PER_ITEM)                        75.000
                                              ──────────
total                                         2.235.000
DP minimum 50%                                1.117.500
```
Kalau setup ikut dikali 18, tagihan melar Rp 1.275.000 — dan pelanggan baru sadar
di kasir. Itulah alasan kolom `basis` ini tidak boleh ditunda.

**Konsekuensi teknis:**
- Semua komponen harga di-**snapshot** ke `order_item_options` saat checkout
  (label, basis, nominal), agar order tetap terbaca benar setelah harga master berubah
- Perhitungan harga harus punya satu fungsi tunggal yang dipakai konfigurator **dan**
  server. Server adalah otoritas — harga dari sisi klien tidak pernah dipercaya

---

## KEP-010 — Harga dihitung otomatis, pelanggan langsung bisa membayar
**Status:** TERKUNCI · 2026-09-07

Tidak ada tahap "Menunggu Konfirmasi Harga". Pelanggan mengonfigurasi produk, harga
muncul seketika, langsung checkout dan bayar DP. Admin tidak menjadi penghambat.

**Alasan:** seluruh tujuan web ini adalah mengurangi percakapan. Kalau pelanggan tetap
harus menunggu admin membalas untuk tahu harganya, web hanya jadi formulir kontak yang
mahal. Kasus tidak biasa tetap bisa ditangani setelah order masuk lewat KEP-007.

**Konsekuensi teknis:**
- Struktur harga (tier + opsi) harus **lengkap dan benar** di data master. Produk dengan
  data harga tidak lengkap tidak boleh tayang
- Validasi MOQ dilakukan di konfigurator **dan** diulang di server saat checkout
- Konfigurator menampilkan rincian harga secara terbuka (bukan hanya total), agar
  pelanggan paham dari mana angkanya — ini mencegah pertanyaan yang justru ingin dihindari

---

## KEP-011 — Produk hybrid didukung: satu produk, dua jalur pembelian
**Status:** TERKUNCI · 2026-09-07

Satu produk boleh berjenis `BOTH` — dapat dibeli sebagai ready stock **atau** dipesan
kustom. Halaman produknya menyediakan dua jalur: **Beli Ready Stock** dan **Pesan Kustom**.

**Konsekuensi teknis:**
- `products.fulfillment_type` bernilai `READY_STOCK | PREORDER | BOTH`
- `order_items.fulfillment_type` di-snapshot per baris — sudah disiapkan KEP-001,
  jadi satu produk `BOTH` bisa muncul dua kali di keranjang dengan jalur berbeda
- **Harga kedua jalur terpisah:** jalur ready stock memakai `harga_dasar +
  variant.tambahan_harga`; jalur kustom memakai `product_price_tiers`. Keduanya tidak
  saling memengaruhi
- **Stok hanya dipotong jalur ready stock.** Jalur kustom tidak menyentuh stok
- **MOQ hanya berlaku di jalur kustom**
- Roster dan alur desain hanya muncul di jalur kustom
- UI harus membuat perbedaan waktu sangat jelas: "Siap diambil besok" vs
  "Selesai ±7 hari kerja setelah desain disetujui". Ini beda ekspektasi paling tajam
  di seluruh web, dan paling berpotensi jadi komplain kalau disamarkan

---

## KEP-012 — Lingkungan lokal Docker dibuat mencerminkan shared hosting
**Status:** TERKUNCI · 2026-09-07

Pengembangan dilakukan di lokal memakai Docker; shared hosting dibeli belakangan.
Susunan containernya sengaja tidak "yang paling nyaman untuk developer", melainkan
**yang paling mirip target produksi**, agar masalah muncul sekarang dan bukan
setelah deploy.

| Pilihan | Alasan |
|---|---|
| **Apache**, bukan nginx | Shared hosting memakai Apache; `.htaccess` dan `mod_rewrite` jadi berperilaku sama di kedua tempat |
| Document root ke `public/` | Kode aplikasi, `.env`, dan `storage/` di luar jangkauan browser — penataan yang ditargetkan di produksi |
| `php.ini` menyalin batas hosting | Upload preview 10 MB yang gagal akan gagal di lokal juga, bukan kejutan setelah rilis |
| Service `scheduler` (`schedule:work`) | Menggantikan cron; di produksi diganti satu baris crontab. Tidak ada Supervisor yang diandalkan |
| MySQL 8, bukan SQLite | Termasuk untuk test — skema memakai kolom JSON dan unique index bernilai NULL yang perilakunya berbeda antar engine |
| PHP 8.2 | Versi yang dipastikan tersedia di hosting tujuan |

**Konsekuensi teknis:**
- `composer install` dijalankan di dalam container, bukan di komputer pemakai —
  PHP dan Composer tidak perlu terpasang di host
- Entrypoint container memperbaiki kepemilikan `storage/` dan `bootstrap/cache/`
  setiap start, karena bind mount dari Windows membuatnya milik root sementara
  Apache berjalan sebagai `www-data`
- Database test terpisah (`setiasport_test`) supaya `migrate:fresh` di test tidak
  menghapus data pengembangan

---

## KEP-013 — Roster diisi setelah pesanan dibuat, bukan saat checkout
**Status:** TERKUNCI · 2026-09-07

Dokumen 03 semula menempatkan pengisian nama–nomor–ukuran di dalam konfigurator,
sebelum checkout. Itu diubah: konfigurator hanya menangani opsi dan jumlah, dan
roster diisi **di halaman pesanan pelanggan** setelah order dibuat.

**Alasan:** kapten tim memesan lebih dulu, lalu mengumpulkan nama dan ukuran dari
anggotanya selama beberapa hari. Mewajibkan roster lengkap di checkout akan
menghentikan pesanan yang sebenarnya sudah pasti — persis pelanggan yang paling
ingin kita layani.

Roster bisa diubah **sampai desain disetujui**, lalu terkunci bersama desainnya.
Batas itu bukan pilihan sembarangan: setelah approve, namanya sudah masuk mesin
cetak dan jersey bernama orang tidak bisa dijual ulang.

**Konsekuensi teknis:**
- Keranjang tidak menyimpan roster; hanya produk, jumlah, dan opsi
- `RosterPemain::simpan()` menolak perubahan bila `rosterTerkunci()`
- Desainer **tidak boleh** mengirim mockup selama roster belum lengkap pada
  produk yang mewajibkannya — lebih murah dihentikan di layar desain daripada
  di meja printing
- Nomor punggung ganda ditolak di klien maupun server

---

## KEP-014 — Status item menyatakan divisi; status job menyatakan aktivitas
**Status:** TERKUNCI · 2026-09-07

Antrian produksi memakai dua tingkat yang tidak boleh dicampur:

| | Arti | Contoh |
|---|---|---|
| `order_items.status_item` | **Divisi mana** yang sedang memegang barang | `JAHIT` = ada di tangan penjahit |
| `production_jobs.status` | **Apa** yang divisi itu lakukan | `ANTRI` / `DIKERJAKAN` / `SELESAI` |

Dua tahap punya status antrian tersendiri yang terlihat pelanggan
(`ANTRIAN_DESAIN`, `ANTRIAN_PRODUKSI`), karena di situlah penungguan paling
terasa. Menekan "Mulai kerjakan" pada keduanya benar-benar memindahkan status
item — supaya timeline pelanggan menunjukkan waktu yang sebenarnya, bukan tahap
yang tercentang tanpa pernah dilewati.

**Konsekuensi teknis:**
- Operator hanya bisa menyentuh item yang berada di divisi yang izinnya ia punya
- Menyelesaikan mockup menutup job `DESAIN`; permintaan revisi membukanya kembali
- Layar antrian berbentuk kartu, bukan tabel: operator membukanya dari HP di
  dekat mesin (docs/04)

---

## KEP-015 — Gerbang pembayaran di balik satu antarmuka, Midtrans lebih dulu
**Status:** DIBATALKAN · 2026-09-08, digantikan KEP-023 pada 2026-09-09

> Toko memutuskan tidak jadi memakai gerbang pembayaran. Seluruh kode di bawah
> sudah dihapus, bukan dinonaktifkan: driver Midtrans, webhook, konfigurasi,
> tesnya, kolom `provider`, `provider_ref`, dan `payload_json` di `payments`.
> Catatan ini disimpan supaya alasannya bisa dibaca kalau kelak dipertimbangkan
> lagi. Yang berlaku sekarang KEP-023.

Penyedia pembayaran disembunyikan di balik `GerbangPembayaran`. Midtrans Snap
diimplementasikan lebih dulu karena **QRIS sudah termasuk** tanpa integrasi
terpisah, dan QRIS hampir wajib untuk pasar ini. Xendit bisa menggantikannya
dengan menulis satu kelas, tanpa menyentuh alur pesanan.

**Selama merchant belum disetujui, gerbangnya nonaktif** dan seluruh sistem tetap
berjalan penuh dengan transfer manual. Konfigurasi setengah jadi diperlakukan
sama dengan tidak ada: tombol yang muncul tapi gagal di tengah jalan lebih buruk
daripada tombol yang tidak ada.

**Konsekuensi teknis:**
- `payments.provider_ref` berisi `<kode order>-P<id payment>`, bukan kode pesanan
  saja — satu pesanan bisa punya beberapa transaksi (DP lalu pelunasan), dan
  Midtrans menuntut `order_id` unik untuk setiap transaksi
- Notifikasi diperiksa tanda tangannya (SHA-512) **sebelum** apa pun disentuh.
  Tanpa itu siapa saja bisa menandai pesanan orang lain lunas
- Notifikasi yang diulang tidak diproses dua kali; Midtrans mengirim ulang
  sampai dibalas 200
- `capture` dengan `fraud_status` selain `accept` tidak dianggap lunas —
  kasir yang memutuskan
- Kegagalan gateway tidak menutup jalur transfer manual

---

## KEP-016 — Transport Resend ditulis sendiri, bukan lewat paket
**Status:** TERKUNCI · 2026-09-08

Pengiriman email memakai transport khusus yang memanggil HTTP API Resend
langsung, alih-alih memasang paket resmi.

**Alasan:** yang kita butuhkan dari Resend bukan hanya "kirim", melainkan **id
pesan** yang dikembalikannya. Tanpa id itu, webhook status tidak punya cara
mencocokkan email mana yang bounce — padahal justru itulah yang membuat admin
tahu kapan harus menyerah pada email dan menindaklanjuti lewat WhatsApp
(docs/03). Transport sendiri hanya ~60 baris, memberi kendali penuh atas
korelasi itu, dan menambah nol dependensi untuk diunggah ke shared hosting.

**Konsekuensi teknis:**
- Baris `email_logs` dibuat **sebelum** email diantre, lalu id-nya ikut sebagai
  header `X-Log-Id`; listener `MessageSent` mencatat balik id dari Resend
- Webhook memverifikasi tanda tangan Svix (HMAC-SHA256) dan **menolak seluruh
  permintaan bila rahasianya belum dipasang** — endpoint yang menerima apa saja
  lebih berbahaya daripada endpoint yang tidak ada
- Notifikasi berumur lebih dari 5 menit ditolak untuk menutup celah pengulangan
- Peristiwa Resend bisa datang tidak berurutan, jadi status hanya boleh maju.
  `BOUNCED` selalu menang karena itu kabar terpenting

---

## KEP-017 — Hanya dua peran: Pemilik dan Admin
**Status:** TERKUNCI · 2026-09-08

Susunan tujuh peran diringkas menjadi dua atas permintaan pemilik toko.

| Peran | Memegang |
|---|---|
| **Pemilik** | Semuanya, termasuk pengaturan, pengguna, dan laporan |
| **Admin** | Seluruh operasi harian: pesanan, pembayaran, kelima antrian produksi, katalog, stok, serah terima |

**Alasan:** di toko sebesar ini satu orang memang mengerjakan banyak hal
sekaligus. Peran yang lebih rinci hanya menciptakan kerepotan tanpa membeli
keamanan apa pun.

**Daftar permission tetap rinci** dan tidak disederhanakan. Peran baru bisa
ditambahkan lewat panel kapan saja tanpa menyentuh kode, jadi keputusan ini
tidak mengunci masa depan. Yang berubah hanya siapa memegang apa.

**Konsekuensi teknis:**
- Batas yang tersisa adalah `pengaturan.kelola` dan `pengguna.kelola`: Admin
  menjalankan toko, Pemilik menentukan aturan mainnya
- Menu "Antrian kerja" kini menampilkan kelima divisi untuk Admin dan Pemilik
- Peran dan akun contoh dari susunan lama dihapus oleh seeder

---

## KEP-018 — Gambar produk boleh diunggah atau ditunjuk ke CDN
**Status:** TERKUNCI · 2026-09-08

Dua sumber didukung sekaligus, dipilih per gambar:

| Sumber | Kapan dipakai |
|---|---|
| **Unggah** | Toko bisa langsung jalan tanpa layanan tambahan |
| **Tautan CDN** | Saat katalog membesar dan kuota disk shared hosting mulai terasa |

Berkas yang diunggah **wajib diproses**, tidak pernah disimpan apa adanya: foto
mentah dari HP lazimnya 3-8 MB, dan menayangkannya begitu saja membuat katalog
berat dibuka dari jaringan seluler sekaligus menghabiskan disk dalam hitungan
bulan. Setiap unggahan dikecilkan ke maksimum 1400px, dibuatkan versi kecil
480px untuk kartu katalog, dan dikonversi ke WebP.

**Konsekuensi teknis:**
- `product_images` menampung `path` atau `external_url`; URL luar menang, jadi
  gambar bisa dipindahkan ke CDN kapan saja tanpa mengubah halaman
- Arah foto dikoreksi dari metadata EXIF, kalau tidak sebagian foto tampil terbaring
- **WebP punya jalan mundur ke JPEG.** Tidak semua shared hosting mengompilasi
  GD dengan dukungan WebP, dan unggahan tidak boleh gagal hanya karena itu
- Menghapus gambar ikut membuang berkasnya; berkas yatim adalah cara paling
  sunyi menghabiskan kuota disk

---

## KEP-019 — Identitas visual diturunkan dari logo, bukan dikarang
**Status:** TERKUNCI · 2026-09-08

Palet hijau lapangan dan oranye pada rancangan awal diganti seluruhnya oleh
warna merek: **navy, merah, kuning**. Logo juga menetapkan aturan pakainya, dan
aturan itu diikuti apa adanya: navy mendominasi sebagai warna struktur,
sementara merah dan kuning hanya muncul sebagai garis tipis penanda seperti dua
batang yang mengapit kata WONOGIRI.

Tipografi memakai **Plus Jakarta Sans** buatan Tokotype, dirancang untuk
identitas kota Jakarta. Dipilih karena memang dibuat untuk merek Indonesia,
bukan karena netral.

**Aturan yang ditegakkan** (rinciannya di [11-panduan-visual.md](11-panduan-visual.md)):
- Merah hanya untuk harga dan satu aksi utama per layar
- Kuning tidak pernah menampung teks, hanya menjadi garis
- **Tidak ada tanda pisah em di teks antarmuka.** Judul halaman memakai `|`
- Berkas logo cukup ditaruh di `public/logo.svg`; komponen `<x-logo />`
  memakainya otomatis di seluruh halaman

---

## KEP-020 — Akun pelanggan sebagai jalur kedua, bukan pengganti
**Status:** TERKUNCI · 2026-09-08

Pelanggan kini punya dua cara membuka pesanannya, dan **keduanya berlaku penuh**:

| Jalur | Untuk siapa |
|---|---|
| **Tautan bertoken di email** (KEP-008) | Semua orang. Tidak pernah dimatikan |
| **Akun dengan kata sandi** | Pelanggan berulang yang ingin melihat semua pesanannya dalam satu halaman |

**Akun tidak pernah menjadi syarat.** Checkout tamu tetap tanpa pendaftaran,
karena memaksa daftar di checkout menurunkan konversi, dan tautan yang bisa
dibagikan ke grup tim adalah fitur, bukan kekurangan. Halaman masuk pun
menampilkan jalur tanpa akun secara terbuka, bukan menyembunyikannya.

Akun ditawarkan di dua titik yang sudah terbukti paling masuk akal: sebagai
centang opsional saat checkout, dan di halaman pesanan setelah pesanan nyata
terbentuk.

**Email adalah kuncinya.** Pemesan tamu yang mendaftar belakangan langsung
mewarisi seluruh riwayat pesanannya, karena `customers` memang sudah dicocokkan
berdasarkan email sejak awal. Tidak ada data yang perlu dipindahkan.

### Pelanggan disimpan terpisah dari staf

Pelanggan memakai tabel `customers` dan **penjaga sesi sendiri** (`pelanggan`),
bukan digabung ke `users`. Alasannya keamanan, bukan kerapian: dengan penjaga
terpisah, akun pelanggan mustahil ikut terbawa ke panel internal karena salah
konfigurasi peran.

**Konsekuensi teknis:**
- `customers` mendapat `password` (nullable), `remember_token`, dan indeks unik
  pada `email`
- Seluruh rute serta pemeriksaan izin panel menyebut guard `web` secara
  eksplisit, tidak lagi bergantung pada guard mana yang kebetulan menjadi bawaan
- Pengalihan tamu sadar konteks: `/panel/*` ke halaman masuk staf, sisanya ke
  halaman masuk pelanggan
- Pelanggan yang sudah masuk tidak mengetik ulang datanya di checkout, dan
  emailnya dikunci karena itulah yang mengikat pesanan ke akunnya
- Pelanggan lama tanpa kata sandi yang mencoba masuk diarahkan ke pendaftaran
  dengan email terisi, bukan diberi pesan gagal yang membingungkan

---

## KEP-021 — Pendaftaran wajib dikonfirmasi lewat email
**Status:** TERKUNCI · 2026-09-08

Ditemukan tinjauan keamanan otomatis pada rancangan awal KEP-020, sebelum
sempat dipakai siapa pun.

### Yang bisa terjadi

Pendaftaran semula langsung mengaktifkan akun dan memasukkan penggunanya. Karena
pesanan diikat ke pelanggan **berdasarkan email**, siapa pun yang tahu alamat
email seorang pelanggan dapat:

1. Membuka `/daftar`, memasukkan email itu dengan kata sandi karangannya
2. Langsung mewarisi seluruh riwayat pesanan pemilik email, termasuk rincian
   barang, nominal, dan **kode ambil**
3. Menimpa nama serta nomor HP pemilik aslinya

Kode ambil adalah yang ditunjukkan di kasir saat mengambil barang, jadi ini
bukan sekadar kebocoran data. Dan email pelanggan bukan rahasia: tertulis di
catatan toko dan diketahui sesama anggota tim.

### Yang berlaku sekarang

Pendaftaran hanya membuat **pendaftaran tertunda**. Kata sandi disimpan tapi
belum berlaku sampai tautan konfirmasi di email diklik, dan tautan itu hanya
bisa dibuka oleh yang benar-benar menguasai email tersebut.

**Akun yang sudah aktif tidak pernah disentuh pendaftaran ulang.** Kata sandi,
nama, dan nomor HP-nya dibiarkan apa adanya, dan tidak ada email yang dikirim.

**Konsekuensi teknis:**
- `customers` mendapat `email_terverifikasi_pada`, `token_verifikasi` (disimpan
  ter-hash), dan `token_dibuat_pada`; tautan berlaku 24 jam
- `akunAktif()` menggantikan `punyaAkun()` di setiap tempat yang menentukan
  boleh-tidaknya masuk
- Checkout tetap membuat pesanan seperti biasa; hanya akunnya yang menunggu.
  Pelanggan tidak pernah tertahan gara-gara email belum dibuka
- Pemesan tamu **tidak menimpa profil** pelanggan yang akunnya sudah aktif
- Halaman masuk memberi pesan yang sama untuk email tak dikenal maupun kata
  sandi salah, dan halaman daftar memberi jawaban yang sama apa pun keadaan
  emailnya, sehingga keduanya tidak bisa dipakai menebak email pelanggan
- Peringatan "belum dikonfirmasi" hanya muncul **setelah** kata sandi benar,
  jadi bukan celah penebakan

### Yang tidak berubah

Tautan bertoken di email pesanan tetap jalur utama dan tidak tersentuh sama
sekali (KEP-008, KEP-020). Pelanggan yang tidak mau membuat akun tidak
kehilangan apa pun, dan tidak ada yang menunggu email untuk melihat pesanannya.

---

## KEP-022 — Paket penutup dipilih dari sudut biaya dukungan, bukan idealisme
**Status:** TERKUNCI · 2026-09-08

Anggaran klien terbatas, dan pekerjaan yang sudah selesai jauh melampauinya.
Karena itu sisa lingkup tidak dipilih berdasarkan "apa yang paling bernilai bagi
toko", melainkan pertanyaan yang berbeda:

> **Apa yang mencegah klien menelepon setelah proyek diserahkan?**

Dukungan pascaserah terima tidak dibayar, dan di situlah margin proyek kecil
sebenarnya hilang. Kriteria itu menghasilkan lima item:

| Item | Telepon yang dicegah |
|---|---|
| Denyut penjadwal | "Stok habis padahal barangnya ada" |
| Proteksi kirim ganda | "Kenapa pesanannya dobel" |
| Halaman galat kustom | "Web-nya rusak" |
| Laporan sederhana | "Tolong hitungkan omzet bulan ini" (selamanya, gratis) |
| Tab katalog berjumlah | Permintaan klien yang sudah dijanjikan |

**Yang sengaja tidak dikerjakan** di anggaran ini: halaman kesehatan sistem
terpisah (cukup satu blok di dashboard), perbandingan produk berdampingan,
soft delete, dan notifikasi email saat penjadwal mati (ironis: kalau penjadwal
mati, email itu sendiri tidak terkirim).

### Denyut penjadwal

Kegagalan paling berbahaya di shared hosting bukan galat, melainkan cron yang
berhenti tanpa suara. Saat itu terjadi: email tidak terkirim, pesanan tidak
pernah kadaluarsa, dan **stok terkunci selamanya** sehingga stok tersedia
menipis tanpa sebab yang terlihat. Toko baru sadar berhari-hari kemudian.

Penjadwal kini menuliskan cap waktu tiap menit, dan dashboard menyalakan
peringatan bila capnya lebih tua dari sepuluh menit. Peringatannya ditulis untuk
pemilik toko: menyebut akibatnya dan menyuruh menghubungi hosting, bukan
menampilkan istilah teknis.

### Tab katalog dengan kolom jumlah

Stok toko dan Kustom bukan dua nilai filter, melainkan dua cara belanja. Yang
pertama menjelajah dan membeli; yang kedua menyusun spesifikasi dan
membandingkan.

Karena itu tab Kustom punya kolom **"Untuk berapa pcs?"** yang menghitung ulang
harga **semua kartu** sekaligus. Kapten tim tidak peduli "mulai Rp 82.000"; ia
mau tahu harga di 24 pcs. Satu input mengubah katalog dari daftar menjadi alat
penawaran, memakai rumus tier yang sama persis dengan server (KEP-009).

---

## KEP-023 — Pembayaran diverifikasi manual, bukti lewat WhatsApp
**Status:** TERKUNCI · 2026-09-09 · menggantikan KEP-015 · jalur WhatsApp diganti KEP-029

> Verifikasi manualnya tetap berlaku. Yang berubah hanya jalur buktinya: klien menetapkan
> tidak ada transaksi lewat WhatsApp, jadi bukti kini diunggah di halaman pesanan dan
> disimpan privat (KEP-029).

Toko sudah punya QRIS statis dan rekening bank yang dipakai sehari-hari, dan
pemiliknya memang membuka mutasi setiap pagi. Gerbang pembayaran menambah biaya
per transaksi, proses persetujuan merchant, dan satu integrasi yang bisa rusak
diam-diam, untuk menggantikan pekerjaan yang sudah berjalan.

**Jalurnya:** pelanggan melihat QRIS dan **dua nomor rekening** toko di halaman pesanannya,
transfer sendiri (DP atau lunas), lalu menekan satu tombol yang membuka WhatsApp
dengan pesan **sudah terisi kode pesanan, nama, dan nominal**. Buktinya dikirim
di sana. Admin mencocokkannya dengan mutasi, lalu menekan Verifikasi di panel.

**Bukti bayar TIDAK diunggah lewat web.** Formulir unggah di sisi pelanggan
dihapus. Alasannya bukan teknis: pemilik toko tetap harus membuka WhatsApp untuk
menjawab pertanyaan pelanggan, jadi bukti yang masuk lewat jalur kedua justru
menambah tempat yang harus diperiksa. Satu antrian lebih sulit dilupakan
daripada dua.

**Konsekuensi teknis:**
- `PaymentMethod` tinggal `TUNAI`, `TRANSFER`, `QRIS`. Yang lama dipetakan lewat
  migrasi, bukan dibiarkan sebagai nilai yatim
- Metode dan bukti diisi **saat verifikasi**, bukan saat checkout: sebelum uangnya
  masuk, tidak ada yang tahu pelanggan akan membayar lewat apa
- Bukti yang diunggah admin disimpan sebagai jejak kalau kelak ada selisih setoran
- Nomor WhatsApp dirapikan ke bentuk internasional oleh `nomor_wa()`; pemilik toko
  akan mengetiknya sebagai "0812-3456-7890" cepat atau lambat
- `qris_path` bertipe `berkas` dan **tidak bisa diubah lewat formulir teks
  pengaturan**. Satu POST yang mengarahkannya ke gambar lain berarti uang
  pelanggan masuk ke rekening orang lain
- Tanpa gerbang, tidak ada yang memberi tahu sistem bahwa uang sudah masuk.
  Batas waktu pembayaran (KEP-005) jadi satu-satunya penjaga stok yang direservasi

---

## KEP-024 — Halaman depan adalah profil usaha, katalog belakangan
**Status:** TERKUNCI · 2026-09-09

Pembeli terbesar di sini bukan orang yang iseng menelusuri produk, melainkan
pengurus sekolah, dinas, atau klub yang sedang memutuskan konveksi mana yang
boleh memegang anggaran mereka. Yang mereka cari lebih dulu adalah bukti
pekerjaan sejenis, alamat yang bisa didatangi, dan orang yang bisa dihubungi.
Etalase produk tidak menjawab satu pun dari itu.

Karena itu halaman depan berurutan: siapa kami, angka yang paling sering
ditanyakan, tentang usaha, portofolio instansi, cara memesan, testimoni, lokasi,
**baru** cuplikan katalog. Pembeda dua jalur (stok toko vs kustom) pindah ke
halaman katalog, tepat sebelum orang mulai memilih.

**Konsekuensi teknis:**
- `portofolios` dan `testimonis` berdiri sendiri, **tidak diturunkan dari
  `orders`**: pesanan yang layak dipamerkan belum tentu masih ada datanya, dan
  nama pemesan tidak otomatis boleh disebut di halaman publik
- Tidak ada formulir testimoni untuk publik. Tanpa moderasi ia jadi sasaran spam,
  dan moderasi berarti antrian kerja yang tidak dibayar
- Portofolio **wajib** punya foto: portofolio tanpa gambar tidak meyakinkan siapa pun
- Setiap bagian dilewati sepenuhnya kalau isinya belum ada, supaya toko yang baru
  dipasang tidak menampilkan kerangka kosong
- Isi profil disimpan sebagai pengaturan bertipe `teks` (berparagraf), bukan
  `string` yang dibatasi satu baris

---

## KEP-025 — `vendor/` keluar dari mount Windows demi kecepatan lokal
**Status:** TERKUNCI · 2026-09-09

KEP-012 menetapkan lingkungan lokal harus mencerminkan shared hosting. Ini satu
penyimpangan yang disengaja, dan alasannya bukan kemiripan melainkan waktu tunggu.

OPcache memeriksa tanggal setiap berkas yang di-cache. Lewat mount Docker Desktop
di Windows, satu pemeriksaan berbiaya milidetik; satu permintaan halaman menyentuh
sekitar 645 berkas, 590 di antaranya milik `vendor/`. Terukur: **5.900 ms rata-rata
untuk `/katalog`**, padahal permintaan yang sama di dalam proses PHP selesai dalam
56 ms. Waktunya habis di pemeriksaan berkas, bukan di kode maupun basis data.

`vendor/` dan `node_modules/` dipindah ke volume Linux. Halaman yang sama kini
**~180 ms**, dan suite test turun dari 3:23 ke 2:11.

**Yang sengaja TIDAK dilakukan:** mematikan `opcache.validate_timestamps`. Itu
memang memberi angka serupa, tapi menukarnya dengan hal yang jauh lebih mahal —
hasil edit tidak muncul sampai OPcache direset, dan orang akan menghabiskan waktu
mencari bug yang sebenarnya cuma cache basi. Menyimpan kode aplikasi tetap di mount
membuat pemeriksaannya tinggal 44 berkas: cukup murah, dan edit tetap seketika.

`config:cache` dan `route:cache` juga ditolak untuk lokal dengan alasan yang sama:
keduanya menghemat ~40 ms tapi membuat perubahan config dan rute diam-diam
diabaikan.

**Konsekuensi teknis:**
- `composer` dijalankan **di dalam container**; entrypoint memasangnya otomatis
  saat volume masih kosong, jadi `docker compose up` tetap cukup untuk clone baru
- `vendor/` di host dibiarkan ada untuk autocomplete IDE. Keduanya dipasang dari
  `composer.lock` yang sama, jadi isinya identik
- `node_modules` di container sengaja kosong: Vite dijalankan di host
- Di produksi ini tidak berlaku sama sekali; shared hosting tidak punya mount lintas
  sistem berkas, dan `vendor/` diunggah apa adanya (docs/12)

---

## KEP-026 — Pengiriman terbatas, khusus barang stok toko
**Status:** TERKUNCI · 2026-09-10 · melonggarkan KEP-001

Rancangan awal menolak pengiriman sepenuhnya, dan penolakan itu benar untuk barang
konveksi: jersey dicoba dulu di toko, dan kalau ukurannya meleset diperbaiki saat
itu juga. Tapi toko juga menjual bola dan sepatu, dan sebagian pembelinya tinggal
jauh dari Wonogiri. Menolak mengirimkan sekotak bola hanya karena sistemnya pernah
memutuskan "tidak ada pengiriman" berarti menolak penjualan tanpa alasan.

**Batasnya dijaga ketat:**
- Hanya pesanan yang isinya **murni stok toko**. Pesanan yang memuat satu saja barang
  kustom ditolak, dan ditolak di model, bukan cuma disembunyikan di tampilan
- Paket **hanya berangkat setelah lunas**. Tidak ada kasir di depan pintu pelanggan
  yang bisa menagih sisanya
- Pesanan yang disetel dikirim **tidak bisa diserahkan lewat meja kasir**, supaya
  barang tidak keluar tanpa nomor resi yang tercatat

**Yang sengaja TIDAK dibangun:** hitung ongkir, integrasi API kurir, penarikan status
resi otomatis, retur. Ongkir dicatat sebagai baris penyesuaian biaya (KEP-007) supaya
pelanggan melihatnya sebagai angka yang bisa dijelaskan. Pelacakan resi diserahkan ke
situs pihak ketiga: menulis integrasi kurir untuk beberapa paket sebulan adalah
pekerjaan yang tidak akan pernah balik modal, dan satu lagi hal yang bisa rusak diam-diam.

**Alamat tidak diminta di checkout.** Pengiriman adalah pengecualian yang disepakati
lewat WhatsApp; menaruh kolom alamat di checkout membuatnya terbaca sebagai layanan
yang selalu tersedia, padahal ongkirnya perlu dihitung manual lebih dulu.

**Konsekuensi teknis:**
- Status baru `DIKIRIM`, dan ia **bukan status final**. Paket yang sudah berangkat
  belum tentu sampai, dan pesanan yang nyangkut di kurir harus tetap terlihat di
  daftar aktif sampai ada yang menekan "sudah diterima"
- `OrderStatusResolver` kini melihat `metode_ambil` dan `diterima_pada`, satu-satunya
  tempat status agregat memandang sesuatu di luar status item
- Stok fisik berkurang saat paket diserahkan ke kurir, sama seperti saat barang
  diserahkan di kasir: yang berbeda cuma siapa yang membawanya pergi
- Daftar jasa kirim adalah pengaturan bertipe teks, satu nama per baris. Kerja sama
  toko dengan kurir berubah tanpa perlu deploy, dan tidak cukup penting untuk punya
  tabel sendiri
- Email `pesanan-siap` bercabang: pesanan yang akan dikirim tidak diberi kode ambil
  dan tidak disuruh datang ke toko

---

## KEP-027 — Kustom dan stok toko dipisah jadi dua tab
**Status:** TERKUNCI · 2026-09-10

KEP-001 memutuskan satu keranjang campuran menghasilkan satu pesanan dengan progres
per item, dan itu tetap berlaku di tingkat data. Yang berubah adalah cara keduanya
**ditampilkan**, karena orang yang mengurus konveksi dan orang yang mengambil barang
dari rak sedang mengerjakan pekerjaan yang berbeda.

**Di panel** daftar pesanan punya tab Kustom dan Stok toko. Pesanan campuran masuk ke
tab Kustom, karena bagian kustomnya yang menentukan kapan seluruh pesanan bisa
diserahkan, dan ia diberi penanda "Campuran" supaya tidak ada yang kaget menemukan
barang stok di dalamnya. Setiap pesanan masuk tepat satu tab, jadi hitungan kedua tab
selalu berjumlah persis sebanyak seluruh pesanan.

**Di halaman pesanan pelanggan** progres dipecah jadi dua kelompok dengan judul
sendiri. Menaruhnya berurutan tanpa pemisah membuat pelanggan mengira semuanya
menunggu hal yang sama, padahal jersey menunggu persetujuan desain sementara bolanya
sudah ada di rak sejak hari pertama. Judul kelompok disembunyikan kalau pesanan cuma
berisi satu jenis: memberi label pada satu-satunya kelompok tidak menjelaskan apa pun.

---

## KEP-028 — Alur baru dari klien: tahap besar untuk pelanggan, subprogres untuk produksi
**Status:** TERKUNCI · 2026-09-10 · merevisi KEP-006 dan KEP-014

Klien menetapkan urutan yang dibaca pelanggan:

- **Custom jersey:** Pesanan → DP → Desain & Data → Produksi → Pelunasan → Siap Diambil → Selesai.
  Produksi punya subprogres: Printing Jersey → Potong Kain → Press / Sublime → Jahit → Finishing / QC
- **Ready stock:** Pesanan → Pembayaran → Disiapkan → Packing → Ambil/Kirim → Selesai

**Dua daftar, dua pembaca.** Status item tetap rinci karena operator butuh tahu satu
jersey ada di meja potong atau di mesin press (KEP-014). Pelanggan yang melihat sebelas
baris hanya belajar bahwa pesanannya rumit. `FasePesanan` mengelompokkan status internal
jadi tahap besar, dan hanya Produksi yang membuka subprogresnya: di situlah penungguan
paling lama terasa, dan di situ pula pelanggan paling sering bertanya "sampai mana".

**Pelunasan jadi gerbang sebelum Siap Diambil.** Ini merevisi KEP-006 (pelunasan saat
pengambilan). Barang kustom dikerjakan dengan modal DP, jadi toko berhak menahannya
sampai lunas, dan kode ambil baru terbit setelah itu. Membayar di kasir tetap bisa:
mencatat pelunasan di kasir menaikkan item ke Siap dengan sendirinya, lalu barang
langsung diserahkan.

**Konsekuensi teknis:**
- Status baru `POTONG`, `PRESS`, `MENUNGGU_PELUNASAN`. Packing tidak lagi dilewati
  barang kustom; Finishing / QC adalah tahap terakhirnya
- Gerbang pelunasan **tidak punya tombol** di panel, sama seperti gerbang persetujuan
  desain. Item naik ke Siap hanya karena uangnya tercatat masuk. Kalau staf bisa
  menaikkannya sendiri, kode ambil terbit dan barang bisa keluar sebelum lunas
- Pesanan yang sudah lunas di muka tidak berhenti di gerbang
- Satu item yang menunggu pelunasan menahan seluruh pesanan, termasuk barang stok di
  pesanan campuran. Status pesanan baru: **Menunggu Pelunasan**
- Pelanggan mendapat email begitu pesanan masuk gerbang ini; tanpa itu mereka tidak
  tahu bahwa kini gilirannya membayar. Email "pembayaran diterima" tidak dikirim kalau
  pembayaran itu langsung membuat pesanan siap, supaya satu kejadian tidak jadi dua email
- Daftar pekerjaan produksi kini **diturunkan** dari urutan status, bukan ditulis ulang
  di `Checkout`. Dua daftar yang dijaga terpisah sempat berselisih: daftar tulisan tangan
  masih menyiapkan packing untuk barang kustom setelah alurnya berubah
- Logika timeline dipindah dari Blade ke `LinimasaItem`, termasuk kasus yang tidak
  tercermin di status item: paket yang sedang dikirim, dan barang stok yang sudah siap
  di pesanan yang masih menunggu jerseynya (tidak disebut "Siap Diambil", karena kode
  ambilnya belum terbit)
- Tidak ada migrasi data: saat alur diubah, belum ada pesanan kustom di tahap Packing
  dan aplikasi belum dipasang di hosting

---

## KEP-029 — Bukti bayar: diunggah di website, disimpan privat, tidak pernah memverifikasi dirinya sendiri
**Status:** TERKUNCI · 2026-09-10 · menggantikan jalur WhatsApp di KEP-023

Klien menetapkan **tidak ada transaksi lewat WhatsApp**. Alasannya keamanan: penipu
menyamar sebagai toko lewat WhatsApp dengan rekening pengganti, pelanggan tertipu, lalu
komplainnya ke toko. Tombol "kirim bukti lewat WhatsApp" dari KEP-023 justru mengajarkan
kebiasaan yang dimanfaatkan penipu itu.

**Hybrid:**
- Pelanggan mengunggah bukti di halaman pesanannya. Bukti otomatis menempel ke tagihan
  dan nominal yang benar, jadi admin tidak perlu mencocokkan manual
- Admin tetap bisa melampirkan bukti saat verifikasi, untuk yang diterima lewat chat CS
  atau langsung di toko

**Yang membuatnya aman:**
1. **Bukti bukanlah verifikasi.** Tangkapan layar transfer bisa diedit dalam semenit.
   Tombol Verifikasi kini mewajibkan admin mencentang pernyataan bahwa ia sudah melihat
   nominal itu masuk di mutasi rekening atau riwayat QRIS, dan pernyataan itu tercatat
   atas namanya. Unggahan pelanggan tidak mengubah status apa pun
2. **Disimpan privat.** Bukti memuat nama dan nomor rekening pelanggan. Berkasnya di disk
   `local` (di luar folder web) dan hanya dilayani lewat rute yang memeriksa hak akses:
   pemegang tautan pesanan itu, atau staf dengan izin `order.lihat`. Tautan pesanan lain
   tidak bisa dipakai untuk membukanya
3. **Hanya gambar, di-encode ulang.** GD membuang metadata, dan berkas yang menyamar
   sebagai gambar tidak selamat dari proses itu. PDF ditolak karena tidak bisa
   diperlakukan sama
4. **Dibatasi.** Enam unggahan per sepuluh menit per alamat IP; tanpa batas, siapa pun
   yang memegang satu tautan bisa memenuhi disk hosting
5. **Halaman pesanan satu-satunya sumber rekening.** Ia menyatakan terang-terangan bahwa
   toko tidak pernah meminta transfer lewat WhatsApp atau ke rekening lain. Email
   pelunasan sengaja **tidak** memuat nomor rekening, karena email bisa dipalsukan;
   ia mengarahkan pelanggan ke halaman pesanan

**Konsekuensi teknis:**
- Baris tagihan pelunasan dibuat saat pelanggan mengunggah bukti, dengan sisa tagihan
  saat itu. Menyimpannya jauh hari akan membuat nominalnya basi begitu ada penyesuaian
  biaya seperti ongkir
- Layar kasir memperingatkan kalau pelanggan sudah mengunggah bukti, supaya pembayaran
  yang sama tidak dicatat dua kali
- Bukti lama dari sebelum keputusan ini (di disk publik) tetap bisa dibuka lewat rute
  berizin yang sama

---

## KEP-030 — Chat CS di website: Pusher sebagai pengantar, UI dan catatannya milik sendiri
**Status:** TERKUNCI · 2026-09-10

Klien ingin CS di website, bukan di WhatsApp, dengan tiga alasan: pertanyaan stok akan
banyak, pelanggan tidak perlu "keluar dari toko", dan penipu yang menyamar sebagai toko
lewat WhatsApp membuat pelanggan tertipu lalu komplainnya ke toko. Klien juga menetapkan
**komplain hanya dilayani lewat chat CS** dan **tidak ada transaksi lewat WhatsApp**.

**Pusher Channels (paket Sandbox gratis) sebagai pengangkut, UI dibuat sendiri.**
"UI bawaan" sebenarnya tidak tersedia: produk chat Pusher (Chatkit) ditutup sejak 2020, dan
Ably menyediakan bahan untuk merakit, bukan widget CS dengan kotak masuk admin. Tawk.to
dipertimbangkan dan ditolak: gratis dan lengkap, tapi data chat tersimpan di pihak ketiga
dan tidak terhubung ke pesanan, padahal log chat kini catatan resmi komplain.

**Arsitektur yang ringan untuk shared hosting:**
- Pesan disimpan di MySQL. Pusher hanya mengantar kabar "ada pesan baru"; koneksi
  websocket yang terbuka lama dipegang Pusher, bukan hosting. Hosting cukup menerima
  permintaan pendek dan mengirim satu permintaan HTTPS keluar per pesan
- `ShouldBroadcastNow`, bukan antrian: di shared hosting antrian dijalankan cron sekali
  per menit, dan chat yang tertunda semenit bukan chat
- Pusher yang gagal tidak menggagalkan pengiriman pesan. Pesannya sudah tersimpan;
  widget menyusul lewat polling 15 detik, dan hanya selama jendela chat terbuka.
  Chat tetap jalan kalau kuota gratis habis atau kuncinya belum diisi
- Echo dan pusher-js (21 KB gzip) dimuat lambat, hanya oleh pengunjung yang benar-benar
  membuka chat atau punya percakapan berjalan. Pengunjung lain tidak mengunduhnya dan
  tidak memakan jatah 100 koneksi gratis
- Konfigurasi realtime dioper lewat halaman saat dijalankan, bukan dibakar ke hasil build,
  supaya satu build berlaku di lokal maupun hosting
- **Lokal memakai Reverb**, server websocket resmi Laravel yang berbicara dengan protokol
  Pusher. Aplikasi memakai driver `pusher` di kedua tempat, jadi yang dites di lokal adalah
  jalur yang sama dengan produksi. Reverb hanya di `require-dev` dan container `reverb`

**Keamanan:**
- Pelanggan tanpa akun dikenali lewat cookie httpOnly terenkripsi berisi token acak; yang
  tersimpan di database hanya hash-nya. Nomor percakapan saja tidak membuka apa pun
- Otorisasi channel realtime ditulis sendiri dengan rumus protokol Pusher: pelanggan hanya
  bisa berlangganan channel percakapannya sendiri, staf hanya channel staf dan hanya
  dengan izin `cs.balas`
- Isi pesan selalu dipasang sebagai teks (`x-text`), tidak pernah sebagai HTML
- Kode pesanan yang **diketik** pelanggan ditandai "belum terverifikasi"; hanya chat yang
  dibuka dari halaman pesanan bertoken, atau oleh akun pemiliknya, yang terverifikasi.
  Tanpa ini siapa pun bisa menyebut kode orang lain lalu meminta staf mengubah nama
  pengambil
- Lampiran hanya gambar, di-encode ulang, disimpan di disk privat, dan dilayani lewat
  rute yang memeriksa pemiliknya
- Batas laju: membuka percakapan 5 kali per jam per IP, mengirim 20 pesan per menit

**Risiko terbesarnya bukan teknis:** chat yang tidak dijawab lebih buruk daripada tidak
ada chat. WhatsApp berbunyi di HP pemilik toko; panel web tidak, kecuali sedang dibuka.
Penangkalnya berlapis:
- Sambungan staf dipasang di **semua** halaman panel: bunyi, angka di menu, dan penanda
  "Chat baru" di kepala halaman, di halaman mana pun admin sedang bekerja
- Chat yang belum dibalas lebih dari 15 menit (bisa diatur) dikirim ke email staf,
  sekali per masa tunggu
- Jam layanan tampil jujur di jendela chat, dan chat bisa dimatikan sementara dari
  Pengaturan saat tidak ada yang bisa menjawab

**Template balasan** dikelola dari panel dan muncul sebagai tombol di atas kolom balasan.
Klien: pertanyaan pesanan custom "tinggal dijawab sesuai template".

**Konsekuensi teknis:**
- Tabel `percakapan_cs`, `pesan_cs`, `template_balasan`; izin baru `cs.balas`
- Relasi `pesan()` sengaja tanpa urutan bawaan. Urutan bawaan membuat `latest()`
  diabaikan diam-diam, dan sempat membalik urutan chat di layar
- Tombol WhatsApp di halaman publik diganti tombol chat CS. Arah sebaliknya, toko
  mengabari pelanggan lewat WhatsApp saat email tidak sampai (KEP-004), tetap ada
- Container lokal butuh ekstensi `pcntl` untuk Reverb; kode aplikasi tidak memakainya

---

## KEP-031 — Ruang CS: antarmuka tersendiri, akun yang sama
**Status:** TERKUNCI · 2026-09-11 · melanjutkan KEP-030

Kotak masuk chat yang pertama ditaruh di dalam tata letak panel, dan hasilnya sesak:
sidebar menu, judul halaman, dan kepala percakapan setinggi hampir 250 piksel menyisakan
ruang pesan yang sempit, dan konteks pelanggan tersembunyi di lipatan. Pola kerja CS
berbeda dari panel. Panel dipakai dengan berpindah menu untuk menyelesaikan tugas;
CS dibuka seharian, menunggu pesan, dan butuh konteks pelanggan selalu terlihat.

**Ruang CS punya tata letak sendiri** (`layouts/cs`), tiga panel: daftar percakapan,
percakapan, dan konteks pelanggan. Tetapi **akun, izin, dan alamatnya tetap panel**
(`/panel/cs`, izin `cs.balas`). Aplikasi atau sistem login kedua ditolak: menambah biaya
dan celah keamanan tanpa menambah apa pun yang dilihat pelanggan.

**Satu hal yang menonjol: pelanggan yang menunggu.** Garis tepi tiap percakapan kuning
selama pelanggan menunggu dalam batas wajar, merah begitu melewati batas peringatan
(15 menit, sama dengan batas email ke staf), dan yang paling lama menunggu selalu di
atas. Percakapan yang terlambat juga memunculkan pita tepat di atas kolom balasan.
Sisanya sengaja tenang: panel dipisah garis, bukan kartu-kartu.

**Status "Menerima chat".** Ruang CS mengirim denyut tiap menit selama saklarnya menyala;
widget pelanggan menampilkan "Online, biasanya membalas dalam beberapa menit" hanya
kalau ada denyut dalam dua setengah menit terakhir. Selain itu: "Sedang offline. Pesan
tetap kami terima, dibalas pada jam layanan." Janji yang jujur lebih baik daripada
pelanggan yang menunggu tanpa tahu ada yang membaca atau tidak.

**Konsekuensi teknis:**
- Halaman dimuat sekali; berpindah percakapan, mencari, dan menyaring lewat JSON dengan
  `history.pushState`. Memuat ulang halaman setiap pindah percakapan memutus sambungan
  realtime yang justru dibutuhkan seharian
- Baris daftar punya satu serializer (`PercakapanCs::untukDaftar`) yang dipakai jawaban
  awal halaman **dan** kabar realtime, supaya baris yang diperbarui lewat Pusher tidak
  pernah berbeda bentuk dari yang dimuat server
- Kehadiran disimpan **per staf**: satu admin istirahat tidak membuat CS tampak offline
  selama admin lain masih menjawab. Tab yang ditutup mengirim pamit lewat
  `sendBeacon`; yang tertutup paksa kedaluwarsa sendiri
- Template dipilih dari daftar yang bisa dicari, atau dengan mengetik `/` di awal kolom
  balasan. Judul yang cocok diurutkan di atas isi yang cocok, supaya Enter memasang
  template yang dimaksud
- Suara dan saklar "Menerima chat" diingat per peramban (localStorage)
- Kotak masuk lama di dalam panel dihapus; badge dan bunyi di halaman panel lain tetap ada

---

## KEP-032 — Data asli klien: dua lokasi, jam operasional, aset merek
**Status:** TERKUNCI · 2026-09-11

Klien mengirim data usahanya. Semuanya menjadi **nilai bawaan seeder**, bukan hanya diisi
di database lokal, supaya instalasi baru di hosting langsung benar tanpa diketik ulang.

**Dua lokasi dengan tugas berbeda.** Toko (Jl. Manyar II No.2, Giripurwo) adalah tempat
pesanan diambil dan dilunasi, buka Senin-Sabtu 08.00-16.00 dan Minggu 08.00-14.00.
Konveksi (Donoharjo, Wuryorejo) adalah tempat jersey dikerjakan, buka Senin-Sabtu
08.00-16.00. Halaman pesanan, email, dan kotak "ambil di toko" merujuk ke **toko**.
**Dikonfirmasi klien: pesanan custom juga diambil di toko**, bukan di konveksi. Kartu konveksi
di halaman depan menyatakannya terang-terangan, supaya pelanggan tidak datang ke tempat yang salah.
Tanpa tautan peta dari klien, alamat dijadikan pencarian Google Maps.

**Jam operasional website: setiap hari 07.00-16.00.** Dipakai di dua tempat: teks yang
dibaca pelanggan (widget chat saat offline, catatan verifikasi bukti bayar), dan logika
peringatan chat. Email "chat belum dibalas" hanya dikirim di dalam jam ini; chat yang masuk
malam tetap tercatat menunggu dan diperingatkan begitu jam operasional dimulai. Jam yang
salah ketik di pengaturan dianggap selalu buka, supaya salah ketik tidak diam-diam
mematikan peringatan.

**Aset merek** disimpan asli di `resources/brand/` dan turunannya di `public/`: logo
tulisan, versi terang untuk latar navy (navy diganti putih, merah dan kuning dipertahankan),
emblem lengkap untuk halaman depan, dan favicon dari bola voli di huruf O.

**QRIS tidak pernah dikompresi.** Berkas klien disalin utuh; isinya dicocokkan dengan pembaca
QR (QRIS statis Bank Mandiri, merchant "Setia Sport", kota "Wonogiri (Kab)") dan berkas yang
disajikan website terbukti identik byte demi byte. Seeder memasangnya hanya kalau toko belum
punya QRIS, jadi QRIS yang diganti lewat panel tidak tertimpa.

**Temuan yang ikut diperbaiki:**
- **Zona waktu aplikasi ternyata UTC.** `config/app.php` menulis `'UTC'` langsung, sehingga
  `APP_TIMEZONE=Asia/Jakarta` diabaikan. Semua jam yang dilihat pelanggan (batas bayar,
  waktu kirim, riwayat) mundur tujuh jam, dan pengingat "09.00" berjalan pukul 16.00 WIB.
  Kini membaca `APP_TIMEZONE` dengan cadangan `Asia/Jakarta`
- **Sesi yang habis membuat ruang CS tampak hidup padahal mati.** Setelah laptop tertidur,
  denyut dan polling ditolak puluhan kali tanpa tanda apa pun di layar. Kini ruang CS
  berhenti dan meminta admin masuk lagi. Widget pelanggan yang tokennya basi mengambil token
  baru lalu mengulang permintaannya sendiri, tanpa pesan yang hilang
- Teks yang masih menyebut "sisa dibayar saat mengambil barang" diselaraskan dengan
  KEP-028: pelunasan terjadi setelah barang jadi, sebelum diambil

---

## KEP-033 — Satu kode, dua versi: chat CS sebagai saklar
**Status:** TERKUNCI · 2026-09-11

Klien belum yakin akan memakai chat CS. Dua branch terpisah ("dengan CS" dan "tanpa CS")
ditolak: setiap revisi harus dikerjakan dua kali dan kedua versi pasti berselisih diam-diam.
Yang dipakai adalah **saklar "Tampilkan Chat CS" di Pengaturan**, sehingga klien bisa
memutuskan kapan saja, bahkan setelah website berjalan, tanpa developer.

Saklar yang ada sebelumnya belum tuntas, dan tesnya membuktikan itu: mematikannya membuat
halaman depan dan halaman pesanan **error 500**, karena fungsi tautan WhatsApp sudah
terhapus sebagai kode mati. Selain itu hanya dua dari enam endpoint chat pelanggan yang
ikut saklar, dan belasan teks masih menyuruh pelanggan "hubungi lewat chat CS".

**Saat CS dimatikan:**
- Jendela chat hilang; semua endpoint chat pelanggan menjawab 404, termasuk untuk pelanggan
  yang sudah punya percakapan, supaya tidak ada pesan yang masuk ke kotak yang tidak dibaca
- Semua teks "hubungi kami lewat ..." di halaman dan email beralih ke WhatsApp toko lewat
  satu helper, `kanal_kontak()`. Tombol konsultasi jadi tautan WhatsApp
- Panel: tanpa bunyi, tanpa polling, tanpa email peringatan. Menu berganti jadi **Arsip
  chat** selama masih ada riwayat, karena log chat adalah catatan resmi komplain; ruang CS
  jadi baca-saja
- WhatsApp hanya dipakai untuk **kontak**. Bukti bayar tetap diunggah di website (KEP-029),
  dan halaman pembayaran tidak pernah menampilkan tautan WhatsApp

**Yang perlu diketahui klien saat memutuskan:** tanpa CS, jalur kontak kembali ke WhatsApp,
dan alasan klien sendiri untuk CS (komplain tercatat di satu tempat, pelanggan tidak dialihkan
ke WhatsApp tempat penipu menyamar) tidak lagi berlaku.

---

## KEP-034 — Chat CS dibatalkan klien untuk sementara: mati sebagai bawaan
**Status:** TERKUNCI · 2026-09-11 · memakai saklar KEP-033

Klien resmi membatalkan chat CS untuk saat ini, dengan kemungkinan dibutuhkan lagi nanti.
Kodenya **tidak dihapus**, hanya dimatikan sebagai bawaan:

- Instalasi baru dimulai **tanpa CS**. Jalur kontak di halaman dan email adalah WhatsApp
  toko (KEP-033)
- Menyalakan kembali cukup satu centang di Pengaturan, tanpa developer. Kunci Pusher hanya
  diperlukan kalau ingin seketika; tanpa itu chat berjalan lewat polling
- Tes CS tetap berjalan pada setiap perubahan dengan saklar dinyalakan di dalam tesnya.
  Itulah yang menjamin fitur ini masih bekerja saat dibutuhkan, bukan sekadar masih ada

**Ditolak: menghapus kodenya lalu mengembalikannya lewat git.** Makin lama CS disimpan di
luar kode utama, makin banyak bagian website yang berubah di sekitarnya, dan pengembaliannya
berubah dari satu perintah jadi pekerjaan baru. Biaya menyimpannya dalam keadaan mati hampir
nol: tidak ada yang tampil, endpoint menjawab 404, dan Pusher tidak perlu didaftarkan.

Percakapan uji di database lokal dihapus supaya demo ke klien tidak menampilkan menu Arsip
chat berisi data uji. Template balasan dipertahankan untuk dipakai kalau CS dinyalakan.

---

## KEP-035 — Contoh portofolio dan testimoni untuk demo, dengan pagar
**Status:** TERKUNCI · 2026-09-12

Klien ingin melihat seperti apa halaman depan yang sudah terisi sebelum punya isinya
sendiri. Contohnya dibuat, tapi dengan pagar, karena testimoni dan portofolio karangan yang
tayang di website asli sama dengan ulasan palsu di depan pelanggan:

- **Tidak ada nama lembaga sungguhan.** Instansi ditulis sebagai jenisnya ("Tim futsal SMA
  di Wonogiri", "Sekolah dasar di Wonogiri"), bukan nama sekolah atau dinas yang tidak pernah
  memesan. Nama tim di gambar pun fiktif. Tes memeriksa pola nama lembaga resmi
- **Gambarnya ilustrasi mockup**, tampak depan dan belakang, berlabel "Contoh ilustrasi".
  Bukan foto stok, karena itu sama dengan mengaku pekerjaan orang lain
- **Setiap baris bertanda `contoh`.** Panel menampilkan tanda Contoh, dashboard
  memperingatkan selama masih ada, dan satu tombol menghapus semuanya beserta gambarnya
  tanpa menyentuh isian asli
- **Tidak ikut instalasi.** `ContohProfilSeeder` tidak dipanggil `DatabaseSeeder` dan menolak
  berjalan di produksi, termasuk dengan `--force`
- **Tanda contoh lepas hanya kalau isinya diganti** (teks, nama, atau foto). Sekadar mengubah
  urutan atau menyembunyikan tidak melepasnya, supaya isi karangan tidak kehilangan tanda
  diam-diam; sebaliknya isian yang sudah diganti pemilik toko tidak ikut terhapus

Bagian "Tentang kami" sengaja tidak diberi contoh: tahun berdiri dan sejarah usaha adalah
fakta yang hanya klien yang tahu.

---

## KEP-036 — Chat CS dipasang lewat `.env`, bukan hanya saklar di Pengaturan
**Status:** TERKUNCI · 2026-09-12

Setelah KEP-034, saklar CS masih tampil di Pengaturan beserta sapaan dan jam
operasionalnya. Pemilik toko melihat tab untuk fitur yang sudah dibatalkan dan bertanya
kenapa masih ada.

**Keputusan:** dua lapis. `FITUR_CS` di `.env` (bawaan `false`) memasang atau melepas
fiturnya; saklar di Pengaturan menyalakan atau mematikannya sementara. Selama
`FITUR_CS=false`:

- Tab Chat CS tidak tampil di Pengaturan, dan isiannya juga tidak masuk tab "Lainnya"
- Kiriman formulir yang memuat isian CS diabaikan
- `cs_aktif()` selalu `false`, apa pun isi saklarnya, jadi seluruh jalur KEP-033 (widget,
  endpoint, notifikasi, teks "hubungi kami") ikut mati

Kodenya tetap ada dan tetap diuji. Menyalakan kembali: `FITUR_CS=true`, `config:cache`,
lalu saklar di Pengaturan (docs/12-deploy.md, "Menyalakan chat CS kembali").

---

## KEP-037 — Urutan produksi mengikuti meja kerja konveksi
**Status:** TERKUNCI · 2026-09-18

Pertemuan dengan klien mengoreksi urutan yang selama ini dipakai sistem. Urutan
sebenarnya di konveksi: **desain → printing → press/sublime → potong → jahit →
finishing & packing**.

**Keputusan:**

- **Press mendahului potong.** Kertas hasil printing dipress ke kain lembaran dulu, baru
  kainnya dipotong menurut pola. Urutan lama (potong dulu) tidak pernah cocok dengan
  meja kerja, dan membuat antrian potong menampung pekerjaan yang belum bisa dipotong.
- **Finishing dan packing jadi satu tahap** untuk barang kustom, karena dikerjakan orang
  yang sama dalam satu langkah. Labelnya "Finishing & Packing".
- **Barang stok toko tetap punya tahap Packing sendiri**, terpisah dari jalur kustom.

Nilai tersimpan di database tidak diubah (`PRESS`, `POTONG`, `QC`), hanya urutan dan
labelnya. Pekerjaan produksi yang telanjur dibuat dengan urutan lama ditukar lewat
migrasi; status item tidak disentuh, supaya tidak ada barang yang berpindah meja karena
migrasi.

Delapan pekerja, dua orang per divisi.

---

## KEP-038 — Revisi desain dibatasi tiga kali, tanpa revisi berbayar
**Status:** TERKUNCI · 2026-09-18

KEP-005 menyisakan pertanyaan berapa biaya revisi keempat. Klien menjawab: tidak ada
revisi keempat. Tiga kali, semuanya gratis, lalu berhenti.

**Keputusan:** `revisi_gratis_maks` berubah arti dari "batas gratis" menjadi **batas
keras**. Permintaan revisi setelah jatahnya habis ditolak, dan formulirnya tidak lagi
ditampilkan di halaman pesanan; pelanggan diarahkan menghubungi toko.

Alasan klien: desain yang belum juga cocok setelah tiga putaran biasanya bukan soal
gambar, melainkan soal yang perlu dibicarakan langsung. Menjual revisi keempat hanya
memperpanjang putaran yang sama sambil membuat pelanggan merasa ditagih berulang.

---

## KEP-039 — Harga per potong menurut kategori ukuran
**Status:** TERKUNCI · 2026-09-18

Daftar harga asli klien tidak punya satu harga per produk. Jersey Serena berharga
Rp45.000 untuk ukuran SD, Rp55.000 untuk SMP, dan Rp60.000 untuk dewasa, sementara satu
tim biasa memesan ketiganya sekaligus. Rumus KEP-009 (`harga_satuan * qty`) tidak bisa
menyatakan itu.

**Keputusan:** harga dihitung per potong.

```
harga_potong  = harga(produk, kategori, bahan)      ← matriks
                + SUM(opsi PER_PCS)                 ← lengan panjang, kerah, saku
                + tambahan_ukuran(ukuran)           ← 3XL, 5XL
subtotal_item = SUM(harga_potong * jumlah) + SUM(opsi PER_ITEM)
```

**Kenapa matriks, bukan penjumlahan bertingkat.** Selisih antar kategori tidak tetap:
Serena naik Rp10.000 dari SD ke SMP lalu Rp5.000 ke dewasa, sedangkan Embos naik
Rp10.000 dua kali. Rumus bertingkat memaksa harga klien dibulatkan supaya cocok, dan itu
sama dengan mengubah harga jual tanpa izin. Matriks menyimpan angka klien apa adanya.

**Kenapa tambahan tetap dipisah.** Lengan panjang selalu +Rp10.000 dan kerah selalu
+Rp10.000 di seluruh kategori dan bahan. Memasukkannya ke matriks akan melipatgandakan
jumlah sel dari 15 menjadi 60 tanpa menambah satu pun angka baru.

Bahan tidak menambah harga sendiri: ia memilih baris matriks. Di rincian pelanggan bahan
tetap ditampilkan, dengan angka nol, supaya pilihan yang dibuatnya tetap terbaca.

Akibat lain yang harus diikuti: **jumlah pesanan dijumlahkan dari daftar ukuran**, bukan
diketik terpisah. Angka yang dipakai menghitung harga wajib sama dengan angka yang nanti
dikerjakan konveksi.

---

## KEP-040 — Pemesanan kustom empat langkah, WhatsApp hanya bantuan
**Status:** TERKUNCI · 2026-09-18

Klien mengirim rancangan alurnya: pilih produk, desain & detail, data pemain, cek
pesanan. Satu kotak jumlah tidak lagi cukup, karena harga baru diketahui setelah daftar
ukurannya terisi (KEP-039).

**Keputusan:**

- **Satu halaman, empat langkah**, bukan empat halaman terpisah. Seluruh pilihan tetap
  ada di memori sampai pesanan dibuat, jadi memperbaiki bahan tidak menghapus daftar
  pemain yang sudah diketik. Langkah yang sudah dilewati bisa diklik untuk mundur.
- **Pilihan berupa kartu, bukan dropdown**, sesuai contoh klien. Harganya ikut tertulis
  di kartu, jadi pelanggan tahu akibat setiap pilihan sebelum memilih.
- **Data pemain: isi manual atau tempel dari Excel.** Yang dipasang penempelan, bukan
  unggah berkas: pelanggan menyalin dari Excel, Sheets, atau WPS, hasilnya langsung
  tampil sebagai baris yang masih bisa diperbaiki. Berkas yang judul kolomnya diubah
  sendiri, selnya digabung, atau dikirim sebagai foto selalu berakhir jadi keluhan ke
  admin. Unggah CSV bisa ditambahkan nanti kalau memang diminta.
- **WhatsApp di halaman ini adalah bantuan, bukan jalur pemesanan.** Klien sempat minta
  langkah terakhir menyambung ke WhatsApp, tetapi itu bertabrakan dengan aturan klien
  sendiri: tidak ada transaksi lewat WhatsApp (KEP-029). Pesanan tetap dibuat di web
  supaya punya kode, tagihan, halaman lacak, dan lembar kerja; tanpa itu produksi
  kehilangan sumber datanya. Tombolnya tetap ada untuk bertanya sebelum memesan.

Minimum pemesanan 6 pcs. Contoh rancangan klien menulis 4 pcs, dan itu dikoreksi lewat
jawaban terakhirnya.

---

## KEP-041 — Pembatalan diajukan pelanggan, diputuskan toko
**Status:** TERKUNCI · 2026-09-19

Klien mengirim alurnya: ajukan pembatalan → konfirmasi admin → refund → pesanan
dibatalkan. Ketentuannya tiga tingkat, mengikuti sejauh mana pekerjaan berjalan.

**Keputusan:**

| Tahap saat diajukan | Pengembalian |
|---|---|
| Belum dikerjakan | Seluruh yang sudah dibayar |
| Desain sudah dikerjakan | Dipotong biaya setup desain yang tercatat di pesanan |
| Sudah disetujui dan masuk produksi | Tidak ada. Kain sudah tercetak dengan nama tim |

**Menekan tombol tidak membatalkan apa pun.** Yang tercipta hanya pengajuan; pesanan
tetap berjalan, stok tetap dipesan, dan konveksi tetap bekerja sampai toko memutuskan.
Kalau satu klik pelanggan bisa langsung membatalkan, satu salah pencet menghentikan kain
yang sedang dipotong.

**Nominal pengembalian diketik admin, bukan dieksekusi otomatis.** Sistem mengusulkan
angkanya supaya admin tidak menghitung sendiri, tetapi uang keluar tetap keputusan toko,
dan batas atasnya dijaga: tidak mungkin mengembalikan lebih dari yang pernah masuk.

**Ketentuannya ditulis sebelum tombolnya**, termasuk kalimat pahit bahwa uang muka bisa
hangus. Pelanggan yang baru tahu akibatnya setelah menekan tombol akan merasa dijebak,
dan itu berakhir jadi pertengkaran di WhatsApp.

Yang dibekukan di pengajuan: berapa yang sudah dibayar dan tahap apa saat itu. Tanpa itu,
keputusan lama tidak bisa lagi dijelaskan ketika pelanggan bertanya sebulan kemudian.

Barang stok yang baru disiapkan atau dipacking dihitung "belum dikerjakan": barangnya
utuh dan bisa kembali ke rak. Paket yang sudah berangkat tidak bisa diajukan lewat web
sama sekali; itu urusan retur, dan retur selalu lewat percakapan dengan toko.

Pengajuan yang menggantung tampil di dashboard bersama bukti bayar yang menunggu, karena
selama menggantung konveksi mengerjakan kain yang mungkin tidak jadi dipakai.

---

## KEP-042 — Rapikan tampilan publik menurut kebiasaan marketplace
**Status:** TERKUNCI · 2026-09-19

Klien membandingkan web ini dengan toko besar yang biasa dipakai pelanggannya, lalu
meminta beberapa perubahan kecil yang semuanya soal kebiasaan, bukan selera.

- **Keranjang jadi ikon** dan berdiri sebelum tombol Masuk. Sebagai tombol bertuliskan
  teks, ia dan tombol Masuk berebut tempat di layar HP. Namanya tetap dibawa untuk
  pembaca layar dan muncul sebagai tooltip, jadi yang hilang cuma lebarnya.
- **Judul halaman ikut menempel di bawah bilah atas, hanya di HP.** Setelah menggulir
  beberapa layar, bilah atas cuma berisi logo dan pelanggan kehilangan jejak sedang ada
  di halaman apa. Di layar lebar judulnya sudah terbaca utuh, jadi bilah ini tidak
  dipasang di sana.
- **Tautan pesanan bisa disalin satu tombol.** Tautan itu satu-satunya kunci pesanan
  (KEP-008), dan pelanggan sering mengirimkannya ke bendahara tim. Menyalin dari bilah
  alamat di HP jauh lebih repot.
- **Bantuan WhatsApp di samping tombol terakhir checkout.** Di situlah keraguan muncul:
  ukuran, bahan, cara bayar. Tanpa jalan bertanya, pelanggan yang ragu menutup halaman
  dan pesanannya hilang tanpa jejak. Tetap bantuan, bukan jalur pemesanan (KEP-040).

---

## KEP-043 — Barang tidak diambil 30 hari dinyatakan hangus, oleh admin
**Status:** TERKUNCI · 2026-09-19

Klien memutuskan barang yang tidak pernah diambil dianggap hangus setelah satu bulan dan
uangnya tidak kembali.

**Keputusan:** batasnya jadi pengaturan (`hangus_hari`, bawaan 30), tetapi **yang
menghanguskan tetap admin, bukan penjadwal.** Sistem menandai, admin memutuskan.

Alasannya: menghanguskan uang pelanggan otomatis di tengah malam adalah keputusan yang
tidak bisa ditarik lagi, sementara alasan barang belum diambil sering ada di luar sistem.
Pelanggan sakit, sudah menelepon toko, atau barangnya sudah diambil tapi lupa dicatat.
Penjadwal tidak tahu apa pun tentang itu.

Tombolnya baru muncul setelah batasnya benar-benar lewat, jadi tidak tersedia
"untuk berjaga-jaga", dan dashboard memisahkan daftar ini dari daftar "belum diambil"
biasa karena tindakannya berbeda: yang satu menelepon, yang lain menghanguskan uang.

Tanggal batasnya ditulis di halaman pesanan pelanggan, di dalam kartu "Ambil di toko",
bukan disembunyikan di halaman syarat.

---

## KEP-044 — Isi pesanan bisa ditambah dan dikurangi, bukan cuma tagihannya
**Status:** TERKUNCI · 2026-09-19

Klien meminta penyesuaian pesanan: tambah pemain, kurangi pemain, berikut biayanya.

Sebelum ini satu-satunya jalan adalah penyesuaian biaya bertuliskan "tambah 2 pemain".
Tagihannya benar, tetapi daftar pemain, jumlah potong, dan lembar kerja tetap menyebut
angka lama. **Konveksi menjahit sesuai lembar kerja, bukan sesuai tagihan**, jadi dua
jersey yang sudah dibayar tidak pernah dibuat.

**Keputusan:** admin mengubah isi pesanannya sendiri.

- **Ukuran yang sudah ada memakai harga yang disepakati saat memesan**, walau daftar
  harga sudah naik. Pesanan adalah kesepakatan, bukan kutipan harga hari ini.
- **Ukuran yang benar-benar baru memakai harga katalog hari ini**, ditambah pilihan
  kerah dan lengan yang sudah disepakati di pesanan itu.
- **Barang stok ikut menyesuaikan reservasi gudang**, dan penambahan ditolak kalau
  raknya tidak cukup.
- **Perubahan masuk riwayat yang dibaca pelanggan**, bukan hanya log internal.
  Perubahan tagihan tanpa jejak adalah hal pertama yang dipersoalkan orang.
- **Kelebihan bayar ditampilkan terpisah** di panel. "Sisa tagihan" berhenti di nol, jadi
  uang lebih setelah pesanan dikurangi tidak akan pernah terlihat di sana.

Penyesuaian biaya yang lama tetap ada untuk hal yang bukan potongan barang: ongkir,
potongan harga reseller, atau kompensasi.

---

## KEP-045 — Ukuran dan warna dipilih lewat kotak, bukan dropdown
**Status:** TERKUNCI · 2026-09-19

Klien mengirim contoh halaman produk toko besar: warna dan ukuran berupa kotak yang
langsung terlihat, dan ukuran yang habis tetap ditampilkan dalam keadaan dicoret.

**Keputusan:** dropdown varian diganti kotak pilihan.

- **Ukuran yang habis tetap digambar, tetapi mati.** Menyembunyikannya membuat pembeli
  mengira toko tidak pernah menjual ukuran itu, padahal hanya sedang kosong.
- **Warna yang dipilih menentukan ukuran mana yang hidup**, dan halaman dibuka pada warna
  yang benar-benar punya stok, supaya tidak semua ukuran tampak mati sejak awal.
- **Tombol keranjang mati sampai ukurannya dipilih**, jadi tidak ada lagi pesanan yang
  terkirim dengan varian yang kebetulan berada di urutan pertama dropdown.

Sekalian diperbaiki: produk berharga per ukuran (KEP-039) tidak punya tier harga, dan
halaman produk dulu menampilkannya sebagai **Rp 0**. Sekarang tampil harga terendah dari
matriksnya, dengan catatan bahwa harga akhirnya mengikuti bahan, model, dan ukuran.

---

## KEP-046 — Invoice dibuat sistem, reseller cukup ditandai
**Status:** TERKUNCI · 2026-09-19

Dua permintaan klien yang ternyata satu urusan: invoice yang rapi, dan perlakuan khusus
untuk reseller.

**Invoice.** Dicetak dari data pesanan, bukan diketik ulang di Excel. File Excel lama
klien menyimpan sisa template toko lain, termasuk **nomor rekening milik orang lain**,
dan rumus totalnya tidak mengurangi diskon. Keduanya mustahil terjadi di lembar yang
dibangun dari data pesanan, dan dua tes menjaga persis kesalahan itu.

Isinya: nomor pesanan sebagai nomor invoice, tanggal, jatuh tempo selama tagihan masih
berjalan, rincian per ukuran, penyesuaian biaya, total, yang sudah dibayar, sisa atau
kelebihan bayar, rekening toko, dan cap **LUNAS** begitu pembayarannya penuh.

Tidak memakai pustaka PDF: peramban sudah bisa menyimpan halaman ini sebagai PDF, dan
menambah pustaka berarti menambah beban di shared hosting untuk pekerjaan yang sudah ada
alatnya.

**Reseller.** Klien memutuskan reseller tidak punya harga sendiri di katalog; potongannya
diberikan admin per pesanan dan kesepakatannya dibicarakan lewat WhatsApp. Maka yang
dibangun cuma penanda di data pelanggan berikut catatan kesepakatannya, supaya admin
tidak perlu mengingat sendiri siapa saja resellernya. Harga tidak pernah berubah otomatis:
potongan tetap ditambahkan sebagai penyesuaian biaya, sehingga tertulis apa adanya di
invoice.

---

## KEP-047 — Tahap produksi ditandai pekerja lewat pindai QR
**Status:** TERKUNCI · 2026-09-19

Klien ingin pekerja menandai sendiri tahap yang selesai, supaya tidak perlu melapor ke
admin konveksi yang lalu mengklik satu per satu. Paket yang diambil paket dasar; payroll
dibatalkan.

**Keputusan:**

- **Satu QR per baris pesanan**, dicetak di lembar kerja yang berjalan bersama kainnya.
  Bukan per pesanan: satu pesanan bisa memuat dua barang yang tahapnya berbeda.
- **Pekerja bukan pengguna panel.** Mereka tidak pernah membuka pesanan, harga, atau data
  pelanggan. Memberi delapan orang akun panel demi satu tombol adalah harga yang terlalu
  mahal. Halaman kerjanya hanya memuat barang apa, berapa potong, ukuran apa saja, dan
  tahap mana yang sedang dipegang.
- **Membuka tautan tidak mengubah apa pun.** Tahap berpindah hanya setelah PIN diketik
  dan tombol ditekan, karena tautan yang beredar di grup WhatsApp ikut dibuka pratinjau
  oleh aplikasinya sendiri.
- **PIN per orang, seperti NIK**, sesuai jawaban klien. Tiap orang hanya boleh menandai
  bagiannya sendiri: tanpa pagar itu, satu PIN bisa menyelesaikan seluruh tahap sekaligus
  dan catatan siapa mengerjakan apa jadi tidak ada artinya.
- **PIN disimpan sebagai hash** dan tidak pernah bisa dibaca ulang, bahkan oleh pemilik
  toko. Kalau lupa, PIN-nya diganti, bukan dilihat. Yang menjaga pintunya bukan PIN empat
  angka itu, melainkan token panjang di tautan lembar kerja.
- **Pekerja yang keluar dinonaktifkan, bukan dihapus**, supaya pekerjaan yang pernah ia
  tandai tetap bisa ditelusuri namanya.
- **Salah tandai diperbaiki admin konveksi**, sesuai jawaban klien.

QR digambar sebagai SVG saat lembar kerja dibuka, bukan disimpan sebagai berkas: isinya
cuma tautan yang sudah ada di database, dan SVG tetap tajam di printer mana pun.

**Yang belum dipasang:** printing per roll (satu roll memuat beberapa pesanan) dan setoran
per potong. Dua hal itu pertanyaannya belum dijawab klien, dan membangunnya berdasar
tebakan akan mengunci cara kerja yang belum tentu benar.

---

## KEP-048 — Biaya layanan per pesanan, disebut terbuka
**Status:** TERKUNCI · 2026-09-20

Toko akan memakai API berbayar untuk hitung ongkir dan pelacakan paket, dan klien
sepakat menutupnya lewat biaya layanan kecil per pesanan.

**Keputusan:** satu angka per pesanan, diatur di Pengaturan (`biaya_layanan`), dan
**ditulis terbuka di checkout sebelum pelanggan menekan tombol bayar**, lalu ikut tampil
di halaman pesanan dan invoice. Angka yang baru muncul di halaman tagihan adalah cara
tercepat membuat pelanggan merasa ditambah-tambahi diam-diam.

Nilainya **dibekukan di pesanan**, bukan dibaca ulang dari pengaturan saat menghitung
total. Kalau tarifnya dinaikkan bulan depan, tagihan yang sudah disepakati hari ini tidak
ikut berubah. Alasannya sama dengan harga per ukuran (KEP-039).

Diisi 0 berarti fitur ini mati sepenuhnya, dan barisnya tidak muncul di mana pun.

---

## KEP-049 — Panduan ukuran yang menentukan ukuran mana yang boleh dipesan
**Status:** TERKUNCI · 2026-09-20

Klien mengirim panduan ukuran celana pendek: S sampai 3XL, lengkap dengan lebar dan
panjang dalam sentimeter, dan **tanpa kategori SD/SMP/SMA**. Sekaligus menjawab dua
pertanyaan lama: kerah berlaku di semua kategori dengan tambahan Rp10.000, dan lengan
panjang bisa dipesan baik untuk atasan saja maupun setelan.

**Keputusan:**

- **Panduan ukuran jadi sumber daftar ukuran**, bukan daftar tetap di kode. Celana pendek
  berhenti di 3XL sementara jersey dewasa sampai 5XL, dan yang tahu batasnya adalah
  panduan yang dikirim klien. Ukuran di luar panduan ditolak kalkulator harga.
- **Produk boleh tidak punya kategori umur sama sekali.** Celana pendek satu harga dari
  ukuran terkecil sampai terbesar, jadi pemilih kategorinya ikut hilang dari halaman
  pemesanan, bukan diisi pilihan yang tidak berarti apa-apa.
- **Angka sentimeternya disimpan sebagai JSON bernama**, misalnya `{"Lebar": 35,
  "Panjang": 45}`, karena tiap produk mengukur hal berbeda: celana diukur lebar dan
  panjang, jersey diukur lingkar dada dan panjang baju.
- **Pengisian panduan dipisah dari pembuatan produk**, karena panduannya menyusul dari
  klien, sering setelah produknya telanjur dibuat. Yang sudah punya panduan tidak
  ditimpa.

Katalog kini memuat lima produk kustom dengan harga asli klien: jersey atasan, setelan,
celana pendek, jaket, dan celana training.

**Yang masih menunggu klien:** panduan ukuran untuk jersey, setelan, jaket, dan training.
Selama belum ada, keempatnya memakai daftar ukuran bawaan.

Satu ketidakcocokan yang perlu ditanyakan: panduan celana menulis **2XL**, sementara
contoh tampilan yang klien setujui menulis **XXL**. Keduanya ukuran yang sama.

---

## KEP-050 — Berat produk, potongan borongan, dan penulisan ukuran
**Status:** TERKUNCI · 2026-09-21

Tiga jawaban klien sekaligus, yang membuka penghalang terbesar berikutnya.

**Berat produk.** Jersey atasan 250 g, setelan 280 g, celana pendek 250 g, jaket 280 g,
training 280 g, barang stok 500 g. Disimpan per produk dan bisa dikoreksi pemilik toko
dari panel. Tanpa angka ini tidak ada penyedia ongkir mana pun yang bisa dipanggil.

**Potongan borongan.** Di atas 12 pcs, harga dipotong Rp5.000 per potong, **tidak berlaku
kelipatan**: 24 pcs dipotong dengan tarif yang sama dengan 12 pcs.

Yang penting dan mudah salah: **uang muka dihitung dari harga SEBELUM potongan.** Toko
memberi potongannya saat pelunasan, bukan saat DP. Contoh dari klien dipakai apa adanya
sebagai tes: 12 pcs × Rp60.000 = Rp720.000, potongan Rp60.000, tagihan Rp660.000, DP 50%
tetap Rp360.000, dan sisanya Rp300.000.

Potongannya disimpan sebagai diskon pesanan, bukan dengan menurunkan harga tiap potong:
pelanggan berhak melihat harga penuh dan potongannya sebagai dua angka terpisah, dan
rincian harga per ukuran harus tetap bisa dijelaskan.

**Penulisan ukuran.** Ukuran dewasa ditulis **2XL**, bukan XXL, mengikuti panduan ukuran
yang dipakai toko. Varian stok lama masih menulis XXL dan perlu diseragamkan terpisah.

---

## KEP-051 — Printing ditandai per roll kertas, bukan per pesanan
**Status:** TERKUNCI · 2026-09-21

Klien menjelaskan cara kerja mesin printing: satu roll kertas memuat beberapa pesanan
sekaligus, dan pesanan kecil diselipkan untuk menggenapi sisa kertas. Menandainya satu
per satu berarti operator menandai hal yang sebenarnya selesai bersamaan, dan itulah
sebabnya status printing selalu telat diperbarui.

**Keputusan:** roll jadi benda tersendiri di sistem.

- Operator mencentang isi roll dari antrian printing, lalu **satu lembar roll dengan satu
  QR** mewakili seluruh isinya.
- Sekali pindai dan satu PIN menaikkan **seluruh pesanan di roll itu** ke tahap
  berikutnya.
- **Roll hanya menyentuh tahap printing.** Setelah kainnya terpisah per pesanan,
  pekerjaannya kembali berjalan sendiri-sendiri.
- **Pesanan yang sudah telanjur dimajukan admin dilewati diam-diam**, bukan menggagalkan
  seluruh roll: yang dimaksud operator tetap benar, yaitu "roll ini sudah selesai
  dicetak".
- Pesanan yang tidak sedang berada di tahap printing **ditolak masuk roll**, lengkap
  dengan sebutan pesanan mana yang bermasalah.

Pagarnya sama dengan lembar kerja per pesanan (KEP-047): membuka tautan tidak mengubah
apa pun, dan yang memindahkan tahap adalah PIN operator printing.

---

## KEP-052 — Harga dan ukuran diisi pemilik toko, bukan lewat pengembang
**Status:** TERKUNCI · 2026-09-21

Matriks harga (KEP-039), panduan ukuran (KEP-049), dan tambahan ukuran besar sempat hanya
bisa diisi lewat seeder. Artinya tiap kali toko mengubah harga, ia harus mengirim ke
pengembang dan menunggu. Itu menyalahi cara kerja seluruh panel ini: angka yang dipakai
berjualan milik pemilik toko.

**Keputusan:** ketiganya mendapat editor di halaman produk.

- **Matriks harga disimpan sekali jalan**, bukan sel demi sel. Pemilik toko membacanya
  sebagai satu tabel, dan menyimpan per sel akan meninggalkan tabel setengah terisi
  begitu halamannya keburu ditutup.
- **Sel yang dikosongkan berarti kombinasi itu tidak dijual**, dan kalkulator harga
  memang menolaknya.
- **Panduan ukuran punya nama kolom yang bisa diatur sendiri**, karena tiap produk
  mengukur hal berbeda: celana lebar dan panjang, jersey lingkar dada dan panjang baju.
- Daftar ukuran di panduan itulah yang **membatasi ukuran mana yang boleh dipesan**.
  Mengosongkannya mengembalikan produk ke daftar bawaan sistem.

**Dua jebakan database yang ditemukan saat mengerjakannya**, keduanya kini dijaga tes:

1. **MySQL menganggap dua NULL berbeda di indeks unik**, sehingga baris "berlaku semua
   kategori" tergandakan diam-diam kalau disimpan lewat `updateOrCreate` biasa.
2. **MySQL mengurutkan ulang kunci objek JSON menurut panjangnya.** Kolom "Lingkar dada"
   dan "Panjang" bertukar tempat sendiri, dan tabel yang dibaca pelanggan jadi berbeda
   dari yang diketik pemilik toko. Panduan ukuran karena itu disimpan sebagai **daftar
   berurutan** `[{nama, nilai}, ...]`, bukan peta `{nama: nilai}`.

Seeder tetap ada, tetapi perannya kembali seperti semula: mengisi data awal instalasi,
bukan satu-satunya jalan mengubah harga.

---

## KEP-053 — Seragam olahraga, tiga jenis kaos, dan kategori barang stok
**Status:** TERKUNCI · 2026-09-22

Klien mengirim daftar harga seragam sekolah dan kaos, plus 22 kategori barang stok.

**Produk baru:** seragam olahraga (kaos + training, minimum 24 set), kaos berkrah, kaos
oblong, dan kaos katun. Semuanya memakai susunan yang sudah ada: matriks bahan kali
kategori, ditambah opsi lengan.

**Yang paling mudah meleset dan karena itu dikunci tes:** lengan panjang menambah
Rp10.000 di hampir semua produk, **tetapi hanya Rp5.000 di kaos katun**. Tarif opsi
memang disimpan per produk, dan kasus inilah alasannya.

**Kaos berkrah dan oblong tidak dipisah jenjang sekolah**, jadi matriksnya memakai baris
"berlaku semua kategori".

**Ukuran kaos hanya M, L, dan XL.** Panduan ukurannya diisi tanpa angka sentimeter,
karena yang dikirim klien baru daftar ukurannya; yang penting daftar itu sudah membatasi
pesanan. Angka sentimeternya menyusul lewat editor panel (KEP-052).

**Yang sengaja belum dipasang:** model Tunik dan Oversize di kaos oblong. Harganya tidak
mengikuti pola pendek-panjang, dan perlu dipastikan dulu apakah itu produk tersendiri
atau pilihan di dalam kaos oblong.

**Berat keempat produk baru belum ada**, jadi ongkirnya belum bisa dihitung otomatis.

Soal cara memasukkannya: data ini tetap ditaruh di seeder sebagai **isi awal instalasi**,
bukan karena harga dikunci di kode. Tanpa itu, pemilik toko harus mengetik ulang seluruh
daftar harga di hosting nanti. Editornya sudah ada (KEP-052), jadi setiap angka di sini
bisa diubah sendiri dari panel kapan saja.

---

## Ringkasan keputusan

| # | Keputusan | Status |
|---|---|---|
| KEP-001 | Keranjang campuran: 1 order, progres per item | TERKUNCI |
| KEP-002 | File desain: hanya preview di sistem, mentah via link | TERKUNCI |
| KEP-003 | Pembayaran fleksibel: lunas atau DP, online + offline | TERKUNCI |
| KEP-004 | Notifikasi: email via Resend, tanpa WhatsApp API | TERKUNCI |
| KEP-005 | DP minimum 50%, boleh bayar lebih | TERKUNCI |
| KEP-006 | Pelunasan saat pengambilan di toko | DIREVISI KEP-028 |
| KEP-007 | Perubahan pesanan sebagai baris penyesuaian | TERKUNCI |
| KEP-008 | Magic link tanpa password, konfirmasi saat approve | TERKUNCI |
| KEP-009 | Harga tier per item + basis PER_PCS / PER_ITEM | TERKUNCI |
| KEP-010 | Harga otomatis, pelanggan langsung bayar | TERKUNCI |
| KEP-011 | Produk hybrid (BOTH): dua jalur pembelian | TERKUNCI |
| KEP-012 | Lingkungan lokal Docker mencerminkan shared hosting | TERKUNCI |
| KEP-013 | Roster diisi setelah pesanan, terkunci saat approve | TERKUNCI |
| KEP-014 | Status item = divisi; status job = aktivitas | TERKUNCI |
| KEP-015 | Gerbang pembayaran di balik antarmuka; Midtrans dulu | DIBATALKAN |
| KEP-016 | Transport Resend ditulis sendiri demi korelasi webhook | TERKUNCI |
| KEP-017 | Hanya dua peran: Pemilik dan Admin | TERKUNCI |
| KEP-018 | Gambar produk: unggah atau tautan CDN | TERKUNCI |
| KEP-019 | Identitas visual diturunkan dari logo | TERKUNCI |
| KEP-020 | Akun pelanggan sebagai jalur kedua, bukan pengganti | TERKUNCI |
| KEP-021 | Pendaftaran wajib dikonfirmasi lewat email | TERKUNCI |
| KEP-022 | Paket penutup dipilih dari sudut biaya dukungan | TERKUNCI |
| KEP-023 | Pembayaran manual (jalur WhatsApp diganti KEP-029) | TERKUNCI |
| KEP-024 | Halaman depan profil usaha, katalog belakangan | TERKUNCI |
| KEP-025 | `vendor/` di volume Linux, bukan mount Windows | TERKUNCI |
| KEP-026 | Pengiriman terbatas, khusus barang stok toko | TERKUNCI |
| KEP-027 | Kustom dan stok toko dipisah jadi dua tab | TERKUNCI |
| KEP-028 | Alur klien: tahap besar, subprogres produksi, gerbang pelunasan | TERKUNCI |
| KEP-029 | Bukti bayar diunggah di web, privat, tanpa WhatsApp | TERKUNCI |
| KEP-030 | Chat CS: Pusher sebagai pengantar, UI dan catatan milik sendiri | TERKUNCI |
| KEP-031 | Ruang CS: antarmuka tersendiri, akun yang sama | TERKUNCI |
| KEP-032 | Data asli klien: dua lokasi, jam operasional, aset merek | TERKUNCI |
| KEP-033 | Satu kode, dua versi: chat CS sebagai saklar | TERKUNCI |
| KEP-034 | Chat CS dibatalkan klien sementara, mati sebagai bawaan | TERKUNCI |
| KEP-035 | Contoh portofolio dan testimoni untuk demo, dengan pagar | TERKUNCI |
| KEP-036 | Chat CS dipasang lewat `.env`, bukan hanya saklar di Pengaturan | TERKUNCI |
| KEP-037 | Urutan produksi mengikuti meja kerja konveksi | TERKUNCI |
| KEP-038 | Revisi desain dibatasi tiga kali, tanpa revisi berbayar | TERKUNCI |
| KEP-039 | Harga per potong menurut kategori ukuran | TERKUNCI |
| KEP-040 | Pemesanan kustom empat langkah, WhatsApp hanya bantuan | TERKUNCI |
| KEP-041 | Pembatalan diajukan pelanggan, diputuskan toko | TERKUNCI |
| KEP-042 | Rapikan tampilan publik menurut kebiasaan marketplace | TERKUNCI |
| KEP-043 | Barang tidak diambil 30 hari dinyatakan hangus, oleh admin | TERKUNCI |
| KEP-044 | Isi pesanan bisa ditambah dan dikurangi, bukan cuma tagihannya | TERKUNCI |
| KEP-045 | Ukuran dan warna dipilih lewat kotak, bukan dropdown | TERKUNCI |
| KEP-046 | Invoice dibuat sistem, reseller cukup ditandai | TERKUNCI |
| KEP-047 | Tahap produksi ditandai pekerja lewat pindai QR | TERKUNCI |
| KEP-048 | Biaya layanan per pesanan, disebut terbuka | TERKUNCI |
| KEP-049 | Panduan ukuran yang menentukan ukuran mana yang boleh dipesan | TERKUNCI |
| KEP-050 | Berat produk, potongan borongan, dan penulisan ukuran | TERKUNCI |
| KEP-051 | Printing ditandai per roll kertas, bukan per pesanan | TERKUNCI |
| KEP-052 | Harga dan ukuran diisi pemilik toko, bukan lewat pengembang | TERKUNCI |
| KEP-053 | Seragam olahraga, tiga jenis kaos, dan kategori barang stok | TERKUNCI |
