<?php

namespace App\Services;

use App\Enums\FulfillmentType;
use App\Models\Category;
use App\Models\Pekerja;
use App\Models\Portofolio;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Testimoni;
use App\Models\User;

/**
 * Apa yang masih kurang sebelum website dibuka untuk umum.
 *
 * Pertanyaan ini muncul di tiap pertemuan dengan pemilik toko, dan selama ini
 * dijawab dari ingatan orang yang membangun sistemnya. Jawaban dari ingatan
 * selalu ketinggalan: satu pengaturan diisi minggu lalu, satu foto diunggah
 * kemarin, dan daftar di kepala tidak ikut berubah.
 *
 * Semua yang diperiksa di sini DIHITUNG dari data, bukan dari kolom "sudah
 * selesai" yang dicentang manual. Centang manual selalu berbohong lebih cepat
 * daripada datanya.
 *
 * Yang TIDAK diperiksa di sini: hal yang berada di luar aplikasi (domain sudah
 * dibeli, cron sudah dipasang penyedia hosting). Itu urusan docs/12-deploy.md;
 * halaman ini hanya menjawab apa yang bisa dilihat sistem sendiri.
 */
class KesiapanTayang
{
    /**
     * @return list<array{
     *     kunci: string, judul: string, ikon: string, catatan: string,
     *     isi: list<array{judul: string, siap: bool, wajib: bool, kenapa: string,
     *         keadaan: string, rute: ?string, tautan: ?string}>
     * }>
     */
    public function bagian(): array
    {
        return [
            $this->toko(),
            $this->pembayaran(),
            $this->katalog(),
            $this->profil(),
            $this->pengiriman(),
            $this->orang(),
        ];
    }

    /** Berapa yang wajib sudah beres, dipakai sebagai angka besar di halaman. */
    public function ringkasan(): array
    {
        $wajib = collect($this->bagian())
            ->flatMap(fn (array $b) => $b['isi'])
            ->filter(fn (array $i) => $i['wajib']);

        return [
            'siap' => $wajib->where('siap', true)->count(),
            'total' => $wajib->count(),
            'belum' => $wajib->where('siap', false)->values()->all(),
        ];
    }

    private function toko(): array
    {
        $jamBuka = (string) Setting::ambil('toko_jam_buka', '');

        return [
            'kunci' => 'toko',
            'judul' => 'Identitas toko',
            'ikon' => 'toko',
            'catatan' => 'Tampil di halaman depan, halaman pesanan, dan semua email ke pelanggan.',
            'isi' => [
                $this->periksa(
                    'Nama toko',
                    filled(Setting::ambil('toko_nama')),
                    'Dipakai di judul halaman, email, dan invoice.',
                    'panel.pengaturan.index',
                ),
                $this->periksa(
                    'Alamat toko',
                    filled(Setting::ambil('toko_alamat')),
                    'Pelanggan datang ke sini untuk mengambil barangnya. Alamat yang salah berarti '
                        .'orang tersesat sambil membawa uang pelunasan.',
                    'panel.pengaturan.index',
                ),
                $this->periksa(
                    'Jam buka',
                    filled($jamBuka),
                    'Tanpa ini pelanggan datang di jam tutup, lalu menyalahkan toko.',
                    'panel.pengaturan.index',
                ),
                $this->periksa(
                    'Nomor WhatsApp toko',
                    filled(Setting::ambil('toko_telepon')),
                    'Satu-satunya jalan pelanggan bertanya sebelum memesan. Kalau kosong, tombol '
                        .'bantuan di halaman pemesanan tidak muncul sama sekali.',
                    'panel.pengaturan.index',
                ),
                $this->periksa(
                    'Alamat konveksi',
                    filled(Setting::ambil('konveksi_alamat')),
                    'Dipakai di halaman depan supaya pemesan tahu pekerjaannya dikerjakan sendiri, '
                        .'bukan dilempar ke pihak lain.',
                    'panel.pengaturan.index',
                    wajib: false,
                ),
            ],
        ];
    }

    private function pembayaran(): array
    {
        return [
            'kunci' => 'pembayaran',
            'judul' => 'Uang masuk',
            'ikon' => 'uang',
            'catatan' => 'Angka-angka ini tampil apa adanya di halaman pesanan pelanggan. Periksa dua kali.',
            'isi' => [
                $this->periksa(
                    'Rekening bank',
                    filled(Setting::ambil('bank_nama')) && filled(Setting::ambil('bank_rekening'))
                        && filled(Setting::ambil('bank_atas_nama')),
                    'Nomor rekening yang salah berarti uang pelanggan masuk ke orang lain, dan toko '
                        .'yang menanggung.',
                    'panel.pengaturan.index',
                ),
                $this->periksa(
                    'Gambar QRIS',
                    filled(Setting::ambil('qris_path')),
                    'Tanpa QRIS, pelanggan hanya bisa transfer manual. Bukan penghalang, tapi '
                        .'kebanyakan orang sekarang membayar dengan memindai.',
                    'panel.pengaturan.index',
                    wajib: false,
                ),
                $this->periksa(
                    'Uang muka minimum sudah ditentukan',
                    (int) Setting::ambil('dp_minimum_persen', 0) > 0,
                    'Dipakai menghitung tagihan pertama pesanan kustom.',
                    'panel.pengaturan.index',
                ),
                $this->periksa(
                    'Batas waktu bayar',
                    (int) Setting::ambil('batas_bayar_jam', 0) > 0,
                    'Pesanan yang tidak dibayar sampai batas ini dibatalkan otomatis, dan stoknya '
                        .'dilepas supaya tidak terkunci selamanya.',
                    'panel.pengaturan.index',
                ),
            ],
        ];
    }

    private function katalog(): array
    {
        $tayang = Product::where('aktif', true)->count();
        $tanpaFoto = Product::where('aktif', true)->whereDoesntHave('images')->count();

        $kustomTanpaHarga = Product::where('aktif', true)
            ->whereIn('fulfillment_type', [FulfillmentType::PREORDER->value, FulfillmentType::BOTH->value])
            ->whereDoesntHave('priceTiers')
            ->whereDoesntHave('prices')
            ->count();

        return [
            'kunci' => 'katalog',
            'judul' => 'Katalog',
            'ikon' => 'produk',
            'catatan' => 'Yang dilihat dan dihitung sendiri oleh pelanggan sebelum ia memesan.',
            'isi' => [
                $this->periksa(
                    'Ada produk yang tayang',
                    $tayang > 0,
                    'Katalog kosong berarti tidak ada yang bisa dipesan.',
                    'panel.produk.index',
                    keadaan: $tayang.' produk tayang',
                ),
                $this->periksa(
                    'Semua produk tayang punya foto',
                    $tanpaFoto === 0,
                    'Produk tanpa foto tetap tayang, tapi jarang sekali dibuka. Orang membeli '
                        .'barang yang bisa dilihat.',
                    'panel.produk.index',
                    keadaan: $tanpaFoto === 0 ? 'semua sudah berfoto' : $tanpaFoto.' produk belum berfoto',
                    tautan: ['saring' => 'belum-lengkap'],
                ),
                $this->periksa(
                    'Semua produk kustom punya harga',
                    $kustomTanpaHarga === 0,
                    'Tanpa harga, pelanggan tidak bisa menghitung biayanya sendiri dan harus '
                        .'bertanya ke admin dulu. Itu mengembalikan pekerjaan yang ingin dihapus '
                        .'sistem ini.',
                    'panel.produk.index',
                    keadaan: $kustomTanpaHarga === 0 ? 'semua sudah berharga' : $kustomTanpaHarga.' produk belum berharga',
                    tautan: ['saring' => 'belum-lengkap'],
                ),
                $this->periksa(
                    'Kategori sudah ditata',
                    Category::where('aktif', true)->exists(),
                    'Kategori adalah jalan pelanggan menelusuri katalog. Urutannya di panel sama '
                        .'dengan urutan yang mereka lihat.',
                    'panel.kategori.index',
                    wajib: false,
                ),
            ],
        ];
    }

    private function profil(): array
    {
        $contoh = Portofolio::where('contoh', true)->count() + Testimoni::where('contoh', true)->count();
        $portofolioAsli = Portofolio::where('contoh', false)->count();
        $testimoniAsli = Testimoni::where('contoh', false)->count();

        return [
            'kunci' => 'profil',
            'judul' => 'Halaman depan',
            'ikon' => 'portofolio',
            'catatan' => 'Yang membuat sekolah dan instansi percaya sebelum menelepon.',
            'isi' => [
                $this->periksa(
                    'Data contoh sudah dihapus',
                    $contoh === 0,
                    'Portofolio dan testimoni contoh adalah karangan. Kalau ikut tayang saat website '
                        .'dibuka, itu sama dengan memasang ulasan palsu di depan pelanggan.',
                    'panel.portofolio.index',
                    keadaan: $contoh === 0 ? 'bersih' : $contoh.' data contoh masih tayang',
                ),
                $this->periksa(
                    'Ada portofolio asli',
                    $portofolioAsli > 0,
                    'Pemesan seragam memilih berdasarkan bukti pernah mengerjakan yang serupa. '
                        .'Ini isi yang paling sering dibaca.',
                    'panel.portofolio.index',
                    keadaan: $portofolioAsli.' pekerjaan',
                ),
                $this->periksa(
                    'Ada testimoni asli',
                    $testimoniAsli > 0,
                    'Cukup tiga yang benar-benar pernah mengatakannya. Diketik ulang dari WhatsApp '
                        .'atau percakapan di toko.',
                    'panel.testimoni.index',
                    keadaan: $testimoniAsli.' testimoni',
                    wajib: false,
                ),
                $this->periksa(
                    'Cerita usaha sudah ditulis',
                    filled(Setting::ambil('tentang_lengkap')),
                    'Bagian "tentang kami" di halaman depan. Tulis dengan bahasa toko sendiri, '
                        .'bukan bahasa perusahaan.',
                    'panel.pengaturan.index',
                    wajib: false,
                ),
            ],
        ];
    }

    private function pengiriman(): array
    {
        $penyedia = app(\App\Services\Ongkir\PenyediaOngkir::class);

        $tanpaBerat = Product::where('aktif', true)
            ->where(fn ($q) => $q->whereNull('berat_gram')->orWhere('berat_gram', 0))
            ->count();

        return [
            'kunci' => 'pengiriman',
            'judul' => 'Pengiriman',
            'ikon' => 'kirim',
            'catatan' => 'Boleh dibuka tanpa ini: ongkirnya tinggal dihitung admin seperti biasa.',
            'isi' => [
                $this->periksa(
                    'Daftar jasa kirim diisi',
                    filled(Setting::ambil('jasa_kirim_daftar')),
                    'Dipakai saat mencatat resi. Isi yang benar-benar dipakai toko saja.',
                    'panel.pengaturan.index',
                ),
                $this->periksa(
                    'Berat produk diisi',
                    $tanpaBerat === 0,
                    'Berat dipakai menghitung ongkir. Yang kosong memakai berat bawaan dari '
                        .'Pengaturan, dan itu selalu meleset sedikit.',
                    'panel.produk.index',
                    keadaan: $tanpaBerat === 0 ? 'semua terisi' : $tanpaBerat.' produk memakai berat bawaan',
                    wajib: false,
                ),
                $this->periksa(
                    'Hitung ongkir otomatis',
                    $penyedia->aktif(),
                    'Kalau menyala, pelanggan memilih kurirnya sendiri saat memesan dan ongkirnya '
                        .'langsung masuk tagihan. Kalau mati, alamatnya tetap tercatat dan '
                        .'ongkirnya diisi admin setelah paket ditimbang.',
                    null,
                    keadaan: $penyedia->aktif() ? 'menyala' : 'mati, ongkir diisi admin',
                    wajib: false,
                ),
            ],
        ];
    }

    private function orang(): array
    {
        $staf = User::count();
        $pekerja = Pekerja::where('aktif', true)->count();

        return [
            'kunci' => 'orang',
            'judul' => 'Orang yang memakai sistem',
            'ikon' => 'pekerja',
            'catatan' => 'Akun panel untuk yang mengurus pesanan, PIN untuk yang memegang jarum.',
            'isi' => [
                $this->periksa(
                    'Akun staf sudah dibuat',
                    $staf > 1,
                    'Kalau hanya ada satu akun, semua orang memakai akun yang sama dan catatan '
                        .'"siapa memverifikasi apa" jadi tidak berarti.',
                    null,
                    keadaan: $staf.' akun panel',
                ),
                $this->periksa(
                    'Pekerja konveksi terdaftar',
                    $pekerja > 0,
                    'Mereka menandai tahap selesai lewat QR dan PIN. Tanpa terdaftar, tidak ada '
                        .'yang bisa menaikkan status produksi selain admin.',
                    'panel.pekerja.index',
                    keadaan: $pekerja.' pekerja aktif',
                ),
                $this->periksa(
                    'Kata sandi bawaan sudah diganti',
                    ! User::where('email', 'owner@setiasport.test')->exists(),
                    'Akun contoh dari pemasangan awal memakai kata sandi yang tertulis di '
                        .'dokumentasi. Hapus atau ganti emailnya sebelum website dibuka.',
                    null,
                    keadaan: User::where('email', 'owner@setiasport.test')->exists()
                        ? 'akun contoh masih ada'
                        : 'sudah diganti',
                ),
            ],
        ];
    }

    /**
     * @param  array<string, string>|null  $tautan
     * @return array<string, mixed>
     */
    private function periksa(
        string $judul,
        bool $siap,
        string $kenapa,
        ?string $rute,
        ?string $keadaan = null,
        bool $wajib = true,
        ?array $tautan = null,
    ): array {
        return [
            'judul' => $judul,
            'siap' => $siap,
            'wajib' => $wajib,
            'kenapa' => $kenapa,
            'keadaan' => $keadaan ?? ($siap ? 'sudah diisi' : 'belum diisi'),
            'rute' => $rute,
            'tautan' => $tautan,
        ];
    }
}
