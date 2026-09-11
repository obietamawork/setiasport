<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `db:seed` dijalankan ulang setiap kali ada pengaturan, izin, atau template
 * baru. Seeder yang menimpa isian pemilik toko adalah bug yang tidak terlihat
 * sampai ada yang rugi: rekening kosong, harga kembali ke draft, atau kata
 * sandi owner kembali ke "password" yang tercantum di README.
 */
class SeedUlangAmanTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_ulang_tidak_menimpa_apa_pun_yang_sudah_diisi_toko(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'owner@setiasport.test')->firstOrFail();
        $owner->update(['password' => Hash::make('sandi-baru-yang-panjang')]);

        Setting::simpan('bank_rekening', '0123456789');

        $produk = Product::firstOrFail();
        $produk->update(['nama' => 'Nama yang sudah dikoreksi pemilik toko']);

        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(
            Hash::check('sandi-baru-yang-panjang', $owner->fresh()->password),
            'Seed ulang mengembalikan kata sandi owner ke bawaan.'
        );
        $this->assertFalse(Hash::check('password', $owner->fresh()->password));
        $this->assertSame('0123456789', Setting::ambil('bank_rekening'));
        $this->assertSame('Nama yang sudah dikoreksi pemilik toko', $produk->fresh()->nama);
    }

    /**
     * Kode pembayaran tidak boleh melewati kompresi apa pun: satu modul yang
     * kabur berarti pelanggan gagal membayar. Yang terpasang harus identik
     * byte demi byte dengan berkas dari klien.
     */
    public function test_instalasi_baru_memasang_qris_asli_tanpa_diubah(): void
    {
        Storage::fake('public');

        $this->seed(DatabaseSeeder::class);

        $path = Setting::ambil('qris_path');
        $this->assertNotEmpty($path);
        $this->assertSame(
            hash_file('sha256', database_path('seeders/aset/qris.jpeg')),
            hash('sha256', Storage::disk('public')->get($path))
        );
    }

    public function test_qris_yang_diganti_pemilik_toko_tidak_ditimpa_seeder(): void
    {
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);

        Setting::simpan('qris_path', 'qris/qris-baru-dari-panel.webp');
        $this->seed(DatabaseSeeder::class);

        $this->assertSame('qris/qris-baru-dari-panel.webp', Setting::ambil('qris_path'));
    }

    public function test_seed_ulang_tetap_menambah_pengaturan_dan_izin_baru(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Seolah-olah kunci ini baru ditambahkan di versi berikutnya.
        Setting::where('key', 'cs_jam_layanan')->delete();

        $this->seed(DatabaseSeeder::class);

        $this->assertNotNull(Setting::ambil('cs_jam_layanan'));
        $this->assertTrue(
            User::where('email', 'admin@setiasport.test')->firstOrFail()->punyaIzin('cs.balas')
        );
    }
}
