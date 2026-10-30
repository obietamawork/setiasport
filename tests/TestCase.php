<?php

namespace Tests;

use App\Models\OptionValue;
use App\Models\Product;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Id nilai opsi seperti yang dikirim konfigurator: label yang disebut dipakai,
     * dan opsi wajib yang tidak disebut diisi nilai aktif pertamanya, sama dengan
     * pilihan bawaan di layar. Server menolak opsi wajib yang kosong (KEP-096).
     *
     * @return array<int, int>
     */
    protected function opsiLengkap(Product $produk, string ...$label): array
    {
        $produk->loadMissing('options.values');

        $dipilih = OptionValue::whereIn('label', $label)
            ->whereHas('option', fn ($q) => $q->where('product_id', $produk->id))
            ->get();

        foreach ($produk->options as $opsi) {
            if ($opsi->wajib && ! $dipilih->contains('product_option_id', $opsi->id)) {
                $bawaan = $opsi->values->firstWhere('aktif', true);

                if ($bawaan) {
                    $dipilih->push($bawaan);
                }
            }
        }

        return $dipilih->pluck('id')->all();
    }
}
