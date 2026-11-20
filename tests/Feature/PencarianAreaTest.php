<?php

namespace Tests\Feature;

use App\Services\Ongkir\Biteship;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pencarian alamat tujuan yang berlapis (KEP-155). Biteship hanya mengenali
 * teks tertentu: "Wonosari Gunungkidul" kosong karena namanya "Gunung Kidul",
 * alamat lengkap dengan nama jalan kosong, dan "Solo" bukan nama resminya.
 * Bila satu cara gagal, cara berikutnya dicoba. Biteship tiruan di bawah
 * meniru jawaban aslinya yang diukur di produksi.
 */
class PencarianAreaTest extends TestCase
{
    /** @var list<string> */
    private array $dicari = [];

    private function biteshipTiruan(): void
    {
        $area = fn (string $id, string $nama, string $pos) => ['id' => $id, 'name' => "$nama. $pos", 'postal_code' => (int) $pos];
        $laweyan = [$area('LWY1', 'Laweyan, Surakarta, Jawa Tengah', '57141'), $area('LWY2', 'Laweyan, Surakarta, Jawa Tengah', '57142')];
        $surakarta = [$area('BJS1', 'Banjarsari, Surakarta, Jawa Tengah', '57131'), ...$laweyan];
        $wonosariGk = [$area('WGK1', 'Wonosari, Gunung Kidul, DI Yogyakarta', '55811')];
        $wonosari = [$area('WKL1', 'Wonosari, Klaten, Jawa Tengah', '57473'), $area('WBL1', 'Wonosari, Boalemo, Gorontalo', '96262'), ...$wonosariGk];

        $jawaban = [
            '57141' => [$laweyan[0]],
            'laweyan' => $laweyan,
            'surakarta' => $surakarta,
            'wonosari' => $wonosari,
            'wonosari, gunung kidul' => $wonosariGk,
        ];

        Http::fake(function (Request $r) use ($jawaban) {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);
            $kata = mb_strtolower((string) ($q['input'] ?? ''));
            $this->dicari[] = $kata;

            return Http::response(['areas' => $jawaban[$kata] ?? []]);
        });
    }

    private function cari(string $kata, bool $tiruan = true): array
    {
        if ($tiruan) {
            $this->biteshipTiruan();
        }

        return (new Biteship('kunci-uji', 'https://api.biteship.test', 'ASAL', ['jne']))->cariArea($kata);
    }

    public function test_kode_pos_langsung_menemukan_area(): void
    {
        $area = $this->cari('57141');

        $this->assertSame('LWY1', $area[0]['kode']);
        $this->assertSame('57141', $area[0]['kode_pos']);
    }

    public function test_kode_pos_di_dalam_alamat_dipakai_lebih_dulu(): void
    {
        $this->assertSame('LWY1', $this->cari('Jl. Mawar No. 5, Laweyan 57141')[0]['kode']);
    }

    public function test_nama_daerah_yang_ditulis_rapat_tetap_ketemu(): void
    {
        $area = $this->cari('Wonosari Gunungkidul');

        $this->assertSame('WGK1', $area[0]['kode']);
    }

    public function test_nama_daerah_dipisah_spasi_diurutkan_yang_paling_cocok(): void
    {
        $area = $this->cari('wonosari gunung kidul');

        $this->assertSame('WGK1', $area[0]['kode']);
    }

    public function test_alamat_lengkap_dengan_nama_jalan_dan_nama_populer_tetap_ketemu(): void
    {
        $area = $this->cari('Jl. Slamet Riyadi Laweyan Solo');

        $this->assertStringStartsWith('Laweyan, Surakarta', $area[0]['nama']);
    }

    /**
     * Temuan tinjauan keamanan (KEP-155): tiap panggilan ke Biteship bisa
     * berbiaya, dan kata pencarian dikirim siapa saja. Satu pencarian paling
     * banyak empat panggilan, termasuk kode pos, tanpa pengulangan otomatis.
     */
    public function test_pencarian_yang_tidak_ketemu_dibatasi_jumlah_panggilannya(): void
    {
        $this->assertSame([], $this->cari('Jl. Antah Berantah Kampung Tak Dikenal Sekali 99999 '.str_repeat('panjang ', 200)));
        $this->assertLessThanOrEqual(4, count($this->dicari));
        $this->assertLessThanOrEqual(100, max(array_map('mb_strlen', $this->dicari)));
    }

    public function test_pencarian_area_tidak_diulang_saat_biteship_galat(): void
    {
        $panggilan = 0;
        Http::fake(function () use (&$panggilan) {
            $panggilan++;

            return Http::response(['error' => 'galat'], 500);
        });

        $this->assertSame([], $this->cari('laweyan', tiruan: false));
        $this->assertSame(1, $panggilan);
    }
}
