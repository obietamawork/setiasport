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
        $daftar = [
            ['Pemilik Toko', 'owner@setiasport.test', ['owner']],
            ['Admin Toko', 'admin@setiasport.test', ['admin']],
        ];

        foreach ($daftar as [$nama, $email, $peran]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $nama,
                'password' => Hash::make('password'),
                'aktif' => true,
            ]);

            $user->roles()->sync(Role::whereIn('slug', $peran)->pluck('id'));
        }

        // Akun contoh dari susunan peran lama.
        User::whereIn('email', [
            'desainer@setiasport.test', 'produksi@setiasport.test', 'gudang@setiasport.test',
        ])->delete();
    }
}
