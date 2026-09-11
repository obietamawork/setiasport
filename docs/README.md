# Dokumentasi Setia Sport

Toko alat olahraga + workshop kustom (jersey dll). Satu aplikasi web, dua alur pesanan,
tracking pesanan sampai **diambil di toko**, atau dikirim untuk barang stok (KEP-026).

## Daftar Dokumen

| File | Isi |
|---|---|
| [01-ringkasan-produk.md](01-ringkasan-produk.md) | Apa yang sebenarnya kita bangun, dan apa yang **bukan** |
| [02-alur-pesanan.md](02-alur-pesanan.md) | Alur Biasa vs Alur PreOrder, mesin status |
| [03-sisi-pelanggan.md](03-sisi-pelanggan.md) | Perjalanan pelanggan, halaman, ekspektasi |
| [04-sisi-internal.md](04-sisi-internal.md) | Peran internal, antrian kerja, prinsip anti-input-ganda |
| [05-model-data.md](05-model-data.md) | Entitas inti dan alasan bentuknya |
| [06-arsitektur-teknis.md](06-arsitektur-teknis.md) | Stack, batasan shared hosting & CDN gratis |
| [07-keputusan-terbuka.md](07-keputusan-terbuka.md) | Pertanyaan yang masih perlu dijawab |
| [08-log-keputusan.md](08-log-keputusan.md) | **Keputusan yang sudah terkunci + alasannya** |
| [09-katalog-contoh.md](09-katalog-contoh.md) | Draft 5 produk penguji model data |
| [10-spesifikasi-hosting.md](10-spesifikasi-hosting.md) | Syarat hosting & panduan membeli |
| [11-panduan-visual.md](11-panduan-visual.md) | Token warna, tipografi, dan prinsip desain |
| [12-deploy.md](12-deploy.md) | Langkah deploy ke shared hosting |
| [13-audit-ui.md](13-audit-ui.md) | Audit UI/UX dan perbaikan mobile |

## Status

Tahap **implementasi fondasi**. Skema database, model, dan kalkulator harga
sudah jalan dan tertutup test. Cara menjalankan proyeknya ada di
[README.md di root](../README.md).

- **33 keputusan terkunci**, satu dibatalkan (KEP-015, gerbang pembayaran)
- **Fase 0 s/d 3 selesai.** Fondasi data, katalog publik, panel internal,
  pesanan end-to-end, serta alur kustom penuh: konfigurator harga live, roster
  pemain, gerbang persetujuan desain, antrian kerja per divisi, dan worksheet
  cetak. 52 test lulus di MySQL
- **Fase 5 selesai.** Webhook status Resend. 72 test lulus di MySQL
- Susunan peran diringkas jadi dua (KEP-017), gambar produk mendukung unggah
  maupun CDN (KEP-018), dan identitas visual diturunkan dari logo (KEP-019).
  Pelanggan kini bisa memakai akun sebagai jalur kedua di samping tautan
  bertoken (KEP-020), dengan konfirmasi email wajib (KEP-021). Seluruh halaman lolos audit mobile di 375px, katalog memakai grid marketplace, dan panel internal diaudit tersendiri. 99 test lulus di MySQL
- Paket penutup selesai (KEP-022): denyut penjadwal, proteksi kirim ganda,
  halaman galat kustom, laporan, dan tab katalog berjumlah
- **Gerbang pembayaran dibatalkan (KEP-023).** Kodenya dihapus, bukan dinonaktifkan.
  Pelanggan membayar ke QRIS statis atau rekening toko lalu mengirim buktinya lewat
  WhatsApp; admin memverifikasinya di panel
- **Halaman depan jadi profil usaha (KEP-024):** portofolio instansi, testimoni,
  cara memesan, dan lokasi, baru cuplikan katalog. Portofolio dan testimoni dikelola
  dari panel. 113 test lulus di MySQL
- **Pengiriman terbatas untuk barang stok (KEP-026)** dan **pemisahan tab kustom vs
  stok (KEP-027)**. Toko juga memakai dua rekening sekarang. 131 test lulus di MySQL
- **Alur baru dari klien (KEP-028):** tahap besar untuk pelanggan dengan subprogres
  produksi (printing, potong kain, press, jahit, finishing), dan pelunasan sebagai gerbang
  sebelum siap diambil. **Bukti bayar (KEP-029)** kembali diunggah di web, disimpan
  privat, dan tidak pernah memverifikasi dirinya sendiri. **Chat CS (KEP-030)** realtime
  lewat Pusher, dengan Reverb di lokal. Seeder kini aman dijalankan ulang tanpa menimpa
  rekening, harga, atau kata sandi
- **Ruang CS (KEP-031):** antarmuka chat tersendiri untuk admin, tiga panel, tanda
  pelanggan menunggu, dan status "Menerima chat" yang terlihat pelanggan. 170 test lulus
- **Data asli klien terpasang (KEP-032):** logo, QRIS (identik dengan aslinya), dua rekening
  Mandiri, toko dan konveksi dengan jam masing-masing, WhatsApp, dan jam operasional
  website. Zona waktu aplikasi yang ternyata UTC ikut diperbaiki ke WIB
- **Chat CS jadi saklar (KEP-033), lalu dibatalkan klien untuk sementara (KEP-034):**
  website memakai WhatsApp sebagai jalur kontak; CS tinggal dicentang di Pengaturan kalau
  dibutuhkan lagi. 184 test lulus
- Sisa pekerjaan bukan lagi kode: isi profil usaha dan portofolio,
  isi kedua rekening dan QRIS di Pengaturan, daftar jasa kirim yang dipakai, sesuaikan
  template balasan CS, daftar akun Pusher, koreksi harga katalog, pembelian hosting,
  dan deploy ([12-deploy.md](12-deploy.md))
- Menunggu dari pemilik toko: isi portofolio & testimoni, teks "Tentang kami", daftar jasa
  kirim, koreksi harga katalog, dan pembelian hosting

Setiap dokumen di atas sudah disinkronkan dengan keputusan terkunci. Kalau ada
pertentangan, **[08-log-keputusan.md](08-log-keputusan.md) yang menang.**
