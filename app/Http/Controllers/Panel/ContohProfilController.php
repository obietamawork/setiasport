<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ContohProfil;
use Illuminate\Http\RedirectResponse;

/**
 * Satu tombol untuk membersihkan data contoh portofolio dan testimoni (KEP-035)
 * sebelum website dibuka untuk umum. Isian asli klien tidak tersentuh.
 */
class ContohProfilController extends Controller
{
    public function hapus(ContohProfil $contoh): RedirectResponse
    {
        $hasil = $contoh->hapus();

        ActivityLog::catat('profil.contoh-dihapus', null, $hasil);

        return back()->with('sukses',
            "Data contoh dihapus: {$hasil['portofolio']} portofolio dan {$hasil['testimoni']} testimoni. "
            .'Isian Anda sendiri tidak tersentuh.');
    }
}
