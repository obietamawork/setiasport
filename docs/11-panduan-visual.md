# 11 — Panduan Visual

Identitas diambil dari logo Setia Sport Wonogiri, bukan dari selera yang
dikarang sendiri. Logo itu sekaligus menetapkan aturan pakainya.

## Yang diajarkan logo

Perhatikan bagaimana logo memakai warnanya: **navy mendominasi** sebagai badan
huruf, sementara **merah dan kuning muncul sebagai dua batang tipis** yang
mengapit kata WONOGIRI. Merah tidak pernah menjadi bidang lebar, kuning tidak
pernah dipakai sebagai latar teks.

Aturan itu dipakai apa adanya di seluruh antarmuka.

## Warna

| Token | Hex | Peran |
|---|---|---|
| `navy-900` | `#0D1733` | header panel, halaman masuk |
| `navy` | `#16255B` | warna struktur utama, blok berani, tombol sekunder |
| `navy-400` | `#5F74A8` | teks pendamping pada judul |
| `navy-200` | `#C9D2E6` | teks pendamping di atas navy 900 (hero, kaki halaman), garis lembut (KEP-173) |
| `navy-100` | `#E7EBF5` | penanda, cincin fokus, tautan di kolom kaki halaman (KEP-179) |
| `navy-50` | `#F2F5FA` | latar tabel dan bidang lembut |
| `merah` | `#D62B1F` | **hanya** tombol yang menggerakkan pesanan (beli, pesan, bayar), satu per layar (KEP-167) |
| `kuning` | `#FDC500` | garis penanda, cincin langkah aktif, dan angka besar di atas navy. Tidak pernah latar teks |
| `ink` | `#131A2E` | teks utama, hitam bersemu navy |
| `muted` | `#5D687F` | teks pendukung |
| `line` / `line-strong` | `#E3E7F0` / `#C3CBDC` | garis pemisah dan tepi kendali |
| `paper` / `field` | `#F6F8FC` / `#FFFFFF` | dasar halaman dan permukaan kartu |

Takarannya "Kit Tim" (KEP-167): kira-kira 60 bagian terang, 30 navy, 10 aksen. Harga
memakai tinta, bukan merah. Kotak info memakai navy 50, bukan merah. Tombol yang tidak
menggerakkan pesanan (masuk, simpan, kirim chat, lihat produk) memakai navy, atau putih di
atas navy (KEP-168, KEP-170). Batas waktu dan peringatan memakai kuning peringatan; merah
selain tombol transaksi hanya untuk galat dan tindakan yang merusak. Tidak ada gradien
navy ke merah: campurannya jatuh ke ungu keruh yang bukan warna logo. Blok berani di
etalase memakai `.motif` (navy 900 padat bergaris tipis) dengan `.pita` tiga warna di
tepinya.

### Warna bagian, khusus panel (KEP-111)

Revisi klien meminta "warna atau penanda visual khusus yang mudah dikenali sebagai
pembeda antarbagian". Warna-warna ini hanya dipakai di panel internal; etalase tetap
navy, merah, dan kuning seperti logo.

| Token | Bagian | Status pesanan yang memakainya |
|---|---|---|
| `biru` (`#2563EB`) | pesanan | |
| `hijau` / `ok` | uang | Selesai |
| `jingga` (`#EA7A0C`) | produksi | Diproses |
| `ungu` (`#7C3AED`) | pelunasan, desain | Menunggu pelunasan |
| `pink` (`#E0336F`) | siap diambil atau dikirim | Siap diambil, Dalam pengiriman |
| `kuning` | | Menunggu pembayaran |
| `merah` | | Dibatalkan, Kedaluwarsa |

Satu bagian satu warna di seluruh panel: ikon kartu dashboard, ikon di samping judul
halaman, ikon kartu ringkasan, dan chip status memakai warna yang sama untuk hal yang sama.
Sejak KEP-171 warna bagian hanya mengisi kotak ikon, chip, dan cincin kartu yang dipilih.
Latar kartu tetap putih. Dipasang lewat kelas `.warna-{nama}` (komponen `<x-kartu-angka>`,
`<x-kop ikon warna>`, `.lencana-bagian`, `.pintas`) dan `.chip-{nama}`. Batang kemajuan
dan batang persentase memakai navy di atas navy 100, atau hijau `ok` kalau sudah tuntas,
bukan warna bagian (KEP-179).

## Tipografi

**Plus Jakarta Sans**, dirancang Tokotype untuk identitas kota Jakarta. Dipilih
bukan karena netral, melainkan karena memang dibuat untuk merek Indonesia yang
ingin terbaca rapi dan modern. Dipakai untuk semua isi.

**Barlow Condensed miring** (700 dan 800) khusus untuk judul tampil dan angka
besar (KEP-167). Sempit dan miring seperti logo, sehingga judul terasa seperti
tulisan di jersey. Tidak pernah dipakai untuk paragraf, harga dalam kalimat, atau
label isian.

| Kelas | Pemakaian |
|---|---|
| `.judul-tampil` + `text-*` | judul halaman dan judul bagian di etalase |
| `.angka-tampil` | nomor langkah, hitungan besar, nomor punggung |
| `.alis` + `.garis-kit` | label kecil di atas judul bagian |
| `font-extrabold` + `tracking` rapat | judul kartu, nama produk, judul di panel |
| `.numeral` | angka besar: lead time, harga utama, ringkasan |
| `.tabular` | semua angka dalam tabel dan daftar |
| `.wordmark` | penanda kata sementara sampai berkas logo dipasang |

## Prinsip

1. **Waktu adalah informasi utama.** Setiap produk menyatakan kapan bisa diambil.
   Itu pertanyaan pertama pelanggan, bukan harga.
2. **Satu warna sinyal.** Merah hanya untuk tombol yang menggerakkan pesanan,
   satu per layar. Harga memakai tinta (KEP-167). Begitu merah muncul di hal
   ketiga, ia berhenti berarti apa pun.
3. **Garis, bukan bayangan.** Struktur dibentuk hairline dan perbedaan latar.
   Tidak ada kartu bershadow seragam.
4. **Kuning tidak pernah menampung teks.** Ia hanya garis: penanda navigasi
   aktif, pita tipis di atas blok berani, batang di wordmark.
5. **Data ditampilkan apa adanya.** Stok per ukuran adalah tabel angka nyata,
   bukan dropdown yang justru menyembunyikan informasi penentu keputusan.
6. **HP dulu, desktop pelebaran** (KEP-167). Setiap templat dirancang di 390px,
   dicek sampai 320px, lalu dilebarkan. Desktop tidak menambah langkah. Di HP satu
   aksi utama menempel di bawah layar, dan tabel menjadi kartu.

## Dua register

| | Etalase | Panel internal |
|---|---|---|
| Tujuan | Mengurangi keraguan sebelum bayar | Menyelesaikan pekerjaan |
| Kepadatan | Longgar | Padat, tabel rapat |
| Warna | Kertas dominan, navy sebagai struktur | Sidebar navy tua, bilah atas putih, warna per bagian di ikon |
| Hiasan | Satu blok berani | Garis kuning di menu aktif, angka besar huruf tampil |

Panel wajib mobile-first: operator produksi membuka antrian dari HP di dekat
mesin, opname dilakukan sambil berdiri di depan rak, dan admin memeriksa pesanan
di jalan. Hasil auditnya ada di [13-audit-ui.md](13-audit-ui.md).

### Aturan tata letak sempit

- Setiap layar baru diperiksa `scrollWidth`-nya di 390px dan 320px. Halaman yang
  bisa digeser ke samping selalu berarti ada yang rusak
- Etalase di bawah 768px memakai bilah bawah (Beranda, Produk, Custom, Lacak,
  Chat), bukan menu garis tiga (KEP-167). Badan halaman diberi ruang setinggi
  bilah itu lewat `.ada-nav-bawah`, dan tidak ada tombol yang mengambang di atasnya
- Aksi utama tidak pernah masuk ke dalam menu: keranjang dan tombol beli selalu
  terlihat
- Halaman yang punya satu aksi utama (beli, lanjut ke data pemesan, buat
  pesanan, kirim bukti bayar, serahkan) menempelkannya di dasar layar HP lewat
  pembungkus `.aksi-bawah`, dan memasang `@section('kelas-body', 'ada-aksi-bawah')`
  supaya bilah itu menggantikan bilah navigasi (KEP-174). Tombolnya tetap di
  dalam formulirnya. Bila bilah navigasi hilang, tombol Chat ikut lewat
  `<x-tombol-chat-bawah />`. Formulir panjang di panel memakai `.bilah-aksi`
- Di layar sentuh setiap kendali paling kecil 44px (KEP-174). Tautan teks yang
  berdiri sendiri dan tombol ikon di dalam tabel mendapat lapisan sentuh tak
  terlihat; elemen kecil lain diberi `.area-sentuh`. Tautan di dalam kalimat
  dikecualikan
- Teks paling kecil 12px, termasuk label bilah bawah dan angka penghitung
- Urutan di layar sempit mengikuti urutan pertanyaan pelanggan, bukan urutan
  markup. Di halaman produk: gambar, kotak beli, baru spesifikasi
- Tabel data yang tidak muat menjadi kartu di HP lewat `.tabel-hp`: setiap `<td>`
  membawa label kolomnya di `data-kolom`, dan kolom utama ditandai `data-utama`
  supaya naik ke baris pertama kartu. Tabel dengan beberapa kolom pendek yang muat
  di 320px tetap tabel, karena kartu memakan beberapa baris per data (KEP-181).
  Hanya tabel ringkas beberapa kolom yang boleh digeser di dalam pembungkusnya,
  dan tidak pernah menggeser halaman
- Kartu angka tidak ditumpuk satu kolom di HP. Tiga kartu tetap sebaris lewat
  `.deret-angka`, yang merapatkan kartunya di bawah 640px; empat atau lima kartu
  dua kolom (KEP-181)
- Deret pilihan yang setara (tab penyaring, penanda langkah) boleh digeser ke
  samping di dalam kotaknya. Deret urutan seperti progres pesanan tidak: tahap
  terakhirnya tidak boleh tersembunyi. Di layar sempit progres menurun lewat
  `<x-tahap-menurun>`, dan versi mendatarnya `.tahap-datar` baru dipakai setelah
  semua tahap muat (KEP-182)
- Mulai 1024px konten dan ringkasannya berdampingan, dengan ringkasan menempel
  di `top-24` supaya tidak tertutup kepala halaman yang menempel
- Item grid yang memuat pembungkus `overflow-x-auto` wajib diberi `min-w-0`,
  karena `min-width: auto` bawaannya membuat pembungkus itu tidak pernah menyempit
- Kotak berasio tidak pernah memuat gambar ber-`h-full`. Pakai `absolute
  inset-0`, kalau tidak ukuran asli gambar diam-diam menentukan tinggi kotak

### Panel internal

Kerangkanya mengikuti gambar contoh klien (KEP-111): sidebar navy gelap setinggi layar
dengan logo dan "Panel Admin", bilah atas putih berisi cari, lonceng, Bantuan, dan akun.
Sejak KEP-171 panel ikut Kit Tim:

- Menu aktif berwarna navy terang dengan garis kuning di kiri, dan penghitungnya putih
  tembus.
- Kartu angka dan ubin jalan pintas berlatar putih dengan bayangan tipis. Warna bagian
  hanya ada di kotak ikonnya, dan angkanya memakai huruf tampil.
- Judul halaman memakai huruf tampil.
- Tombol yang tidak merusak berwarna navy.
- Total pesanan memakai tinta.
- Merah hanya untuk galat, tindakan merusak, dan penanda masalah: sisa tagihan,
  keterlambatan, dan stok habis.

Satu kop halaman, satu kelas kartu, satu kelas tabel. Halaman yang dipakai
berjam-jam tiap hari tidak boleh menuntut penyesuaian ulang di tiap layar.

- Menu dikelompokkan menurut jenis pekerjaan, dan yang menyimpan pekerjaan
  tertunda memakai penghitung
- Bilah bawah di layar sentuh mengikuti peran (KEP-175): pemilik dan admin
  Dashboard, Pesanan, Customer Service, Serah terima; kasir mendahulukan Serah
  terima; bagian produksi mendahulukan Antrian kerja. Sisanya ada di laci Menu
- Antrian kerja di layar lebar berbentuk papan kerja: satu kolom per tahap,
  paling banyak enam kartu pekerjaan per kolom, dan papannya digeser ke samping
- Lebar kerja dibatasi `.kolom-kerja`, bukan mengikuti lebar layar
- Formulir yang jarang dipakai dilipat di balik ringkasan
- Tindakan berisiko turun ke `.zona-bahaya` yang diredam, tidak sebobot kartu
  informasi di sebelahnya
- Grid dua kolom baru aktif mulai `lg`; di bawah itu tumpukan biasa

### Kartu katalog

Mengikuti kebiasaan marketplace, bukan kartu artikel: **dua kolom di HP**, empat
sampai lima di layar lebar, gambar persegi, nama dipotong dua baris, dan satu
frasa waktu. Penanda jalur ditempel di gambar karena setiap baris teks berharga
di kartu selebar 160px. Kepadatan adalah fiturnya.

## Aturan penulisan

- **Tanpa tanda pisah em.** Pakai koma, titik, atau kurung. Judul halaman
  memakai `|` sebagai pemisah, bukan tanda pisah.
- Kalimat biasa, bukan huruf kapital semua.
- Tombol menyebut apa yang terjadi: "Simpan stok", bukan "Kirim".
- Layar kosong adalah ajakan bertindak, bukan kabar buruk.
- Pesan galat menjelaskan apa yang salah **dan** cara memperbaikinya, tanpa
  minta maaf dan tanpa samar.

## Logo

Komponen `<x-logo />` memakai berkas asli begitu tersedia. Taruh di
`public/logo.svg` (boleh juga `logo.png` atau `logo.webp`) dan seluruh halaman
memakainya otomatis, tanpa perlu disentuh satu per satu. Selama berkas belum
ada, komponen menampilkan penanda kata yang meniru susunan logo.

## Catatan teknis

- Kedua huruf di-host sendiri dari `resources/fonts`: Plus Jakarta Sans untuk isi
  dan Barlow Condensed miring untuk judul tampil. Tidak ada pemanggilan ke Google
  Fonts.
- **Email** tidak bisa memakai kelas atau token CSS, jadi warnanya ditulis inline
  memakai heksadesimal token yang sama (KEP-172): kepala `navy-900` dengan pita tiga
  warna, tombol `navy`, teks `ink` dan `muted`, kotak info `navy-50`. Email memakai
  Arial, bukan huruf tampil, karena banyak klien email tidak memuat huruf web.
- **Bangun aset setelah semua view ditulis.** Tailwind memindai berkas untuk
  menemukan kelas yang dipakai; `npm run build` yang dijalankan sebelum sebuah
  view dibuat menghasilkan CSS tanpa kelas view tersebut.
