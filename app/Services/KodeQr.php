<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Gambar QR untuk lembar kerja (KEP-047).
 *
 * SVG, bukan PNG: lembar kerja dicetak, dan SVG tetap tajam di printer mana pun
 * tanpa perlu ekstensi gambar di hosting. Digambar saat halaman dibuka, tidak
 * disimpan sebagai berkas, karena isinya cuma tautan yang sudah ada di database.
 */
class KodeQr
{
    public function svg(string $isi, int $ukuran = 180): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($ukuran, 1),
            new SvgImageBackEnd(),
        ));

        // Deklarasi XML dibuang supaya SVG-nya bisa ditempel langsung di HTML.
        return preg_replace('/<\?xml.*?\?>\s*/', '', $writer->writeString($isi));
    }
}
