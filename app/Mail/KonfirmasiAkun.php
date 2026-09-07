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
 * Tautan konfirmasi pendaftaran. Inilah satu-satunya pembuktian bahwa yang
 * mendaftar benar-benar menguasai email tersebut (KEP-021).
 */
class KonfirmasiAkun extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Customer $pelanggan,
        public string $tautan,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Konfirmasi akun '.setting('toko_nama', 'Setia Sport'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.konfirmasi-akun');
    }
}
