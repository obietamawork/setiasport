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
            ['Admin Toko', 'admin@setiasport.test', ['admin-cs']],
            ['Desainer', 'desainer@setiasport.test', ['desainer']],

            // Satu orang memegang dua peran sekaligus — hal biasa di toko kecil,
            // dan alasan relasi user-role dibuat many-to-many (docs/04).
            ['Operator Produksi', 'produksi@setiasport.test', ['operator-printing', 'operator-jahit']],

            ['Staf Gudang', 'gudang@setiasport.test', ['gudang']],
        ];

        foreach ($daftar as [$nama, $email, $peran]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $nama,
                'password' => Hash::make('password'),
                'aktif' => true,
            ]);

            $user->roles()->sync(Role::whereIn('slug', $peran)->pluck('id'));
        }
    }
}
