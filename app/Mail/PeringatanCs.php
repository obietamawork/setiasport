<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Chat pelanggan yang belum dibalas (KEP-030). Jaring pengaman untuk saat
 * tidak ada yang sedang membuka panel: bunyi dan badge hanya bekerja kalau
 * halamannya terbuka, email sampai di HP.
 *
 * Sengaja TIDAK antri (ShouldQueue): dikirim dari tugas terjadwal yang memang
 * sudah berjalan di latar, dan menitipkannya ke antrian hanya menambah semenit.
 */
class PeringatanCs extends Mailable
{
    use Queueable, SerializesModels;

    /** @param  Collection<int, \App\Models\PercakapanCs>  $percakapan */
    public function __construct(public Collection $percakapan) {}

    public function envelope(): Envelope
    {
        $n = $this->percakapan->count();

        return new Envelope(subject: $n === 1
            ? 'Chat dari '.$this->percakapan->first()->nama.' belum dibalas'
            : $n.' chat pelanggan belum dibalas');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.peringatan-cs');
    }
}
