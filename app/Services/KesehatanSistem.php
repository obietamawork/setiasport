<?php

namespace App\Services;

use App\Enums\EmailStatus;
use App\Models\EmailLog;
use App\Models\Portofolio;
use App\Models\Testimoni;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pemeriksaan singkat yang ditampilkan di dashboard.
 *
 * Ditulis untuk dibaca pemilik toko, bukan developer: setiap masalah disertai
 * apa yang harus dilakukan, bukan istilah teknisnya.
 */
class KesehatanSistem
{
    private const BATAS_DENYUT_MENIT = 10;

    public function denyutPenjadwal(): ?CarbonImmutable
    {
        $cap = Cache::get('denyut_penjadwal');

        return $cap ? CarbonImmutable::parse($cap) : null;
    }

    public function penjadwalHidup(): bool
    {
        $denyut = $this->denyutPenjadwal();

        return $denyut !== null && $denyut->gt(now()->subMinutes(self::BATAS_DENYUT_MENIT));
    }

    public function pekerjaanGagal(): int
    {
        return DB::table('failed_jobs')->count();
    }

    public function emailGagal(): int
    {
        return EmailLog::whereIn('status', [EmailStatus::BOUNCED->value, EmailStatus::GAGAL->value])->count();
    }

    /**
     * @return array<int, array{judul: string, isi: string}>
     */
    public function masalah(): array
    {
        $masalah = [];

        if (! $this->penjadwalHidup()) {
            $denyut = $this->denyutPenjadwal();

            $masalah[] = [
                'judul' => 'Tugas otomatis berhenti berjalan',
                'isi' => 'Selama ini berlangsung, email ke pelanggan tidak terkirim dan '
                    .'pesanan yang tidak dibayar tidak pernah dibatalkan, sehingga stoknya '
                    .'tetap terkunci. '
                    .($denyut
                        ? 'Terakhir berjalan '.$denyut->diffForHumans().'. '
                        : 'Belum pernah tercatat berjalan sama sekali. ')
                    .'Hubungi penyedia hosting dan sebutkan bahwa cron job situs ini berhenti.',
            ];
        }

        /*
         * Data contoh (KEP-035) hanya untuk memperlihatkan tampilan ke klien.
         * Kalau lupa dihapus, halaman depan memuat testimoni dan portofolio
         * karangan, dan itu sama dengan ulasan palsu di depan pelanggan.
         */
        $contohPortofolio = Portofolio::where('contoh', true)->count();
        $contohTestimoni = Testimoni::where('contoh', true)->count();

        if ($contohPortofolio + $contohTestimoni > 0) {
            $masalah[] = [
                'judul' => 'Halaman depan masih memuat data contoh',
                'isi' => $contohPortofolio.' portofolio dan '.$contohTestimoni.' testimoni di halaman '
                    .'depan adalah contoh karangan, bukan pekerjaan dan pelanggan sungguhan. '
                    .'Hapus di menu Portofolio atau Testimoni sebelum website dibuka untuk umum, '
                    .'lalu isi dengan yang asli.',
            ];
        }

        if (($n = $this->pekerjaanGagal()) > 0) {
            $masalah[] = [
                'judul' => $n.' tugas latar gagal dijalankan',
                'isi' => 'Umumnya email yang gagal terkirim. Periksa daftar email di detail '
                    .'pesanan terkait, lalu kabari pelanggannya lewat WhatsApp.',
            ];
        }

        return $masalah;
    }
}
