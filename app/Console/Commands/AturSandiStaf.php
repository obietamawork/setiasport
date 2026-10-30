<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Pemulihan kata sandi staf lewat server.
 *
 * Sengaja tidak lewat email: pemulihan berbasis email bergantung pada cron dan
 * Resend, dan keduanya justru bisa ikut bermasalah saat dibutuhkan. Untuk toko
 * dengan dua akun, satu perintah di server lebih dapat diandalkan daripada satu
 * alur yang bisa gagal di tiga tempat.
 */
class AturSandiStaf extends Command
{
    protected $signature = 'staf:sandi {email} {sandi?}
        {--sementara : Sandi untuk orang lain; wajib diganti pemilik akun saat pertama masuk}';

    protected $description = 'Mengatur ulang kata sandi akun staf';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Tidak ada akun staf dengan email '.$this->argument('email').'.');
            $this->line('Akun yang ada: '.User::pluck('email')->implode(', '));

            return self::FAILURE;
        }

        $sandi = $this->argument('sandi') ?: $this->secret('Kata sandi baru');

        $cek = Validator::make(['sandi' => $sandi], ['sandi' => ['required', Password::min(8)]]);

        if ($cek->fails()) {
            $this->error($cek->errors()->first('sandi'));

            return self::FAILURE;
        }

        $sementara = (bool) $this->option('sementara');

        $user->forceFill([
            'password' => Hash::make($sandi),
            'aktif' => true,
            'wajib_ganti_sandi' => $sementara,
        ])->save();

        $this->info("Kata sandi {$user->email} berhasil diganti.");
        $this->line('Akun juga dipastikan aktif.');

        if ($sementara) {
            $this->line('Sandi ini sementara: wajib diganti saat pertama masuk panel.');
        }

        return self::SUCCESS;
    }
}
