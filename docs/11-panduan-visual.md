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
| `navy-100` | `#E7EBF5` | penanda, cincin fokus |
| `navy-50` | `#F2F5FA` | latar tabel dan bidang lembut |
| `merah` | `#D62B1F` | **hanya** harga dan satu aksi utama per layar |
| `kuning` | `#FDC500` | **hanya** garis penanda, tidak pernah latar teks |
| `ink` | `#131A2E` | teks utama, hitam bersemu navy |
| `muted` | `#5D687F` | teks pendukung |
| `line` / `line-strong` | `#E3E7F0` / `#C3CBDC` | garis pemisah dan tepi kendali |
| `paper` / `field` | `#F6F8FC` / `#FFFFFF` | dasar halaman dan permukaan kartu |

## Tipografi

**Plus Jakarta Sans**, dirancang Tokotype untuk identitas kota Jakarta. Dipilih
bukan karena netral, melainkan karena memang dibuat untuk merek Indonesia yang
ingin terbaca rapi dan modern. Satu keluarga untuk seluruh sistem.

| Kelas | Pemakaian |
|---|---|
| `font-extrabold` + `tracking` rapat | judul halaman dan nama produk |
| `.numeral` | angka besar: lead time, harga utama, ringkasan |
| `.tabular` | semua angka dalam tabel dan daftar |
| `.wordmark` | penanda kata sementara sampai berkas logo dipasang |

## Prinsip

1. **Waktu adalah informasi utama.** Setiap produk menyatakan kapan bisa diambil.
   Itu pertanyaan pertama pelanggan, bukan harga.
2. **Satu warna sinyal.** Merah dipakai untuk harga dan satu aksi utama saja.
   Begitu ia muncul di hal ketiga, ia berhenti berarti apa pun.
3. **Garis, bukan bayangan.** Struktur dibentuk hairline dan perbedaan latar.
   Tidak ada kartu bershadow seragam.
4. **Kuning tidak pernah menampung teks.** Ia hanya garis: penanda navigasi
   aktif, pita tipis di atas blok berani, batang di wordmark.
5. **Data ditampilkan apa adanya.** Stok per ukuran adalah tabel angka nyata,
   bukan dropdown yang justru menyembunyikan informasi penentu keputusan.

## Dua register

| | Etalase | Panel internal |
|---|---|---|
| Tujuan | Mengurangi keraguan sebelum bayar | Menyelesaikan pekerjaan |
| Kepadatan | Longgar | Padat, tabel rapat |
| Warna | Kertas dominan, navy sebagai struktur | Navy tua sebagai chrome, putih sebagai ruang kerja |
| Hiasan | Satu blok berani | Tidak ada |

Panel wajib mobile-first: operator produksi membuka antrian dari HP di dekat
mesin, opname dilakukan sambil berdiri di depan rak, dan admin memeriksa pesanan
di jalan. Hasil auditnya ada di [13-audit-ui.md](13-audit-ui.md).

### Aturan tata letak sempit

- Setiap layar baru diperiksa `scrollWidth`-nya di 375px. Halaman yang bisa
  digeser ke samping selalu berarti ada yang rusak
- Aksi utama tidak pernah masuk ke dalam menu: keranjang dan tombol beli selalu
  terlihat
- Urutan di layar sempit mengikuti urutan pertanyaan pelanggan, bukan urutan
  markup. Di halaman produk: gambar, kotak beli, baru spesifikasi
- Tabel data boleh digeser di dalam pembungkusnya, tidak pernah menggeser halaman
- Item grid yang memuat pembungkus `overflow-x-auto` wajib diberi `min-w-0`,
  karena `min-width: auto` bawaannya membuat pembungkus itu tidak pernah menyempit
- Kotak berasio tidak pernah memuat gambar ber-`h-full`. Pakai `absolute
  inset-0`, kalau tidak ukuran asli gambar diam-diam menentukan tinggi kotak

### Panel internal

Satu kop halaman, satu kelas kartu, satu kelas tabel. Halaman yang dipakai
berjam-jam tiap hari tidak boleh menuntut penyesuaian ulang di tiap layar.

- Menu dikelompokkan menurut jenis pekerjaan, dan yang menyimpan pekerjaan
  tertunda memakai penghitung
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

- Font dimuat dari Google Fonts. Saat pindah ke shared hosting, pertimbangkan
  self-host agar tidak bergantung pihak ketiga dan lebih cepat di jaringan lokal.
- **Bangun aset setelah semua view ditulis.** Tailwind memindai berkas untuk
  menemukan kelas yang dipakai; `npm run build` yang dijalankan sebelum sebuah
  view dibuat menghasilkan CSS tanpa kelas view tersebut.
