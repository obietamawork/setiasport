<?php

namespace App\Providers;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Angka pada menu panel.
 *
 * Disuntikkan lewat view composer, bukan lewat tiap controller, supaya tidak
 * ada halaman yang terlewat dan menampilkan menu tanpa penghitung. Yang
 * menunggu ditangani harus terlihat tanpa perlu membuka halamannya dulu.
 */
class PanelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer('layouts.panel', function ($view) {
            $user = auth('web')->user();

            if (! $user) {
                return;
            }

            $view->with([
                'jumlahPesananAktif' => $user->punyaIzin('order.lihat')
                    ? Order::aktif()->count()
                    : null,

                'jumlahSiapDiambil' => $user->punyaIzin('serah_terima.proses')
                    ? Order::where('status', OrderStatus::SIAP_DIAMBIL)->count()
                    : null,

                'jumlahAntrian' => $this->antrian($user),
            ]);
        });
    }

    private function antrian($user): ?int
    {
        $tahap = $user->tahapProduksi();

        if (empty($tahap)) {
            return null;
        }

        // Status item menyatakan divisi yang memegang barang (KEP-014).
        $dipegang = [
            'DESAIN' => [OrderItemStatus::ANTRIAN_DESAIN, OrderItemStatus::PROSES_DESAIN],
            'PRINT' => [OrderItemStatus::ANTRIAN_PRODUKSI, OrderItemStatus::PRINTING],
            'JAHIT' => [OrderItemStatus::JAHIT],
            'QC' => [OrderItemStatus::QC],
            'PACKING' => [OrderItemStatus::PACKING],
        ];

        $status = collect($tahap)
            ->flatMap(fn ($t) => $dipegang[$t->value] ?? [])
            ->map(fn (OrderItemStatus $s) => $s->value)
            ->unique()
            ->all();

        return OrderItem::whereIn('status_item', $status)
            ->whereHas('order', fn ($q) => $q->aktif())
            ->count();
    }
}
