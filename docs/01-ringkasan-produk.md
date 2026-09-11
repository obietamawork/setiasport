# 01 — Ringkasan Produk

## Kesalahpahaman yang harus dihindari sejak awal

Dari luar, Setia Sport terlihat seperti **marketplace/toko online**. Secara teknis, dia
sebenarnya **dua sistem yang digabung**:

1. **Toko ritel** — katalog, keranjang, stok, bayar, ambil. (Alur Biasa)
2. **Sistem job order pabrik kecil** — antrian desain, printing, jahit, QC. (Alur PreOrder)

Kesalahan paling umum di project sejenis: **bikin e-commerce dulu, produksi ditempel
belakangan**. Hasilnya, produksi jadi spreadsheet terpisah, status di web tidak pernah
akurat, dan pelanggan tetap chat WA "sudah sampai mana?" — fitur tracking-nya jadi mubazir.

**Keputusan fondasi: `Order` dan `Produksi` adalah satu tulang punggung sejak hari pertama.**
Status yang dilihat pelanggan bukan data yang diketik ulang admin, tapi **turunan otomatis**
dari pekerjaan nyata yang diselesaikan operator.

## Yang ada di lingkup

- Katalog produk publik (SEO-friendly, bisa dishare ke WA/IG)
- Dua alur pesanan dalam satu mesin status
- Konfigurator produk kustom (pilih desain / kirim desain) + roster nama-nomor-ukuran
- Tracking pesanan untuk pelanggan (tanpa harus login, cukup kode + no HP)
- Panel internal berbasis **antrian per divisi**, bukan CRUD generik
- Serah terima di toko (kode ambil + bukti)
- RBAC internal yang bisa dikonfigurasi (role & kategori diisi belakangan)
- Notifikasi email otomatis lewat Resend (tanpa WhatsApp API)
- Pembayaran diverifikasi manual: QRIS statis dan dua rekening, bukti diunggah di halaman pesanan (KEP-023, KEP-029)
- Halaman depan berupa profil usaha (portofolio instansi, testimoni, lokasi), katalog belakangan (KEP-024)

## Yang TIDAK ada di lingkup

- Hitung ongkir otomatis, integrasi API kurir, dan retur barang — **tidak ada**.
  Pengiriman yang ADA sengaja seminimal mungkin: khusus barang stok, admin mengetik
  nomor resi dan pelanggan melacaknya sendiri di situs pihak ketiga (KEP-026)
- Multi-toko / multi-cabang / multi-vendor
- Aplikasi mobile native
- Chat in-app (WhatsApp tetap kanal percakapan, dikirim manual oleh admin)

> Pengambilan di toko sebagai norma adalah **keuntungan besar**. Ongkir, alamat,
> integrasi kurir, dan retur adalah ~40% kerumitan e-commerce biasa. Yang
> menggantikannya jauh lebih murah: estimasi tanggal siap ambil + kode ambil +
> verifikasi saat serah terima.
>
> Pengiriman untuk barang stok (KEP-026) tidak membatalkan itu, justru menegaskannya:
> yang ditambahkan cuma satu kolom nomor resi dan satu kolom alamat, bukan mesin ongkir.
> Barang kustom tetap wajib diambil di toko, karena ukurannya perlu dicoba.

## Dua sisi, satu masalah yang sama

| | Yang pelanggan mau | Yang internal mau |
|---|---|---|
| Sebelum bayar | Tahu harga & lama pengerjaan tanpa nanya | Tidak menjawab pertanyaan harga yang sama 50x |
| Saat kustom | Yakin hasilnya sesuai bayangan | Tidak salah nama/nomor/ukuran |
| Setelah bayar | Tahu pesanan lagi di tahap mana | Tidak dibanjiri chat "sudah jadi belum?" |
| Selesai | Tahu kapan & bagaimana ambilnya | Tidak ada order nyangkut atau salah diserahkan |

Perhatikan: **keduanya menginginkan hal yang sama, dari arah berlawanan.** Fitur tracking
yang benar melayani dua-duanya sekaligus. Itulah fitur bernilai tertinggi di project ini —
lebih tinggi daripada otomatisasi pembayaran.
