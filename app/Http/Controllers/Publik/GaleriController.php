<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Portofolio;
use App\Models\Testimoni;
use Illuminate\Contracts\View\View;

/**
 * Halaman "Lihat semua" untuk galeri portofolio dan testimoni pelanggan
 * (KEP-106). Halaman depan hanya memajang sebagian; di sini semuanya, dengan
 * ringkasan pekerjaan yang tidak muat di ubin beranda. Halaman kosong (belum
 * ada isinya, atau nomor halaman melewati yang terakhir) dijawab 404, bukan
 * halaman berjudul tanpa isi.
 */
class GaleriController extends Controller
{
    public function portofolio(): View
    {
        $portofolio = Portofolio::tampilan()->paginate(24);
        abort_if($portofolio->isEmpty(), 404);

        return view('publik.portofolio', ['portofolio' => $portofolio]);
    }

    public function testimoni(): View
    {
        $testimoni = Testimoni::tampilan()->paginate(24);
        abort_if($testimoni->isEmpty(), 404);

        return view('publik.testimoni', ['testimoni' => $testimoni]);
    }
}
