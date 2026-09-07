<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PengaturanController extends Controller
{
    public function index()
    {
        return view('panel.pengaturan.index', [
            'grup' => Setting::orderBy('grup')->orderBy('key')->get()->groupBy('grup'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $masuk = $request->input('setting', []);
        $diubah = [];

        foreach (Setting::whereIn('key', array_keys($masuk))->get() as $baris) {
            $nilai = $masuk[$baris->key];

            $aturan = match ($baris->tipe) {
                'int' => ['required', 'integer', 'min:0', 'max:1000000'],
                'bool' => ['nullable'],
                default => ['nullable', 'string', 'max:500'],
            };

            validator(
                ['nilai' => $nilai],
                ['nilai' => $aturan],
                [],
                ['nilai' => $baris->label]
            )->validate();

            if ((string) $baris->value !== (string) $nilai) {
                $diubah[$baris->key] = ['dari' => $baris->value, 'ke' => $nilai];
                Setting::simpan($baris->key, $nilai);
            }
        }

        if ($diubah !== []) {
            ActivityLog::catat('pengaturan.diubah', null, $diubah);
        }

        return back()->with('sukses', $diubah === []
            ? 'Tidak ada pengaturan yang berubah.'
            : count($diubah).' pengaturan disimpan.');
    }
}
