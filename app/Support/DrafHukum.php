<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Kebijakan Privasi dan Syarat & Ketentuan (KEP-085).
 *
 * Drafnya disusun dari cara kerja sistem ini sendiri: data apa yang benar-
 * benar dikumpulkan, dipakai untuk apa, dan dikirim ke pihak mana. Pemilik
 * toko tetap wajib meninjau dan menyesuaikannya (Kesiapan tayang menahannya
 * sebagai butir wajib selama teksnya masih sama persis dengan draf ini).
 *
 * Penanda {kurung} diganti nilai pengaturan saat ditampilkan, supaya angka
 * seperti batas waktu bayar tidak pernah berbeda dari yang benar-benar
 * berlaku di sistem.
 */
class DrafHukum
{
    public const KUNCI = ['kebijakan_privasi', 'syarat_ketentuan'];

    /** Teks siap tampil: penanda diganti nilai pengaturan, Markdown jadi HTML aman. */
    public static function html(string $kunci): string
    {
        $teks = strtr((string) setting($kunci, ''), self::penanda());

        // html_input=strip: tag HTML yang diketik di Pengaturan dibuang, jadi
        // isian ini tidak bisa dipakai menyisipkan skrip ke halaman publik.
        return Str::markdown($teks, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /** Masih sama persis dengan draf bawaan, alias belum pernah ditinjau. */
    public static function masihDraf(string $kunci): bool
    {
        $draf = match ($kunci) {
            'kebijakan_privasi' => self::kebijakanPrivasi(),
            'syarat_ketentuan' => self::syaratKetentuan(),
        };

        return trim(str_replace("\r\n", "\n", (string) Setting::ambil($kunci, ''))) === trim($draf);
    }

    /** @return array<string, string> */
    public static function penanda(): array
    {
        return [
            '{toko}' => (string) setting('toko_nama', 'Setia Sport'),
            '{alamat}' => (string) setting('toko_alamat', ''),
            '{whatsapp}' => (string) setting('toko_telepon', ''),
            '{batas_bayar_jam}' => (string) setting('batas_bayar_jam', 24),
            '{dp_minimum}' => (string) setting('dp_minimum_persen', 50),
            '{revisi_gratis}' => (string) setting('revisi_gratis_maks', 3),
            '{hangus_hari}' => (string) setting('hangus_hari', 30),
        ];
    }

    public static function kebijakanPrivasi(): string
    {
        return <<<'MD'
Kebijakan ini menjelaskan data pribadi apa yang dikumpulkan {toko} saat Anda berbelanja atau memesan di website ini, untuk apa data itu dipakai, dan apa hak Anda atasnya. Kebijakan ini mengikuti Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi.

## Data yang kami kumpulkan

- **Data pemesan**: nama, email, dan nomor HP/WhatsApp yang Anda isi saat checkout.
- **Alamat pengiriman**: alamat lengkap dan tautan lokasi Google Maps, hanya bila pesanan dikirim.
- **Isi pesanan**: barang, ukuran, jumlah, catatan, serta daftar pemain (nama dan nomor punggung) untuk pesanan kustom.
- **Berkas yang Anda kirim**: desain atau logo, dan foto bukti pembayaran.
- **Akun pelanggan** (bila Anda membuatnya): email dan kata sandi. Kata sandi disimpan dalam bentuk terenkripsi satu arah, sehingga tidak bisa dibaca siapa pun, termasuk kami.
- **Data teknis**: cookie sesi untuk keranjang dan status masuk, serta alamat IP untuk mencegah penyalahgunaan formulir.

## Untuk apa data dipakai

- Memproses, memproduksi, dan menyerahkan atau mengirim pesanan Anda.
- Memverifikasi pembayaran dan menghitung ongkos kirim.
- Menghubungi Anda tentang pesanan, misalnya persetujuan desain, pengingat pelunasan, atau barang yang sudah siap diambil.

Kami tidak menjual data Anda dan tidak memakainya untuk iklan.

## Pihak lain yang menerima data

Hanya sebatas yang diperlukan untuk menyelesaikan pesanan:

- **Penyedia pengiriman email**, untuk mengirim email konfirmasi dan pemberitahuan pesanan.
- **Penyedia hitung ongkir dan jasa kurir**, untuk pesanan yang dikirim: nama, nomor HP, dan alamat penerima.
- **Penyedia hosting**, tempat website dan datanya disimpan.

## Daftar pemain dan data anak

Daftar pemain untuk tim sekolah sering memuat nama anak. Dengan mengirimkannya, pemesan (guru, pelatih, atau orang tua) menyatakan berwenang memberikan data tersebut. Data itu hanya dipakai untuk dicetak di jersey dan tidak ditampilkan di bagian publik website.

## Keamanan

- Halaman pesanan hanya bisa dibuka lewat tautan khusus yang dikirim ke email Anda, dan tidak diindeks mesin pencari.
- Foto bukti pembayaran disimpan di tempat yang tidak bisa dibuka lewat alamat web.
- Akses panel staf dibatasi akun dan peran, dan setiap perubahan penting tercatat.

## Berapa lama data disimpan

Data pesanan dan pembayaran disimpan selama diperlukan untuk menyelesaikan pesanan, menangani komplain, dan memenuhi kewajiban pembukuan sesuai peraturan yang berlaku. Akun pelanggan disimpan sampai Anda meminta untuk dihapus.

## Hak Anda

Anda berhak meminta salinan data Anda, membetulkan data yang keliru, dan meminta penghapusan data yang tidak lagi wajib kami simpan. Sampaikan permintaan lewat WhatsApp {whatsapp} atau langsung di toko, {alamat}. Kami menanggapinya paling lambat 3 x 24 jam sejak permintaan diterima.

## Perubahan kebijakan

Kebijakan ini dapat diperbarui sewaktu-waktu. Tanggal pembaruan terakhir tercantum di bagian atas halaman ini.
MD;
    }

    public static function syaratKetentuan(): string
    {
        return <<<'MD'
Dengan memesan di website {toko}, Anda menyetujui ketentuan berikut.

## Pemesanan

- Ada dua jalur: **barang stok toko** (sudah ada di rak) dan **pesanan kustom** (dibuat di konveksi kami sesuai desain Anda).
- Harga yang berlaku adalah harga yang tampil saat pesanan dibuat. Perubahan harga di katalog sesudahnya tidak mengubah pesanan Anda.
- Pesanan dianggap sah setelah pembayaran pertamanya diverifikasi.

## Pembayaran

- Pembayaran dilakukan lewat transfer bank atau QRIS, lalu bukti pembayarannya diunggah di halaman pesanan.
- Pesanan yang belum dibayar dalam {batas_bayar_jam} jam dibatalkan otomatis, dan stoknya dilepas kembali.
- Pesanan kustom dapat dimulai dengan uang muka minimal {dp_minimum}% dari total. Sisanya dilunasi sebelum barang diambil atau dikirim.

## Desain pesanan kustom

- Produksi dimulai setelah Anda menyetujui mockup desain di halaman pesanan.
- Revisi desain sebelum disetujui gratis, maksimal {revisi_gratis} kali.
- Anda menjamin desain, logo, dan nama yang dikirim tidak melanggar hak cipta atau merek pihak lain.
- Ukuran, nama punggung, dan nomor punggung dicetak sesuai daftar yang Anda setujui. Periksa kembali sebelum menyetujui desain.

## Pengambilan dan pengiriman

- Barang yang sudah siap diambil di toko pada jam buka, setelah pesanan lunas.
- Barang yang tidak diambil lebih dari {hangus_hari} hari sejak dinyatakan siap dapat dianggap hangus, setelah kami menghubungi Anda terlebih dahulu.
- Untuk pesanan yang dikirim, paket berangkat setelah pesanan lunas. Nomor resi tampil di halaman pesanan.

## Pembatalan

Pembatalan diajukan lewat halaman pesanan dan diputuskan oleh toko. Untuk pesanan kustom yang bahannya sudah mulai dikerjakan, uang muka dapat tidak dikembalikan, seluruhnya atau sebagian, sesuai pekerjaan yang sudah berjalan.

## Komplain

Komplain atas cacat produksi atau barang yang tidak sesuai pesanan disampaikan secepatnya setelah barang diterima, disertai foto, lewat WhatsApp {whatsapp} atau langsung di toko.

## Lain-lain

Ketentuan ini dapat diperbarui sewaktu-waktu. Pesanan yang sudah dibuat tetap mengikuti ketentuan yang berlaku saat pesanan itu dibuat.
MD;
    }
}
