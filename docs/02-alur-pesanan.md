# 02 — Alur Pesanan & Mesin Status

## Prinsip: satu mesin, dua profil

Jangan bikin dua sistem pesanan. Bikin **satu pipeline** yang mengambil daftar tahap
berbeda tergantung tipe item. Ini penting supaya laporan, pembayaran, notifikasi, dan
serah terima tidak perlu ditulis dua kali.

```
Order
 └── OrderItem (punya fulfillment_type: READY_STOCK | PREORDER)
      └── ProductionJob (hanya untuk PREORDER)
```

## Alur Biasa (Ready Stock)

```
Menunggu Pembayaran
      ↓ (bayar lunas)
Diproses / Diambil dari Gudang
      ↓
Packing
      ↓
Siap Diambil          ← notifikasi email ke pelanggan
      ↓ (serah terima di toko)
Selesai
```
Cabang: `Dibatalkan`, `Kadaluarsa` (tidak dibayar sampai batas waktu),
`Tidak Diambil` (lewat X hari sejak siap).

## Alur PreOrder (Kustom)

```
Menunggu DP
      ↓ (DP masuk)
Antrian Desain
      ↓
Proses Desain / Mockup       ←──┐
      ↓                          │ revisi (dibatasi N kali)
Menunggu Persetujuan Pelanggan ──┘
      ↓ (pelanggan SETUJU)  ← ★ GERBANG KUNCI
Antrian Produksi
      ↓
Printing
      ↓
Jahit
      ↓
QC
      ↓
Packing
      ↓
Siap Diambil          ← notifikasi email
      ↓ (serah terima)
Selesai
```

### Kenapa "Persetujuan Pelanggan" itu gerbang kunci

Ini bukan sekadar tahap UI. Ini **titik kunci komersial dan hukum kecil**:

- Sebelum approve: revisi gratis (sampai batas N kali).
- Setelah approve: desain dikunci. Perubahan = order baru / biaya tambahan.
- Sistem **wajib mencatat**: siapa yang menyetujui, kapan (timestamp), dan **versi file
  mockup mana** yang disetujui. Ini yang menyelamatkanmu saat pelanggan bilang
  "warnanya bukan yang saya minta".
- Sebelum approve, jam produksi belum jalan. Estimasi tanggal siap ambil **baru dihitung
  dari tanggal approve**, bukan tanggal order. Ini harus dijelaskan ke pelanggan sejak awal,
  kalau tidak ekspektasinya akan meleset dan tracking justru jadi sumber komplain.

### Tahap yang sering dilupakan

- **QC** — sebelum packing. Kalau tidak ada, barang cacat baru ketahuan saat pelanggan
  buka bungkus di toko, dan itu jadi drama di depan kasir.
- **Antrian** (Antrian Desain, Antrian Produksi) — ini status jujur "sudah masuk sistem,
  belum dikerjakan". Kalau tidak ada, operator terpaksa memalsukan status "sedang
  dikerjakan" sejak awal, dan tracking kehilangan makna.

## Order campuran (bola ready stock + jersey kustom dalam 1 checkout)

Ini pasti terjadi. Tiga pilihan:

| Opsi | Cara | Konsekuensi |
|---|---|---|
| A | Pecah otomatis jadi 2 order terpisah saat checkout | Paling sederhana di kode. Pelanggan bingung dapat 2 kode & 2 tagihan. |
| B | **1 order, progres di level item, status order = agregat** | Lebih rumit sedikit, tapi paling masuk akal untuk pelanggan & toko. |
| C | Larang campur di keranjang | Paling mudah, tapi menyebalkan dan menurunkan penjualan. |

**Rekomendasi: B.** Aturan agregatnya: order `Siap Diambil` hanya ketika **semua** item
siap. Pelanggan datang sekali, ambil semua. Tapi ini butuh keputusanmu — lihat dokumen 07.

## Uang: DP vs Lunas  → lihat KEP-003, KEP-005, KEP-006

Sudah diputuskan:

- **Ready stock:** lunas di muka.
- **PreOrder:** pelanggan memilih **bayar lunas** atau **DP minimum 50%** (boleh lebih).
  Sisa dilunasi **saat pengambilan di toko** — tunai atau digital.
- **Tidak ada tahap yang menahan barang sampai lunas.** Produksi jalan dengan modal DP.
  Risiko barang tidak diambil ditanggung DP 50% yang sudah menutup HPP.

Implikasi teknis yang harus dipegang sejak awal:

- Satu order punya **banyak** `payments`. Jangan pernah ada kolom `paid_at` atau
  `payment_status` tunggal di `orders`.
- `total`, `sudah_dibayar`, dan `sisa_tagihan` **selalu dihitung ulang**, tidak pernah
  disimpan sebagai kolom yang bisa basi.
- Pembayaran offline (tunai di kasir) dan online (gateway) diperlakukan setara —
  bedanya hanya di kolom `metode`, `provider`, dan `dicatat_oleh`.
- Layar serah terima menggabungkan **catat pelunasan + tandai diambil** dalam satu aksi.
- Order tidak boleh berstatus `Selesai` selama `sisa_tagihan > 0`.

### Perubahan pesanan setelah DP masuk

Tambah pemain, tambah patch, diskon nego — semuanya jadi baris `order_adjustments`
(deskripsi + nominal, boleh negatif), bukan hasil mengedit item lama. Total dan sisa
tagihan naik otomatis, dan **pelanggan melihatnya di halaman tracking sebelum datang
ke toko**. Ini inti dari "DP yang nyaman": tidak ada kejutan angka di kasir.

## Estimasi tanggal siap ambil

Jangan janjikan tanggal pasti dari sistem otomatis di awal. Yang realistis:
- Ready stock: "siap diambil H+1 kerja"
- PreOrder: `tanggal_approve + lead_time_produk(hari kerja) + antrian`

Fase 1 cukup: admin/desainer **mengisi manual** target tanggal saat order masuk antrian
produksi. Otomatisasi berbasis kapasitas produksi adalah fitur fase lanjut, dan sering
salah kalau dipaksakan di awal.
