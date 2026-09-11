# Berkas merek dari klien

Berkas asli, diterima 11 September 2026. Jangan dipakai langsung di halaman: terlalu besar.
Yang tampil di website adalah turunannya:

| Asli | Turunan | Dipakai di |
|---|---|---|
| `logo-tulisan-asli.png` | `public/logo.png` (tinggi 160px) | Kepala halaman publik, footer |
| | `public/logo-terang.png` (navy diganti putih) | Latar navy: panel, ruang CS, login staf, email |
| | `public/favicon.ico`, `favicon.png`, `apple-touch-icon.png` | Ikon tab dan layar utama HP, dari bola voli di huruf O |
| `logo-emblem-asli.png` | `public/logo-lengkap.webp` (lebar 1000px) | Pembuka halaman depan |
| `qris-asli.jpeg` | `database/seeders/aset/qris.jpeg` (**salinan utuh**) | Halaman pembayaran pelanggan |

QRIS sengaja **tidak** dikompresi atau diperkecil. Isinya sudah dicocokkan dengan pembaca QR:
QRIS statis Bank Mandiri, merchant "Setia Sport", kota "Wonogiri (Kab)". Kalau klien mengganti
QRIS, unggah lewat Panel → Pengaturan; seeder tidak akan menimpanya.

Kalau logo diganti, turunannya dibuat ulang dari berkas asli: potong rapat mengikuti area
tidak transparan, perkecil, dan untuk versi terang ganti piksel navy (gelap dan kebiruan)
jadi putih dengan transparansi dipertahankan.
