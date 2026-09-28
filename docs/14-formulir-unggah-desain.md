# 14 — Formulir Unggah File Desain Besar

Panduan untuk pemilik toko / admin, bukan untuk pengembang. Tidak butuh coding,
cukup akun Google yang sudah ada (Gmail toko).

## Kenapa ini dibutuhkan

Pelanggan yang punya file desain siap cetak (AI, PSD, CDR — biasanya dari
desainer sendiri atau sponsor tim) tidak bisa mengunggahnya langsung ke website
ini. Alasannya ada di [KEP-002](08-log-keputusan.md) dan
[KEP-064](08-log-keputusan.md): file semacam itu bisa ratusan MB, dan kalau
semua pelanggan mengunggahnya ke server kita, disk hosting habis dalam
hitungan bulan. Yang website terima cuma **gambar preview** untuk dilihat
desainer, bukan file kerjanya.

Sejauh ini, pelanggan yang punya file besar diminta menempelkan tautan Google
Drive-nya sendiri. Masalahnya: tidak semua orang tahu cara membuat tautan
berbagi (klik kanan → Bagikan → ubah jadi "Siapa saja yang punya tautan").
Banyak yang menyerah di langkah itu dan akhirnya kirim lewat WhatsApp, padahal
itu justru yang ingin dihindari — filenya jadi tidak tertaut ke pesanan mana
pun, dan tercecer di riwayat chat.

**Solusinya:** toko menyediakan satu tempat "drop box" sendiri. Pelanggan
tinggal pilih file dari HP/komputernya dan unggah — sama seperti mengunggah
foto di aplikasi lain, tidak perlu tahu apa itu "tautan berbagi".

## Cara membuatnya (Google Form — disarankan)

Butuh waktu sekitar 5 menit. Pakai akun Google yang sama dengan yang dipakai
untuk email toko.

1. Buka [forms.google.com](https://forms.google.com), buat formulir baru.
2. Judul formulir: misalnya **"Unggah File Desain — Setia Sport"**.
3. Tambah pertanyaan pertama: **"Kode pesanan"** (contoh: SS-2609-0042),
   jenis jawaban **Jawaban singkat**, centang **Wajib diisi**. Ini yang
   dipakai untuk mencocokkan file dengan pesanan siapa.
4. Tambah pertanyaan kedua: **"Nama pemesan"**, jawaban singkat, tidak wajib
   (sekadar jaga-jaga kalau kode pesanannya salah ketik).
5. Tambah pertanyaan ketiga: jenis **Upload file**. Google akan minta izin
   membuat folder Drive untuk menampung file yang masuk — izinkan. Atur
   ukuran maksimal (10 MB cukup longgar; boleh dinaikkan sampai 1 GB kalau
   perlu, filenya tidak lewat server kita).
6. Klik ikon mata (Preview) untuk memastikan formulirnya benar, lalu klik
   **Kirim** → salin tautannya (ikon rantai).

**Penting:** file yang masuk lewat formulir ini **hanya bisa dilihat toko**,
tidak bisa dilihat pelanggan lain yang mengisi formulir yang sama. Google Forms
memang dirancang begitu — setiap orang hanya melihat unggahannya sendiri.

### Cara melihat file yang masuk

Buka Google Forms yang sudah dibuat → tab **Tanggapan** → ikon Google Sheets
di kanan atas untuk membuka semuanya sebagai tabel (kode pesanan, nama, dan
tautan filenya sejajar per baris). Atau buka Google Drive → folder dengan nama
formulirnya, semua file yang diunggah ada di sana.

Tidak ada notifikasi otomatis ke panel — ini di luar sistem web, sengaja,
supaya tidak menambah beban server. Cek tabelnya sesekali, atau nyalakan
notifikasi email dari Google Forms sendiri (tab Tanggapan → ikon titik tiga →
"Dapatkan notifikasi email untuk tanggapan baru").

## Alternatif: Dropbox File Request

Kalau lebih suka Dropbox daripada Google, fitur **File Request** (gratis)
punya cara kerja serupa: buat permintaan file, dapat tautan, siapa pun bisa
unggah tanpa perlu akun Dropbox sama sekali.

Bedanya: Dropbox File Request tidak punya kolom "kode pesanan" bawaan. Kalau
memilih ini, minta pelanggan menuliskan kode pesanannya di **nama filenya**
sendiri (misalnya `SS-2609-0042 - desain.zip`) sebelum diunggah, supaya tetap
bisa dicocokkan nanti.

## Memasangnya di website

1. Masuk panel → **Pengaturan** → tab **Pesanan** → bagian **Desain &
   produksi**.
2. Tempel tautan formulirnya di kolom **Tautan Unggah File Besar**.
3. Simpan.

Begitu terisi, tombol **"Unggah file besar di sini"** otomatis muncul di
halaman pesanan pelanggan, tepat di sebelah kolom tautan Google Drive. Kosongkan
kolom itu kapan saja untuk menyembunyikan tombolnya lagi — tidak ada yang perlu
diubah di kode.
