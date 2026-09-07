<?php

namespace App\Services\Pembayaran;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Midtrans Snap.
 *
 * Dipilih lebih dulu karena QRIS sudah termasuk tanpa integrasi terpisah, dan
 * QRIS hampir wajib untuk pasar ini (docs/07). Xendit bisa menggantikannya
 * dengan mengimplementasikan GerbangPembayaran, tanpa menyentuh alur pesanan.
 */
class Midtrans implements GerbangPembayaran
{
    public function __construct(private array $konfig) {}

    public function aktif(): bool
    {
        return filled($this->konfig['server_key'] ?? null);
    }

    public function nama(): string
    {
        return 'Midtrans';
    }

    public function buatTransaksi(Payment $payment): string
    {
        $payment->loadMissing('order.customer');
        $order = $payment->order;

        // Midtrans menuntut order_id unik untuk SETIAP transaksi. Satu pesanan
        // bisa punya beberapa pembayaran (DP lalu pelunasan), jadi id pembayaran
        // ikut disertakan, bukan hanya kode pesanan.
        $ref = $order->kode.'-P'.$payment->id;

        $respons = Http::withBasicAuth($this->konfig['server_key'], '')
            ->acceptJson()
            ->timeout(20)
            ->post($this->endpoint(), [
                'transaction_details' => [
                    'order_id' => $ref,
                    'gross_amount' => $payment->jumlah,
                ],
                'customer_details' => [
                    'first_name' => $order->customer->nama,
                    'email' => $order->customer->email,
                    'phone' => $order->customer->no_hp,
                ],
                'item_details' => [[
                    'id' => (string) $payment->id,
                    'price' => $payment->jumlah,
                    'quantity' => 1,
                    'name' => mb_substr($payment->jenis->label().' '.$order->kode, 0, 50),
                ]],
                'callbacks' => [
                    'finish' => route('lacak.token', $order->access_token),
                ],
                'expiry' => [
                    'unit' => 'hour',
                    'duration' => max(1, (int) setting('batas_bayar_jam', 24)),
                ],
            ]);

        if ($respons->failed()) {
            throw new RuntimeException(
                'Midtrans menolak permintaan: '.($respons->json('error_messages.0') ?? $respons->status())
            );
        }

        $url = $respons->json('redirect_url');

        if (! $url) {
            throw new RuntimeException('Midtrans tidak mengembalikan URL pembayaran.');
        }

        $payment->update([
            'provider' => 'midtrans',
            'provider_ref' => $ref,
            'payload_json' => ['snap_token' => $respons->json('token')],
        ]);

        return $url;
    }

    public function bacaNotifikasi(array $payload): ?array
    {
        $ref = $payload['order_id'] ?? null;
        $status = $payload['transaction_status'] ?? null;
        $tandaTangan = $payload['signature_key'] ?? null;

        if (! $ref || ! $status || ! $tandaTangan) {
            return null;
        }

        // Notifikasi datang dari internet terbuka, jadi tanda tangannya wajib
        // dicocokkan sebelum apa pun disentuh. Tanpa ini, siapa saja bisa
        // menandai pesanan orang lain sebagai lunas.
        $harusnya = hash('sha512',
            $ref
            .($payload['status_code'] ?? '')
            .($payload['gross_amount'] ?? '')
            .$this->konfig['server_key']
        );

        if (! hash_equals($harusnya, (string) $tandaTangan)) {
            return null;
        }

        $terjemahan = match ($status) {
            'capture', 'settlement' => PaymentStatus::LUNAS,
            'pending' => PaymentStatus::MENUNGGU,
            'deny', 'cancel', 'failure' => PaymentStatus::GAGAL,
            'expire' => PaymentStatus::KADALUARSA,
            default => null,
        };

        // Kartu kredit yang ter-capture tapi ditandai berisiko tinggi belum
        // boleh dianggap lunas; biarkan kasir yang memutuskan.
        if ($status === 'capture' && ($payload['fraud_status'] ?? 'accept') !== 'accept') {
            $terjemahan = PaymentStatus::MENUNGGU;
        }

        return $terjemahan ? ['ref' => $ref, 'status' => $terjemahan] : null;
    }

    private function endpoint(): string
    {
        return $this->konfig['produksi']
            ? $this->konfig['endpoint']['produksi']
            : $this->konfig['endpoint']['sandbox'];
    }
}
