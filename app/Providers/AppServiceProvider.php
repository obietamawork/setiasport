<?php

namespace App\Providers;

use App\Listeners\CatatIdPesanEmail;
use App\Mail\Transport\ResendTransport;
use Illuminate\Mail\Events\MessageSent;
use App\Services\Ongkir\Biteship;
use App\Services\Ongkir\OngkirMati;
use App\Services\Ongkir\PenyediaOngkir;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * Penyedia ongkir dipilih dari konfigurasi (KEP-057). Selama kuncinya
         * belum ada, yang terpasang penyedia mati: checkout tetap jalan dan
         * ongkirnya diisi admin, persis cara kerja toko hari ini.
         */
        $this->app->singleton(PenyediaOngkir::class, function () {
            if (config('ongkir.penyedia') !== 'biteship') {
                return new OngkirMati;
            }

            return new Biteship(
                kunci: config('ongkir.biteship.kunci'),
                url: (string) config('ongkir.biteship.url'),
                areaAsal: config('ongkir.biteship.area_asal'),
                kurir: (array) config('ongkir.kurir', []),
            );
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
