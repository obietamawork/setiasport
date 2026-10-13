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

## Putaran keempat: setelah rombak beranda dan alur desain (KEP-062 s/d 065)

Audit pemolesan menyeluruh setelah beranda dirombak mengikuti mockup klien dan
alur unggah desain diperketat. Kali ini ikut memeriksa alur status pesanan di
panel, bukan cuma tampilan.

### Gerbang pembayaran awal bisa dilewati (kritis)

Ditemukan saat menelusuri tombol "Tandai [tahap]" di detail pesanan: gerbang
persetujuan desain dan gerbang pelunasan sudah dijaga tanpa tombol (docs/02,
KEP-028), tapi **gerbang pembayaran awal justru terlewat**. Staf bisa
mengetuk tombol itu berkali-kali pada item yang `MENUNGGU_PEMBAYARAN` —
uangnya belum pernah diverifikasi sama sekali — dan sistem tetap menaikkannya
sampai `SIAP_DIAMBIL`, kode ambil terbit, seolah barang boleh dibawa pulang.

Ditemukan lewat kombinasi tak sengaja: pesanan demo lama yang tersangkut di
status Siap Diambil padahal `sisa_tagihan`-nya masih penuh, dan halaman
lacaknya menampilkan pesan yang bertentangan (transfer sekarang sekaligus
bayar saat ambil).

**Diperbaiki:** ditolak di `AlurPesanan::majukanItem`, sejajar dengan dua
gerbang lain, bukan sekadar disembunyikan di tampilan. Tombolnya di panel
kini berganti keterangan "Naik ke [tahap] begitu pembayaran tercatat" saat
item masih `MENUNGGU_PEMBAYARAN`. Ditutup test.

### Bintang testimoni tidak terlihat penuh

Ikon garis (`fill="none"`, gaya ikon panel keseluruhan) dipakai apa adanya
untuk bintang rating testimoni di beranda, sehingga di layar kecil terbaca
sebagai bentuk kosong, bukan bintang emas penuh seperti kebiasaan rating pada
umumnya.

**Diperbaiki:** komponen ikon menerima `fill="currentColor"` lewat
`$attributes` (atribut pertama menang saat HTML diparse), tanpa mengubah
default ikon di tempat lain.

### Yang diperiksa dan aman

Sapuan otorisasi atas seluruh route panel (`izin:` middleware terpusat di
`routes/panel.php`, bukan tercecer per-controller), entropi token pelacakan
(64 karakter acak) dan pembatasan laju pencarian kode+email (10x/5 menit),
serta kecocokan `order_id` di setiap route bertoken/berparameter item —
semuanya konsisten dan tidak ditemukan celah baru.

## Putaran kelima: revisi klien atas halaman depan dan produk

### Area kosong raksasa di halaman produk (kritis)

Dilaporkan klien lewat tangkapan layar: ada jarak kosong besar di bawah foto
produk, sebelum tabel "Pilihan untuk pesanan kustom". Penyebabnya tata letak
grid dua kolom lama — kotak beli (kanan) memakai `row-span-2` melintasi baris
gambar dan baris spesifikasi (kiri) supaya bisa disisipkan di antara keduanya
saat tampilan HP menyusun ulang jadi gambar → kotak beli → spesifikasi.

Masalahnya: spesifikasi CSS Grid membesarkan track baris `auto` yang dilintasi
supaya cukup menampung elemen yang melintang, dan kotak beli produk kustom ini
jauh lebih tinggi daripada gambar produknya sendiri (banyak kolom pilihan:
bahan, lengan, kerah, celana, nameset, setup desain). Selisihnya tampil sebagai
ruang kosong di bawah gambar. `grid-auto-rows: min-content` **tidak menutup**
celah ini di Chrome — diverifikasi lewat percobaan langsung, bukan diasumsikan
dari dokumentasi spesifikasi.

**Diperbaiki:** ditata ulang jadi flexbox. Gambar dan spesifikasi dibungkus
satu elemen (`display: contents` di bawah `lg`, `flex flex-col` di atasnya)
supaya keduanya ikut tersusun sebagai satu kolom flex di layar lebar, sementara
di HP pembungkusnya "tembus" dan urutan `order-1/2/3` tetap menyisipkan kotak
beli di antara gambar dan spesifikasi. Flexbox tidak punya konsep "track"
yang dipaksa menyamai tinggi elemen lain, jadi kolom yang lebih pendek
berhenti begitu isinya habis, tidak dipaksa mengisi ruang setinggi kolom
sebelahnya.

### Kategori, ikon sosial, dan keranjang dipoles

- **Grid kategori dikecilkan**: 2/3/6 kolom (HP/tablet/desktop) menjadi
  3/4/6, kartunya lebih rapat mengikuti masukan klien.
- **Ikon Instagram/TikTok nyaris tak terlihat** di kaki halaman (garis tipis
  abu di atas kartu terang). Diganti kotak navy solid — tetap satu warna
  bermerek toko, bukan warna resmi Instagram/TikTok yang akan pecah dari
  palet situs.
- **Teks "Keranjang" muncul meluncur saat ikon di-hover** di layar besar
  (`md:` ke atas), memakai `max-w-0` yang membesar lewat `group-hover`. HP
  tidak berubah karena tidak punya konsep hover.

### Gambar produk mengikuti varian yang dipilih (KEP-066)

Fitur baru, bukan bug: klien membandingkan dengan marketplace yang mengganti
foto begitu pembeli memilih warna — lalu menegaskan itu bukan cuma soal warna,
produk kustom juga punya pilihan (kerah, bahan) yang tampilannya beda-beda.
Berlaku untuk keduanya: warna varian ready stock, dan nilai opsi kustom.
Rincian keputusan dan alasan teknisnya ada di
[08-log-keputusan.md](08-log-keputusan.md#kep-066--gambar-produk-bisa-ditandai-mewakili-satu-varian).

## Putaran keenam: audit menyeluruh pelanggan dan internal, fokus HP

Diminta klien secara eksplisit: audit ulang seluruh website dan panel, HP
paling diutamakan, plus sederhanakan sisi internal kalau memang ribet.

### Sapuan lebar layar di ~28 halaman

Dicek `scrollWidth` di 375px untuk seluruh halaman publik (beranda, katalog,
produk, keranjang, checkout, lacak, masuk, daftar) dan seluruh halaman panel
(dashboard, pesanan, antrian, kategori, produk, stok, portofolio, testimoni,
pekerja, laporan, pengaturan, serah terima, kesiapan, bantuan, worksheet,
invoice). **Nol halaman meluber.** Putaran audit sebelumnya (satu sampai
lima) sudah menutup celah-celah itu; tidak ada regresi baru.

### Ringkasan tagihan terkubur di bawah tombol checkout (kritis)

Di halaman "Data pemesan" (checkout), kartu Ringkasan — total yang harus
dibayar — berada di kolom kanan yang di layar lebar memang selalu terlihat.
Di HP, grid dua kolom itu runtuh jadi satu kolom mengikuti urutan markup:
formulir dulu, **Ringkasan paling akhir, setelah tombol "Buat pesanan"**.
Pelanggan mengisi nama, email, nomor HP, dan alamat tanpa pernah melihat
angka yang disepakati sampai mereka menggulir melewati tombol konfirmasinya
sendiri — bertentangan dengan prinsip *visibility of system status*, dan
dengan aturan #3 dokumen ini sendiri ("urutan mobile mengikuti urutan
pertanyaan pelanggan, bukan urutan markup").

**Diperbaiki:** `order-1`/`order-2` Tailwind pada `<form>` dan `<aside>`
Ringkasan, dibalik hanya di bawah `lg`. Ringkasan sekarang tampil tepat di
bawah judul halaman, sebelum formulir dimulai. Layar lebar tidak berubah
sama sekali karena keduanya memang sudah berdampingan di sana.

### Pembayaran bisa tercatat lunas dua kali (kritis, ditemukan tak sengaja)

Sambil memeriksa halaman detail pesanan di panel, satu pesanan demo
menampilkan DUA baris pembayaran untuk nominal yang identik: satu berstatus
**Lunas** (dibayar tunai di kasir), satu lagi masih **Menunggu Pembayaran**
(tagihan transfer dari saat checkout, tidak pernah dibatalkan). Pesanannya
sendiri sudah lunas (`sisa_tagihan` 0), tapi tombol "Verifikasi pembayaran
ini" pada baris lama **masih aktif dan bisa diklik**.

Akar masalahnya: `AlurPesanan::terimaPembayaran()` hanya memproses SATU
baris pembayaran yang sedang diterima; baris pembayaran lama dari jalur lain
(mis. permintaan transfer saat checkout) dibiarkan menggantung kalau
pelanggan ternyata bayar lewat jalur berbeda (tunai di kasir). Kalau staf
tidak sadar dan mengklik verifikasi pada baris lama itu juga, pesanan yang
sama tercatat lunas **dua kali** di laporan keuangan — uang yang sebenarnya
diterima sekali terlihat seperti diterima dua kali.

**Diperbaiki:** begitu `terimaPembayaran()` membuat `sisa_tagihan` turun ke
0, seluruh baris pembayaran LAIN yang masih Menunggu otomatis ditandai
Kadaluarsa — meniru persis pola yang sudah ada di `AlurPesanan::batalkan()`
untuk pesanan yang dibatalkan. Chip status pembayaran juga dirapikan supaya
Kadaluarsa dan Gagal tidak lagi memakai warna kuning yang sama dengan
Menunggu (yang masih butuh tindakan) — jadi sekilas pun sudah jelas mana
yang masih perlu dikerjakan dan mana yang sekadar riwayat.

### Target sentuh di bawah 40px

Disisir seluruh tombol dan tautan ikon-saja di halaman publik lewat
`getBoundingClientRect()`. Tombol menu hamburger dan ikon sosial di kaki
halaman keduanya 36&times;36px, di bawah rekomendasi umum 44&times;44px
(Apple HIG, Material Design) untuk target yang sering disentuh berulang.
Dibesarkan ke 44px. Tautan teks mengalir (misalnya "Lihat semua") sengaja
tidak diubah: ukurannya mengikuti teks, dan memaksa tinggi baris 44px
untuk tautan inline justru merusak kerapatan teks di sekitarnya.

### Navigasi internal: tidak disederhanakan, karena sudah tidak ribet

Diperiksa ulang dengan heuristik kompleksitas (Hick's Law: makin banyak
pilihan sejajar, makin lama memutuskan). Menu sampingnya sudah terbagi
empat kelompok (Operasional, Katalog, Profil usaha, Toko) hasil putaran
audit ketiga, dan bilah bawah HP hanya menonjolkan 4 tujuan tersering
(Dashboard, Pesanan, Antrian, Serah terima) plus satu "Menu" untuk
sisanya — bukan sepuluh ikon berjajar. Target sentuhnya sendiri sudah
72&times;56px, jauh di atas batas minimum. Tidak ditemukan alasan kuat
untuk merombaknya lebih jauh; menyederhanakan sesuatu yang sudah
sederhana hanya memindahkan kerumitan ke tempat lain.

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
11. **Kolom bertinggi tidak sama tidak pernah dipaksa lewat `row-span` grid.**
    Baris `auto` yang dilintangi membesar mengikuti elemen tertinggi meski
    `grid-auto-rows: min-content` dicoba, menyisakan ruang kosong di elemen
    yang lebih pendek. Pakai flexbox (`display: contents` di breakpoint
    sempit kalau urutan harus tetap bisa disisipkan) untuk kolom yang
    tingginya wajar berbeda.
