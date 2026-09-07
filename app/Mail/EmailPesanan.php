<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Satu Mailable untuk seluruh email pesanan; yang membedakan hanya template.
 *
 * ShouldQueue penting: di shared hosting pengiriman berjalan lewat cron, dan
 * checkout tidak boleh menunggu jaringan Resend membalas.
 */
class EmailPesanan extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $template,
        public string $subjek,
        public ?int $logId = null,
    ) {}

    /**
     * Id baris email_logs ikut sebagai header, supaya id pesan yang dikembalikan
     * Resend bisa dicatat balik ke baris yang benar — dan webhook status nanti
     * punya sesuatu untuk dicocokkan.
     */
    public function headers(): Headers
    {
        return new Headers(text: $this->logId ? ['X-Log-Id' => (string) $this->logId] : []);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjek);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.'.$this->template,
            with: [
                'order' => $this->order->loadMissing(['customer', 'items.options', 'adjustments', 'payments']),
                'tautan' => route('lacak.token', $this->order->access_token),
            ],
        );
    }
}
