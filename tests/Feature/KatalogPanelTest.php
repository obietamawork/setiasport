<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Layar katalog di panel: produk, kategori, stok, dan halaman bantuan.
 *
 * Yang dijaga bukan tampilannya, melainkan janji yang dibuat tampilan itu:
 * penyaring "belum lengkap" benar-benar menemukan produk yang bermasalah,
 * urutan kategori benar-benar tersimpan, dan halaman bantuan benar-benar bisa
 * dibuka semua orang yang boleh masuk panel.
 */
class KatalogPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function owner(): User
    {
        return User::where('email', 'owner@setiasport.test')->firstOrFail();
    }

    public function test_penyaring_belum_lengkap_menemukan_produk_tanpa_gambar(): void
    {
        $tanpaGambar = Product::where('aktif', true)
            ->whereDoesntHave('images')
            ->firstOrFail();

        $berGambar = Product::where('aktif', true)
            ->whereHas('images')
            ->whereHas('priceTiers')
            ->first();

        $halaman = $this->actingAs($this->owner())
            ->get(route('panel.produk.index', ['saring' => 'belum-lengkap']))
            ->assertOk()
            ->assertSee($tanpaGambar->nama)
            ->assertSee('Belum ada gambar');

        if ($berGambar) {
            $halaman->assertDontSee($berGambar->nama);
        }
    }

    public function test_daftar_produk_menandai_yang_disembunyikan(): void
    {
        $produk = Product::firstOrFail();
        $produk->update(['aktif' => false]);

        $this->actingAs($this->owner())
            ->get(route('panel.produk.index', ['saring' => 'sembunyi']))
            ->assertOk()
            ->assertSee($produk->nama)
            ->assertSee('Disembunyikan');
    }

    /** Urutan kategori digeser dari daftarnya, dan harus benar-benar tersimpan. */
    public function test_urutan_kategori_bisa_digeser_dan_tersimpan(): void
    {
        $induk = Category::whereNull('parent_id')->orderBy('urutan')->orderBy('nama')->get();

        $this->assertGreaterThan(1, $induk->count(), 'Perlu minimal dua kategori untuk diuji.');

        [$pertama, $kedua] = [$induk[0], $induk[1]];

        $this->actingAs($this->owner())
            ->post(route('panel.kategori.pindah', $pertama), ['arah' => 'turun'])
            ->assertRedirect();

        $urutanBaru = Category::whereNull('parent_id')->orderBy('urutan')->orderBy('nama')->get();

        $this->assertTrue($urutanBaru[0]->is($kedua), 'Kategori kedua naik ke posisi pertama.');
        $this->assertTrue($urutanBaru[1]->is($pertama), 'Kategori pertama turun ke posisi kedua.');

        $this->assertDatabaseHas('categories', ['id' => $pertama->id, 'urutan' => 1]);
        $this->assertDatabaseHas('categories', ['id' => $kedua->id, 'urutan' => 0]);
    }

    /** Yang sudah di ujung tidak pindah, dan itu bukan galat. */
    public function test_menggeser_kategori_teratas_ke_atas_tidak_mengubah_apa_pun(): void
    {
        $pertama = Category::whereNull('parent_id')->orderBy('urutan')->orderBy('nama')->firstOrFail();

        $this->actingAs($this->owner())
            ->post(route('panel.kategori.pindah', $pertama), ['arah' => 'naik'])
            ->assertRedirect();

        $this->assertTrue(
            Category::whereNull('parent_id')->orderBy('urutan')->orderBy('nama')->firstOrFail()->is($pertama)
        );
    }

    /**
     * Angka yang diketik di halaman stok adalah jumlah di rak, bukan jumlah
     * yang boleh dijual. Kalau keduanya tertukar, stok toko ikut kacau.
     */
    public function test_halaman_stok_membedakan_jumlah_di_rak_dan_yang_bisa_dijual(): void
    {
        $varian = \App\Models\ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $varian->update(['stok' => 10, 'stok_dipesan' => 3]);

        $this->actingAs($this->owner())
            ->get(route('panel.stok.index'))
            ->assertOk()
            ->assertSee('jumlah yang benar-benar ada di rak')
            ->assertSee('3 sudah dipesan,')
            ->assertSee('sisa 7 bisa dijual')
            // Kotak isiannya memuat stok fisik, bukan sisa yang bisa dijual.
            ->assertSee('value="10"', false);
    }

    public function test_halaman_bantuan_terbuka_dan_menjelaskan_alasannya(): void
    {
        $this->actingAs($this->owner())
            ->get(route('panel.bantuan'))
            ->assertOk()
            ->assertSee('Bantuan')
            // Bukan sekadar daftar tombol: alasannya ikut ditulis.
            ->assertSee('Kenapa banyak hal tidak bisa saya ubah langsung')
            ->assertSee('mutasi rekening')
            ->assertSee('jumlah yang benar-benar ada di rak');
    }

    /** Yang perannya paling sempit justru yang paling butuh penjelasan. */
    public function test_bantuan_bisa_dibuka_tanpa_izin_khusus(): void
    {
        $kasir = User::create([
            'name' => 'Kasir Uji',
            'email' => 'kasir.uji@setiasport.test',
            'password' => bcrypt('rahasia-panjang'),
        ]);

        $this->actingAs($kasir)->get(route('panel.bantuan'))->assertOk();
    }

    public function test_tautan_bantuan_ada_di_bilah_atas_setiap_halaman_panel(): void
    {
        foreach ([route('panel.dashboard'), route('panel.produk.index'), route('panel.stok.index')] as $jalur) {
            $this->actingAs($this->owner())
                ->get($jalur)
                ->assertOk()
                ->assertSee(route('panel.bantuan'), false);
        }
    }
}
