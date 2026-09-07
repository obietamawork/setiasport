<?php

namespace App\Providers;

use App\Listeners\CatatIdPesanEmail;
use App\Mail\Transport\ResendTransport;
use App\Services\Pembayaran\GerbangNonaktif;
use App\Services\Pembayaran\GerbangPembayaran;
use App\Services\Pembayaran\Midtrans;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * Gerbang pembayaran dipilih lewat konfigurasi. Selama merchant belum
         * disetujui, GerbangNonaktif yang dipakai dan seluruh sistem tetap
         * berjalan penuh dengan transfer manual (KEP-003).
         */
        $this->app->singleton(GerbangPembayaran::class, function ($app) {
            $gerbang = $app['config']->get('pembayaran.gerbang');

            $driver = match ($gerbang) {
                'midtrans' => new Midtrans($app['config']->get('pembayaran.midtrans')),
                default => new GerbangNonaktif,
            };

            // Konfigurasi setengah jadi lebih berbahaya daripada tidak ada
            // sama sekali: tombol muncul tapi pembayaran gagal di tengah jalan.
            return $driver->aktif() ? $driver : new GerbangNonaktif;
        });
    }

    public function boot(): void
    {
        Mail::extend('resend', fn (array $konfig) => new ResendTransport(
            $konfig['key'] ?? config('services.resend.key', '')
        ));

        Event::listen(MessageSent::class, CatatIdPesanEmail::class);
    }
}
