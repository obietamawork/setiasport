<?php

namespace App\Mail;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tautan atur ulang kata sandi pelanggan (KEP-157), diminta pelanggan dari
 * halaman lupa kata sandi atau dikirim staf dari menu Pelanggan.
 */
class AturUlangSandi extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Customer $pelanggan,
        public string $tautan,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Atur ulang kata sandi akun '.setting('toko_nama', 'Setia Sport'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.atur-ulang-sandi');
    }
}
