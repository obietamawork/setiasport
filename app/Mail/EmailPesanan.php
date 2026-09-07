<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
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
    ) {}

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
