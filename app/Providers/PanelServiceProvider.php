<?php

namespace App\Providers;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PercakapanCs;
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

                // Chat CS (KEP-030): percakapan yang pesannya belum dibaca staf.
                // Saat CS dimatikan (KEP-033) tidak ada bunyi dan tidak ada polling.
                'csBelumDibaca' => cs_aktif() && $user->punyaIzin('cs.balas')
                    ? PercakapanCs::where('belum_dibaca_staf', '>', 0)->pluck('id')->all()
                    : null,

                // Menu tetap ada selama masih ada arsip: log chat adalah catatan
                // resmi komplain, jadi mematikan CS tidak boleh menyembunyikannya.
                'csTampilMenu' => cs_aktif() || PercakapanCs::query()->exists(),
            ]);
        });
    }

    private function antrian($user): ?int
    {
        $tahap = $user->tahapProduksi();

        if (empty($tahap)) {
            return null;
        }

        $status = collect($tahap)
            ->flatMap(fn ($t) => $t->statusDipegang())
            ->map(fn (OrderItemStatus $s) => $s->value)
            ->unique()
            ->all();

        return OrderItem::whereIn('status_item', $status)
            ->whereHas('order', fn ($q) => $q->aktif())
            ->count();
    }
}
