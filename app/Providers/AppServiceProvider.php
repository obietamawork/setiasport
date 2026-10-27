<?php

namespace App\Providers;

use App\Listeners\CatatIdPesanEmail;
use App\Mail\Transport\ResendTransport;
use App\Models\Setting;
use Illuminate\Mail\Events\MessageSent;
use App\Services\Ongkir\Biteship;
use App\Services\Ongkir\OngkirMati;
use App\Services\Ongkir\PenyediaOngkir;
use Illuminate\Database\Eloquent\Model;
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
                // Area asal diatur staf lewat Pengaturan > Pengiriman (KEP-067),
                // bukan .env: kodenya tidak bisa ditebak, jadi harus dicari
                // lewat kotak pencarian, bukan diketik ke berkas server.
                areaAsal: Setting::ambil('biteship_area_asal') ?: null,
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

        /*
         * Relasi yang dimuat diam-diam di dalam perulangan (kueri N+1) langsung
         * gagal di lokal dan di tes, bukan baru terasa lambat di shared hosting
         * saat daftar pesanan sudah ratusan baris (KEP-090). Di produksi
         * dimatikan: halaman yang lambat masih lebih baik daripada halaman galat.
         */
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
