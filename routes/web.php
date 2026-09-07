<?php

use App\Http\Controllers\Publik\BerandaController;
use App\Http\Controllers\Publik\CheckoutController;
use App\Http\Controllers\Publik\KatalogController;
use App\Http\Controllers\Publik\KeranjangController;
use App\Http\Controllers\Publik\LacakController;
use Illuminate\Support\Facades\Route;

Route::get('/', BerandaController::class)->name('beranda');
Route::get('/katalog', [KatalogController::class, 'index'])->name('katalog');
Route::get('/produk/{produk:slug}', [KatalogController::class, 'show'])->name('produk');

Route::get('/keranjang', [KeranjangController::class, 'index'])->name('keranjang');
Route::post('/keranjang', [KeranjangController::class, 'tambah'])->name('keranjang.tambah');
Route::put('/keranjang', [KeranjangController::class, 'ubah'])->name('keranjang.ubah');
Route::delete('/keranjang', [KeranjangController::class, 'hapus'])->name('keranjang.hapus');

Route::get('/checkout', [CheckoutController::class, 'form'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'proses'])->name('checkout.proses');

// Halaman pesanan pelanggan: tanpa akun, tanpa kata sandi (KEP-008).
Route::get('/lacak', [LacakController::class, 'form'])->name('lacak');
Route::post('/lacak', [LacakController::class, 'cari'])->name('lacak.cari');
Route::get('/lacak/{token}', [LacakController::class, 'show'])->name('lacak.token');
Route::post('/lacak/{token}/bukti', [LacakController::class, 'buktiTransfer'])->name('lacak.bukti');

require __DIR__.'/panel.php';
