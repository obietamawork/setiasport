<?php

use App\Http\Controllers\BuktiBayarController;
use App\Http\Controllers\Panel\AktivitasController;
use App\Http\Controllers\Panel\AkunSayaController;
use App\Http\Controllers\Panel\AuthController;
use App\Http\Controllers\Panel\BahanController;
use App\Http\Controllers\Panel\BantuanController;
use App\Http\Controllers\Panel\ContohProfilController;
use App\Http\Controllers\Panel\CsController;
use App\Http\Controllers\Panel\TemplateBalasanController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\InvoiceController;
use App\Http\Controllers\Panel\IsiPesananController;
use App\Http\Controllers\Panel\KategoriController;
use App\Http\Controllers\Panel\KesiapanController;
use App\Http\Controllers\Panel\AntrianController;
use App\Http\Controllers\Panel\GambarProdukController;
use App\Http\Controllers\Panel\LaporanController;
use App\Http\Controllers\Panel\MockupController;
use App\Http\Controllers\Panel\PekerjaController;
use App\Http\Controllers\Panel\PembatalanController;
use App\Http\Controllers\Panel\PenggunaController;
use App\Http\Controllers\Panel\PembayaranController;
use App\Http\Controllers\Panel\PenyesuaianController;
use App\Http\Controllers\Panel\PengaturanController;
use App\Http\Controllers\Panel\PengirimanController;
use App\Http\Controllers\Panel\PortofolioController;
use App\Http\Controllers\Panel\RollCetakController;
use App\Http\Controllers\Panel\PesananController;
use App\Http\Controllers\Panel\PesananManualController;
use App\Http\Controllers\Panel\SerahTerimaController;
use App\Http\Controllers\Panel\WorksheetController;
use App\Http\Controllers\Panel\ProdukController;
use App\Http\Controllers\Panel\ProdukHargaController;
use App\Http\Controllers\Panel\ProdukKomponenController;
use App\Http\Controllers\Panel\StokController;
use App\Http\Controllers\Panel\TestimoniController;
use Illuminate\Support\Facades\Route;

Route::prefix('panel')->name('panel.')->group(function () {

    Route::middleware('guest:web')->group(function () {
        Route::get('/login', [AuthController::class, 'form'])->name('login');
        Route::post('/login', [AuthController::class, 'masuk'])->name('masuk');
    });

    // Guard disebut eksplisit. Panel tidak boleh bergantung pada guard mana
    // yang kebetulan menjadi bawaan, karena pelanggan kini punya guard sendiri.
    // akun.layak: akun nonaktif terputus dan akun bersandi sementara diarahkan
    // ke Akun saya pada SETIAP permintaan, bukan hanya saat masuk (KEP-084).
    Route::middleware(['auth:web', 'akun.layak'])->group(function () {
        Route::post('/keluar', [AuthController::class, 'keluar'])->name('keluar');
        Route::get('/', DashboardController::class)->name('dashboard');

        // Akun saya: semua staf, tanpa izin khusus.
        Route::get('akun', [AkunSayaController::class, 'show'])->name('akun');
        Route::put('akun', [AkunSayaController::class, 'updateProfil'])->name('akun.profil');
        Route::put('akun/sandi', [AkunSayaController::class, 'updateSandi'])
            ->middleware('throttle:10,1')->name('akun.sandi');

        Route::middleware('izin:pengguna.kelola')->prefix('pengguna')->name('pengguna.')->group(function () {
            Route::get('/', [PenggunaController::class, 'index'])->name('index');
            Route::post('/', [PenggunaController::class, 'store'])->name('simpan');
            Route::put('{pengguna}', [PenggunaController::class, 'update'])->name('update');
            Route::post('{pengguna}/aktif', [PenggunaController::class, 'aktif'])->name('aktif');
            Route::post('{pengguna}/sandi', [PenggunaController::class, 'aturUlangSandi'])->name('sandi');
        });

        // Bantuan dibuka semua orang yang boleh masuk panel, tanpa izin khusus:
        // yang paling butuh penjelasan justru yang perannya paling sempit.
        Route::get('/bantuan', BantuanController::class)->name('bantuan');

        // Kesiapan tayang menyebut pengaturan toko dan isi katalog, jadi hanya
        // untuk yang memang mengurusnya.
        Route::get('/kesiapan', KesiapanController::class)
            ->middleware('izin:pengaturan.kelola')->name('kesiapan');

        // Pesanan yang diketik admin untuk pembeli offline (KEP-054).
        Route::middleware('izin:order.kelola')->group(function () {
            Route::get('pesanan/baru', [PesananManualController::class, 'form'])->name('pesanan.baru');
            Route::post('pesanan/baru', [PesananManualController::class, 'simpan'])->name('pesanan.baru.simpan');
        });

        Route::middleware('izin:order.lihat')->group(function () {
            Route::get('pesanan', [PesananController::class, 'index'])->name('pesanan.index');
            Route::get('pesanan/{pesanan}', [PesananController::class, 'show'])->name('pesanan.show');
            Route::get('pesanan/{pesanan}/worksheet', WorksheetController::class)->name('pesanan.worksheet');
        });

        // Antrian kerja per divisi. Izin diperiksa per tahap di dalam controller,
        // karena satu orang bisa memegang beberapa divisi sekaligus (docs/04).
        Route::prefix('antrian')->name('antrian.')->group(function () {
            Route::get('/', [AntrianController::class, 'index'])->name('index');
            Route::get('{tahap}', [AntrianController::class, 'tahap'])->name('tahap');
            Route::post('item/{item}/mulai', [AntrianController::class, 'mulai'])->name('mulai');
            Route::post('item/{item}/selesai', [AntrianController::class, 'selesai'])->name('selesai');
        });

        Route::post('item/{item}/mockup', [MockupController::class, 'store'])
            ->middleware('izin:produksi.desain')->name('mockup.simpan');

        Route::prefix('pesanan/{pesanan}')->name('pesanan.')->group(function () {
            Route::post('item/{item}/maju', [PesananController::class, 'majukanItem'])
                ->middleware('izin:order.kelola')->name('item.maju');

            // Tambah atau kurangi isi pesanan yang sedang berjalan (KEP-044).
            Route::post('item/{item}/jumlah', [IsiPesananController::class, 'ubah'])
                ->middleware('izin:order.kelola')->name('item.jumlah');

            Route::post('batal', [PesananController::class, 'batalkan'])
                ->middleware('izin:order.batal')->name('batal');

            Route::post('hangus', [PesananController::class, 'hanguskan'])
                ->middleware('izin:order.batal')->name('hangus');

            // Penanda reseller (KEP-046) dan lembar invoice.
            Route::post('reseller', [PesananController::class, 'tandaiReseller'])
                ->middleware('izin:order.kelola')->name('reseller');
            Route::get('invoice', InvoiceController::class)
                ->middleware('izin:order.lihat')->name('invoice');

            Route::middleware('izin:order.batal')->group(function () {
                Route::post('pembatalan/{pembatalan}/setujui', [PembatalanController::class, 'setujui'])
                    ->name('pembatalan.setujui');
                Route::post('pembatalan/{pembatalan}/tolak', [PembatalanController::class, 'tolak'])
                    ->name('pembatalan.tolak');
            });

            Route::post('penyesuaian', [PenyesuaianController::class, 'store'])
                ->middleware('izin:order.penyesuaian')->name('penyesuaian.simpan');
            Route::delete('penyesuaian/{penyesuaian}', [PenyesuaianController::class, 'destroy'])
                ->middleware('izin:order.penyesuaian')->name('penyesuaian.hapus');

            Route::post('pembayaran', [PembayaranController::class, 'catat'])
                ->middleware('izin:pembayaran.catat')->name('pembayaran.catat');
            Route::post('pembayaran/{pembayaran}/verifikasi', [PembayaranController::class, 'verifikasi'])
                ->middleware('izin:pembayaran.verifikasi')->name('pembayaran.verifikasi');
            Route::post('pembayaran/{pembayaran}/tolak', [PembayaranController::class, 'tolak'])
                ->middleware('izin:pembayaran.verifikasi')->name('pembayaran.tolak');
            Route::get('pembayaran/{pembayaran}/bukti', [BuktiBayarController::class, 'lihatStaf'])
                ->middleware('izin:order.lihat')->name('pembayaran.bukti');

            Route::post('serah-terima', [SerahTerimaController::class, 'proses'])
                ->middleware('izin:serah_terima.proses')->name('serah-terima');

            // Pengiriman memakai izin yang sama dengan serah terima: keduanya
            // sama-sama "barang keluar dari toko" (KEP-026).
            Route::middleware('izin:serah_terima.proses')->group(function () {
                Route::post('pengiriman', [PengirimanController::class, 'atur'])->name('pengiriman.atur');
                Route::post('pengiriman/kirim', [PengirimanController::class, 'kirim'])->name('pengiriman.kirim');
                Route::post('pengiriman/diterima', [PengirimanController::class, 'diterima'])->name('pengiriman.diterima');
            });
        });

        Route::get('serah-terima', [SerahTerimaController::class, 'form'])
            ->middleware('izin:serah_terima.proses')->name('serah-terima');

        // Roll kertas printing (KEP-051): satu roll, beberapa pesanan.
        Route::middleware('izin:produksi.print')->prefix('roll')->name('roll.')->group(function () {
            Route::post('/', [RollCetakController::class, 'store'])->name('simpan');
            Route::get('{roll}', [RollCetakController::class, 'show'])->name('show');
        });

        // Pekerja konveksi yang memindai QR lembar kerja (KEP-047).
        Route::middleware('izin:pengguna.kelola')->prefix('pekerja')->name('pekerja.')->group(function () {
            Route::get('/', [PekerjaController::class, 'index'])->name('index');
            Route::post('/', [PekerjaController::class, 'store'])->name('simpan');
            Route::put('{pekerja}', [PekerjaController::class, 'update'])->name('update');
            Route::post('{pekerja}/nonaktif', [PekerjaController::class, 'nonaktif'])->name('nonaktif');
        });

        Route::middleware('izin:katalog.kelola')->group(function () {
            Route::resource('kategori', KategoriController::class)
                ->except('show')
                ->parameters(['kategori' => 'kategori']);

            // Urutan kategori digeser satu langkah dari daftarnya, bukan dengan
            // mengetik angka di formulir dan menebak hasilnya.
            Route::post('kategori/{kategori}/pindah', [KategoriController::class, 'pindah'])
                ->name('kategori.pindah');

            // Kamus bahan lintas produk (KEP-072).
            Route::resource('bahan', BahanController::class)
                ->except('show')
                ->parameters(['bahan' => 'bahan']);

            Route::resource('produk', ProdukController::class)
                ->except('show')
                ->parameters(['produk' => 'produk']);

            Route::prefix('produk/{produk}')->name('produk.')->group(function () {
                Route::post('gambar', [GambarProdukController::class, 'store'])->name('gambar.simpan');
                Route::post('gambar/{gambar}/utama', [GambarProdukController::class, 'utama'])->name('gambar.utama');
                Route::delete('gambar/{gambar}', [GambarProdukController::class, 'destroy'])->name('gambar.hapus');

                Route::post('varian', [ProdukKomponenController::class, 'simpanVarian'])->name('varian.simpan');
                Route::delete('varian/{varian}', [ProdukKomponenController::class, 'hapusVarian'])->name('varian.hapus');

                Route::post('tier', [ProdukKomponenController::class, 'simpanTier'])->name('tier.simpan');
                Route::delete('tier/{tier}', [ProdukKomponenController::class, 'hapusTier'])->name('tier.hapus');

                Route::post('opsi', [ProdukKomponenController::class, 'simpanOpsi'])->name('opsi.simpan');
                Route::delete('opsi/{opsi}', [ProdukKomponenController::class, 'hapusOpsi'])->name('opsi.hapus');

                // Matriks harga, panduan ukuran, dan tambahan ukuran besar (KEP-052).
                Route::post('matriks', [ProdukHargaController::class, 'simpanMatriks'])->name('matriks.simpan');
                Route::post('panduan', [ProdukHargaController::class, 'simpanPanduan'])->name('panduan.simpan');
                Route::post('tambahan-ukuran', [ProdukHargaController::class, 'simpanTambahanUkuran'])
                    ->name('tambahan.simpan');
                Route::delete('tambahan-ukuran/{tambahan}', [ProdukHargaController::class, 'hapusTambahanUkuran'])
                    ->name('tambahan.hapus');

                Route::post('opsi/{opsi}/nilai', [ProdukKomponenController::class, 'simpanNilai'])->name('nilai.simpan');
                Route::delete('opsi/{opsi}/nilai/{nilai}', [ProdukKomponenController::class, 'hapusNilai'])->name('nilai.hapus');
            });
        });

        // Chat CS (KEP-030). Template didaftarkan lebih dulu supaya "template"
        // tidak ditangkap sebagai nomor percakapan.
        Route::middleware('izin:cs.balas')->prefix('cs')->name('cs.')->group(function () {
            Route::resource('template', TemplateBalasanController::class)
                ->only(['index', 'store', 'edit', 'update', 'destroy'])
                ->parameters(['template' => 'template']);

            Route::post('auth', [CsController::class, 'otorisasi'])->name('auth');
            Route::get('ringkasan', [CsController::class, 'ringkasan'])->name('ringkasan');
            Route::get('daftar', [CsController::class, 'daftar'])->name('daftar');
            Route::post('hadir', [CsController::class, 'hadir'])->name('hadir');
            Route::get('/', [CsController::class, 'index'])->name('index');
            Route::get('{percakapan}', [CsController::class, 'index'])->whereNumber('percakapan')->name('show');
            Route::get('{percakapan}/detail', [CsController::class, 'detail'])->whereNumber('percakapan')->name('detail');
            Route::get('{percakapan}/pesan', [CsController::class, 'pesanBaru'])->whereNumber('percakapan')->name('pesan');
            Route::post('{percakapan}/balas', [CsController::class, 'balas'])->whereNumber('percakapan')->name('balas');
            Route::post('{percakapan}/tutup', [CsController::class, 'tutup'])->whereNumber('percakapan')->name('tutup');
            Route::post('{percakapan}/tautkan', [CsController::class, 'tautkan'])->whereNumber('percakapan')->name('tautkan');
            Route::get('{percakapan}/lampiran/{pesan}', [CsController::class, 'lampiran'])->whereNumber('percakapan')->name('lampiran');
        });

        // Profil usaha: halaman depan bercerita lebih dulu, katalog belakangan (KEP-024).
        Route::middleware('izin:profil.kelola')->group(function () {
            Route::resource('portofolio', PortofolioController::class)
                ->except('show')
                ->parameters(['portofolio' => 'portofolio']);

            Route::resource('testimoni', TestimoniController::class)
                ->except('show')
                ->parameters(['testimoni' => 'testimoni']);

            Route::delete('profil/contoh', [ContohProfilController::class, 'hapus'])->name('profil.contoh.hapus');
        });

        Route::middleware('izin:stok.kelola')->group(function () {
            Route::get('stok', [StokController::class, 'index'])->name('stok.index');
            Route::put('stok', [StokController::class, 'update'])->name('stok.update');
        });

        Route::get('laporan', LaporanController::class)
            ->middleware('izin:laporan.lihat')->name('laporan');

        Route::middleware('izin:pengaturan.kelola')->group(function () {
            Route::get('pengaturan', [PengaturanController::class, 'index'])->name('pengaturan.index');
            Route::put('pengaturan', [PengaturanController::class, 'update'])->name('pengaturan.update');
            Route::get('pengaturan/biteship/area', [PengaturanController::class, 'cariAreaBiteship'])
                ->name('pengaturan.biteship-area');
            Route::post('pengaturan/qris', [PengaturanController::class, 'unggahQris'])->name('pengaturan.qris');
            Route::delete('pengaturan/qris', [PengaturanController::class, 'hapusQris'])->name('pengaturan.qris.hapus');
            Route::get('aktivitas', AktivitasController::class)->name('aktivitas');
        });
    });
});
