<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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

        foreach ($daftar as [$nama, $email, $peran]) {
            $user = User::create([
                'name' => $nama,
                'email' => $email,
                'password' => Hash::make('password'),
                'aktif' => true,
            ]);

            $user->roles()->sync(Role::whereIn('slug', $peran)->pluck('id'));
        }
    }
}
