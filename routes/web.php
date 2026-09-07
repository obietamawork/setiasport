<?php

use App\Http\Controllers\Publik\BerandaController;
use App\Http\Controllers\Publik\KatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/', BerandaController::class)->name('beranda');
Route::get('/katalog', [KatalogController::class, 'index'])->name('katalog');
Route::get('/produk/{produk:slug}', [KatalogController::class, 'show'])->name('produk');

require __DIR__.'/panel.php';
