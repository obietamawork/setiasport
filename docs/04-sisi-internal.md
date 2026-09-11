# 04 — Sisi Internal

## Prinsip #1: Antrian, bukan CRUD

Panel admin generik ("daftar semua order, klik, ubah status di dropdown") **akan gagal**
di lingkungan workshop. Operator jahit tidak peduli pada 200 order; dia peduli pada
**5 pekerjaan yang harus dia jahit hari ini**.

Maka panel internal dibangun sebagai **antrian per divisi**:

- Desainer buka panel → melihat "Antrian Desain saya" → kerjakan → upload mockup → selesai
- Operator printing → "Siap Print" → tandai selesai
- Operator jahit → "Siap Jahit" → tandai selesai
- Gudang → "Siap Packing" → tandai selesai

Satu klik menyelesaikan pekerjaan **dan** menaikkan status yang dilihat pelanggan.

## Prinsip #2: Nol input ganda

**Status pelanggan tidak pernah diketik.** Ia adalah efek samping dari pekerjaan nyata
yang diselesaikan. Kalau operator harus mengerjakan jahitan *lalu* membuka layar lain
untuk memperbarui status, dalam dua minggu status akan basi dan sistemnya ditinggalkan.

Konsekuensi desain: setiap perpindahan tahap harus bisa dilakukan dari layar tempat
pekerjaan itu berada, dalam satu aksi, di HP (operator produksi tidak duduk di depan PC).
**Panel internal harus mobile-first.**

## Prinsip #3: Peran boleh dikonfigurasi, tapi ada bawaan yang masuk akal

Sesuai permintaan: kategori, jenis, dan role internal diisi belakangan. Maka pakai
**RBAC berbasis permission** (bukan enum role di kode), dengan role bawaan yang bisa
diubah namanya, digabung, atau ditambah.

| Peran bawaan | Tanggung jawab |
|---|---|
| Pemilik | Semuanya, termasuk pengaturan, pengguna, dan laporan |
| Admin | Seluruh operasi harian: pesanan, pembayaran, kelima antrian produksi, katalog, stok, serah terima |

Sesuai permintaan pemilik toko, hanya dua peran yang dipakai (KEP-017). Daftar
permission tetap rinci, jadi peran yang lebih spesifik bisa ditambahkan lewat
panel kapan saja tanpa menyentuh kode.

Relasi user dan role tetap **many-to-many**, supaya satu orang bisa memegang
beberapa peran sekaligus bila susunannya kelak diperinci lagi.

## Layar internal yang benar-benar dibutuhkan

### Dashboard harian (layar paling sering dibuka)
Bukan grafik omzet. Yang dibutuhkan tiap pagi adalah **daftar tindakan**:
- Pembayaran menunggu verifikasi: N
- Menunggu approval pelanggan > 2 hari: N ← ini yang paling sering nyangkut
- Email bounce / tidak terkirim: N ← harus di-WA manual
- Antrian print / jahit hari ini
- Siap diambil hari ini
- **Sudah siap tapi belum diambil > 7 hari** ← memakan rak toko, sering terlupakan

### Detail order (satu layar, semua konteks)
Item, roster, file desain (semua versi), riwayat pembayaran, dan **log aktivitas lengkap**:
siapa mengubah apa dan kapan. Untuk toko yang punya beberapa karyawan, log ini adalah
alat penyelesaian perselisihan internal.

### Worksheet produksi (cetak)
Selembar kertas per order yang menempel di kain saat dikerjakan: kode order, nama tim,
mockup kecil, dan **tabel roster nama–nomor–ukuran**. Ini fitur bernilai sangat tinggi
dengan biaya implementasi sangat rendah — cukup halaman HTML dengan CSS print.

### Serah terima (satu layar, dua aksi)
Konsekuensi KEP-006: pelunasan terjadi di kasir saat pengambilan. Maka layar ini
menggabungkan **catat pelunasan + tandai diambil** dalam satu alur:
cari/scan kode ambil → tampil sisa tagihan → pilih metode (Tunai/Transfer/QRIS) →
nominal terisi otomatis → simpan → order `Selesai`.
Order **tidak boleh** menjadi `Selesai` selama sisa tagihan > 0.

### Verifikasi pembayaran (konsekuensi KEP-023)
Tidak ada gerbang yang mengabari sistem bahwa uang sudah masuk. Admin membuka mutasi
rekening atau riwayat QRIS, mencocokkannya dengan bukti yang diunggah pelanggan di
halaman pesanannya (KEP-029), lalu menekan Verifikasi di detail order. Tombol itu
mewajibkan pernyataan bahwa uangnya sudah terlihat di mutasi: bukti bisa diedit,
mutasi tidak. Di situ pula **metode sebenarnya**
dan **gambar buktinya** dicatat, karena saat checkout belum ada yang tahu pelanggan
akan membayar lewat apa. Buktinya disimpan sebagai jejak kalau kelak ada selisih setoran.

Alternatifnya satu tombol lagi: **tandai gagal** dengan alasan yang wajib ditulis,
supaya ada yang bisa dijelaskan ke pelanggan.

### Pengiriman (konsekuensi KEP-026)
Khusus pesanan yang isinya murni stok toko. Disetel dari halaman pesanan, bukan dari
layar kasir, karena kesepakatan mengirim terjadi lewat chat CS jauh sebelum barangnya
siap. Admin mengisi alamat yang disepakati, lalu setelah paket diserahkan ke kurir ia
memilih jasa kirim dan mengetik nomor resinya. Pelanggan langsung menerima email berisi
resi itu.

Ongkirnya dicatat terpisah sebagai **penyesuaian biaya**, bukan kolom tersendiri, supaya
pelanggan melihatnya sebagai angka yang bisa dijelaskan.

Pesanan berhenti di **Dalam Pengiriman** sampai ada yang menekan "paket sudah diterima".
Itu disengaja: selama belum ditekan, pesanan tetap muncul di daftar aktif, dan paket yang
nyangkut di kurir tidak hilang dari pandangan.

### Daftar pesanan dipisah dua tab (konsekuensi KEP-027)
Kustom dan stok toko punya tabnya sendiri, karena yang mengurus konveksi dan yang
mengambil barang dari rak sedang mengerjakan pekerjaan berbeda. Pesanan campuran masuk
ke tab Kustom dengan penanda "Campuran".

### Ruang CS (konsekuensi KEP-030 dan KEP-031)
Antarmuka kerja tersendiri di `/panel/cs`, layar penuh tanpa sidebar panel, dengan akun
dan izin yang sama. Tiga panel: daftar percakapan, percakapan, dan info pelanggan. Yang
paling lama menunggu di urutan teratas; garis tepinya kuning selama masih dalam batas
wajar dan merah begitu lewat 15 menit. Komplain ditandai merah.

Saklar **Menerima chat** di bilah atas menentukan apa yang dilihat pelanggan: online,
atau offline dengan janji dibalas pada jam layanan. Matikan saat istirahat. Di samping percakapan tampil pesanan yang ditautkan, dengan penanda
**terverifikasi** (dibuka dari tautan pesanan atau akun pemiliknya) atau **belum
terverifikasi** (kode yang sekadar diketik). Jangan ubah apa pun di pesanan, misalnya nama
pengambil, hanya berdasarkan chat yang belum terverifikasi.

Template balasan dipilih lewat tombol Template atau dengan mengetik `/` di awal kolom
balasan, dan dikelola dari "Kelola template" di bilah atas.

Sambungan chat terpasang di **semua** halaman panel: admin yang sedang mengurus stok tetap
mendengar bunyi dan melihat angka merah saat pelanggan menulis. Chat yang belum dibalas
lebih dari 15 menit dikirim ke email staf.

### Portofolio dan testimoni (konsekuensi KEP-024)
Halaman depan adalah profil usaha, dan isinya dikelola dari panel: pekerjaan yang
pernah selesai (lengkap dengan foto, instansi, tahun, jumlah pcs) dan kutipan
pelanggan. Nama instansi diketik manual, hanya untuk yang sudah mengizinkan; tidak
ada yang ditarik otomatis dari tabel `orders`.

### Tindak lanjut email (konsekuensi KEP-004)
Karena tidak ada WhatsApp API, sistem harus tahu kapan menyerah dan menyerahkan urusan
ke manusia:
- Setiap notifikasi punya tombol **"Salin pesan WA"** — teks tersusun otomatis berikut
  tautan tracking, admin klik, `wa.me` terbuka, tinggal kirim
- Status email dari webhook Resend (delivered/opened/bounced) tampil di detail order
- Dashboard menandai order yang emailnya bounce atau tidak direspons > 2 hari

### Manajemen stok (hanya untuk alur biasa)
- Stok di level **varian** (ukuran × warna), bukan di level produk
- **Reservasi stok** saat order dibuat, dilepas kalau order kadaluarsa/batal. Tanpa ini,
  dua orang bisa membeli sepatu terakhir yang sama
- Penyesuaian stok manual dengan alasan (opname, rusak, retur)

## Yang sengaja TIDAK dibangun di fase awal

- Perhitungan HPP / margin per order otomatis
- Penjadwalan kapasitas produksi otomatis
- Absensi, payroll, upah borongan operator
- Integrasi akuntansi

Semua ini terlihat menarik, tapi tidak ada yang mengurangi chat "sudah sampai mana?".
Tunda sampai alur inti terbukti dipakai setiap hari.
