<?php

namespace Tests\Feature;

use App\Models\FaqBot;
use App\Models\LogBot;
use App\Models\PercakapanCs;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\FaqBotSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kelola FAQ chatbot dan kurasi pertanyaan yang belum terjawab (KEP-151).
 */
class FaqBotPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class, FaqBotSeeder::class]);
        config(['fitur.cs' => true]);
        Setting::simpan('cs_aktif', '1');
    }

    private function admin(): User
    {
        return User::where('email', 'admin@setiasport.test')->firstOrFail();
    }

    private function logBelumTerjawab(string $pertanyaan = 'Bisa bordir topi?'): LogBot
    {
        $percakapan = PercakapanCs::create([
            'token_hash' => PercakapanCs::hashToken(PercakapanCs::tokenBaru()), 'nama' => 'Tamu', 'kontak' => 'tamu@contoh.test',
            'topik' => 'LAIN', 'status' => 'TERBUKA', 'ip' => '127.0.0.1',
        ]);

        return LogBot::create(['percakapan_id' => $percakapan->id, 'pertanyaan' => $pertanyaan, 'lapis' => 'admin', 'alasan' => 'Tidak ada jawaban']);
    }

    public function test_seeder_mengisi_faq_awal_dan_tidak_menimpa(): void
    {
        $this->assertGreaterThanOrEqual(8, FaqBot::count());

        FaqBot::query()->update(['jawaban' => 'Diubah pemilik.']);
        $this->seed(FaqBotSeeder::class);

        $this->assertSame(0, FaqBot::where('jawaban', '!=', 'Diubah pemilik.')->count());
    }

    public function test_admin_mengelola_faq(): void
    {
        $this->actingAs($this->admin())->get(route('panel.faq.index'))
            ->assertOk()
            ->assertSee('Minimal pemesanan');

        $this->actingAs($this->admin())->post(route('panel.faq.store'), [
            'pertanyaan' => 'Bisa bordir topi?', 'jawaban' => 'Bisa, minimal 12 pcs.', 'kata_kunci' => 'bordir, topi', 'aktif' => '1',
        ])->assertRedirect();
        $faq = FaqBot::where('pertanyaan', 'Bisa bordir topi?')->firstOrFail();

        $this->actingAs($this->admin())->put(route('panel.faq.update', $faq), [
            'pertanyaan' => 'Bisa bordir topi?', 'jawaban' => 'Bisa, minimal 24 pcs.', 'kata_kunci' => 'bordir, topi',
        ])->assertRedirect();
        $this->assertSame('Bisa, minimal 24 pcs.', $faq->fresh()->jawaban);
        $this->assertFalse($faq->fresh()->aktif);

        $this->actingAs($this->admin())->delete(route('panel.faq.destroy', $faq))->assertRedirect();
        $this->assertNull($faq->fresh());
    }

    public function test_pertanyaan_belum_terjawab_bisa_dijadikan_faq(): void
    {
        $log = $this->logBelumTerjawab();

        $this->actingAs($this->admin())->get(route('panel.faq.index', ['tab' => 'belum']))
            ->assertOk()
            ->assertSee('Bisa bordir topi?');

        $this->actingAs($this->admin())->post(route('panel.faq.store'), [
            'pertanyaan' => 'Bisa bordir topi?', 'jawaban' => 'Bisa.', 'kata_kunci' => 'bordir', 'aktif' => '1', 'log_id' => $log->id,
        ])->assertRedirect();

        $this->assertNotNull($log->fresh()->dikurasi_pada);
    }

    public function test_pertanyaan_belum_terjawab_bisa_diabaikan(): void
    {
        $log = $this->logBelumTerjawab();

        $this->actingAs($this->admin())->post(route('panel.faq.abaikan', $log))->assertRedirect();

        $this->assertNotNull($log->fresh()->dikurasi_pada);
        $this->actingAs($this->admin())->get(route('panel.faq.index', ['tab' => 'belum']))
            ->assertDontSee('Bisa bordir topi?');
    }

    public function test_staf_tanpa_izin_cs_tidak_bisa_mengelola_faq(): void
    {
        $this->actingAs(User::factory()->create())->get(route('panel.faq.index'))->assertForbidden();
    }
}
