# 05 — Model Data (rancangan awal)

Belum final. Tujuannya menunjukkan **bentuk** dan alasan di balik setiap keputusan.

## Katalog

```
categories          id, parent_id, nama, slug, urutan
products            id, category_id, nama, slug, deskripsi, 
                    fulfillment_type (READY_STOCK | PREORDER | BOTH),
                    harga_dasar, moq, lead_time_hari, aktif
product_variants    id, product_id, sku, ukuran, warna, tambahan_harga, stok, stok_dipesan
product_images      id, product_id, path, urutan, alt
product_options     id, product_id, nama (Bahan/Lengan/Kerah), tipe, wajib
option_values       id, option_id, label, tambahan_harga, 
                    basis (PER_PCS | PER_ITEM)
product_price_tiers id, product_id, min_qty, max_qty (nullable), harga_satuan
```

Catatan:
- `fulfillment_type` di level **produk**, bukan global. Produk `BOTH` (KEP-011) punya
  **dua jalur harga terpisah**: ready stock memakai `harga_dasar + variant.tambahan_harga`,
  kustom memakai `product_price_tiers`. Stok hanya dipotong jalur ready stock; MOQ dan
  roster hanya berlaku di jalur kustom.
- `option_values.basis` menentukan apakah biaya dikali qty (`PER_PCS`, mis. bahan) atau
  dikenakan sekali per baris pesanan (`PER_ITEM`, mis. setup desain). **Wajib ada sejak
  awal** — lihat KEP-009 untuk contoh selisih yang ditimbulkannya.
- `product_price_tiers` dipilih berdasarkan qty **satu baris item**, bukan gabungan
  keranjang.
- `stok_dipesan` memisahkan stok fisik dari stok yang sudah direservasi order belum bayar.
  Stok tersedia = `stok - stok_dipesan`.

## Desain

```
design_templates    id, category_id, nama, preview_path, source_path, aktif
design_assets       id, order_item_id, tipe (UPLOAD_PELANGGAN | MOCKUP), 
                    versi, path, external_url, catatan, 
                    diunggah_oleh, dibuat_pada
```

`external_url` menampung tautan Google Drive ke file mentah siap cetak (KEP-002);
`path` hanya untuk preview <=10 MB yang benar-benar tersimpan di hosting.

`design_assets` **berversi**. Mockup v1 → revisi → v2 → v3. Versi yang disetujui
pelanggan disimpan referensinya di `order_items.approved_design_asset_id`. Tanpa
versioning, sengketa "saya approve yang mana" tidak bisa diselesaikan.

## Pesanan

```
customers           id, nama, no_hp, email, user_id (nullable — guest didukung)
orders              id, kode, customer_id, status, access_token,
                    diskon, tipe_pembayaran (LUNAS | DP),
                    target_siap_pada, siap_pada, diambil_pada,
                    kode_ambil, diambil_oleh, catatan,
                    metode_ambil (AMBIL_TOKO | KIRIM), alamat_kirim,
                    jasa_kirim, no_resi, dikirim_pada, diterima_pada
order_items         id, order_id, product_id, variant_id, 
                    fulfillment_type, qty, harga_satuan, status_item,
                    approved_design_asset_id, approved_at,
                    approved_by_name, approved_ip
order_adjustments   id, order_id, order_item_id (nullable),
                    deskripsi, jumlah (boleh negatif), dibuat_oleh, dibuat_pada
order_item_rosters  id, order_item_id, urutan, nama_punggung, nomor, ukuran, catatan
order_item_options  id, order_item_id, option_nama, option_value, 
                    tambahan_harga, basis
```

Keputusan penting:
- `fulfillment_type` **disalin** ke `order_items` saat checkout. Jangan mengandalkan
  join ke produk — kalau produk diubah bulan depan, order lama akan salah baca.
- Begitu juga `harga_satuan` (sudah termasuk tier + opsi PER_PCS) dan snapshot opsi
  lengkap dengan `basis`-nya. **Order adalah dokumen historis**,
  bukan tampilan dari data master saat ini.
- `status_item` ada di item, `status` order adalah agregat (lihat 02).
- **Tidak ada kolom `subtotal` / `total` / `sudah_dibayar` / `sisa_tagihan` di `orders`.**
  Semuanya dihitung ulang: `total = SUM(qty x harga_satuan) + SUM(adjustments) - diskon`,
  `sisa_tagihan = total - SUM(payments LUNAS)`. Menyimpannya sebagai kolom berarti
  mengundang angka basi di layar kasir - persis masalah yang ingin dihindari.
- `access_token` acak >=32 byte, unik, dibuat saat order dibuat (KEP-008).
- `approved_by_name` adalah nama yang **diketik pelanggan** di dialog konfirmasi, bukan
  relasi ke tabel user - pelanggan tidak punya akun.

## Produksi

```
production_jobs     id, order_item_id, tahap (DESAIN|PRINT|JAHIT|QC|PACKING), 
                    status (ANTRI|DIKERJAKAN|SELESAI), 
                    ditugaskan_ke, dimulai_pada, selesai_pada, catatan
```

Satu `order_item` PreOrder menghasilkan beberapa `production_jobs` berurutan. Inilah yang
mengisi antrian per divisi di dokumen 04, dan inilah sumber kebenaran status pelanggan.

## Pembayaran

```
payments            id, order_id, jenis (DP | PELUNASAN | FULL), 
                    metode (TUNAI | TRANSFER | QRIS), 
                    jumlah, status (MENUNGGU|LUNAS|GAGAL|KADALUARSA),
                    bukti_path, dicatat_oleh, diverifikasi_oleh, dibayar_pada
```

Seluruh pembayaran diverifikasi manual (KEP-023): tidak ada gerbang, jadi tidak ada
kolom penyedia. Yang membedakan satu baris dari lainnya hanya `metode` dan siapa yang
mencatatnya. Kolom `dicatat_oleh` wajib untuk pembayaran tunai: ini titik rawan uang
kas, dan setiap pencatatan masuk audit log.

`metode` dan `bukti_path` diisi **saat verifikasi**, bukan saat checkout. Sebelum
uangnya masuk, tidak ada yang tahu pelanggan akan membayar lewat apa.

**Satu order → banyak payment**: DP lebih dulu, pelunasan saat pengambilan.

## Chat CS (KEP-030)

```
percakapan_cs       id, token_hash, customer_id, order_id, order_terverifikasi,
                    nama, kontak, topik (STOK|KUSTOM|KOMPLAIN|LAIN),
                    status (TERBUKA|SELESAI), belum_dibaca_staf,
                    belum_dibaca_pelanggan, pesan_terakhir_pada,
                    menunggu_sejak, diperingatkan_pada, ip

pesan_cs            id, percakapan_id, pengirim (PELANGGAN|STAF|SISTEM),
                    user_id, isi, lampiran_path, created_at

template_balasan    id, judul, isi, urutan
```

Pesan disimpan di sini, bukan dititipkan ke Pusher: log chat adalah catatan resmi
komplain. `token_hash` menyimpan hash token cookie, bukan tokennya.

## Profil usaha

```
portofolios         id, judul, slug, instansi, kategori, tahun, jumlah_pcs,
                    ringkasan, gambar_path, thumb_path, aktif, urutan

testimonis          id, nama, peran, isi, bintang, aktif, urutan
```

Keduanya berdiri sendiri, **tidak diturunkan dari `orders`** (KEP-024). Pesanan yang
layak dipamerkan belum tentu masih ada datanya, dan nama pemesan tidak otomatis boleh
disebut di halaman publik: `instansi` diketik manual, hanya untuk yang sudah mengizinkan.

Tidak ada jalur publik untuk menulis testimoni. Tanpa moderasi ia jadi sasaran spam, dan
moderasi berarti antrian kerja yang tidak dibayar.

## Sistem & Audit

```
users               id, nama, email, no_hp, password, aktif
roles               id, nama, slug
permissions         id, nama, slug
role_user           role_id, user_id          ← many-to-many, satu orang banyak peran
permission_role     permission_id, role_id
order_status_logs   id, order_id, order_item_id, dari, ke, oleh, catatan, dibuat_pada
activity_logs       id, user_id, aksi, subjek_tipe, subjek_id, data_json, dibuat_pada
settings            key, value                ← jam buka, alamat, %DP, batas revisi, dll
email_logs          id, order_id, ke, template, resend_id,
                    status (TERKIRIM|DELIVERED|OPENED|BOUNCED|GAGAL),
                    dikirim_pada, diperbarui_pada
```

`email_logs` menyimpan status dari webhook Resend. Inilah yang membuat admin tahu kapan
email gagal sampai dan urusannya harus diambil alih manusia lewat WhatsApp (lihat 03).

`settings` sebagai tabel key-value menghindari hardcode nilai bisnis (persentase DP,
batas waktu bayar, jumlah revisi gratis) yang pasti akan diubah pemilik toko.
