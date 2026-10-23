<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\DrafHukum;
use Illuminate\Contracts\View\View;

/** Kebijakan Privasi dan Syarat & Ketentuan (KEP-085). */
class HalamanHukumController extends Controller
{
    public function privasi(): View
    {
        return $this->tampil('kebijakan_privasi', 'Kebijakan Privasi',
            'Data apa yang kami kumpulkan saat Anda memesan, untuk apa, dan apa hak Anda atasnya.');
    }

    public function syarat(): View
    {
        return $this->tampil('syarat_ketentuan', 'Syarat & Ketentuan',
            'Aturan pemesanan, pembayaran, desain, pengambilan, dan pembatalan.');
    }

    private function tampil(string $kunci, string $judul, string $ringkas): View
    {
        $baris = Setting::find($kunci);
        abort_if(! $baris || blank($baris->value), 404);

        return view('publik.hukum', [
            'judul' => $judul,
            'ringkas' => $ringkas,
            'isi' => DrafHukum::html($kunci),
            // Tanggal dari kapan teksnya terakhir disimpan, bukan diketik
            // manual: tanggal ketikan selalu lupa diperbarui.
            'diperbarui' => $baris->updated_at,
        ]);
    }
}
