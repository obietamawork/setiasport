<?php

namespace App\Services;

use App\Enums\EmailStatus;
use App\Mail\EmailPesanan;
use App\Models\EmailLog;
use App\Models\Order;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Email adalah satu-satunya kanal otomatis ke pelanggan (KEP-004).
 *
 * Setiap pengiriman dicatat di email_logs. Bukan untuk arsip, melainkan supaya
 * admin tahu kapan email gagal sampai dan urusannya harus diambil alih manusia
 * lewat WhatsApp, tanpa WhatsApp API, itulah jaring pengamannya.
 */
class PengirimEmail
{
    private const SUBJEK = [
        'order-dibuat' => 'Pesanan %s diterima: menunggu pembayaran',
        'pembayaran-diterima' => 'Pembayaran pesanan %s sudah kami terima',
        'pesanan-siap' => 'Pesanan %s siap diambil di toko',
        'pengingat-ambil' => 'Pengingat: pesanan %s masih menunggu diambil',
    ];

    public function kirim(Order $order, string $template): EmailLog
    {
        $subjek = sprintf(self::SUBJEK[$template], $order->kode);
        $ke = $order->customer->email;

        // Baris log dibuat lebih dulu supaya id-nya bisa ikut sebagai header,
        // dan id pesan dari Resend punya tempat untuk dicatat balik.
        $log = EmailLog::create([
            'order_id' => $order->id,
            'ke' => $ke,
            'template' => $template,
            'subjek' => $subjek,
            'status' => EmailStatus::TERKIRIM,
            'dikirim_pada' => now(),
        ]);

        try {
            Mail::to($ke)->queue(new EmailPesanan($order, $template, $subjek, $log->id));
        } catch (Throwable $e) {
            // Kegagalan kirim tidak boleh menggagalkan checkout atau verifikasi
            // pembayaran. Dicatat, lalu muncul di dashboard untuk ditindaklanjuti
            // manual lewat WhatsApp.
            $log->update([
                'status' => EmailStatus::GAGAL,
                'error' => mb_substr($e->getMessage(), 0, 250),
            ]);

            report($e);
        }

        return $log->refresh();
    }

    /**
     * Teks siap tempel untuk WhatsApp. Bukan otomatisasi, admin yang menekan
     * kirim, tapi nol biaya dan realistis untuk pasar ini (docs/03).
     */
    public function teksWhatsApp(Order $order, string $template): string
    {
        $tautan = route('lacak.token', $order->access_token);
        $nama = $order->customer->nama;

        return match ($template) {
            'order-dibuat' => "Halo {$nama}, pesanan {$order->kode} sudah kami terima. "
                ."Total ".rupiah($order->total).". Rincian dan cara bayar ada di sini: {$tautan}",

            'pembayaran-diterima' => "Halo {$nama}, pembayaran untuk pesanan {$order->kode} sudah kami terima. "
                .($order->sisa_tagihan > 0
                    ? "Sisa ".rupiah($order->sisa_tagihan)." dibayar saat pengambilan. "
                    : '')
                ."Pantau progresnya di sini: {$tautan}",

            'pesanan-siap' => "Halo {$nama}, pesanan {$order->kode} sudah siap diambil di toko. "
                ."Kode ambil: {$order->kode_ambil}. "
                .($order->sisa_tagihan > 0 ? "Mohon bawa ".rupiah($order->sisa_tagihan)." untuk pelunasan. " : '')
                ."Detail: {$tautan}",

            default => "Halo {$nama}, pesanan {$order->kode} masih menunggu diambil di toko. "
                ."Kode ambil: {$order->kode_ambil}. Detail: {$tautan}",
        };
    }
}
