# 09 — Katalog Contoh (draft seeder)

Lima produk yang sengaja dipilih untuk **menguji batas model data**, bukan sekadar
mengisi etalase. Angka masih draft — mohon dikoreksi dengan harga asli toko.

---

## A. `BOLA-MITRE-01` — Bola Futsal Mitre Ultimatch
**Tipe:** READY_STOCK · Kategori: Bola › Futsal

| | |
|---|---|
| Harga | Rp 285.000 |
| Varian | tunggal (tanpa ukuran/warna) |
| Stok | 12 |

*Menguji:* produk paling sederhana — tanpa varian, tanpa opsi.

---

## B. `SEPATU-ORT-CTL` — Sepatu Futsal Ortuseight Catalyst
**Tipe:** READY_STOCK · Kategori: Sepatu › Futsal

| | |
|---|---|
| Harga dasar | Rp 450.000 |
| Varian | ukuran **39–44** × warna **(Hitam, Merah)** = **12 SKU** |
| Stok | per SKU |

*Menguji:* matriks varian, stok per SKU, dan **reservasi stok** — dua orang tidak boleh
membeli sepatu terakhir yang sama.

---

## C. `JRS-FUTSAL-FP` — Jersey Futsal Full Printing
**Tipe:** PREORDER · Kategori: Jersey › Futsal · **MOQ 12** · lead time **14 hari kerja
sejak desain disetujui**

**Tier harga**

| Jumlah | Harga/pcs |
|---|---|
| 12–23 pcs | Rp 95.000 |
| 24–47 pcs | Rp 88.000 |
| 48+ pcs | Rp 82.000 |

**Opsi**

| Opsi | Pilihan | Tambahan | Basis |
|---|---|---|---|
| Bahan | Dryfit Serena | +0 | PER_PCS |
| | Milano | +7.000 | PER_PCS |
| | Hyget | −8.000 | PER_PCS |
| Lengan | Pendek | +0 | PER_PCS |
| | Panjang | +8.000 | PER_PCS |
| Kerah | O-neck / V-neck | +0 | PER_PCS |
| | Kerah Polo | +10.000 | PER_PCS |
| Celana | Tanpa | +0 | PER_PCS |
| | Dengan celana | +45.000 | PER_PCS |
| Nameset | Tidak | +0 | PER_PCS |
| | Ya (nama & nomor) | +10.000 | PER_PCS |
| **Setup desain kustom** | — | **+75.000** | **PER_ITEM** |

Roster **wajib**, jumlah baris = qty.

*Menguji:* tier harga, kedua basis biaya (KEP-009), roster, alur desain & approval —
produk terpenting untuk membuktikan model data.

---

## D. `KAOS-SEKOLAH-SB` — Kaos Olahraga Sekolah (Sablon)
**Tipe:** PREORDER · Kategori: Kaos › Sekolah · **MOQ 40** · lead time 10 hari kerja

**Tier harga**

| Jumlah | Harga/pcs |
|---|---|
| 40–99 pcs | Rp 55.000 |
| 100–299 pcs | Rp 48.000 |
| 300+ pcs | Rp 43.000 |

| Opsi | Pilihan | Tambahan | Basis |
|---|---|---|---|
| Bahan | Cotton Combed 30s / Lacoste CVC | +0 / +6.000 | PER_PCS |
| Nama siswa di dada | Tidak / Ya | +0 / +4.000 | PER_PCS |
| Setup film sablon | — | +150.000 | PER_ITEM |

Roster: **opsional** (hanya kalau "Nama siswa" dipilih).

*Menguji:* MOQ tinggi, bentuk tier berbeda, dan **roster kondisional** — roster tidak
selalu wajib untuk semua produk PreOrder.

---

## E. `JRS-POLOS-01` — Jersey Polos
**Tipe:** **BOTH** (KEP-011) · Kategori: Jersey › Polos

**Jalur Ready Stock**

| | |
|---|---|
| Harga | Rp 65.000 |
| Varian | ukuran **S–XXL** × warna **(Merah, Biru, Hitam, Putih)** = 20 SKU |
| Stok | per SKU |
| Waktu | siap diambil **besok** |

**Jalur Pesan Kustom** — MOQ 12 · lead time 10 hari kerja

| Jumlah | Harga/pcs |
|---|---|
| 12–47 pcs | Rp 58.000 |
| 48+ pcs | Rp 52.000 |

| Opsi | Pilihan | Tambahan | Basis |
|---|---|---|---|
| Sablon logo tim | Tidak / Ya | +0 / +12.000 | PER_PCS |
| Setup film sablon | — | +100.000 | PER_ITEM |

*Menguji:* produk hybrid. Perhatikan harga kustom **lebih murah** dari ready stock pada
jumlah besar — ini normal (produksi massal vs stok satuan) dan model data harus
membolehkannya. Kedua jalur benar-benar terpisah: tier tidak memengaruhi harga ready
stock, dan stok tidak dipotong jalur kustom.

---

## Yang perlu dikoreksi pemilik toko

1. Harga asli setiap produk & tier
2. Ambang tier — apakah 12/24/48 sesuai praktik toko?
3. Daftar bahan yang benar-benar tersedia
4. Nominal setup desain / setup film — dan apakah memang dibebankan, atau digratiskan
   sebagai daya tarik
5. Lead time realistis per produk
6. Apakah kasus hybrid (produk E) memang ada, atau cukup dua produk terpisah
