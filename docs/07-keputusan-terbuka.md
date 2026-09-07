# 07 — Keputusan Terbuka

Sebelas keputusan sudah terkunci — lihat [08-log-keputusan.md](08-log-keputusan.md).
Tidak ada lagi item yang mengubah skema database secara mendasar; sisanya adalah aturan
bisnis dan data yang bisa diisi sambil jalan.

---

## 1. Aturan revisi desain
- Berapa kali revisi gratis sebelum kena biaya?
- Setelah pelanggan menyetujui: benar-benar terkunci, atau masih bisa diubah dengan
  biaya (yang otomatis jadi `order_adjustment`)?

## 2. Roster / nameset — siapa yang mengisi?
Pelanggan sendiri di web, atau admin yang menginput dari data WA? Perlu unggah
Excel/CSV, atau cukup form dinamis? Ini menentukan berapa besar porsi UI konfigurator.

## 3. Kebijakan pembatalan setelah DP masuk
Usulan bertingkat:
- Batal **sebelum** desain dikerjakan → DP kembali (dipotong biaya admin?)
- Batal **setelah** desain disetujui / masuk produksi → DP hangus

Kebijakan ini harus tampil sebagai centang persetujuan **di halaman checkout**, bukan
disembunyikan di halaman syarat & ketentuan.

## 4. Batas waktu
- Order belum dibayar kadaluarsa setelah berapa jam? (mempengaruhi pelepasan stok)
- Barang siap tapi belum diambil: pengingat kapan, dan setelah berapa lama dianggap hangus?

## 5. Kedalaman tracking yang ditampilkan ke pelanggan
Berapa tahap yang benar-benar ingin diperlihatkan? Tahap terlalu detail menciptakan
ekspektasi bahwa semuanya diperbarui tepat waktu — itu beban operator.
**Sedikit tahap yang selalu akurat lebih baik daripada banyak tahap yang sering basi.**

## 6. Foto progres
Apakah operator benar-benar akan memfoto hasil printing/jahitan? Bernilai tinggi untuk
kepercayaan pelanggan, tapi hanya kalau ada yang disiplin melakukannya. Kalau ragu,
jangan dibangun dulu.

## 7. Koreksi katalog contoh
Draft 5 produk ada di [09-katalog-contoh.md](09-katalog-contoh.md). Yang perlu dikoreksi
pemilik toko: harga asli, ambang tier (12/24/48), daftar bahan yang tersedia, nominal
setup desain, dan lead time realistis per produk.

## 8. Pembelian hosting
PHP 8.2 sudah dipastikan tersedia, jadi **Laravel 12 dipilih dan sudah dipasang** —
pertanyaan framework selesai. Yang masih perlu dipastikan sebelum membeli:
kuota disk, ketersediaan cron, dan apakah document root bisa diarahkan ke `public/`.
Daftar lengkap beserta skrip `cek.php` ada di
[10-spesifikasi-hosting.md](10-spesifikasi-hosting.md).

Sementara itu pengembangan berjalan di Docker lokal yang sengaja dibuat menyerupai
shared hosting (KEP-012).

## 9. Domain & Resend
Domain sudah ada? DNS-nya di mana? Resend butuh verifikasi domain (record SPF/DKIM) —
mudah kalau DNS sudah di Cloudflare, dan Cloudflare memang direkomendasikan (lihat 06).

## 10. Pendaftaran merchant payment gateway
Pilihan teknisnya sudah selesai: **Midtrans** diimplementasikan lebih dulu karena QRIS
sudah termasuk (KEP-015), dan Xendit tetap bisa menggantikannya dengan satu kelas.

Yang tersisa adalah urusan administratif, bukan teknis: **syarat pendaftaran merchant**
(badan usaha vs perorangan, rekening atas nama siapa, dokumen yang diminta). Ini sering
jadi penghambat sesungguhnya. Selama merchant belum disetujui, toko tetap beroperasi
penuh dengan transfer manual.
