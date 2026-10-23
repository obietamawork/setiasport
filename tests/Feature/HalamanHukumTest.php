<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\KesiapanTayang;
use App\Support\DrafHukum;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kebijakan Privasi dan Syarat & Ketentuan (KEP-085), header keamanan
 * (KEP-086), dan teks profil yang dulu menampilkan "\n\n" (KEP-087).
 */
class HalamanHukumTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    /** Angka di dokumen diambil dari pengaturan, jadi tidak bisa berbeda dari yang berlaku. */
    public function test_syarat_ketentuan_memakai_angka_dari_pengaturan(): void
    {
        Setting::simpan('batas_bayar_jam', 12);
        Setting::simpan('dp_minimum_persen', 40);

        $this->get(route('syarat-ketentuan'))
            ->assertOk()
            ->assertSee('<h2>Pembayaran</h2>', false)
            ->assertSee('dalam 12 jam')
            ->assertSee('minimal 40%')
            ->assertDontSee('{batas_bayar_jam}');
    }

    public function test_kebijakan_privasi_tampil_dan_menyebut_uu_pdp(): void
    {
        $this->get(route('kebijakan-privasi'))
            ->assertOk()
            ->assertSee('Undang-Undang Nomor 27 Tahun 2022')
            ->assertSee(setting('toko_telepon'));
    }

    /** Isian di Pengaturan tidak bisa dipakai menyisipkan skrip ke halaman publik. */
    public function test_html_mentah_di_isian_dibuang(): void
    {
        Setting::simpan('kebijakan_privasi', "## Judul\n\n<script>alert('x')</script>\n\n[klik](javascript:alert(1))");

        $this->get(route('kebijakan-privasi'))
            ->assertOk()
            ->assertSee('<h2>Judul</h2>', false)
            ->assertDontSee('<script>alert', false)
            ->assertDontSee('javascript:alert', false);
    }

    public function test_ditautkan_dari_kaki_halaman_dan_checkout(): void
    {
        $this->get(route('beranda'))
            ->assertSee(route('kebijakan-privasi'), false)
            ->assertSee(route('syarat-ketentuan'), false);

        $varian = \App\Models\ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();
        $this->post(route('keranjang.tambah'), ['variant_id' => $varian->id, 'qty' => 1]);

        $this->get(route('checkout'))
            ->assertOk()
            ->assertSeeInOrder(['Dengan membuat pesanan, Anda menyetujui', 'Syarat &amp; Ketentuan', 'Kebijakan Privasi'], false);
    }

    /** Drafnya disusun developer; tanggung jawab isinya ada di pemilik toko. */
    public function test_kesiapan_menahan_selama_masih_draf_bawaan(): void
    {
        $butir = fn () => collect(app(KesiapanTayang::class)->bagian())
            ->flatMap(fn ($b) => $b['isi'])->keyBy('judul')['Kebijakan Privasi dan Syarat & Ketentuan ditinjau'];

        $this->assertTrue($butir()['wajib']);
        $this->assertFalse($butir()['siap']);
        $this->assertTrue(DrafHukum::masihDraf('kebijakan_privasi'));

        Setting::simpan('kebijakan_privasi', DrafHukum::kebijakanPrivasi()."\n\nKontak email: info@setiasport.id");
        Setting::simpan('syarat_ketentuan', DrafHukum::syaratKetentuan()."\n\nBerlaku sejak November 2026.");

        $this->assertTrue($butir()['siap']);
    }

    /** Dokumen kebijakan jauh lebih panjang dari batas 5.000 karakter teks biasa. */
    public function test_pengaturan_menerima_dokumen_panjang(): void
    {
        $owner = User::where('email', 'owner@setiasport.test')->firstOrFail();
        $panjang = str_repeat("Paragraf ketentuan yang cukup panjang untuk diuji.\n\n", 150);

        $this->actingAs($owner)
            ->put(route('panel.pengaturan.update'), ['setting' => ['syarat_ketentuan' => $panjang]])
            ->assertSessionHas('sukses');

        $this->assertGreaterThan(5000, strlen(Setting::ambil('syarat_ketentuan')));
    }

    public function test_header_keamanan_terpasang_di_setiap_respons(): void
    {
        foreach ([route('beranda'), route('panel.login'), '/halaman-yang-tidak-ada'] as $url) {
            $res = $this->get($url);

            $res->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

            $csp = $res->headers->get('Content-Security-Policy');
            $this->assertStringContainsString("frame-ancestors 'self'", $csp);
            $this->assertStringContainsString("object-src 'none'", $csp);
            $this->assertStringContainsString("form-action 'self'", $csp);
            $this->assertFalse($res->headers->has('X-Powered-By'));
        }
    }

    /** HSTS hanya lewat HTTPS; lewat HTTP justru tidak boleh dikirim. */
    public function test_hsts_hanya_lewat_https(): void
    {
        $this->get(route('beranda'))->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    /** Draf profil dulu menyimpan "\n" sebagai dua huruf, dan halaman depan menampilkannya. */
    public function test_teks_tentang_terpecah_jadi_paragraf(): void
    {
        $nilai = (string) Setting::ambil('tentang_lengkap');

        $this->assertStringNotContainsString('\n', $nilai);
        $this->assertCount(3, preg_split('/\R{2,}/', $nilai));
        $this->get(route('beranda'))->assertDontSee('\n\n', false);
    }
}
