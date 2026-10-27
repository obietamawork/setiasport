<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Pekerja;
use App\Models\RollCetak;
use App\Services\RollPrinting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Halaman roll yang dibuka operator printing lewat pindai QR (KEP-051).
 *
 * Sama seperti lembar kerja per pesanan: membuka halaman tidak mengubah apa
 * pun, dan yang memindahkan tahap adalah PIN operator.
 */
class RollController extends Controller
{
    public function __construct(private RollPrinting $roll) {}

    public function show(string $token): View
    {
        return view('publik.roll', ['roll' => $this->cari($token)]);
    }

    public function selesai(Request $request, string $token): RedirectResponse
    {
        $roll = $this->cari($token);

        $data = $request->validate([
            'pin' => ['required', 'string', 'min:4', 'max:8'],
        ], ['pin.required' => 'Masukkan PIN Anda dulu.']);

        $pekerja = Pekerja::periksaPin($data['pin'], 'roll:'.$roll->id);

        $maju = $this->roll->selesaikan($roll, $pekerja);

        return back()->with('sukses',
            $roll->kode.' selesai printing. '.$maju.' pesanan naik ke tahap berikutnya.');
    }

    private function cari(string $token): RollCetak
    {
        return RollCetak::where('token', $token)
            ->with(['items.order', 'items.rosters', 'pekerja'])
            ->firstOrFail();
    }
}
