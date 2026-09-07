<?php

use App\Http\Controllers\Panel\AuthController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\KategoriController;
use App\Http\Controllers\Panel\PengaturanController;
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

        Route::middleware('izin:katalog.kelola')->group(function () {
            Route::resource('kategori', KategoriController::class)
                ->except('show')
                ->parameters(['kategori' => 'kategori']);

            Route::resource('produk', ProdukController::class)
                ->except('show')
                ->parameters(['produk' => 'produk']);

            Route::prefix('produk/{produk}')->name('produk.')->group(function () {
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
