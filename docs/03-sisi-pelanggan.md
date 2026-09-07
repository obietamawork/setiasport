# 03 — Sisi Pelanggan

## Prinsip

Pelanggan Setia Sport **tidak sedang belanja santai seperti di Shopee**. Sebagian besar
datang dengan niat spesifik: satu tim butuh 18 jersey, satu sekolah butuh 40 kaos.
Nilai transaksinya besar, keputusannya berkelompok, dan mereka **takut salah**.

Maka prioritas UI bukan "banyak produk terlihat", tapi **mengurangi keraguan**:
harga jelas, hasil bisa dibayangkan, progres bisa dilihat.

## Perjalanan pelanggan

### 1. Menemukan & menjelajah
- Beranda: kategori besar (Jersey, Bola, Sepatu, Aksesoris, Perlengkapan Tim…)
- Katalog dengan filter: kategori, harga, dan **badge tipe alur** — "Ready Stock" vs
  "PreOrder / Kustom". Badge ini krusial; pelanggan harus tahu sejak halaman daftar
  bahwa barang ini butuh waktu pengerjaan.
- Halaman produk harus **SEO-friendly** (URL slug, meta, structured data). Ini kanal
  akuisisi paling murah untuk toko lokal.

### 2. Halaman produk — dua wajah berbeda

**Ready Stock:** galeri foto, varian (ukuran × warna), sisa stok, tombol beli. Selesai.

**Kustom / PreOrder:** ini bukan halaman produk, ini **konfigurator**. Isinya:
- Pilih bahan / tipe (dryfit, hyget, dll) — mempengaruhi harga
- **Pilih desain dari template** ATAU **kirim desain sendiri** (upload)
- (Roster **tidak** diisi di sini — lihat KEP-013)
- Tabel ukuran (size chart) yang jelas + catatan "ukuran Asia"
- MOQ (minimum order) ditegakkan di sini, bukan di checkout
- **Harga & estimasi lead time terhitung live** saat pilihan berubah
- Catatan tambahan (logo sponsor, warna spesifik)

### Roster adalah fitur pembeda, bukan pelengkap

Untuk toko jersey, input nama-nomor-ukuran per pemain adalah **inti pekerjaan**.
Kalau ini dibiarkan lewat WA, semua nilai sistem hilang: admin ketik ulang, salah ketik,
pelanggan menyangkal, jersey dicetak ulang, rugi.

Yang harus ada:
- Form dinamis (tambah/hapus baris), atau upload Excel/CSV dengan template
- Validasi: nomor tidak duplikat, ukuran harus dari daftar, jumlah baris = qty
- Diisi di halaman pesanan **setelah** order dibuat, bukan saat checkout (KEP-013):
  kapten tim memesan dulu, lalu mengumpulkan data anggotanya selama beberapa hari
- Bisa diubah **hanya sampai desain disetujui**, setelah itu terkunci
- Ringkasan otomatis: "S:3, M:8, L:5, XL:2" — untuk pelanggan sekaligus untuk gudang

### 3. Keranjang & Checkout
- Boleh campur ready stock + kustom (lihat 02 — perlu keputusan)
- Checkout menampilkan **rincian pembayaran yang jujur**: total, DP yang harus dibayar
  sekarang, sisa pelunasan
- **Tidak ada form alamat pengiriman.** Ganti dengan: pilihan estimasi tanggal ambil +
  info alamat & jam operasional toko
- **Tanpa pendaftaran akun** (KEP-008). Cukup: nama, **email (wajib)**, no HP.
  Email wajib karena ia satu-satunya kanal notifikasi otomatis

### 4. Pembayaran
- Fase 1: transfer manual + upload bukti → diverifikasi kasir
- Fase akhir: Midtrans/Xendit (VA, QRIS, e-wallet). QRIS hampir wajib untuk pasar ini
- Batas waktu bayar yang jelas (mis. 24 jam), setelah itu order kadaluarsa & stok dilepas

### 5. Tracking — halaman terpenting di seluruh web
- Diakses dengan **kode order + no HP**, tanpa login. Link langsung bisa dishare ke grup tim
- Tampilan **timeline vertikal**, bukan tabel status. Tahap yang sudah lewat, yang sedang
  berjalan, dan yang akan datang — semua terlihat
- Setiap tahap punya **waktu** dan, kalau relevan, **foto**. Foto hasil printing atau
  jahitan setengah jadi adalah bentuk kepercayaan paling murah yang bisa kamu berikan
- Untuk PreOrder: di tahap approval, halaman ini punya **tombol Setujui / Minta Revisi**
- Saat siap: tampilkan **kode ambil + QR**, alamat, jam buka, dan siapa yang boleh ambil

### 6. Serah terima
- Pelanggan datang, tunjukkan kode/QR
- Staf scan/ketik kode, verifikasi, tandai selesai
- Kalau diwakilkan: nama pengambil dicatat

## Akses & Notifikasi  → lihat KEP-004, KEP-008

### Akses: magic link, tanpa password

Tidak ada pendaftaran akun. Setiap order punya token akses; emailnya berisi tautan
sekali-klik ke halaman tracking. Bisa juga dibuka manual dengan **kode order + email**.

Tautan **sengaja bisa dibagikan** — Budi memesan 18 jersey lalu membagikannya ke grup
tim, dan 17 anggota lain memantau progres sendiri tanpa bertanya ke Budi. Konsekuensinya:
halaman tracking tidak boleh memuat data sensitif (no HP dan email disamarkan).

Tombol **Setujui Desain** meminta konfirmasi tambahan — ketik nama + centang pernyataan —
lalu mencatat siapa, kapan, dari IP mana, dan versi mockup keberapa. Perlindungan
diletakkan tepat di titik yang berkonsekuensi, bukan di pintu masuk.

### Notifikasi: email lewat Resend

Tidak ada WhatsApp Business API. Notifikasi otomatis dikirim via **Resend** menggunakan
HTTP API — bukan SMTP, sehingga aman dari pemblokiran port di shared hosting.
Email **wajib diisi** saat checkout.

Titik notifikasi wajib:
1. Order dibuat — kode order, rincian, tautan tracking, instruksi bayar
2. Pembayaran diterima (DP atau lunas) — sisa tagihan ditegaskan
3. **Mockup siap disetujui** — tautan langsung ke halaman approve
4. **Pesanan siap diambil** — kode ambil, alamat, jam buka, sisa yang harus dibawa
5. Pengingat belum diambil (H+3, H+7)

### Risiko yang diakui: email sering tidak dibaca

Di segmen pelanggan ini (tim futsal, sekolah), email kerap terabaikan atau masuk folder
Promosi. Kalau notifikasi approval tidak dibaca, order nyangkut dan justru **menambah**
pekerjaan admin. Tiga mitigasi, semuanya tanpa biaya:

1. **Tombol "Salin pesan WA" di panel admin.** Sistem menyusun teks lengkap berikut
   tautan tracking; admin klik, `wa.me` terbuka, tinggal kirim. Bukan otomatis, tapi
   nol biaya dan realistis. WA tetap jadi kanal nyata, hanya dipicu manusia.
2. **Webhook status Resend** (delivered / opened / bounced) disimpan per email. Admin
   tahu email sampai, dibuka, atau bounce — kalau bounce, langsung tahu harus WA.
3. **Dashboard menandai order nyangkut**: "email approval terkirim 3 hari lalu, belum
   ada respons" → admin follow up manual.

Nomor 2 dan 3 adalah alasan kenapa email tetap layak dipakai meski tingkat bacanya rendah:
sistem **tahu** kapan harus menyerah dan menyerahkan urusan ke manusia.
