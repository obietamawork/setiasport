<?php

namespace App\Http\Controllers\Panel;

use App\Enums\ProductionStage;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\IsiPanduan;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panduan internal untuk orang toko sendiri (KEP-162).
 *
 * Tiap orang membaca bagian yang sesuai izinnya: pemilik melihat semuanya,
 * admin bagian operasional, dan peran lain hanya yang memang ia kerjakan.
 * Isinya ada di App\Support\IsiPanduan.
 */
class BantuanController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $pengguna */
        $pengguna = auth('web')->user();

        // Pemilik bisa melihat panduan sebagai peran lain, misalnya sebelum
        // mengajari admin baru. Peran lain tidak bisa meminjam tampilan siapa pun.
        $bolehMeniru = $pengguna->punyaIzin('pengguna.kelola');
        $sebagai = $bolehMeniru && $request->filled('sebagai')
            ? Role::with('permissions')->where('slug', $request->string('sebagai')->toString())->first()
            : null;

        $punya = $sebagai
            ? fn (string $izin): bool => $sebagai->slug === 'owner' || $sebagai->permissions->contains('slug', $izin)
            : fn (string $izin): bool => $pengguna->punyaIzin($izin);

        $bagian = collect(IsiPanduan::semua())
            ->filter(fn (array $b): bool => $b['izin'] === null || collect($b['izin'])->contains($punya))
            ->values()
            ->all();

        return view('panel.bantuan', [
            'bagian' => $bagian,
            'peran' => $bolehMeniru ? Role::orderByDesc('slug')->get() : collect(),
            'sebagai' => $sebagai,
            'bolehCetakKonveksi' => collect(IsiPanduan::IZIN_PRODUKSI)->contains(fn (string $izin): bool => $pengguna->punyaIzin($izin)),
        ]);
    }

    /** Panduan kerja konveksi siap cetak A4, untuk ditempel di workshop. */
    public function konveksi(): View
    {
        return view('panel.bantuan-konveksi', [
            'langkah' => IsiPanduan::langkahKonveksi(),
            'tahap' => array_filter(ProductionStage::cases(), fn (ProductionStage $t): bool => $t !== ProductionStage::PACKING),
        ]);
    }
}
