<?php

namespace Tests\Feature;

use App\Events\PesanCsTerkirim;
use App\Mail\PeringatanCs;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PercakapanCs;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\TemplateBalasan;
use App\Models\User;
use App\Services\LayananCs;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Chat CS di website (KEP-030).
 *
 * Log chat adalah catatan resmi toko, karena komplain hanya dilayani lewat
 * sini. Yang paling penting diuji: percakapan orang lain tidak bisa dibuka,
 * channel realtime tidak bisa disadap, dan pesan tidak hilang ketika Pusher
 * sedang bermasalah.
 */
class ChatCsTest extends TestCase
{
    use RefreshDatabase;

    private const COOKIE = 'cs_percakapan';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);

        // CS mati sebagai bawaan (KEP-034); tes ini menguji fiturnya saat dinyalakan.
        config(['fitur.cs' => true]);
        Setting::simpan('cs_aktif', '1');

        // Widget memanggil server dengan credentials: 'same-origin', jadi cookie
        // selalu ikut. Permintaan JSON di tes tidak mengirimnya tanpa ini.
        $this->withCredentials();

        // Kunci palsu cukup untuk menguji tanda tangan otorisasi.
        config([
            'broadcasting.connections.pusher.key' => 'kunci-tes',
            'broadcasting.connections.pusher.secret' => 'rahasia-tes',
        ]);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@setiasport.test')->firstOrFail();
    }

    /** @return array{0: PercakapanCs, 1: string} */
    private function mulaiSebagaiTamu(array $tambahan = []): array
    {
        $res = $this->postJson(route('cs.mulai'), array_merge([
            'nama' => 'Pak Warno',
            'kontak' => 'warno@contoh.test',
            'topik' => 'KUSTOM',
            'isi' => 'Jersey futsal 15 pcs berapa?',
        ], $tambahan))->assertCreated();

        $cookie = collect($res->headers->getCookies())->first(fn ($c) => $c->getName() === self::COOKIE);
        $this->assertNotNull($cookie, 'Percakapan tamu harus dikenali lewat cookie.');
        $this->assertTrue($cookie->isHttpOnly(), 'Skrip di halaman tidak boleh bisa membaca token percakapan.');

        // Cookie di respons sudah terenkripsi; token mentahnya diambil kembali
        // supaya bisa dibandingkan dengan hash dan dikirim ulang seperti browser.
        $mentah = CookieValuePrefix::remove(app('encrypter')->decrypt($cookie->getValue(), false));

        return [PercakapanCs::latest('id')->firstOrFail(), $mentah];
    }

    public function test_tamu_memulai_percakapan(): void
    {
        [$percakapan, $token] = $this->mulaiSebagaiTamu();

        $this->assertSame('Pak Warno', $percakapan->nama);
        $this->assertSame(1, $percakapan->pesan()->count());
        $this->assertSame(1, $percakapan->belum_dibaca_staf);
        $this->assertNotNull($percakapan->menunggu_sejak);

        // Yang tersimpan hanya hash; token mentah tidak pernah ada di database.
        $this->assertSame(PercakapanCs::hashToken($token), $percakapan->token_hash);
        $this->assertNotSame($token, $percakapan->token_hash);
    }

    public function test_tamu_wajib_mengisi_nama_dan_kontak(): void
    {
        $this->postJson(route('cs.mulai'), ['topik' => 'STOK', 'isi' => 'Bola ada?'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nama', 'kontak']);

        $this->assertSame(0, PercakapanCs::count());
    }

    public function test_pelanggan_dengan_akun_tidak_perlu_mengisi_identitas(): void
    {
        $customer = Customer::create([
            'nama' => 'Bu Sri', 'email' => 'sri@contoh.test', 'no_hp' => '081200000001',
            'password' => bcrypt('rahasia-panjang'), 'email_verified_at' => now(),
        ]);

        $this->actingAs($customer, 'pelanggan')
            ->postJson(route('cs.mulai'), ['topik' => 'STOK', 'isi' => 'Bola ada?'])
            ->assertCreated();

        $percakapan = PercakapanCs::firstOrFail();
        $this->assertSame($customer->id, $percakapan->customer_id);
        $this->assertSame('Bu Sri', $percakapan->nama);
    }

    public function test_percakapan_hanya_bisa_dilanjutkan_pemegang_cookie(): void
    {
        [$percakapan, $token] = $this->mulaiSebagaiTamu();

        $this->withCookie(self::COOKIE, $token)
            ->postJson(route('cs.kirim'), ['isi' => 'Warna navy bisa?'])
            ->assertCreated();

        $this->assertSame(2, $percakapan->pesan()->count());

        // Tanpa cookie, atau dengan token karangan, tidak ada yang terbuka.
        $this->withCookie(self::COOKIE, 'token-karangan')
            ->postJson(route('cs.kirim'), ['isi' => 'Menyusup'])
            ->assertNotFound();

        $this->withCookie(self::COOKIE, 'token-karangan')
            ->getJson(route('cs.status'))
            ->assertOk()
            ->assertJsonPath('percakapan', null)
            ->assertJsonPath('pesan', []);
    }

    public function test_channel_realtime_hanya_diotorisasi_untuk_pemiliknya(): void
    {
        [$milikWarno, $tokenWarno] = $this->mulaiSebagaiTamu();
        [$milikLain] = $this->mulaiSebagaiTamu(['nama' => 'Orang Lain', 'kontak' => 'lain@contoh.test']);

        $socket = '1234.5678';

        $res = $this->withCookie(self::COOKIE, $tokenWarno)
            ->postJson(route('cs.auth'), ['socket_id' => $socket, 'channel_name' => $milikWarno->channel()])
            ->assertOk();

        // Rumus tanda tangan protokol Pusher: HMAC-SHA256 atas "socket:channel".
        $this->assertSame(
            'kunci-tes:'.hash_hmac('sha256', $socket.':'.$milikWarno->channel(), 'rahasia-tes'),
            $res->json('auth')
        );

        // Menyadap percakapan orang lain, atau channel staf, ditolak.
        $this->withCookie(self::COOKIE, $tokenWarno)
            ->postJson(route('cs.auth'), ['socket_id' => $socket, 'channel_name' => $milikLain->channel()])
            ->assertForbidden();

        $this->withCookie(self::COOKIE, $tokenWarno)
            ->postJson(route('cs.auth'), ['socket_id' => $socket, 'channel_name' => 'private-cs.staf'])
            ->assertForbidden();
    }

    public function test_channel_staf_butuh_akun_staf(): void
    {
        $this->postJson(route('panel.cs.auth'), ['socket_id' => '1.2', 'channel_name' => 'private-cs.staf'])
            ->assertUnauthorized();

        $this->actingAs($this->admin())
            ->postJson(route('panel.cs.auth'), ['socket_id' => '1.2', 'channel_name' => 'private-cs.staf'])
            ->assertOk()
            ->assertJsonStructure(['auth']);

        // Staf pun hanya mendapat channel staf, bukan channel sembarang.
        $this->actingAs($this->admin())
            ->postJson(route('panel.cs.auth'), ['socket_id' => '1.2', 'channel_name' => 'private-lain'])
            ->assertForbidden();
    }

    public function test_balasan_staf_sampai_ke_pelanggan_tanpa_nama_pribadi(): void
    {
        [$percakapan, $token] = $this->mulaiSebagaiTamu();

        $this->actingAs($this->admin())
            ->postJson(route('panel.cs.balas', $percakapan), ['isi' => 'Sekitar Rp 120.000 per pcs.'])
            ->assertCreated();

        $percakapan->refresh();
        $this->assertNull($percakapan->menunggu_sejak, 'Sudah dibalas, tidak lagi menunggu.');
        $this->assertSame(1, $percakapan->belum_dibaca_pelanggan);

        auth('web')->logout();

        $pesan = $this->withCookie(self::COOKIE, $token)
            ->getJson(route('cs.status'))
            ->assertOk()
            ->json('pesan');

        $this->assertSame(['PELANGGAN', 'STAF'], array_column($pesan, 'pengirim'));
        $this->assertSame('CS Setia Sport', $pesan[1]['nama']);
    }

    public function test_pesan_disiarkan_ke_pihak_yang_benar(): void
    {
        Event::fake([PesanCsTerkirim::class]);

        [$percakapan] = $this->mulaiSebagaiTamu();

        Event::assertDispatched(PesanCsTerkirim::class, fn ($e) => $e->untuk === 'staf');
        Event::assertNotDispatched(PesanCsTerkirim::class, fn ($e) => $e->untuk === 'pelanggan');

        $this->actingAs($this->admin())
            ->postJson(route('panel.cs.balas', $percakapan), ['isi' => 'Halo'])
            ->assertCreated();

        Event::assertDispatched(PesanCsTerkirim::class, fn ($e) => $e->untuk === 'pelanggan'
            && $e->broadcastOn()[0]->name === 'private-cs.percakapan.'.$percakapan->id);
    }

    /**
     * Pusher yang mati, kuota habis, atau kunci salah tidak boleh membuat pesan
     * pelanggan hilang. Yang hilang hanya kecepatannya; widget menyusul lewat
     * polling.
     */
    public function test_pesan_tetap_tersimpan_saat_pusher_tidak_bisa_dihubungi(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.options.host' => '127.0.0.1',
            'broadcasting.connections.pusher.options.port' => 1,
            'broadcasting.connections.pusher.options.scheme' => 'http',
            'broadcasting.connections.pusher.options.useTLS' => false,
        ]);

        [$percakapan] = $this->mulaiSebagaiTamu();

        $this->assertSame(1, $percakapan->pesan()->count());
    }

    public function test_kode_pesanan_yang_diketik_tidak_dianggap_terverifikasi(): void
    {
        $order = $this->buatPesanan();

        $this->mulaiSebagaiTamu(['topik' => 'KOMPLAIN', 'kode_pesanan' => $order->kode]);

        $percakapan = PercakapanCs::latest('id')->firstOrFail();
        $this->assertSame($order->id, $percakapan->order_id);
        $this->assertFalse($percakapan->order_terverifikasi);
    }

    public function test_chat_dari_halaman_pesanan_terverifikasi(): void
    {
        $order = $this->buatPesanan();

        $this->mulaiSebagaiTamu(['topik' => 'KOMPLAIN', 'token_pesanan' => $order->access_token]);

        $percakapan = PercakapanCs::latest('id')->firstOrFail();
        $this->assertSame($order->id, $percakapan->order_id);
        $this->assertTrue($percakapan->order_terverifikasi);
    }

    public function test_menutup_lalu_pelanggan_menulis_lagi_membukanya_kembali(): void
    {
        [$percakapan, $token] = $this->mulaiSebagaiTamu();

        $this->actingAs($this->admin())->postJson(route('panel.cs.tutup', $percakapan))
            ->assertOk()
            ->assertJsonPath('percakapan.status', 'SELESAI');
        $this->assertSame('SELESAI', $percakapan->fresh()->status);
        $this->assertSame('SISTEM', $percakapan->pesan()->latest('id')->first()->pengirim);

        auth('web')->logout();

        $this->withCookie(self::COOKIE, $token)
            ->postJson(route('cs.kirim'), ['isi' => 'Maaf, satu lagi.'])
            ->assertCreated();

        $this->assertSame('TERBUKA', $percakapan->fresh()->status);
    }

    public function test_chat_yang_tidak_dijawab_diperingatkan_sekali(): void
    {
        // Dikunci di dalam jam operasional; tanpa ini tes gagal saat dijalankan malam.
        $this->travelTo(now()->setTime(10, 0));

        [$percakapan] = $this->mulaiSebagaiTamu();
        $percakapan->update(['menunggu_sejak' => now()->subMinutes(30)]);

        $cs = app(LayananCs::class);

        $this->assertSame(1, $cs->peringatkanYangMenunggu());
        Mail::assertSent(PeringatanCs::class, fn ($m) => $m->hasTo('admin@setiasport.test'));

        // Masa tunggu yang sama tidak diperingatkan dua kali.
        $this->assertSame(0, $cs->peringatkanYangMenunggu());
        Mail::assertSent(PeringatanCs::class, 1);
    }

    /**
     * Klien: website beroperasi setiap hari 07.00-16.00. Chat yang masuk malam
     * tetap tercatat menunggu, tapi staf tidak dikirimi email tengah malam;
     * peringatannya menyusul begitu jam operasional dimulai.
     */
    public function test_peringatan_chat_diam_di_luar_jam_operasional(): void
    {
        $this->travelTo(now()->setTime(21, 0));

        [$percakapan] = $this->mulaiSebagaiTamu();
        $percakapan->update(['menunggu_sejak' => now()->subHour()]);

        $cs = app(LayananCs::class);
        $this->assertSame(0, $cs->peringatkanYangMenunggu());
        Mail::assertNothingSent();

        $this->travelTo(now()->addDay()->setTime(7, 5));
        $this->assertSame(1, $cs->peringatkanYangMenunggu());
    }

    public function test_jam_operasional_yang_salah_ketik_tidak_mematikan_peringatan(): void
    {
        Setting::simpan('cs_jam_mulai', 'jam tujuh');
        $this->travelTo(now()->setTime(22, 0));

        $this->assertTrue(app(LayananCs::class)->dalamJamOperasional());
    }

    public function test_chat_yang_baru_menunggu_sebentar_belum_diperingatkan(): void
    {
        $this->mulaiSebagaiTamu();

        $this->assertSame(0, app(LayananCs::class)->peringatkanYangMenunggu());
        Mail::assertNothingSent();
    }

    public function test_isi_pesan_berbahaya_tidak_tampil_sebagai_html_di_panel(): void
    {
        [$percakapan] = $this->mulaiSebagaiTamu(['isi' => '<script>alert("curi")</script>']);

        $this->actingAs($this->admin())
            ->get(route('panel.cs.show', $percakapan))
            ->assertOk()
            ->assertDontSee('<script>alert("curi")</script>', false);
    }

    public function test_membuka_percakapan_dibatasi_per_jam(): void
    {
        foreach (range(1, 5) as $i) {
            $this->postJson(route('cs.mulai'), [
                'nama' => 'Spam '.$i, 'kontak' => 'spam@contoh.test', 'topik' => 'LAIN', 'isi' => 'iklan',
            ])->assertCreated();
        }

        $this->postJson(route('cs.mulai'), [
            'nama' => 'Spam 6', 'kontak' => 'spam@contoh.test', 'topik' => 'LAIN', 'isi' => 'iklan',
        ])->assertStatus(429);

        $this->assertSame(5, PercakapanCs::count());
    }

    public function test_lampiran_hanya_gambar_dan_disimpan_privat(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->postJson(route('cs.mulai'), [
            'nama' => 'Pak Warno', 'kontak' => 'warno@contoh.test', 'topik' => 'KOMPLAIN',
            'lampiran' => UploadedFile::fake()->create('virus.exe', 50, 'application/octet-stream'),
        ])->assertStatus(422);

        [$percakapan] = $this->mulaiSebagaiTamu([
            'topik' => 'KOMPLAIN', 'isi' => 'Jahitannya lepas',
            'lampiran' => UploadedFile::fake()->image('jahitan.jpg', 900, 1200),
        ]);

        $path = $percakapan->pesan()->first()->lampiran_path;
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_template_dikelola_staf_dan_muncul_di_kotak_masuk(): void
    {
        [$percakapan] = $this->mulaiSebagaiTamu();

        $this->post(route('panel.cs.template.store'), ['judul' => 'Tamu', 'isi' => 'x'])
            ->assertRedirect(route('panel.login'));

        $this->actingAs($this->admin())
            ->post(route('panel.cs.template.store'), [
                'judul' => 'Minimal order', 'isi' => 'Minimal pemesanan jersey custom 12 pcs.',
            ])->assertRedirect(route('panel.cs.template.index'));

        $this->assertSame(1, TemplateBalasan::count());

        $this->actingAs($this->admin())
            ->get(route('panel.cs.show', $percakapan))
            ->assertSee('Minimal order');
    }

    public function test_chat_bisa_dimatikan_dari_pengaturan(): void
    {
        Setting::simpan('cs_aktif', '0');

        $this->get(route('beranda'))->assertOk()->assertDontSee('Buka chat dengan CS');
        $this->postJson(route('cs.mulai'), [
            'nama' => 'A', 'kontak' => 'a@contoh.test', 'topik' => 'LAIN', 'isi' => 'halo',
        ])->assertNotFound();
    }

    public function test_kotak_centang_pengaturan_bisa_dimatikan(): void
    {
        $owner = User::where('email', 'owner@setiasport.test')->firstOrFail();

        // Kotak yang tidak dicentang tidak mengirim apa pun; formulir mengirim
        // nilai cadangan "0" lewat input tersembunyi.
        $this->actingAs($owner)
            ->put(route('panel.pengaturan.update'), ['setting' => ['cs_aktif' => '0']])
            ->assertSessionHas('sukses');

        $this->assertFalse(Setting::ambil('cs_aktif'));
    }

    public function test_ruang_cs_mencari_dan_menyaring_percakapan(): void
    {
        $order = $this->buatPesanan();

        [$menunggu] = $this->mulaiSebagaiTamu(['nama' => 'Pak Warno']);
        [$dibalas] = $this->mulaiSebagaiTamu([
            'nama' => 'Bu Sri', 'kontak' => 'sri@contoh.test', 'topik' => 'KOMPLAIN', 'kode_pesanan' => $order->kode,
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('panel.cs.balas', $dibalas), ['isi' => 'Kami cek dulu.'])
            ->assertCreated();

        // "Menunggu" hanya berisi yang belum dibalas.
        $this->actingAs($this->admin())
            ->getJson(route('panel.cs.daftar', ['saring' => 'menunggu']))
            ->assertOk()
            ->assertJsonCount(1, 'daftar')
            ->assertJsonPath('daftar.0.id', $menunggu->id)
            ->assertJsonPath('jumlah.menunggu', 1);

        // Kode pesanan yang ditautkan ikut bisa dicari.
        $this->actingAs($this->admin())
            ->getJson(route('panel.cs.daftar', ['saring' => 'semua', 'q' => $order->kode]))
            ->assertOk()
            ->assertJsonCount(1, 'daftar')
            ->assertJsonPath('daftar.0.nama', 'Bu Sri')
            ->assertJsonPath('daftar.0.cuplikan', 'Kami cek dulu.')
            ->assertJsonPath('daftar.0.cuplikan_dari', 'STAF');
    }

    public function test_membuka_percakapan_menandainya_terbaca(): void
    {
        [$percakapan] = $this->mulaiSebagaiTamu();
        $this->assertSame(1, $percakapan->belum_dibaca_staf);

        $this->actingAs($this->admin())
            ->getJson(route('panel.cs.detail', $percakapan))
            ->assertOk()
            ->assertJsonPath('percakapan.nama', 'Pak Warno')
            ->assertJsonCount(1, 'pesan');

        $this->assertSame(0, $percakapan->fresh()->belum_dibaca_staf);
    }

    /**
     * Pelanggan melihat CS online hanya selama ada staf yang membuka ruang CS
     * dengan "Menerima chat" menyala (KEP-031). Janji "biasanya membalas dalam
     * beberapa menit" tidak boleh tampil saat tidak ada yang membaca.
     */
    public function test_status_online_mengikuti_staf_yang_menerima_chat(): void
    {
        $cs = app(LayananCs::class);

        $this->get(route('beranda'))->assertSee('Sedang offline');
        $this->assertFalse($cs->adaYangHadir());

        $this->actingAs($this->admin())->get(route('panel.cs.index'))->assertOk();
        $this->assertTrue($cs->adaYangHadir(), 'Membuka ruang CS berarti siap menerima chat.');

        $this->actingAs($this->admin())
            ->postJson(route('panel.cs.hadir'), ['menerima' => false])
            ->assertOk()
            ->assertJsonPath('online', false);

        $this->assertFalse($cs->adaYangHadir());
    }

    public function test_satu_admin_istirahat_tidak_membuat_cs_offline(): void
    {
        $cs = app(LayananCs::class);
        $owner = User::where('email', 'owner@setiasport.test')->firstOrFail();

        $cs->tandaiHadir($owner, true);
        $cs->tandaiHadir($this->admin(), true);
        $cs->tandaiHadir($this->admin(), false);

        $this->assertTrue($cs->adaYangHadir());
    }

    public function test_staf_yang_pergi_tanpa_pamit_dianggap_offline(): void
    {
        $cs = app(LayananCs::class);
        $cs->tandaiHadir($this->admin(), true);

        $this->travel(3)->minutes();

        $this->assertFalse($cs->adaYangHadir(), 'Tab yang ditutup tanpa mengirim denyut harus kedaluwarsa.');
    }

    private function buatPesanan(): Order
    {
        $bola = ProductVariant::where('sku', 'BOLA-MITRE-01')->firstOrFail();

        $this->post('/keranjang', ['variant_id' => $bola->id, 'qty' => 1]);
        $this->post('/checkout', [
            'nama' => 'Budi', 'email' => 'budi@contoh.test', 'no_hp' => '081234567890', 'setuju' => '1',
        ]);

        return Order::latest('id')->firstOrFail();
    }
}
