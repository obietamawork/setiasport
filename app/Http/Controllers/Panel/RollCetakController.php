<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\RollCetak;
use App\Services\KodeQr;
use App\Services\RollPrinting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Roll kertas printing (KEP-051). */
class RollCetakController extends Controller
{
    public function __construct(private RollPrinting $roll, private KodeQr $qr) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'item' => ['required', 'array', 'min:1'],
            'item.*' => ['integer'],
            'catatan' => ['nullable', 'string', 'max:200'],
        ], [
            'item.required' => 'Centang dulu pesanan yang masuk roll ini.',
        ]);

        $roll = $this->roll->buat($data['item'], $data['catatan'] ?? null, auth('web')->id());

        return redirect()->route('panel.roll.show', $roll)
            ->with('sukses', $roll->kode.' dibuat. Cetak lembarnya dan tempel di roll kertasnya.');
    }

    public function show(RollCetak $roll): View
    {
        $roll->load(['items.order.customer', 'items.rosters', 'pekerja', 'pembuat']);

        return view('panel.roll', [
            'roll' => $roll,
            'qr' => $this->qr->svg(route('roll', $roll->token), 160),
            'tautan' => route('roll', $roll->token),
        ]);
    }
}
