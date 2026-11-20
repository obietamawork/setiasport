<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Mengubah foto dari kamera atau HP menjadi berkas yang layak ditayangkan.
 *
 * Foto produk mentah dari HP lazimnya 3–8 MB. Menayangkannya apa adanya
 * membuat halaman katalog berat di jaringan seluler dan menghabiskan kuota disk
 * shared hosting dalam hitungan bulan. Setiap unggahan dikecilkan, dikonversi ke
 * WebP, dan dibuatkan versi kecil untuk kartu produk.
 */
class PengunggahGambar
{
    private const LEBAR_MAKS = 1400;

    private const LEBAR_THUMB = 480;

    private const MUTU = 82;

    /** Resolusi tertinggi yang mau dibuka, berapa pun memorinya: 40 megapiksel. */
    public const MAKS_PIKSEL = 40_000_000;

    /**
     * Gambar yang tidak muat di memori PHP saat dibuka GD (KEP-153). Hanya
     * kepala berkas yang dibaca: PNG seratus bait yang mengaku 6000x5000
     * piksel dulu cukup untuk menghabiskan memori 256 MB dan menjatuhkan
     * proses. GD membuka gambar utuh, 4 bait per piksel, dan PNG butuh
     * salinan mentah lagi.
     */
    public static function terlaluBesar(string $path): bool
    {
        $info = @getimagesize($path);

        if ($info === false) {
            return false;
        }

        $piksel = $info[0] * $info[1];
        $baitPerPiksel = $info[2] === IMAGETYPE_PNG ? 8 : 5;

        return $piksel > self::MAKS_PIKSEL || $piksel * $baitPerPiksel > self::sisaMemori();
    }

    private static function sisaMemori(): int
    {
        $batas = trim((string) ini_get('memory_limit'));

        if ($batas === '' || $batas === '-1') {
            return PHP_INT_MAX;
        }

        $kali = match (strtolower(substr($batas, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        return (int) $batas * $kali - memory_get_usage();
    }

    /**
     * Bukti pembayaran memakai disk 'local' (privat) dan tanpa versi kecil
     * (KEP-029): isinya nama dan nomor rekening pelanggan, jadi tidak boleh bisa
     * dibuka siapa pun yang kebetulan menebak alamatnya.
     *
     * @return array{path: string, thumb_path: ?string, lebar: int, tinggi: int}
     */
    public function simpan(UploadedFile $berkas, string $folder = 'produk', string $disk = 'public', bool $denganThumb = true, int $lebarMaks = self::LEBAR_MAKS): array
    {
        $sumber = $this->baca($berkas);
        [$lebarAsli, $tinggiAsli] = [imagesx($sumber), imagesy($sumber)];

        $nama = Str::random(24);
        $ext = $this->webpTersedia() ? 'webp' : 'jpg';

        // Banner selebar layar butuh lebih dari 1400px supaya tidak buram di
        // monitor 1920px (KEP-092); foto produk dan portofolio tetap 1400.
        $utama = $this->kecilkan($sumber, $lebarMaks);

        $path = "{$folder}/{$nama}.{$ext}";
        $thumbPath = null;

        Storage::disk($disk)->put($path, $this->kodekan($utama));

        if ($denganThumb) {
            $thumb = $this->kecilkan($sumber, self::LEBAR_THUMB);
            $thumbPath = "{$folder}/{$nama}-kecil.{$ext}";
            Storage::disk($disk)->put($thumbPath, $this->kodekan($thumb));
            imagedestroy($thumb);
        }

        $lebar = imagesx($utama);
        $tinggi = imagesy($utama);

        imagedestroy($sumber);
        imagedestroy($utama);

        return [
            'path' => $path,
            'thumb_path' => $thumbPath,
            'lebar' => $lebar,
            'tinggi' => $tinggi,
        ];
    }

    public function hapus(?string ...$path): void
    {
        foreach (array_filter($path) as $p) {
            Storage::disk('public')->delete($p);
        }
    }

    public function hapusDari(string $disk, ?string ...$path): void
    {
        foreach (array_filter($path) as $p) {
            Storage::disk($disk)->delete($p);
        }
    }

    private function baca(UploadedFile $berkas): \GdImage
    {
        if (self::terlaluBesar($berkas->getRealPath())) {
            throw new RuntimeException('Resolusi foto terlalu besar. Kirim foto dengan resolusi lebih kecil, misalnya tangkapan layar.');
        }

        $isi = file_get_contents($berkas->getRealPath());
        $gambar = @imagecreatefromstring($isi);

        if (! $gambar) {
            throw new RuntimeException('Berkas itu bukan gambar yang bisa dibaca.');
        }

        // Foto dari HP sering menyimpan arah lewat metadata EXIF, bukan lewat
        // pikselnya. Tanpa koreksi ini, sebagian foto tampil terbaring.
        // Memutar membuat salinan kedua; bila memori tidak cukup, foto tampil
        // apa adanya daripada prosesnya jatuh.
        $cukup = imagesx($gambar) * imagesy($gambar) * 4 < self::sisaMemori();

        if ($cukup && function_exists('exif_read_data') && $berkas->getMimeType() === 'image/jpeg') {
            $exif = @exif_read_data($berkas->getRealPath());
            $arah = $exif['Orientation'] ?? 1;

            $gambar = match ($arah) {
                3 => imagerotate($gambar, 180, 0),
                6 => imagerotate($gambar, -90, 0),
                8 => imagerotate($gambar, 90, 0),
                default => $gambar,
            };
        }

        return $gambar;
    }

    private function kecilkan(\GdImage $sumber, int $lebarMaks): \GdImage
    {
        $lebar = imagesx($sumber);
        $tinggi = imagesy($sumber);

        if ($lebar <= $lebarMaks) {
            $salinan = imagecreatetruecolor($lebar, $tinggi);
            $this->siapkanKanvas($salinan);
            imagecopy($salinan, $sumber, 0, 0, 0, 0, $lebar, $tinggi);

            return $salinan;
        }

        $lebarBaru = $lebarMaks;
        $tinggiBaru = (int) round($tinggi * ($lebarMaks / $lebar));

        $hasil = imagecreatetruecolor($lebarBaru, $tinggiBaru);
        $this->siapkanKanvas($hasil);
        imagecopyresampled($hasil, $sumber, 0, 0, 0, 0, $lebarBaru, $tinggiBaru, $lebar, $tinggi);

        return $hasil;
    }

    private function siapkanKanvas(\GdImage $kanvas): void
    {
        imagealphablending($kanvas, false);
        imagesavealpha($kanvas, true);
        imagefill($kanvas, 0, 0, imagecolorallocatealpha($kanvas, 255, 255, 255, 127));
    }

    /**
     * WebP jauh lebih kecil, tapi tidak semua shared hosting mengompilasi GD
     * dengan dukungannya. Daripada gagal mengunggah, turun ke JPEG.
     */
    private function webpTersedia(): bool
    {
        return function_exists('imagewebp');
    }

    private function kodekan(\GdImage $gambar): string
    {
        ob_start();

        if ($this->webpTersedia()) {
            imagewebp($gambar, null, self::MUTU);
        } else {
            // JPEG tidak mengenal transparansi; latar putih lebih baik daripada hitam.
            $datar = imagecreatetruecolor(imagesx($gambar), imagesy($gambar));
            imagefill($datar, 0, 0, imagecolorallocate($datar, 255, 255, 255));
            imagecopy($datar, $gambar, 0, 0, 0, 0, imagesx($gambar), imagesy($gambar));
            imagejpeg($datar, null, self::MUTU);
            imagedestroy($datar);
        }

        return (string) ob_get_clean();
    }
}
