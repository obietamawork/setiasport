<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            RolePermissionSeeder::class,
            UserSeeder::class,
            CatalogSeeder::class,
            // Dipanggil setelah katalog: ia mengisi angka panduan ukuran dan
            // berat pada produk yang baru saja dibuat CatalogSeeder.
            PanduanUkuranSeeder::class,
            TemplateBalasanSeeder::class,
        ]);
    }
}
