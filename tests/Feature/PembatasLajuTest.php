<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\Ongkir\Biteship;
use App\Services\Ongkir\PenyediaOngkir;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pembatas laju per route (KEP-097).
 *
 * `throttle:X,Y` tanpa awalan memakai SATU penghitung per IP untuk semua route.
 * Pembeli yang mencari area ongkir dan memilih kurir lalu mengunggah bukti
 * transfer ditolak 429, karena pencarian tadi ikut menghabiskan jatah enam
 * unggahan bukti.
 */
class PembatasLajuTest extends TestCase
{
    use RefreshDatabase;

    public function test_setiap_throttle_punya_penghitung_sendiri(): void
    {
        $tanpaAwalan = collect(Route::getRoutes()->getRoutes())
            ->flatMap(fn ($r) => collect($r->gatherMiddleware())
                ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:'))
                ->filter(fn ($m) => count(explode(',', substr($m, 9))) < 3)
                ->map(fn ($m) => $r->uri().' '.$m))
            ->values()
            ->all();

        $this->assertSame([], $tanpaAwalan, 'Throttle tanpa awalan berbagi satu penghitung dengan route lain.');
    }

    public function test_bukti_transfer_tetap_bisa_diunggah_setelah_mencari_ongkir(): void
    {
        Mail::fake();
        Storage::fake('local');
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);

        $this->app->instance(PenyediaOngkir::class, new Biteship(
            kunci: 'kunci-uji', url: 'https://api.biteship.test', areaAsal: 'IDNP6IDNC148IDND874', kurir: ['jne'],
        ));
        Http::fake([
            '*/v1/maps/areas*' => Http::response(['areas' => [['id' => 'IDNP6IDNC148IDND845', 'name' => 'Kebayoran Baru']]]),
            '*/v1/rates/couriers' => Http::response(['pricing' => [[
                'courier_code' => 'jne', 'courier_service_code' => 'reg', 'courier_name' => 'JNE',
                'courier_service_name' => 'Reguler', 'price' => 15_000, 'duration' => '1 - 2 days',
            ]]]),
        ]);

        // Persis yang dilakukan pembeli: menambah barang, mengetik nama area
        // beberapa kali, melihat tarif, lalu membuat pesanan.
        $this->post('/keranjang', ['variant_id' => ProductVariant::where('sku', 'BOLA-MITRE-01')->value('id'), 'qty' => 1]);
        foreach (['Keb', 'Keba', 'Kebay', 'Kebayoran', 'Kebayoran B', 'Kebayoran Baru'] as $kata) {
            $this->get('/pengiriman/area?q='.urlencode($kata))->assertOk();
        }
        $this->get('/pengiriman/tarif?area=IDNP6IDNC148IDND845')->assertOk();
        $this->get('/pengiriman/tarif?area=IDNP6IDNC148IDND845')->assertOk();
        $this->post('/checkout', [
            'nama' => 'Budi', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890', 'setuju' => '1',
            'metode_ambil' => 'KIRIM', 'alamat_kirim' => 'Jl. Melati 5, Jakarta',
            'area_tujuan' => 'IDNP6IDNC148IDND845', 'tarif' => 'jne:reg',
        ])->assertRedirect();

        $order = Order::firstOrFail();

        $this->post(route('lacak.bukti', $order->access_token), [
            'bukti' => UploadedFile::fake()->image('transfer.jpg', 700, 1400),
        ])->assertSessionHas('sukses');

        $this->assertNotNull($order->payments()->first()->bukti_path);
    }
}
