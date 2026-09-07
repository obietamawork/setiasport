# Dokumentasi Setia Sport

Toko alat olahraga + workshop kustom (jersey dll). Satu aplikasi web, dua alur pesanan,
tracking pesanan sampai **siap diambil di toko** (tanpa pengiriman).

## Daftar Dokumen

| File | Isi |
|---|---|
| [01-ringkasan-produk.md](01-ringkasan-produk.md) | Apa yang sebenarnya kita bangun, dan apa yang **bukan** |
| [02-alur-pesanan.md](02-alur-pesanan.md) | Alur Biasa vs Alur PreOrder, mesin status |
| [03-sisi-pelanggan.md](03-sisi-pelanggan.md) | Perjalanan pelanggan, halaman, ekspektasi |
| [04-sisi-internal.md](04-sisi-internal.md) | Peran internal, antrian kerja, prinsip anti-input-ganda |
| [05-model-data.md](05-model-data.md) | Entitas inti dan alasan bentuknya |
| [06-arsitektur-teknis.md](06-arsitektur-teknis.md) | Stack, batasan shared hosting & CDN gratis |
| [07-keputusan-terbuka.md](07-keputusan-terbuka.md) | Pertanyaan yang masih perlu dijawab |
| [08-log-keputusan.md](08-log-keputusan.md) | **Keputusan yang sudah terkunci + alasannya** |
| [09-katalog-contoh.md](09-katalog-contoh.md) | Draft 5 produk penguji model data |
| [10-spesifikasi-hosting.md](10-spesifikasi-hosting.md) | Syarat hosting & panduan membeli |
| [11-panduan-visual.md](11-panduan-visual.md) | Token warna, tipografi, dan prinsip desain |
| [12-deploy.md](12-deploy.md) | Langkah deploy ke shared hosting |

## Status

Tahap **implementasi fondasi**. Skema database, model, dan kalkulator harga
sudah jalan dan tertutup test. Cara menjalankan proyeknya ada di
[README.md di root](../README.md).

- **12 keputusan fondasi terkunci** (KEP-001 s/d KEP-012)
- **Fase 0 s/d 3 selesai.** Fondasi data, katalog publik, panel internal,
  pesanan end-to-end, serta alur kustom penuh: konfigurator harga live, roster
  pemain, gerbang persetujuan desain, antrian kerja per divisi, dan worksheet
  cetak. 52 test lulus di MySQL
- **Fase 5 selesai.** Gerbang pembayaran Midtrans di balik antarmuka yang bisa
  ditukar, dan webhook status Resend. 72 test lulus di MySQL
- Sisa pekerjaan bukan lagi kode: koreksi harga katalog, pembelian hosting,
  pendaftaran merchant, dan deploy ([12-deploy.md](12-deploy.md))
- Menunggu dari pemilik toko: koreksi harga katalog & pembelian hosting

Setiap dokumen di atas sudah disinkronkan dengan keputusan terkunci. Kalau ada
pertentangan, **[08-log-keputusan.md](08-log-keputusan.md) yang menang.**
