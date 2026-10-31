<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\EmailLog;
use App\Models\Order;
use App\Services\PengirimEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Jalan keluar ketika kabar ke pelanggan tidak sampai (KEP-098).
 *
 * Email adalah satu-satunya kanal otomatis (KEP-004). Dulu panel hanya bisa
 * menunjukkan bahwa sebuah email gagal: alamat yang salah ketik tidak bisa
 * dibetulkan, email yang gagal saat penyedia email mati tidak bisa dikirim
 * ulang, dan peringatannya tidak pernah hilang walau pelanggannya sudah
 * dikabari lewat WhatsApp.
 */
class TindakLanjutController extends Controller
{
    public function __construct(private PengirimEmail $email) {}

    /** Email yang sama dikirim lagi, ke alamat pelanggan yang sekarang. */
    public function kirimUlang(Order $pesanan, EmailLog $log): RedirectResponse
    {
        abort_unless($log->order_id === $pesanan->id, 404);
        abort_unless($this->email->punyaTemplate($log->template), 422, 'Jenis email ini tidak bisa dikirim ulang.');

        if (blank($pesanan->customer->email)) {
            return back()->withErrors('Pelanggan ini tidak punya alamat email. Isi dulu di bagian Pelanggan.');
        }

        $baru = $this->email->kirim($pesanan, $log->template);
        $this->tandai($log);

        ActivityLog::catat('email.dikirim-ulang', $pesanan, ['template' => $log->template, 'ke' => $baru?->ke]);

        return back()->with('sukses', 'Email "'.$log->subjek.'" dikirim ulang ke '.$baru?->ke.'.');
    }

    /** Pelanggannya sudah dikabari lewat jalan lain, biasanya WhatsApp. */
    public function selesai(Order $pesanan, EmailLog $log): RedirectResponse
    {
        abort_unless($log->order_id === $pesanan->id, 404);

        $this->tandai($log);

        ActivityLog::catat('email.ditindaklanjuti', $pesanan, ['template' => $log->template]);

        return back()->with('sukses', 'Ditandai sudah ditangani. Email ini tidak tampil lagi di dashboard.');
    }

    /**
     * Membetulkan nama, nomor WhatsApp, atau email pelanggan. Berlaku untuk
     * semua pesanannya, karena datanya disimpan per pelanggan.
     *
     * Email akun yang sudah aktif tidak diubah dari sini: alamat itu sudah
     * terbukti milik pelanggan lewat tautan konfirmasi (KEP-021), dan dipakai
     * untuk masuk.
     */
    public function ubahKontak(Request $request, Order $pesanan): RedirectResponse
    {
        /** @var Customer $pelanggan */
        $pelanggan = $pesanan->customer;

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'no_hp' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('customers', 'email')->ignore($pelanggan->id)],
        ], [
            'email.unique' => 'Email ini sudah dipakai pelanggan lain di sistem, jadi tidak bisa dipasang di sini.',
        ], ['no_hp' => 'nomor WhatsApp']);

        $emailBaru = filled($data['email'] ?? null) ? trim($data['email']) : null;

        if ($pelanggan->akunAktif() && $emailBaru !== $pelanggan->email) {
            return back()->withErrors('Pelanggan ini punya akun, jadi emailnya tidak diubah dari panel. '
                .'Nama dan nomor WhatsApp-nya tetap bisa dibetulkan.');
        }

        $dari = $pelanggan->only(['nama', 'no_hp', 'email']);

        $pelanggan->update(['nama' => $data['nama'], 'no_hp' => $data['no_hp'], 'email' => $emailBaru]);

        ActivityLog::catat('pelanggan.kontak-diubah', $pesanan, [
            'dari' => $dari,
            'ke' => $pelanggan->only(['nama', 'no_hp', 'email']),
        ]);

        return back()->with('sukses', 'Kontak pelanggan disimpan.'
            .($dari['email'] !== $emailBaru && $emailBaru
                ? ' Email yang gagal bisa dikirim ulang dari bagian Email di bawah.'
                : ''));
    }

    private function tandai(EmailLog $log): void
    {
        $log->update([
            'ditindaklanjuti_pada' => now(),
            'ditindaklanjuti_oleh' => auth('web')->id(),
        ]);
    }
}
