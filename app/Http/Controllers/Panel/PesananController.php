<?php

namespace App\Http\Controllers\Panel;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\AlurPesanan;
use App\Services\PengirimEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PesananController extends Controller
{
    public function __construct(
        private AlurPesanan $alur,
        private PengirimEmail $email,
    ) {}

    public function index(Request $request)
    {
        $status = $request->string('status')->toString();

        return view('panel.pesanan.index', [
            'pesanan' => Order::query()
                ->with(['customer', 'items', 'payments', 'adjustments'])
                ->when($status === 'aktif', fn ($q) => $q->aktif())
                ->when($status && $status !== 'aktif', fn ($q) => $q->where('status', $status))
                ->when($request->filled('q'), function ($q) use ($request) {
                    $cari = '%'.$request->string('q').'%';
                    $q->where(fn ($w) => $w->where('kode', 'like', $cari)
                        ->orWhereHas('customer', fn ($c) => $c->where('nama', 'like', $cari)
                            ->orWhere('email', 'like', $cari)
                            ->orWhere('no_hp', 'like', $cari)));
                })
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'status' => $status,
            'jumlahStatus' => [
                'aktif' => Order::aktif()->count(),
                OrderStatus::MENUNGGU_PEMBAYARAN->value => Order::where('status', OrderStatus::MENUNGGU_PEMBAYARAN)->count(),
                OrderStatus::DIPROSES->value => Order::where('status', OrderStatus::DIPROSES)->count(),
                OrderStatus::SIAP_DIAMBIL->value => Order::where('status', OrderStatus::SIAP_DIAMBIL)->count(),
            ],
        ]);
    }

    public function show(Order $pesanan)
    {
        $pesanan->load([
            'customer', 'items.options', 'items.rosters', 'items.productionJobs',
            'adjustments.pembuat', 'payments.pencatat', 'payments.verifikator',
            'statusLogs.pelaku', 'statusLogs.orderItem', 'emailLogs',
        ]);

        return view('panel.pesanan.show', [
            'pesanan' => $pesanan,
            'pesanWa' => $this->pesanWhatsApp($pesanan),
        ]);
    }

    public function majukanItem(Order $pesanan, OrderItem $item): RedirectResponse
    {
        abort_unless($item->order_id === $pesanan->id, 404);

        $baru = $this->alur->majukanItem($item, auth()->id());

        return back()->with('sukses', "{$item->nama_produk} sekarang: {$baru->label()}.");
    }

    public function batalkan(Request $request, Order $pesanan): RedirectResponse
    {
        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:200'],
        ], [
            'alasan.required' => 'Tulis alasan pembatalan: pelanggan berhak tahu, dan ini masuk riwayat pesanan.',
        ], ['alasan' => 'alasan pembatalan']);

        $this->alur->batalkan($pesanan, $data['alasan'], auth()->id());

        return back()->with('sukses', 'Pesanan dibatalkan dan reservasi stoknya dilepas.');
    }

    /**
     * Tanpa WhatsApp API (KEP-004), sistem menyiapkan teksnya dan admin yang
     * menekan kirim. Bukan otomatisasi, tapi nol biaya dan realistis.
     */
    private function pesanWhatsApp(Order $order): array
    {
        $template = match ($order->status) {
            OrderStatus::MENUNGGU_PEMBAYARAN => 'order-dibuat',
            OrderStatus::SIAP_DIAMBIL => 'pesanan-siap',
            default => 'pembayaran-diterima',
        };

        $nomor = preg_replace('/[^0-9]/', '', $order->customer->no_hp);
        $nomor = str_starts_with($nomor, '0') ? '62'.substr($nomor, 1) : $nomor;

        return [
            'teks' => $this->email->teksWhatsApp($order, $template),
            'url' => 'https://wa.me/'.$nomor,
        ];
    }
}
