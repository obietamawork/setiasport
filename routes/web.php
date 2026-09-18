<?php

use App\Http\Controllers\BuktiBayarController;
use App\Http\Controllers\Publik\AkunController;
use App\Http\Controllers\Publik\BerandaController;
use App\Http\Controllers\Publik\CheckoutController;
use App\Http\Controllers\Publik\CsController;
use App\Http\Controllers\Publik\KatalogController;
use App\Http\Controllers\Publik\KeranjangController;
use App\Http\Controllers\Publik\KustomController;
use App\Http\Controllers\Publik\LacakController;
use App\Http\Controllers\Publik\PembatalanController;
use App\Http\Controllers\Publik\PesananItemController;
use App\Http\Controllers\Publik\PesananSayaController;
use App\Http\Controllers\Webhook\ResendController;
use Illuminate\Support\Facades\Route;

Route::get('/', BerandaController::class)->name('beranda');
Route::get('/katalog', [KatalogController::class, 'index'])->name('katalog');
Route::get('/produk/{produk:slug}', [KatalogController::class, 'show'])->name('produk');

// Pemesanan kustom empat langkah (KEP-040).
Route::get('/pesan/{produk:slug}', [KustomController::class, 'form'])->name('kustom.buat');
Route::post('/pesan/{produk:slug}', [KustomController::class, 'simpan'])->name('kustom.simpan');

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
    Route::get('/daftar/terkirim', [AkunController::class, 'terkirim'])->name('daftar.terkirim');

    // Penguasaan email dibuktikan di sini, bukan di formulir (KEP-021).
    Route::get('/daftar/konfirmasi/{token}', [AkunController::class, 'konfirmasi'])
        ->name('daftar.konfirmasi');
});

Route::middleware('auth:pelanggan')->group(function () {
    Route::get('/pesanan-saya', PesananSayaController::class)->name('pesanan-saya');
    Route::post('/keluar', [AkunController::class, 'keluar'])->name('keluar');
});

// Halaman pesanan pelanggan: tanpa akun, tanpa kata sandi (KEP-008).
Route::get('/lacak', [LacakController::class, 'form'])->name('lacak');
Route::post('/lacak', [LacakController::class, 'cari'])->name('lacak.cari');
Route::get('/lacak/{token}', [LacakController::class, 'show'])->name('lacak.token');

/*
 * Bukti bayar dari pelanggan (KEP-029). Dibatasi ketat karena rute ini publik:
 * tanpa batas, siapa pun yang memegang satu tautan bisa memenuhi disk hosting.
 */
Route::post('/lacak/{token}/bukti', [BuktiBayarController::class, 'unggah'])
    ->middleware('throttle:6,10')->name('lacak.bukti');
Route::get('/lacak/{token}/pembayaran/{pembayaran}/bukti', [BuktiBayarController::class, 'lihatPelanggan'])
    ->middleware('throttle:30,1')->name('lacak.bukti.lihat');

/*
 * Chat CS (KEP-030). Batasnya ketat karena semuanya publik: membuka percakapan
 * dibatasi per jam supaya kotak masuk staf tidak bisa dibanjiri, dan polling
 * cadangan dibatasi supaya widget yang macet tidak menghantam hosting.
 */
Route::prefix('/cs')->name('cs.')->group(function () {
    Route::get('/', [CsController::class, 'status'])->middleware('throttle:30,1')->name('status');
    Route::post('/mulai', [CsController::class, 'mulai'])->middleware('throttle:5,60')->name('mulai');
    Route::post('/pesan', [CsController::class, 'kirim'])->middleware('throttle:20,1')->name('kirim');
    Route::get('/pesan', [CsController::class, 'pesanBaru'])->middleware('throttle:20,1')->name('pesan');
    Route::post('/auth', [CsController::class, 'otorisasi'])->middleware('throttle:20,1')->name('auth');
    Route::get('/lampiran/{pesan}', [CsController::class, 'lampiran'])->middleware('throttle:30,1')->name('lampiran');
});

// Pengajuan pembatalan dari pelanggan (KEP-041). Dibatasi supaya satu tautan
// tidak bisa dipakai membanjiri panel dengan pengajuan.
Route::post('/lacak/{token}/pembatalan', [PembatalanController::class, 'ajukan'])
    ->middleware('throttle:6,60')->name('lacak.pembatalan');

Route::prefix('/lacak/{token}/item/{item}')->name('lacak.item.')->group(function () {
    Route::post('roster', [PesananItemController::class, 'simpanRoster'])->name('roster');
    Route::post('desain', [PesananItemController::class, 'unggahDesain'])->name('desain');
    Route::post('setujui', [PesananItemController::class, 'setujuiDesain'])->name('setujui');
    Route::post('revisi', [PesananItemController::class, 'mintaRevisi'])->name('revisi');
});

/*
 * Webhook status email. Pengirimnya bukan browser pelanggan, jadi tidak ada
 * sesi maupun token CSRF; keabsahannya sepenuhnya bergantung pada pemeriksaan
 * tanda tangan di dalam controller.
 */
Route::post('/webhook/resend', ResendController::class)->name('webhook.resend');

require __DIR__.'/panel.php';
