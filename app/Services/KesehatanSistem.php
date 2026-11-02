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

    /**
     * Hanya seminggu terakhir (KEP-098). Baris failed_jobs tidak pernah
     * terhapus dari panel, jadi tanpa batas ini satu email yang gagal saat
     * penyedia email sempat mati membuat peringatannya menyala selamanya.
     * Emailnya sendiri tetap tercatat gagal di pesanan terkait.
     */
    public function pekerjaanGagal(): int
    {
        return DB::table('failed_jobs')->where('failed_at', '>=', now()->subDays(7))->count();
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

        /*
         * Mode debug di produksi menampilkan jejak kode dan isi konfigurasi di
         * setiap halaman galat (KEP-105). Hanya terjadi kalau .env di hosting
         * salah salin dari .env.example, dan tidak ada gejala lain yang terlihat.
         */
        if (app()->isProduction() && config('app.debug')) {
            $masalah[] = [
                'judul' => 'Mode debug menyala di website yang sudah tayang',
                'isi' => 'Halaman galat menampilkan isi teknis website kepada siapa saja. '
                    .'Minta developer mengubah APP_DEBUG menjadi false di berkas .env hosting.',
            ];
        }

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
                'judul' => $n.' tugas latar gagal dijalankan minggu ini',
                'isi' => 'Umumnya email yang gagal terkirim. Daftarnya ada di bagian "Email tidak sampai" '
                    .'di bawah: kirim ulang dari halaman pesanannya, atau kabari pelanggannya lewat WhatsApp.',
            ];
        }

        return $masalah;
    }
}
