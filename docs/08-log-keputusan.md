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
**Status:** TERKUNCI · 2026-09-07

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
  "Selesai ±14 hari kerja setelah desain disetujui". Ini beda ekspektasi paling tajam
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
**Status:** TERKUNCI · 2026-09-08

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

## Ringkasan keputusan

| # | Keputusan | Status |
|---|---|---|
| KEP-001 | Keranjang campuran: 1 order, progres per item | TERKUNCI |
| KEP-002 | File desain: hanya preview di sistem, mentah via link | TERKUNCI |
| KEP-003 | Pembayaran fleksibel: lunas atau DP, online + offline | TERKUNCI |
| KEP-004 | Notifikasi: email via Resend, tanpa WhatsApp API | TERKUNCI |
| KEP-005 | DP minimum 50%, boleh bayar lebih | TERKUNCI |
| KEP-006 | Pelunasan saat pengambilan di toko | TERKUNCI |
| KEP-007 | Perubahan pesanan sebagai baris penyesuaian | TERKUNCI |
| KEP-008 | Magic link tanpa password, konfirmasi saat approve | TERKUNCI |
| KEP-009 | Harga tier per item + basis PER_PCS / PER_ITEM | TERKUNCI |
| KEP-010 | Harga otomatis, pelanggan langsung bayar | TERKUNCI |
| KEP-011 | Produk hybrid (BOTH): dua jalur pembelian | TERKUNCI |
| KEP-012 | Lingkungan lokal Docker mencerminkan shared hosting | TERKUNCI |
| KEP-013 | Roster diisi setelah pesanan, terkunci saat approve | TERKUNCI |
| KEP-014 | Status item = divisi; status job = aktivitas | TERKUNCI |
| KEP-015 | Gerbang pembayaran di balik antarmuka; Midtrans dulu | TERKUNCI |
| KEP-016 | Transport Resend ditulis sendiri demi korelasi webhook | TERKUNCI |
| KEP-017 | Hanya dua peran: Pemilik dan Admin | TERKUNCI |
| KEP-018 | Gambar produk: unggah atau tautan CDN | TERKUNCI |
| KEP-019 | Identitas visual diturunkan dari logo | TERKUNCI |
| KEP-020 | Akun pelanggan sebagai jalur kedua, bukan pengganti | TERKUNCI |
| KEP-021 | Pendaftaran wajib dikonfirmasi lewat email | TERKUNCI |
