<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\KesiapanTayang;
use Illuminate\View\View;

/**
 * Daftar kesiapan sebelum website dibuka untuk umum.
 *
 * Dipisahkan dari dashboard dengan sengaja: dashboard menjawab "apa yang perlu
 * dikerjakan hari ini", halaman ini menjawab "apa yang masih kurang sebelum
 * website dibuka". Yang kedua hanya dibaca beberapa kali, lalu tidak pernah
 * lagi, dan tidak boleh ikut memenuhi layar harian.
 */
class KesiapanController extends Controller
{
    public function __invoke(KesiapanTayang $kesiapan): View
    {
        return view('panel.kesiapan', [
            'bagian' => $kesiapan->bagian(),
            'ringkasan' => $kesiapan->ringkasan(),
        ]);
    }
}
