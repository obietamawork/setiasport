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

        /*
         * Kustom dan stok toko dipisah karena alurnya memang berbeda (KEP-027):
         * yang satu melewati desain, persetujuan, printing, dan jahit; yang lain
         * cuma diambil dari rak. Mencampurnya di satu daftar membuat orang yang
         * sedang mengurus konveksi harus menyaring sendiri setiap kali.
         *
         * Pesanan campuran masuk ke tab Kustom, karena bagian kustomnya yang
         * menentukan kapan seluruh pesanan bisa diserahkan. Ia diberi penanda
         * sendiri di daftar supaya tidak ada yang kaget menemukan barang stok
         * di dalamnya.
         */
        $jalur = $request->string('jalur')->toString();

        $saring = fn ($q) => $q
            ->when($jalur === 'kustom', fn ($w) => $w->adaKustom())
            ->when($jalur === 'stok', fn ($w) => $w->hanyaStok());

        return view('panel.pesanan.index', [
            'pesanan' => Order::query()
                ->with(['customer', 'items', 'payments', 'adjustments'])
                ->tap($saring)
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
            'jalur' => $jalur,
            // Hitungan status mengikuti tab yang sedang dibuka; kalau tidak,
            // angkanya menjanjikan hasil yang tidak akan muncul saat diklik.
            'jumlahStatus' => [
                'aktif' => Order::query()->tap($saring)->aktif()->count(),
                OrderStatus::MENUNGGU_PEMBAYARAN->value => Order::query()->tap($saring)->where('status', OrderStatus::MENUNGGU_PEMBAYARAN)->count(),
                OrderStatus::DIPROSES->value => Order::query()->tap($saring)->where('status', OrderStatus::DIPROSES)->count(),
                OrderStatus::MENUNGGU_PELUNASAN->value => Order::query()->tap($saring)->where('status', OrderStatus::MENUNGGU_PELUNASAN)->count(),
                OrderStatus::SIAP_DIAMBIL->value => Order::query()->tap($saring)->where('status', OrderStatus::SIAP_DIAMBIL)->count(),
                OrderStatus::DIKIRIM->value => Order::query()->tap($saring)->where('status', OrderStatus::DIKIRIM)->count(),
            ],
            'jumlahJalur' => [
                '' => Order::count(),
                'kustom' => Order::adaKustom()->count(),
                'stok' => Order::hanyaStok()->count(),
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

        $kebijakan = app(\App\Services\KebijakanPembatalan::class);

        return view('panel.pesanan.show', [
            'pesanan' => $pesanan,
            'pesanWa' => $this->pesanWhatsApp($pesanan),

            // Pengajuan yang belum diputuskan (KEP-041). Selama ada di sini,
            // pekerjaan tetap berjalan, jadi ia ditaruh paling atas di layar.
            'pembatalan' => $kebijakan->pengajuanTertunda($pesanan),
            'biayaSetup' => $kebijakan->biayaSetup($pesanan),
        ]);
    }

    /**
     * Barang yang tidak pernah diambil (KEP-043). Uang pelanggan hangus, jadi
     * tombolnya hanya muncul setelah batas waktunya benar-benar lewat.
     */
    public function hanguskan(Order $pesanan): RedirectResponse
    {
        abort_unless($pesanan->bolehDihanguskan(), 422,
            'Pesanan ini belum melewati batas waktu pengambilan.');

        $this->alur->hanguskan($pesanan, auth('web')->id());

        return back()->with('sukses', 'Pesanan ditandai hangus dan reservasi stoknya dilepas.');
    }

    public function majukanItem(Order $pesanan, OrderItem $item): RedirectResponse
    {
        abort_unless($item->order_id === $pesanan->id, 404);

        $baru = $this->alur->majukanItem($item, auth('web')->id());

        return back()->with('sukses', "{$item->nama_produk} sekarang: {$baru->label()}.");
    }

    public function batalkan(Request $request, Order $pesanan): RedirectResponse
    {
        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:200'],
        ], [
            'alasan.required' => 'Tulis alasan pembatalan: pelanggan berhak tahu, dan ini masuk riwayat pesanan.',
        ], ['alasan' => 'alasan pembatalan']);

        $this->alur->batalkan($pesanan, $data['alasan'], auth('web')->id());

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
            OrderStatus::MENUNGGU_PELUNASAN => 'menunggu-pelunasan',
            OrderStatus::SIAP_DIAMBIL => 'pesanan-siap',
            OrderStatus::DIKIRIM => 'pesanan-dikirim',
            default => 'pembayaran-diterima',
        };

        return [
            'teks' => $this->email->teksWhatsApp($order, $template),
            'url' => 'https://wa.me/'.nomor_wa($order->customer->no_hp),
        ];
    }
}
