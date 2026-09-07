<?php

use App\Enums\EmailStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\AlurPesanan;
use App\Services\PengirimEmail;
use Illuminate\Support\Facades\Schedule;

/**
 * Di shared hosting tidak ada daemon; seluruh pekerjaan latar berjalan lewat
 * satu baris cron yang memanggil schedule:run tiap menit (docs/06).
 */

// Antrian email. Tanpa ini email hanya menumpuk di tabel jobs dan tidak pernah terkirim.
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

/**
 * Order yang tidak dibayar sampai batas waktu dibatalkan, dan reservasi stoknya
 * dilepas. Tanpa ini, barang terkunci selamanya oleh pesanan yang tidak pernah
 * dibayar dan stok tersedia perlahan habis tanpa sebab yang terlihat.
 */
Schedule::call(function (AlurPesanan $alur) {
    Order::query()
        ->where('status', OrderStatus::MENUNGGU_PEMBAYARAN)
        ->whereNotNull('kadaluarsa_pada')
        ->where('kadaluarsa_pada', '<', now())
        ->with('items')
        ->each(function (Order $order) use ($alur) {
            $alur->batalkan($order, 'Kadaluarsa: tidak dibayar sampai batas waktu.');
            $order->update(['status' => OrderStatus::KADALUARSA]);
        });
})->hourly()->name('kadaluarsa-order')->withoutOverlapping();

/**
 * Pengingat barang yang sudah siap tapi belum diambil. Barang seperti ini
 * memakan rak toko dan paling sering terlupakan.
 */
Schedule::call(function (PengirimEmail $email) {
    $hari = (int) setting('pengingat_ambil_hari', 3);

    Order::query()
        ->where('status', OrderStatus::SIAP_DIAMBIL)
        ->whereNotNull('siap_pada')
        ->where('siap_pada', '<', now()->subDays($hari))
        ->whereDoesntHave('emailLogs', fn ($q) => $q
            ->where('template', 'pengingat-ambil')
            ->where('status', '!=', EmailStatus::GAGAL->value))
        ->with('customer')
        ->each(fn (Order $order) => $email->kirim($order, 'pengingat-ambil'));
})->dailyAt('09:00')->name('pengingat-ambil')->withoutOverlapping();
