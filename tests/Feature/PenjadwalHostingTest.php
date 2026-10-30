<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Penjadwal di hosting (KEP-093): proc_open dimatikan, jadi tidak ada tugas yang
 * boleh berjalan sebagai proses terpisah. Kalau ada, ia gagal tiap menit tanpa suara.
 */
class PenjadwalHostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tidak_ada_tugas_terjadwal_yang_membuka_proses_baru(): void
    {
        foreach (app(Schedule::class)->events() as $tugas) {
            $this->assertInstanceOf(
                CallbackEvent::class,
                $tugas,
                "Tugas \"{$tugas->getSummaryForDisplay()}\" berjalan sebagai proses terpisah dan akan gagal di hosting."
            );
        }
    }

    public function test_antrian_email_dikerjakan_di_dalam_proses_penjadwal(): void
    {
        config(['queue.default' => 'database']);
        dispatch(fn () => Cache::put('antrian-uji', 'terkirim'));
        $this->assertSame(1, DB::table('jobs')->count());

        $tugas = collect(app(Schedule::class)->events())->firstOrFail(fn ($e) => $e->description === 'antrian-email');
        $tugas->run(app());

        $this->assertSame('terkirim', Cache::get('antrian-uji'));
        $this->assertSame(0, DB::table('jobs')->count());
    }
}
