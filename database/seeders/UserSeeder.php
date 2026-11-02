<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Akun contoh dari susunan peran lama.
        User::whereIn('email', [
            'desainer@setiasport.test', 'produksi@setiasport.test', 'gudang@setiasport.test',
        ])->delete();

        /*
         * Hanya untuk instalasi yang belum punya akun sama sekali. Seeder ini ikut
         * berjalan di setiap deploy (deploy/pasang.sh, KEP-093). Kalau ia membuat
         * ulang akun yang tidak ditemukan, Pemilik yang mengganti emailnya ke
         * alamat asli akan mendapati owner@setiasport.test bersandi "password"
         * dan berperan Pemilik hidup lagi setelah pembaruan berikutnya. Akun yang
         * dihapus juga kembali, dan peran yang diubah Pemilik dipulihkan.
         */
        if (User::query()->exists()) {
            return;
        }

        $daftar = [
            ['Pemilik Toko', 'owner@setiasport.test', ['owner']],
            ['Admin Toko', 'admin@setiasport.test', ['admin']],
        ];

        /*
         * Di produksi tidak pernah ada sandi bawaan (KEP-105). Repo ini publik,
         * jadi "password" bisa dibaca siapa saja, dan akun Pemilik bersandi itu
         * berarti panel terbuka untuk umum sampai ada yang ingat menggantinya.
         * Sandinya acak dan tidak dicetak; pemilik toko mengaturnya lewat
         * `php artisan staf:sandi` (docs/12-deploy.md).
         */
        $produksi = app()->isProduction();

        foreach ($daftar as [$nama, $email, $peran]) {
            $user = User::create([
                'name' => $nama,
                'email' => $email,
                'password' => Hash::make($produksi ? Str::password(32) : 'password'),
                'aktif' => true,
            ]);
            $user->forceFill(['wajib_ganti_sandi' => $produksi])->save();

            $user->roles()->sync(Role::whereIn('slug', $peran)->pluck('id'));
        }
    }
}
