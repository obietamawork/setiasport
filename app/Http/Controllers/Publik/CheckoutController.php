<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Services\Checkout;
use App\Enums\MetodeAmbil;
use App\Services\Keranjang;
use App\Services\Ongkir\Pengiriman;
use App\Services\Ongkir\PilihanKirim;
use App\Services\PengirimEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private Keranjang $keranjang,
        private Checkout $checkout,
        private PengirimEmail $email,
        private Pengiriman $pengiriman,
    ) {}

    public function form()
    {
        if ($this->keranjang->kosong()) {
            return redirect()->route('keranjang');
        }

        $isi = $this->keranjang->isi();

        return view('publik.checkout', [
            'isi' => $isi,
            'total' => $this->keranjang->total(),
            'adaMasalah' => $this->keranjang->adaMasalah(),
            // DP hanya masuk akal untuk pesanan kustom: barang stok diambil
            // besok, jadi tidak ada gunanya menunda pembayarannya (KEP-005).
            'bolehDp' => $isi->contains(fn ($b) => $b['jalur'] === \App\Enums\FulfillmentType::PREORDER),
            // Keranjang campuran (stok + kustom): barang stok ikut menunggu
            // sampai pesanan SELURUHNYA siap dan lunas (satu nomor pesanan,
            // satu kali serah terima -- Order::bolehDiserahkan() mensyaratkan
            // SEMUA item Siap). Teks DP yang bilang "setelah barang selesai
            // dikerjakan" membingungkan kalau sebagian isinya cuma barang
            // stok yang sebenarnya tidak "dikerjakan" apa-apa.
            'campuran' => $isi->contains(fn ($b) => $b['jalur'] === \App\Enums\FulfillmentType::PREORDER)
                && $isi->contains(fn ($b) => $b['jalur'] === \App\Enums\FulfillmentType::READY_STOCK),
            'persenDp' => (int) setting('dp_minimum_persen', 50),
            'biayaLayanan' => (int) setting('biaya_layanan', 0),
            /*
             * Dihitung dengan aturan yang sama persis dengan Order::dpMinimum():
             * dari harga SEBELUM potongan borongan (KEP-050) dan tanpa ongkir
             * (KEP-058). Angka uang muka yang tertulis di sini harus sama dengan
             * yang ditagih sesudahnya; selisih sedikit pun terbaca sebagai
             * tagihan yang diam-diam dinaikkan.
             */
            'dpMinimum' => (int) ceil(
                ((int) $isi->sum(fn ($b) => $b['rincian']?->subtotal ?? 0) + (int) setting('biaya_layanan', 0))
                * (int) setting('dp_minimum_persen', 50) / 100
            ),
            'pelanggan' => Auth::guard('pelanggan')->user(),

            // Pengiriman (KEP-058). Saat penyedia ongkirnya belum dinyalakan,
            // pelanggan tetap boleh minta dikirim: alamatnya dicatat dan
            // ongkirnya menyusul dari admin. Menyembunyikan pilihannya berarti
            // menolak pemesan luar kota, dan itu justru yang mau dihindari.
            'ongkirOtomatis' => $this->pengiriman->otomatis(),
            'gratisOngkirMulai' => $this->pengiriman->gratisMulai(),
            'beratGram' => $this->keranjang->beratGram(),
        ]);
    }

    /**
     * Pelanggan yang sudah masuk tidak perlu mengetik ulang datanya.
     * Emailnya dikunci karena itulah yang mengikat pesanan ke akunnya.
     */
    private function isiOtomatis(Request $request): void
    {
        if ($pelanggan = Auth::guard('pelanggan')->user()) {
            $request->merge([
                'email' => $pelanggan->email,
                'nama' => $request->filled('nama') ? $request->input('nama') : $pelanggan->nama,
                'no_hp' => $request->filled('no_hp') ? $request->input('no_hp') : $pelanggan->no_hp,
                'buat_akun' => null,
            ]);
        }
    }

    /**
     * Akun dibuat sebagai pendaftaran TERTUNDA, dan pelanggan tidak langsung
     * dimasukkan (KEP-021). Pesanannya sendiri tetap berjalan normal: tautan
     * bertoken di email pesanan tetap membukanya tanpa perlu akun.
     */
    private function buatkanAkun(\App\Models\Customer $pelanggan, string $password): void
    {
        if ($pelanggan->akunAktif()) {
            return;
        }

        $token = $pelanggan->siapkanKonfirmasi($password);

        $this->email->kirimKonfirmasiAkun($pelanggan, route('daftar.konfirmasi', $token));
    }

    /**
     * Cara pesanan ini diserahkan, dihitung ulang di server (KEP-009).
     *
     * Harga ongkir dari formulir tidak dipakai sama sekali: yang masuk pesanan
     * adalah tarif yang baru saja ditanyakan lagi ke penyedia. Pilihan yang
     * sudah hilang dari daftar ditolak, bukan diam-diam dianggap gratis.
     *
     * @param  array<string, mixed>  $data
     */
    private function pilihanKirim(array $data): PilihanKirim
    {
        if (($data['metode_ambil'] ?? 'AMBIL_TOKO') !== 'KIRIM') {
            return PilihanKirim::ambilDiToko();
        }

        $alamat = trim((string) ($data['alamat_kirim'] ?? ''));
        $area = trim((string) ($data['area_tujuan'] ?? ''));
        $kode = trim((string) ($data['tarif'] ?? ''));

        // Penyedia ongkirnya mati, atau tujuannya belum ketemu di daftar area.
        // Pesanannya tetap dibuat: alamatnya tercatat dan ongkirnya menyusul
        // dari admin, persis cara kerja toko sebelum ada hitungan otomatis.
        if (! $this->pengiriman->otomatis() || $area === '' || $kode === '') {
            return new PilihanKirim(MetodeAmbil::KIRIM, $alamat, $area !== '' ? $area : null);
        }

        $pilihan = $this->pengiriman->pilihan(
            $area, $this->keranjang->beratGram(), $this->keranjang->total()
        );

        // Penyedianya sedang tidak bisa menjawab. Sama seperti saat ia mati:
        // pesanan tetap jadi, ongkirnya menyusul. Menggagalkan checkout karena
        // API pihak ketiga sedang bermasalah berarti kehilangan pesanan.
        if ($pilihan === []) {
            return new PilihanKirim(MetodeAmbil::KIRIM, $alamat, $area);
        }

        $tarif = collect($pilihan)->first(fn ($t) => $t->kode() === $kode);

        if ($tarif === null) {
            throw ValidationException::withMessages([
                'tarif' => 'Pilihan pengirimannya sudah tidak berlaku. Pilih ulang kurirnya, ya.',
            ]);
        }

        return new PilihanKirim(MetodeAmbil::KIRIM, $alamat, $area, $tarif);
    }

    public function proses(Request $request): RedirectResponse
    {
        $this->isiOtomatis($request);

        if ($this->keranjang->kosong()) {
            return redirect()->route('keranjang');
        }

        if ($this->keranjang->adaMasalah()) {
            return redirect()->route('keranjang')
                ->withErrors('Ada barang yang stoknya berubah. Periksa keranjang Anda dulu.');
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            // Email wajib: ia satu-satunya kanal otomatis ke pelanggan (KEP-004),
            // sekaligus kunci untuk membuka halaman pesanan tanpa akun (KEP-008).
            'email' => ['required', 'email', 'max:150'],
            'no_hp' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'catatan' => ['nullable', 'string', 'max:500'],

            // Pengiriman (KEP-058). Alamat wajib kalau minta dikirim; kurirnya
            // hanya wajib kalau memang ada daftar tarif untuk dipilih.
            'metode_ambil' => ['nullable', 'in:AMBIL_TOKO,KIRIM'],
            'alamat_kirim' => ['nullable', 'string', 'max:500', 'required_if:metode_ambil,KIRIM'],
            'area_tujuan' => ['nullable', 'string', 'max:60'],
            'tarif' => ['nullable', 'string', 'max:60'],
            // Longgar dengan sengaja: keranjang berisi barang stok saja tidak
            // menampilkan pilihan ini, dan lunas adalah satu-satunya arti yang
            // masuk akal di sana.
            'tipe_pembayaran' => ['nullable', 'in:LUNAS,DP'],
            'setuju' => ['accepted'],

            // Akun sepenuhnya opsional. Kolomnya hanya diperiksa bila kotaknya
            // dicentang, supaya checkout tamu tidak pernah terhalang (KEP-020).
            'buat_akun' => ['nullable', 'boolean'],
            'password' => ['exclude_unless:buat_akun,1', 'required', 'confirmed', Password::min(8)],
        ], [
            'password.confirmed' => 'Ulangi kata sandi belum sama.',
            'email.required' => 'Email wajib diisi: ke sanalah tautan pesanan dan kabar progres dikirim.',
            'no_hp.regex' => 'Nomor HP hanya boleh berisi angka, spasi, tanda plus, dan strip.',
            'setuju.accepted' => 'Centang persetujuannya dulu untuk melanjutkan.',
            'alamat_kirim.required_if' => 'Tulis alamat lengkapnya. Tanpa itu paket tidak bisa dibuatkan resi.',
        ], [
            'nama' => 'nama', 'email' => 'email', 'no_hp' => 'nomor HP',
            'alamat_kirim' => 'alamat pengiriman',
        ]);

        $kirim = $this->pilihanKirim($data);

        $adaKustom = $this->keranjang->isi()
            ->contains(fn ($b) => $b['jalur'] === \App\Enums\FulfillmentType::PREORDER);

        $tipe = (($data['tipe_pembayaran'] ?? 'LUNAS') === 'DP' && $adaKustom) ? 'DP' : 'LUNAS';

        try {
            $order = $this->checkout->proses($data, $tipe, $kirim);
        } catch (\RuntimeException $e) {
            // Umumnya stok habis diambil pembeli lain di detik terakhir.
            throw ValidationException::withMessages(['nama' => $e->getMessage()]);
        }

        $this->email->kirim($order, 'order-dibuat');

        $pesan = 'Pesanan Anda dibuat. Rincian pembayaran ada di bawah.';

        if ($kirim->dikirim() && $kirim->ongkir() === 0 && ! $this->pengiriman->otomatis()) {
            $pesan .= ' Ongkos kirimnya kami hitung dulu setelah paket ditimbang, lalu muncul di tagihan ini.';
        }

        if ($request->boolean('buat_akun')) {
            $this->buatkanAkun($order->customer, $request->string('password')->toString());
            $pesan .= ' Kami juga mengirim tautan konfirmasi akun ke email Anda.';
        }

        return redirect()->route('lacak.token', $order->access_token)->with('sukses', $pesan);
    }
}
