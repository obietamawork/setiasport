<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Riwayat aktivitas (KEP-079): satu tempat membaca jejak yang sudah dicatat
 * `ActivityLog::catat()` di seluruh panel.
 *
 * Alat penyelesaian perselisihan internal (docs/04) hanya berguna kalau bisa
 * DIBACA. Sebelum ini, satu-satunya cara melihat isi `activity_logs` adalah
 * membuka database langsung -- sesuatu yang tidak pernah dilakukan pemilik
 * toko di tengah perselisihan sungguhan.
 */
class AktivitasController extends Controller
{
    public function __invoke(Request $request): View
    {
        $log = ActivityLog::query()
            ->with('user')
            ->when($request->filled('q'), fn ($q) => $q->where('aksi', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('panel.aktivitas', [
            'log' => $log,
            'pengguna' => User::orderBy('name')->get(),
        ]);
    }
}
