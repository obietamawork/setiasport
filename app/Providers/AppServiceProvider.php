<?php

namespace App\Providers;

use App\Listeners\CatatIdPesanEmail;
use App\Mail\Transport\ResendTransport;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Mail::extend('resend', fn (array $konfig) => new ResendTransport(
            $konfig['key'] ?? config('services.resend.key', '')
        ));

        Event::listen(MessageSent::class, CatatIdPesanEmail::class);
    }
}
