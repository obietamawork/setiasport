<?php

namespace App\Http\Controllers\Panel;

use App\Enums\FulfillmentType;
use App\Enums\KategoriUkuran;
use App\Enums\MetodeAmbil;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\AlurPesanan;
use App\Services\Checkout;
use App\Services\PriceCalculator;
use App\Services\RincianPerUkuran;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Pesanan yang diketik admin (KEP-054).
 *
 * Pesanan reseller dan pembeli yang datang langsung ke toko selama ini hanya
 * tercatat di buku. Akibatnya pelanggan yang bertanya "sampai mana jersey saya"
 * harus dijawab manual, dan konveksi mengerjakannya tanpa lembar kerja maupun
 * QR penanda tahap.
 *
 * Yang dibuat di sini melewati jalan yang sama persis dengan pesanan dari web:
 * nomor pesanan, halaman lacak bertoken, reservasi stok, antrian produksi, dan
 * potongan borongan.
 */
class PesananManualController extends Controller
{
    public function __construct(
        private PriceCalculator $kalkulator,
        private Checkout $checkout,
        private AlurPesanan $alur,
    ) {}

    public function form(): View
    {
        return view('panel.pesanan.manual', [
            'produk' => Product::where('aktif', true)
                ->with(['prices', 'sizeCharts', 'sizeSurcharges', 'options.values', 'variants'])
                ->orderBy('nama')
                ->get(),
            'kategori' => KategoriUkuran::cases(),
            'persenDp' => (int) setting('dp_minimum_persen', 50),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'no_hp' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'catatan' => ['nullable', 'string', 'max:1000'],

            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'opsi' => ['array'],
            'opsi.*' => ['nullable', 'integer'],
            'baris' => ['array'],
            'baris.*.kategori' => ['nullable', 'string', 'max:10'],
            'baris.*.ukuran' => ['nullable', 'string', 'max:10'],
            'baris.*.jumlah' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'baris.*.nama_punggung' => ['nullable', 'string', 'max:60'],
            'baris.*.nomor' => ['nullable', 'string', 'max:5'],

            'tipe_pembayaran' => ['required', 'in:DP,LUNAS'],
            'metode_ambil' => ['required', 'in:AMBIL,KIRIM'],
            'alamat_kirim' => ['nullable', 'string', 'max:500', 'required_if:metode_ambil,KIRIM'],
            // https saja (bukan javascript:/data:) -- tautannya diklik admin
            // langsung dari halaman pesanan, jadi skema liar tidak boleh lolos.
            'tautan_lokasi' => ['nullable', 'url:https', 'max:500'],

            // Uang yang sudah diterima di kasir, kalau ada.
            'dibayar' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'metode_bayar' => ['nullable', 'in:TUNAI,TRANSFER'],
        ], [
            'alamat_kirim.required_if' => 'Isi alamat kirimnya.',
        ], [
            'no_hp' => 'nomor HP',
            'product_id' => 'produk',
            'baris' => 'daftar ukuran',
        ]);

        $produk = Product::with(['prices', 'sizeCharts', 'sizeSurcharges', 'options.values'])
            ->findOrFail($data['product_id']);

        try {
            $baris = $this->baris($produk, $data);
        } catch (InvalidArgumentException $e) {
            // Pesan aslinya yang paling menjelaskan, misalnya "Minimum pemesanan 6 pcs".
            throw ValidationException::withMessages(['product_id' => $e->getMessage()]);
        }

        $pelanggan = $this->pelanggan($data);

        $order = $this->checkout->buat(
            collect([$baris]),
            [
                'nama' => $pelanggan->nama,
                'email' => $pelanggan->email,
                'no_hp' => $pelanggan->no_hp,
                'catatan' => $data['catatan'] ?? null,
            ],
            $data['tipe_pembayaran'] === 'DP' ? 'DP' : 'LUNAS',
            manual: true,
        );

        $order->update([
            'metode_ambil' => $data['metode_ambil'] === 'KIRIM' ? MetodeAmbil::KIRIM : MetodeAmbil::AMBIL_TOKO,
            'alamat_kirim' => $data['metode_ambil'] === 'KIRIM' ? $data['alamat_kirim'] : null,
            'tautan_lokasi' => $data['metode_ambil'] === 'KIRIM' ? ($data['tautan_lokasi'] ?? null) : null,
        ]);

        $pesan = 'Pesanan '.$order->kode.' dibuat.';

        /*
         * Uang yang sudah diterima di kasir dicatat lewat baris pembayaran yang
         * memang sudah dibuat pesanan ini, bukan baris baru: kalau tidak, satu
         * pesanan punya dua tagihan dan sisa tagihannya salah.
         */
        if (($data['dibayar'] ?? 0) > 0) {
            $order->load(['items.options', 'payments', 'adjustments']);
            $tagihan = $order->payments->firstWhere('status', PaymentStatus::MENUNGGU);

            $jumlah = min((int) $data['dibayar'], $order->total);

            // jumlah/metode/dicatat_oleh lewat $tambahan, BUKAN $tagihan->update()
            // terpisah di sini: kalau pemeriksaan sisa tagihan di dalam
            // terimaPembayaran() menolak, perubahan field ini ikut batal,
            // bukan tersimpan duluan padahal pesanan belum benar-benar lunas.
            $this->alur->terimaPembayaran($tagihan, auth('web')->id(), [
                'jumlah' => $jumlah,
                'metode' => ($data['metode_bayar'] ?? 'TUNAI') === 'TRANSFER'
                    ? PaymentMethod::TRANSFER
                    : PaymentMethod::TUNAI,
                'dicatat_oleh' => auth('web')->id(),
                'dibayar_pada' => now(),
            ]);

            $pesan .= ' Pembayaran '.rupiah($jumlah).' tercatat.';
        }

        ActivityLog::catat('pesanan.manual', $order, [
            'kode' => $order->kode,
            'pelanggan' => $pelanggan->nama,
        ]);

        return redirect()->route('panel.pesanan.show', $order)->with('sukses',
            $pesan.' Kirimkan tautan halaman pesanannya ke pelanggan lewat WhatsApp.');
    }

    /**
     * Pelanggan dicari dulu sebelum dibuat baru: pembeli yang sama sering
     * memesan berkali-kali, dan dua kartu pelanggan untuk satu orang membuat
     * riwayatnya terpecah.
     *
     * @param  array<string, mixed>  $data
     */
    private function pelanggan(array $data): Customer
    {
        $customer = filled($data['email'] ?? null)
            ? Customer::where('email', $data['email'])->first()
            : Customer::where('no_hp', $data['no_hp'])->whereNull('email')->first();

        if (! $customer) {
            return Customer::create([
                'nama' => $data['nama'],
                'email' => $data['email'] ?? null,
                'no_hp' => $data['no_hp'],
            ]);
        }

        // Profil pelanggan yang punya akun tidak ditimpa dari panel (KEP-021).
        if (! $customer->akunAktif()) {
            $customer->update(['nama' => $data['nama'], 'no_hp' => $data['no_hp']]);
        }

        return $customer;
    }

    /**
     * Satu baris pesanan, dihitung memakai kalkulator harga yang sama dengan
     * halaman pemesanan pelanggan.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function baris(Product $produk, array $data): array
    {
        $opsi = collect($data['opsi'] ?? [])->filter()->map(fn ($v) => (int) $v)->values()->all();

        if ($produk->punyaJalurReadyStock() && filled($data['variant_id'] ?? null)) {
            $varian = ProductVariant::with('product')->findOrFail($data['variant_id']);
            $qty = (int) ($data['qty'] ?? 1);

            return [
                'kunci' => 'manual',
                'produk' => $produk,
                'varian' => $varian,
                'jalur' => FulfillmentType::READY_STOCK,
                'rincian' => $this->kalkulator->readyStock($varian, $qty),
                'masalah' => null,
                'qty' => $qty,
                'opsi' => [],
                'catatan' => null,
                'ukuran' => null,
            ];
        }

        $ukuran = collect($data['baris'] ?? [])
            ->filter(fn ($b) => filled($b['ukuran'] ?? null) && (int) ($b['jumlah'] ?? 0) > 0)
            ->map(fn ($b) => [
                'kategori' => $b['kategori'] ?? '',
                'ukuran' => $b['ukuran'],
                'jumlah' => (int) $b['jumlah'],
                'nama_punggung' => $b['nama_punggung'] ?? null,
                'nomor' => $b['nomor'] ?? null,
            ])
            ->values()
            ->all();

        if ($produk->prices->isNotEmpty()) {
            if ($ukuran === []) {
                throw new InvalidArgumentException('Isi dulu daftar ukurannya, minimal satu baris.');
            }

            $rincian = $this->kalkulator->kustomPerUkuran($produk, $ukuran, $opsi);

            return [
                'kunci' => 'manual',
                'produk' => $produk,
                'varian' => null,
                'jalur' => FulfillmentType::PREORDER,
                'rincian' => $rincian,
                'masalah' => null,
                'qty' => $rincian->qty,
                'opsi' => $opsi,
                'catatan' => null,
                'ukuran' => $ukuran,
            ];
        }

        // Produk kustom lama yang masih memakai tier harga per jumlah.
        $qty = (int) ($data['qty'] ?? 0);

        return [
            'kunci' => 'manual',
            'produk' => $produk,
            'varian' => null,
            'jalur' => FulfillmentType::PREORDER,
            'rincian' => $this->kalkulator->kustom($produk, $qty, $opsi),
            'masalah' => null,
            'qty' => $qty,
            'opsi' => $opsi,
            'catatan' => null,
            'ukuran' => null,
        ];
    }
}
