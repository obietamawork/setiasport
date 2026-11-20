<?php

namespace App\Rules;

use App\Services\PengunggahGambar;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Gambar unggahan benar-benar bisa dibuka (KEP-154): tidak rusak atau
 * terpotong, dan resolusinya muat di memori. Aturan "image" bawaan hanya
 * memeriksa kepala berkas, sehingga foto yang terpotong saat diunggah lewat
 * sinyal jelek tetap lolos lalu gagal saat diproses.
 */
class GambarBisaDiproses implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        $masalah = PengunggahGambar::masalah((string) $value->getRealPath());

        if ($masalah !== null) {
            $fail($masalah);
        }
    }
}
