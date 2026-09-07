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
