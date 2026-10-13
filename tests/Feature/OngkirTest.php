<?php

namespace Tests\Feature;

use App\Services\Ongkir\Biteship;
use App\Services\Ongkir\OngkirMati;
use App\Services\Ongkir\PenyediaOngkir;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Penyedia ongkir dan pelacakan paket (KEP-057).
 *
 * Diuji dengan jawaban API palsu, jadi tidak perlu kunci asli maupun saldo.
 * Yang paling penting di sini bukan jalan mulusnya, melainkan apa yang terjadi
 * ketika penyedianya mati: checkout tidak boleh ikut mati, karena ongkir yang
 * dihitung admin memang cara kerja toko selama ini.
 */
class OngkirTest extends TestCase
{
    private function biteship(): Biteship
    {
        return new Biteship(
            kunci: 'kunci-uji',
            url: 'https://api.biteship.test',
            areaAsal: 'IDNP6IDNC148IDND874',
            kurir: ['jne', 'jnt'],
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        cache()->flush();
    }

    public function test_tanpa_kunci_penyedianya_mati_dan_tidak_memanggil_apa_pun(): void
    {
        Http::fake();

        config(['ongkir.penyedia' => 'mati']);

        $penyedia = app(PenyediaOngkir::class);

        $this->assertInstanceOf(OngkirMati::class, $penyedia);
        $this->assertFalse($penyedia->aktif());
        $this->assertSame([], $penyedia->tarif('IDNP6', 1000));
        $this->assertNull($penyedia->lacak('JNE123', 'jne'));

        Http::assertNothingSent();
    }

    public function test_tarif_dibaca_jadi_pilihan_pengiriman(): void
    {
        Http::fake(['*/v1/rates/couriers' => Http::response([
            'pricing' => [
                [
                    'courier_code' => 'jne', 'courier_service_code' => 'REG',
                    'courier_name' => 'JNE', 'courier_service_name' => 'Reguler',
                    'price' => 22_000, 'duration' => '2-3 hari',
                ],
                [
                    'courier_code' => 'jnt', 'courier_service_code' => 'EZ',
                    'courier_name' => 'J&T', 'courier_service_name' => 'Ekonomi',
                    'price' => 20_000, 'duration' => '3-4 hari',
                ],
            ],
        ])]);

        $tarif = $this->biteship()->tarif('IDNP6IDNC148IDND874', 1_250);

        $this->assertCount(2, $tarif);
        $this->assertSame('jne:REG', $tarif[0]->kode());
        $this->assertSame(22_000, $tarif[0]->harga);
        $this->assertSame('JNE Reguler (2-3 hari)', $tarif[0]->label());
        $this->assertSame(20_000, $tarif[1]->harga);
    }

    /** Tarif yang sama tidak perlu ditanyakan dua kali; tiap panggilan berbiaya. */
    public function test_tarif_yang_sama_hanya_dipanggil_sekali(): void
    {
        Http::fake(['*/v1/rates/couriers' => Http::response([
            'pricing' => [[
                'courier_code' => 'jne', 'courier_service_code' => 'REG',
                'courier_name' => 'JNE', 'courier_service_name' => 'Reguler',
                'price' => 22_000, 'duration' => '2-3 hari',
            ]],
        ])]);

        $penyedia = $this->biteship();
        $penyedia->tarif('IDNP6', 1_000);
        $penyedia->tarif('IDNP6', 1_000);

        Http::assertSentCount(1);
    }

    /**
     * Penyedia yang sedang mati tidak boleh menggagalkan pemesanan. Daftar
     * kosong berarti "hitung manual", dan itu tetap bisa dikerjakan toko.
     */
    public function test_penyedia_yang_error_menghasilkan_daftar_kosong(): void
    {
        Http::fake(['*' => Http::response(['error' => 'saldo habis'], 402)]);

        $this->assertSame([], $this->biteship()->tarif('IDNP6', 1_000));
        $this->assertNull($this->biteship()->lacak('JNE123', 'jne'));
    }

    public function test_jaringan_putus_juga_tidak_melempar_galat(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('putus'));

        $this->assertSame([], $this->biteship()->tarif('IDNP6', 1_000));
    }

    public function test_pelacakan_membaca_status_dan_riwayatnya(): void
    {
        Http::fake(['*/v1/trackings/*' => Http::response([
            'status' => 'delivered',
            'note' => 'Diterima oleh yang bersangkutan',
            'history' => [
                ['updated_at' => '2026-09-20T09:00:00Z', 'note' => 'Paket diterima di gerai Wonogiri'],
                ['updated_at' => '2026-09-21T14:00:00Z', 'note' => 'Diterima oleh yang bersangkutan'],
            ],
        ])]);

        $status = $this->biteship()->lacak('JNE0012345678', 'jne');

        $this->assertNotNull($status);
        $this->assertSame('delivered', $status->status);
        $this->assertTrue($status->diterima);
        $this->assertCount(2, $status->riwayat);
        $this->assertSame('Paket diterima di gerai Wonogiri', $status->riwayat[0]['keterangan']);
    }

    public function test_paket_yang_masih_jalan_belum_dianggap_diterima(): void
    {
        Http::fake(['*/v1/trackings/*' => Http::response([
            'status' => 'on_process',
            'history' => [['updated_at' => null, 'note' => 'Dalam perjalanan']],
        ])]);

        $status = $this->biteship()->lacak('JNE001', 'jne');

        $this->assertFalse($status->diterima);
    }

    public function test_pencarian_area_mengembalikan_kode_dan_namanya(): void
    {
        Http::fake(['*/v1/maps/areas*' => Http::response([
            'areas' => [
                ['id' => 'IDNP6IDNC148IDND874', 'name' => 'Wonogiri, Wonogiri, Jawa Tengah'],
            ],
        ])]);

        $area = $this->biteship()->cariArea('wonogiri');

        $this->assertSame('IDNP6IDNC148IDND874', $area[0]['kode']);
        $this->assertStringContainsString('Wonogiri', $area[0]['nama']);
    }

    /** Kata terlalu pendek memanggil API tanpa guna, dan tiap panggilan berbiaya. */
    public function test_kata_pencarian_terlalu_pendek_tidak_memanggil_api(): void
    {
        Http::fake();

        $this->assertSame([], $this->biteship()->cariArea('wo'));

        Http::assertNothingSent();
    }

    public function test_tanpa_area_asal_penyedianya_belum_aktif(): void
    {
        Http::fake();

        $penyedia = new Biteship('kunci-uji', 'https://api.biteship.test', null, ['jne']);

        $this->assertFalse($penyedia->aktif());
        $this->assertSame([], $penyedia->tarif('IDNP6', 1_000));

        Http::assertNothingSent();
    }

    /**
     * Pencarian area hanya butuh kunci, BUKAN `aktif()` penuh (KEP-067).
     * Tanpa ini, satu-satunya cara mengisi area asal pertama kali — mencari
     * areanya sendiri lewat API — tidak pernah bisa dipakai, sebab `aktif()`
     * juga mensyaratkan area asal yang justru belum ada.
     */
    public function test_pencarian_area_tetap_jalan_walau_area_asal_belum_terisi(): void
    {
        Http::fake(['*/v1/maps/areas*' => Http::response([
            'areas' => [['id' => 'IDNP6IDNC148IDND874', 'name' => 'Wonogiri, Wonogiri, Jawa Tengah']],
        ])]);

        $penyedia = new Biteship('kunci-uji', 'https://api.biteship.test', null, ['jne']);

        $this->assertFalse($penyedia->aktif());

        $area = $penyedia->cariArea('wonogiri');

        $this->assertSame('IDNP6IDNC148IDND874', $area[0]['kode']);
        Http::assertSentCount(1);
    }

    /** Tanpa kunci sama sekali, pencarian area juga tidak memanggil apa pun. */
    public function test_pencarian_area_tanpa_kunci_tidak_memanggil_apa_pun(): void
    {
        Http::fake();

        $penyedia = new Biteship(null, 'https://api.biteship.test', null, ['jne']);

        $this->assertSame([], $penyedia->cariArea('wonogiri'));
        Http::assertNothingSent();
    }
}
