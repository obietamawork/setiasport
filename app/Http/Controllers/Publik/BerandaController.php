<?php

namespace App\Http\Controllers\Publik;

use App\Enums\FulfillmentType;
use App\Http\Controllers\Controller;
use App\Models\Portofolio;
use App\Models\Product;
use App\Models\Testimoni;
use Illuminate\Contracts\View\View;

/**
 * Halaman depan adalah profil usaha, bukan etalase (KEP-024).
 *
 * Pembeli terbesar di sini bukan orang yang iseng menelusuri produk, melainkan
 * pengurus sekolah, dinas, atau klub yang sedang memutuskan konveksi mana yang
 * dipercaya memegang anggaran mereka. Yang mereka cari lebih dulu adalah bukti
 * pekerjaan, alamat yang bisa didatangi, dan orang yang bisa dihubungi. Katalog
 * baru berguna setelah pertanyaan itu terjawab, jadi ia diletakkan belakangan.
 */
class BerandaController extends Controller
{
    public function __invoke(): View
    {
        $leadTime = Product::query()
            ->aktif()
            ->whereIn('fulfillment_type', [
                FulfillmentType::PREORDER->value, FulfillmentType::BOTH->value,
            ])
            ->min('lead_time_hari');

        return view('publik.beranda', [
            'portofolio' => Portofolio::tampilan()->take(6)->get(),
            'testimoni' => Testimoni::tampilan()->take(3)->get(),

            // Sekadar cuplikan. Penelusuran sesungguhnya terjadi di katalog.
            'produk' => Product::query()
                ->aktif()
                ->with(['images', 'priceTiers'])
                ->latest('id')
                ->take(5)
                ->get(),

            'leadTime' => $leadTime,
        ]);
    }
}
