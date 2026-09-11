# 07 — Keputusan Terbuka

Tiga puluh empat keputusan sudah terkunci — lihat [08-log-keputusan.md](08-log-keputusan.md).
Tidak ada lagi item yang mengubah skema database secara mendasar.

Sebagian besar pertanyaan di bawah **sudah punya jawaban teknisnya di kode**; yang
tersisa adalah angka dan kebijakan yang diisi pemilik toko lewat Panel → Pengaturan,
bukan lewat deploy. Yang masih benar-benar terbuka ditandai **BELUM DIPUTUSKAN**.

---

## 1. Aturan revisi desain
Jumlah revisi gratis sudah jadi pengaturan (`revisi_gratis_maks`, bawaan 3). Yang
**BELUM DIPUTUSKAN**: berapa biaya revisi ke-4 dan seterusnya, dan apakah desain yang
sudah disetujui masih boleh diubah dengan biaya (yang otomatis jadi `order_adjustment`).

## 2. Roster / nameset — SELESAI
Pelanggan mengisinya sendiri di halaman pesanannya, lewat form dinamis, dan roster
terkunci begitu desain disetujui (KEP-013). Unggah Excel/CSV tidak dibangun: untuk
ukuran tim 12-30 orang, mengetik langsung lebih cepat daripada menyiapkan berkas.

## 3. Kebijakan pembatalan setelah DP masuk
Usulan bertingkat:
- Batal **sebelum** desain dikerjakan → DP kembali (dipotong biaya admin?)
- Batal **setelah** desain disetujui / masuk produksi → DP hangus

Kebijakan ini harus tampil sebagai centang persetujuan **di halaman checkout**, bukan
disembunyikan di halaman syarat & ketentuan.

## 4. Batas waktu
Ketiganya sudah jadi pengaturan dengan nilai bawaan yang masuk akal:
`batas_bayar_jam` (24), `pengingat_ambil_hari` (3), `ambil_peringatan_hari` (7).
Pemilik toko tinggal mengoreksi angkanya. Yang **BELUM DIPUTUSKAN**: setelah berapa
lama barang yang tidak pernah diambil dianggap hangus, dan apa yang terjadi pada DP-nya.

## 5. Kedalaman tracking — SELESAI
Lima tahap: desain, printing, jahit, QC/packing, siap diambil. Masing-masing berubah
karena ada orang yang benar-benar menekan tombol saat mengerjakannya, bukan karena
timer. **Sedikit tahap yang selalu akurat lebih baik daripada banyak tahap yang sering
basi**, dan itulah kenapa tidak ditambah lagi.

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

## 10. Pendaftaran merchant payment gateway — SELESAI, tidak jadi dipakai
Toko memutuskan tetap memakai QRIS statis dan rekening yang sudah ada, dengan
verifikasi manual (KEP-023). KEP-015 dibatalkan dan kodenya dihapus.

Yang perlu disiapkan pemilik toko sekarang cuma isi Pengaturan: nama bank, nomor
rekening, atas nama siapa, gambar QRIS, dan nomor WhatsApp yang benar-benar dibaca.
