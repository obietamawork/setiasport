<?php

use App\Http\Controllers\Publik\AkunController;
use App\Http\Controllers\Publik\BerandaController;
use App\Http\Controllers\Publik\CheckoutController;
use App\Http\Controllers\Publik\KatalogController;
use App\Http\Controllers\Publik\KeranjangController;
use App\Http\Controllers\Publik\LacakController;
use App\Http\Controllers\Publik\PembayaranOnlineController;
use App\Http\Controllers\Publik\PesananItemController;
use App\Http\Controllers\Publik\PesananSayaController;
use App\Http\Controllers\Webhook\MidtransController;
use App\Http\Controllers\Webhook\ResendController;
use Illuminate\Support\Facades\Route;

Route::get('/', BerandaController::class)->name('beranda');
Route::get('/katalog', [KatalogController::class, 'index'])->name('katalog');
Route::get('/produk/{produk:slug}', [KatalogController::class, 'show'])->name('produk');

Route::get('/keranjang', [KeranjangController::class, 'index'])->name('keranjang');
Route::post('/keranjang', [KeranjangController::class, 'tambah'])->name('keranjang.tambah');
Route::post('/keranjang/kustom/{produk:slug}', [KeranjangController::class, 'tambahKustom'])
    ->name('keranjang.kustom');
Route::put('/keranjang', [KeranjangController::class, 'ubah'])->name('keranjang.ubah');
Route::delete('/keranjang', [KeranjangController::class, 'hapus'])->name('keranjang.hapus');

Route::get('/checkout', [CheckoutController::class, 'form'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'proses'])->name('checkout.proses');

/*
 * Akun pelanggan: jalur kedua, bukan pengganti (KEP-020). Tautan bertoken di
 * bawah tetap bekerja penuh untuk pelanggan yang tidak mau membuat akun.
 */
Route::middleware('guest:pelanggan')->group(function () {
    Route::get('/masuk', [AkunController::class, 'formMasuk'])->name('masuk');
    Route::post('/masuk', [AkunController::class, 'masuk'])->name('masuk.proses');
    Route::get('/daftar', [AkunController::class, 'formDaftar'])->name('daftar');
    Route::post('/daftar', [AkunController::class, 'daftar'])->name('daftar.proses');
});

Route::middleware('auth:pelanggan')->group(function () {
    Route::get('/pesanan-saya', PesananSayaController::class)->name('pesanan-saya');
    Route::post('/keluar', [AkunController::class, 'keluar'])->name('keluar');
});

// Halaman pesanan pelanggan: tanpa akun, tanpa kata sandi (KEP-008).
Route::get('/lacak', [LacakController::class, 'form'])->name('lacak');
Route::post('/lacak', [LacakController::class, 'cari'])->name('lacak.cari');
Route::get('/lacak/{token}', [LacakController::class, 'show'])->name('lacak.token');
Route::post('/lacak/{token}/bukti', [LacakController::class, 'buktiTransfer'])->name('lacak.bukti');

Route::post('/lacak/{token}/bayar', [PembayaranOnlineController::class, 'mulai'])->name('lacak.bayar');

Route::prefix('/lacak/{token}/item/{item}')->name('lacak.item.')->group(function () {
    Route::post('roster', [PesananItemController::class, 'simpanRoster'])->name('roster');
    Route::post('desain', [PesananItemController::class, 'unggahDesain'])->name('desain');
    Route::post('setujui', [PesananItemController::class, 'setujuiDesain'])->name('setujui');
    Route::post('revisi', [PesananItemController::class, 'mintaRevisi'])->name('revisi');
});

/*
 * Webhook. Pengirimnya bukan browser pelanggan, jadi tidak ada sesi maupun
 * token CSRF; keabsahannya sepenuhnya bergantung pada pemeriksaan tanda tangan
 * di dalam masing-masing controller.
 */
Route::post('/webhook/midtrans', MidtransController::class)->name('webhook.midtrans');
Route::post('/webhook/resend', ResendController::class)->name('webhook.resend');

require __DIR__.'/panel.php';
