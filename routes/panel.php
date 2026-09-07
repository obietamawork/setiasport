<?php

use App\Http\Controllers\Panel\AuthController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\KategoriController;
use App\Http\Controllers\Panel\AntrianController;
use App\Http\Controllers\Panel\GambarProdukController;
use App\Http\Controllers\Panel\MockupController;
use App\Http\Controllers\Panel\PembayaranController;
use App\Http\Controllers\Panel\PenyesuaianController;
use App\Http\Controllers\Panel\PengaturanController;
use App\Http\Controllers\Panel\PesananController;
use App\Http\Controllers\Panel\SerahTerimaController;
use App\Http\Controllers\Panel\WorksheetController;
use App\Http\Controllers\Panel\ProdukController;
use App\Http\Controllers\Panel\ProdukKomponenController;
use App\Http\Controllers\Panel\StokController;
use Illuminate\Support\Facades\Route;

Route::prefix('panel')->name('panel.')->group(function () {

    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'form'])->name('login');
        Route::post('/login', [AuthController::class, 'masuk'])->name('masuk');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/keluar', [AuthController::class, 'keluar'])->name('keluar');
        Route::get('/', DashboardController::class)->name('dashboard');

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

            Route::post('batal', [PesananController::class, 'batalkan'])
                ->middleware('izin:order.batal')->name('batal');

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

            Route::post('serah-terima', [SerahTerimaController::class, 'proses'])
                ->middleware('izin:serah_terima.proses')->name('serah-terima');
        });

        Route::get('serah-terima', [SerahTerimaController::class, 'form'])
            ->middleware('izin:serah_terima.proses')->name('serah-terima');

        Route::middleware('izin:katalog.kelola')->group(function () {
            Route::resource('kategori', KategoriController::class)
                ->except('show')
                ->parameters(['kategori' => 'kategori']);

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

                Route::post('opsi/{opsi}/nilai', [ProdukKomponenController::class, 'simpanNilai'])->name('nilai.simpan');
                Route::delete('opsi/{opsi}/nilai/{nilai}', [ProdukKomponenController::class, 'hapusNilai'])->name('nilai.hapus');
            });
        });

        Route::middleware('izin:stok.kelola')->group(function () {
            Route::get('stok', [StokController::class, 'index'])->name('stok.index');
            Route::put('stok', [StokController::class, 'update'])->name('stok.update');
        });

        Route::middleware('izin:pengaturan.kelola')->group(function () {
            Route::get('pengaturan', [PengaturanController::class, 'index'])->name('pengaturan.index');
            Route::put('pengaturan', [PengaturanController::class, 'update'])->name('pengaturan.update');
        });
    });
});
