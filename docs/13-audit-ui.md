# 13 - Audit UI/UX dan Pengembangan Mobile

Audit menyeluruh atas 21 halaman pada lebar **375px** (iPhone SE/13 mini) dan
**1280px**. Setiap halaman diukur langsung di peramban, bukan diperkirakan.

## Cara mengukur ulang

Lebar dokumen yang melebihi lebar layar berarti halaman bisa digeser ke samping,
dan itu selalu berarti ada yang rusak. Diperiksa dengan memuat tiap halaman di
dalam iframe selebar 375px lalu membandingkan `scrollWidth` dengan lebar layar.

## Temuan dan perbaikan

### 1. Navigasi etalase meluber (kritis)

Bilah navigasi mencapai 527px pada layar 375px, sehingga tautannya terpotong dan
seluruh halaman bisa digeser ke samping.

**Diperbaiki:** navigasi penuh hanya tampil mulai lebar sedang; di bawah itu
dipindah ke panel yang dibuka tombol menu. **Keranjang tetap terlihat di semua
ukuran** karena ia satu-satunya aksi yang sedang dikejar pelanggan.

### 2. Sidebar panel menghimpit konten (kritis)

Sidebar 208px yang muncul sebagai kolom di layar 375px menyisakan 139px untuk
isi, membuat panel praktis tidak terpakai dari HP.

**Diperbaiki:** di mobile sidebar menjadi blok selebar layar di atas konten, dan
menutup sendiri setelah menu dipilih.

### 3. Harga dan tombol beli terkubur (penting)

Di halaman produk, pelanggan mobile harus melewati sekitar 700px tabel
spesifikasi sebelum sampai ke harga dan tombol beli.

**Diperbaiki:** urutan di layar sempit menjadi gambar, kotak beli, lalu
spesifikasi. Susunan desktop tidak berubah. Dikerjakan lewat penempatan baris
dan kolom grid, bukan penggandaan markup.

### 4. Item grid menolak menyusut

Tabel stok per ukuran mendorong seluruh halaman melebar. Penyebabnya item grid
memiliki `min-width: auto`, sehingga pembungkus `overflow-x-auto` di dalamnya
tidak pernah menyempit.

**Diperbaiki:** `min-w-0` pada setiap item grid.

### 5. Daftar pemain memaksa geser horizontal

Tabel empat kolom selebar 400px, padahal daftar ini justru paling sering diisi
dari HP sambil menyalin dari grup tim.

**Diperbaiki:** baris yang melipat, bukan tabel. Nama dan nomor di baris
pertama, ukuran dan tombol hapus di baris kedua.

### 6. Halaman stok dan daftar pesanan

Halaman stok melebar 441px; daftar pesanan berupa tabel enam kolom yang digeser.

**Diperbaiki:** stok memakai baris yang melipat, dan daftar pesanan memakai
kartu di layar sempit sambil tetap tabel di layar lebar. Keduanya dibuka dari
HP dalam pemakaian sehari-hari: opname dilakukan sambil berdiri di depan rak.

### 7. Panel dapat melewati gerbang persetujuan (logika)

Ditemukan saat mengaudit halaman detail pesanan: staf bisa menekan tombol yang
memajukan item dari `MENUNGGU_PERSETUJUAN`, melewati persetujuan pelanggan.
Akibatnya `approved_by_name` dan versi mockup yang disetujui kosong, padahal
justru itu yang menyelesaikan sengketa berbulan-bulan kemudian (docs/02).

**Diperbaiki:** ditolak di `AlurPesanan::majukanItem`, bukan sekadar
disembunyikan di tampilan, dan ditutup test.

## Putaran kedua: kartu katalog

Audit pertama memburu halaman yang rusak, dan melewatkan hal yang lebih
mendasar: **kartu produknya berukuran kartu artikel, bukan kartu marketplace.**

Di layar 375px satu kartu menghabiskan hampir seluruh layar, padahal
marketplace mana pun menampilkan dua kolom. Halamannya tidak rusak, jadi
pengukuran `scrollWidth` tidak menangkapnya.

### Yang diubah

| | Sebelum | Sesudah |
|---|---|---|
| Kolom di 375px | 1 | 2 |
| Kolom di layar lebar | 3 | 4, lalu 5 mulai 1280px |
| Rasio gambar | 4:3 | 1:1 |
| Penanda jalur | baris teks sendiri | ditempel di gambar |
| Baris keterangan | kalimat penuh | satu frasa, dipotong |
| Produk per halaman katalog | 12 | 24 |

Kartu setinggi 266px membuat 2x2 terlihat sekaligus di layar HP, dan baris
ketiga sudah mengintip.

### Rasio gambar yang diam-diam lepas

Tiga dari lima kartu punya tinggi gambar berbeda meski lebarnya sama: 160px,
200px, bahkan 224px pada kotak yang seharusnya persegi.

Penyebabnya `h-full` pada gambar. Tinggi persen tidak bisa dipecahkan terhadap
induk yang tingginya berasal dari `aspect-ratio`, sehingga gambar jatuh ke
tinggi aslinya dan `min-height: auto` membuat kotaknya ikut melar mengikuti.
Yang terlihat rusak hanya produk yang fotonya kebetulan lebih tinggi daripada
lebar.

**Diperbaiki:** gambar diposisikan absolut memenuhi kotak (`absolute inset-0`),
sehingga ukuran aslinya tidak pernah bisa menentukan tinggi kotak. Diterapkan
juga di galeri halaman produk dan thumbnail panel, yang punya cacat sama tetapi
belum kelihatan.

### Filter katalog

Formulir bertumpuk memakan hampir 300px sebelum produk pertama terlihat. Di
layar sempit kini diringkas menjadi satu baris pencarian dengan tombol Filter,
dan pilihannya dibuka saat dibutuhkan.

## Hasil

| | Sebelum | Sesudah |
|---|---|---|
| Halaman meluber di 375px | 4 | 0 |
| Kartu produk terlihat sekaligus di 375px | 1 | 4 |
| Tinggi kartu dalam satu baris | berbeda-beda | seragam |
| Halaman diperiksa | | 21 |

## Putaran ketiga: panel internal

Dua putaran pertama menyisir sisi pelanggan. Panel diaudit tersendiri karena
ukurannya berbeda: ia dipakai berjam-jam tiap hari oleh orang yang sama, jadi
yang menentukan bukan kesan pertama melainkan berapa banyak gerakan yang
dituntutnya.

### Navigasi

Delapan tautan datar memaksa membaca seluruh daftar setiap kali. Kini
dikelompokkan **Operasional, Katalog, Toko**, dan tiga menu yang menyimpan
pekerjaan tertunda memakai penghitung: pesanan berjalan, antrian divisi, dan
pesanan siap diambil. Yang menunggu ditangani terlihat tanpa membuka halamannya.

Angkanya disuntikkan lewat view composer, bukan lewat tiap controller, supaya
tidak ada halaman yang menampilkan menu tanpa penghitung.

### Dashboard

Dulu langsung membuka dengan peringatan stok. Kini menjawab pertanyaan yang
sebenarnya diajukan pemilik toko tiap pagi lebih dulu: **pesanan masuk hari
ini, uang diterima hari ini, sedang dikerjakan, siap diambil**. Baru setelah
itu daftar tindakan.

Daftar tindakannya dipecah dua kolom di layar lebar. Baris data yang meregang
sampai seribu piksel memaksa mata berjalan jauh dari nama barang ke angkanya.

### Detail pesanan

Layar terpadat dan paling sering dibuka, dan dulunya butuh menggulir dua layar
penuh. Sekarang muat satu layar:

- Rincian item punya hierarki: nama tebal, jumlah dan harga meredup, opsi jadi
  penanda, dan **rincian ukuran ditonjolkan** karena itulah satu-satunya angka
  di sana yang dipakai orang produksi saat memotong kain
- Titik tahapan menunjukkan posisi item tanpa perlu membaca riwayat
- Formulir yang jarang dipakai dilipat di balik ringkasan; kotak isian yang
  menganggur membuat layar terlihat penuh padahal tidak ada yang dikerjakan
- Riwayat dilipat karena ia rujukan, bukan pekerjaan
- **Membatalkan pesanan turun ke zona tersendiri** yang diredam. Tindakan
  berisiko tidak boleh sebobot kartu informasi di sebelahnya
- Teks WhatsApp dapat tombol salin

### Halaman produk

Sangat panjang tanpa cara berpindah. Ditambah tautan lompat ke Keterangan,
Gambar, Varian, Tier harga, dan Opsi, yang hanya menampilkan bagian yang
memang berlaku untuk jalur produk itu.

### Halaman masuk staf

Kartu mengambang sendirian di bidang navy terasa belum jadi. Kini terbagi dua:
sisi kiri memuat konteks pekerjaan yang bisa dilakukan di sana, sisi kanan
formulirnya. Ditambah keterangan jalur pemulihan kata sandi, yang sebelumnya
tidak ada sama sekali.

### Bug yang ditemukan saat audit

**`rupiah_ringkas(10000)` menghasilkan "Rp 1 rb".** Nol di belakang dipotong
tanpa memeriksa ada tidaknya koma, sehingga nol signifikan ikut hilang. Angka
uang yang salah jauh lebih buruk daripada angka yang panjang. Ditutup test.

**Trek grid melar mengikuti max-content.** Di layar sempit, kolom `auto` pada
detail pesanan tumbuh sampai 695px meski itemnya sudah diberi `min-w-0`.
Diperbaiki dengan menjadikannya tumpukan biasa di bawah `lg`.

### Konsistensi

Kartu dan tabel di seluruh panel kini memakai kelas yang sama, dan setiap
halaman punya kop dengan judul serta satu kalimat yang menjelaskan isinya.

## Aturan yang dipegang selanjutnya

1. **Ukur, jangan kira.** Setiap layar baru diperiksa `scrollWidth`-nya di 375px.
2. **Aksi utama tidak pernah masuk menu.** Keranjang dan tombol beli selalu terlihat.
3. **Urutan mobile mengikuti urutan pertanyaan pelanggan**, bukan urutan markup.
4. **Tabel data boleh digeser di dalam pembungkusnya**, tidak pernah menggeser halaman.
5. **Layar yang dipakai sambil berdiri** (antrian produksi, stok, serah terima)
   dirancang mobile lebih dulu.
6. **Kartu katalog mengikuti kebiasaan marketplace**: dua kolom di HP, gambar
   persegi, teks ringkas. Kepadatan adalah fiturnya, bukan kekurangannya.
7. **Kotak berasio tidak pernah memuat gambar `h-full`.** Pakai `absolute
   inset-0`, kalau tidak ukuran asli gambar diam-diam menentukan tinggi kotak.
8. **Grid dua kolom baru aktif mulai `lg`.** Di bawah itu pakai tumpukan biasa;
   trek `auto` melar mengikuti max-content isinya meski itemnya sudah `min-w-0`.
9. **Panel: satu kop halaman, satu kelas kartu, satu kelas tabel.** Halaman yang
   dipakai berjam-jam tiap hari tidak boleh menuntut penyesuaian ulang.
10. **Formulir yang jarang dipakai dilipat**, dan tindakan berisiko diletakkan
    di zona tersendiri yang diredam.
