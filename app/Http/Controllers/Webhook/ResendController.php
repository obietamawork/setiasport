<?php

namespace App\Http\Controllers\Webhook;

use App\Enums\EmailStatus;
use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Status pengiriman email dari Resend.
 *
 * Inilah yang membuat sistem tahu kapan harus menyerah pada email dan
 * menyerahkan urusan ke manusia: bounce dan gagal muncul di dashboard sebagai
 * daftar yang harus ditindaklanjuti lewat WhatsApp (docs/03).
 */
class ResendController extends Controller
{
    private const PETA = [
        'email.sent' => EmailStatus::TERKIRIM,
        'email.delivered' => EmailStatus::DELIVERED,
        'email.opened' => EmailStatus::OPENED,
        'email.bounced' => EmailStatus::BOUNCED,
        'email.complained' => EmailStatus::BOUNCED,
        'email.delivery_delayed' => EmailStatus::TERKIRIM,
    ];

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->tandaTanganSah($request)) {
            return response()->json(['pesan' => 'Tanda tangan tidak sah.'], 403);
        }

        $status = self::PETA[$request->input('type')] ?? null;
        $id = $request->input('data.email_id');

        if (! $status || ! $id) {
            // Jenis peristiwa yang belum kami tangani bukan kesalahan pengirim.
            return response()->json(['pesan' => 'Diabaikan.']);
        }

        $log = EmailLog::where('resend_id', $id)->first();

        if (! $log) {
            return response()->json(['pesan' => 'Log tidak ditemukan.']);
        }

        // Peristiwa Resend bisa datang tidak berurutan. Status yang lebih maju
        // tidak boleh dimundurkan oleh notifikasi yang datang terlambat.
        if ($this->peringkat($status) > $this->peringkat($log->status)) {
            $log->update(['status' => $status]);
        }

        return response()->json(['pesan' => 'Diterima.']);
    }

    private function peringkat(EmailStatus $status): int
    {
        return match ($status) {
            EmailStatus::GAGAL => 0,
            EmailStatus::TERKIRIM => 1,
            EmailStatus::DELIVERED => 2,
            EmailStatus::OPENED => 3,
            // Bounce adalah kabar terpenting dan tidak boleh tertimpa apa pun.
            EmailStatus::BOUNCED => 9,
        };
    }

    /** Svix, seperti dipakai Resend: id + timestamp + payload, ditandatangani HMAC-SHA256. */
    private function tandaTanganSah(Request $request): bool
    {
        $rahasia = config('services.resend.webhook_secret');

        if (blank($rahasia)) {
            // Tanpa rahasia, endpoint ini tidak boleh menerima apa pun: siapa
            // saja bisa memalsukan status email.
            return false;
        }

        $id = $request->header('svix-id');
        $waktu = $request->header('svix-timestamp');
        $kiriman = $request->header('svix-signature');

        if (! $id || ! $waktu || ! $kiriman) {
            return false;
        }

        // Menolak notifikasi lama menutup celah pengulangan rekaman.
        if (abs(time() - (int) $waktu) > 300) {
            return false;
        }

        $kunci = base64_decode(str_replace('whsec_', '', $rahasia));
        $harusnya = base64_encode(hash_hmac('sha256', "{$id}.{$waktu}.".$request->getContent(), $kunci, true));

        foreach (explode(' ', $kiriman) as $bagian) {
            [$versi, $nilai] = array_pad(explode(',', $bagian, 2), 2, '');

            if ($versi === 'v1' && hash_equals($harusnya, $nilai)) {
                return true;
            }
        }

        return false;
    }
}
