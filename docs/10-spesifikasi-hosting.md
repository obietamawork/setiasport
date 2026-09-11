# 10 — Spesifikasi & Rekomendasi Hosting

Hosting belum dibeli. Dokumen ini adalah daftar syarat yang **wajib dipenuhi**, cara
memverifikasinya, dan panduan memilih paket.

---

## Syarat wajib (jangan beli kalau ada yang tidak terpenuhi)

| Syarat | Nilai minimum | Kenapa |
|---|---|---|
| **PHP** | **8.2+**, bisa dipilih di panel | Laravel 11/12. Kalau maksimal 8.1 → turun ke CodeIgniter 4 |
| **MySQL / MariaDB** | MySQL 8.0 atau MariaDB 10.6+ | Butuh dukungan JSON & CTE |
| **Cron job** | Tersedia, interval 1 menit | **Nyawa sistem.** Tanpa ini: order tidak pernah kadaluarsa, stok tidak dilepas, email tidak terkirim, pengingat tidak jalan |
| **Document root bisa diarahkan** | Ke subfolder (`public/`) | Kalau tidak bisa, `.env` & `storage/` berisiko terekspos — kebocoran paling umum di shared hosting |
| **HTTPS keluar (port 443)** | Tidak diblokir | Resend dan Pusher (chat CS) |
| **Disk** | **≥ 10 GB** | Foto produk + preview desain. Paket 1–3 GB akan sesak dalam setahun |
| **SSL** | Let's Encrypt gratis | Atau cukup lewat Cloudflare |
| **Ekstensi PHP** | bcmath, ctype, curl, dom, fileinfo, gd/imagick, mbstring, openssl, pdo_mysql, tokenizer, xml, zip | Kebutuhan Laravel + pemrosesan gambar |

### Sangat dianjurkan (bukan wajib)

| Fitur | Manfaat |
|---|---|
| **Akses SSH** | Deploy via git pull + `php artisan migrate`. Tanpa SSH, semua lewat File Manager/FTP — bisa, tapi menyiksa |
| Backup otomatis harian | Shared hosting **akan** bermasalah suatu hari |
| Lokasi server Indonesia / Singapura | Latensi ke pelanggan lokal |
| `entry process` ≥ 20 | Batas rendah membuat situs error saat beberapa pengunjung bersamaan |

---

## Yang sering menjadi masalah, beserta solusinya

**1. `exec` / `proc_open` dinonaktifkan**
Composer tidak bisa jalan di server. *Solusi:* jalankan `composer install --no-dev
--optimize-autoloader` di lokal, lalu unggah folder `vendor/`. Bukan penghalang, tapi
harus diketahui sejak awal agar alur deploy dirancang benar.

**2. `upload_max_filesize` / `post_max_size` kecil (biasanya 2 MB)**
Upload preview desain 10 MB gagal. *Solusi:* naikkan lewat panel atau `.htaccess` —
butuh `upload_max_filesize`, `post_max_size`, dan `max_execution_time` sekaligus.
**Pastikan ini bisa diubah** sebelum membeli.

**3. Port SMTP diblokir**
Sering terjadi di hosting murah. *Tidak menjadi masalah bagi kita* — Resend dipanggil
lewat HTTPS, bukan SMTP (KEP-004). Ini salah satu keuntungan tak terduga dari pilihan itu.

**4. `memory_limit` 128 MB**
Cukup untuk aplikasi, tapi bisa kurang saat memproses gambar besar. Minta 256 MB.

---

## Panduan memilih paket

**Jangan ambil paket paling murah.** Paket entry biasanya mengunci PHP versi lama,
membatasi disk 1–3 GB, tanpa SSH, dan `entry process` sangat rendah. Selisih harganya
ke paket menengah kecil, tapi selisih kenyamanannya besar.

**Ambil paket menengah** yang menyebut: PHP selectable, disk ≥10 GB, cron unlimited,
SSH, dan document root bisa diubah.

Penyedia yang lazim dipakai untuk Laravel di Indonesia — **verifikasi spesifikasinya
langsung, jangan percaya dokumen ini** karena paket & harga berubah:
Domainesia, Rumahweb, Niagahoster, Dewaweb, Hostinger, Jagoan Hosting.

Pertanyaan yang layak ditanyakan ke tim penjualan **sebelum membayar**:
1. Apakah document root bisa diarahkan ke subfolder `public/`?
2. Apakah `exec` dan `proc_open` aktif?
3. Berapa `entry process` dan `IO limit` paket ini?
4. Apakah `upload_max_filesize` bisa saya naikkan sendiri?
5. Apakah SSH tersedia di paket ini?

Simpan jawabannya — kalau nanti ada masalah, jawaban tertulis itu berguna.

---

## Catatan jujur: shared hosting vs VPS

Harga shared hosting paket menengah dan VPS entry level sering **berdekatan**. Perbandingan
sebenarnya bukan soal harga:

| | Shared hosting | VPS |
|---|---|---|
| Perawatan | Ditangani penyedia | Kamu sendiri (update, keamanan, backup) |
| Queue worker | Hanya via cron per menit | Supervisor, jalan terus, jauh lebih responsif |
| Redis / cache | Tidak ada | Ada |
| Batas proses | Ada, dan bisa menggigit | Sesuai resource |
| Butuh orang IT | Tidak | Ya, minimal paham dasar Linux |

**Untuk toko tanpa orang IT, shared hosting adalah pilihan yang benar** — dan seluruh
arsitektur di dokumen 06 memang dirancang untuk berjalan baik di sana.

Yang perlu diketahui: karena stack-nya PHP + MySQL biasa, **migrasi ke VPS nanti mudah**
kalau trafik tumbuh. Tidak ada keputusan di project ini yang mengunci kita di shared hosting.

---

## Langkah verifikasi setelah membeli

Unggah file ini ke root, buka di browser, lalu **hapus segera** (jangan ditinggal —
file ini membocorkan informasi konfigurasi server):

```php
<?php
// cek.php - HAPUS setelah dibaca
echo "PHP: " . PHP_VERSION . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
echo "disable_functions: " . (ini_get('disable_functions') ?: '(kosong)') . "<br><br>";

foreach (['bcmath','ctype','curl','dom','fileinfo','gd','imagick','mbstring',
          'openssl','pdo_mysql','tokenizer','xml','zip'] as $e) {
    printf("%-12s %s<br>", $e, extension_loaded($e) ? 'ADA' : '>>> TIDAK ADA <<<');
}

// uji HTTPS keluar (untuk Resend)
$c = curl_init('https://api.resend.com');
curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
curl_exec($c);
echo "<br>HTTPS keluar: " . (curl_errno($c) ? "GAGAL - " . curl_error($c) : "OK");
curl_close($c);
```

Hasilnya kirim ke saya, nanti saya pastikan Laravel atau CodeIgniter yang dipakai dan
menyesuaikan cara deploy-nya.
