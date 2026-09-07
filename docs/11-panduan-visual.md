# 11 — Panduan Visual

Identitas visual Setia Sport tidak diambil dari "olahraga" secara umum, melainkan dari
hal yang khas milik workshop ini: **nomor punggung** dan **size run** (S:3 M:8 L:5).
Angka dan grid ukuran dipakai sebagai elemen aktif, bukan hiasan.

## Warna

Diambil dari lantai lapangan futsal dan garis kapur. Hitamnya berbasis hijau, kertasnya
bersemu dingin — sengaja menjauh dari palet krem netral yang dipakai di mana-mana.

| Token | Hex | Peran |
|---|---|---|
| `court` | `#14523A` | hijau lapangan — warna struktural, chrome panel |
| `court-dark` | `#0B2B20` | header panel, latar halaman masuk |
| `court-soft` | `#E7EFE9` | latar penanda "stok toko", sorotan lembut |
| `ink` | `#0E1C17` | teks utama |
| `muted` | `#5C6B64` | teks pendukung |
| `paper` | `#F5F7F4` | dasar halaman |
| `field` | `#FFFFFF` | permukaan kartu, tabel, formulir |
| `line` | `#DCE4DE` | hairline seperti garis lapangan |
| `signal` | `#FF5A1F` | oranye sublimasi |

**Aturan `signal`:** hanya untuk **harga** dan **aksi utama**. Begitu ia dipakai untuk
hal ketiga, ia berhenti berarti apa-apa.

## Tipografi

Satu keluarga: **Archivo** (variabel, sumbu `wdth` 62–125). Kontras datang dari **lebar**,
bukan dari tipe huruf kedua — persis cara penomoran jersey bekerja: satu huruf, dilebarkan.

| Kelas | Pemakaian |
|---|---|
| `.t-wide` | judul, nama produk — `font-stretch: 112%` |
| `.numeral` | angka besar: lead time, harga utama, ringkasan — bobot 800, rapat |
| `.tabular` | semua angka dalam tabel dan daftar, agar berbaris rapi |

## Prinsip

1. **Waktu adalah informasi utama.** Setiap produk menyatakan kapan bisa diambil.
   Pertanyaan pertama pelanggan selalu itu, bukan harga.
2. **Angka besar hanya untuk yang benar-benar angka** — harga, lead time, stok.
   Bukan untuk memberi kesan penting pada teks biasa.
3. **Garis, bukan bayangan.** Struktur dibentuk hairline; tidak ada kartu bershadow
   seragam. Sudut nyaris tajam (radius 2–3px) agar terasa teknis seperti perkakas bengkel.
4. **Satu tempat untuk berani.** Di beranda itu blok hijau jalur kustom. Sisanya tenang.
5. **Data ditampilkan apa adanya.** Stok per ukuran adalah tabel ukuran × warna dengan
   angka sebenarnya, bukan dropdown — dropdown menyembunyikan justru informasi yang
   paling menentukan keputusan.

## Dua register

| | Sisi publik | Panel internal |
|---|---|---|
| Tujuan | Mengurangi keraguan sebelum bayar | Menyelesaikan pekerjaan |
| Kepadatan | Longgar, banyak ruang | Padat, tabel rapat |
| Warna | Kertas dominan, hijau sebagai aksen | Hijau tua sebagai chrome, putih sebagai ruang kerja |
| Hiasan | Satu blok berani | Tidak ada |

Panel **wajib mobile-first**: operator produksi tidak duduk di depan PC (docs/04).

## Aturan penulisan

- Kalimat biasa, bukan huruf kapital semua. Kapital hanya di `chip` penanda jalur.
- Tombol menyebut apa yang terjadi: "Simpan stok", bukan "Kirim".
- Nama aksi konsisten dari tombol sampai pesan konfirmasi.
- Layar kosong adalah ajakan bertindak, bukan kabar buruk: sebutkan langkah berikutnya
  dan sediakan tombolnya.
- Pesan galat menjelaskan apa yang salah **dan** cara memperbaikinya. Tidak minta maaf,
  tidak samar. Contoh yang dipakai: "Kategori ini masih dipakai produk atau punya
  subkategori, jadi belum bisa dihapus. Pindahkan isinya lebih dulu."

## Catatan teknis

- Font dimuat dari Google Fonts. Saat pindah ke shared hosting, pertimbangkan
  self-host agar tidak bergantung pada pihak ketiga dan lebih cepat di jaringan lokal.
- **Bangun aset setelah semua view ditulis.** Tailwind memindai berkas untuk menemukan
  kelas yang dipakai; `npm run build` yang dijalankan sebelum sebuah view dibuat akan
  menghasilkan CSS tanpa kelas-kelas view tersebut.
