<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * Pengirim email lewat HTTP API Resend, bukan SMTP.
 *
 * Ini alasan teknis utama Resend dipilih (KEP-004): shared hosting sering
 * memblokir port SMTP 25/465/587, tapi hampir tidak pernah memblokir HTTPS
 * keluar. Ditulis sendiri alih-alih menambah paket, supaya id pesan yang
 * dikembalikan Resend bisa langsung dipakai mencocokkan webhook status.
 */
class ResendTransport extends AbstractTransport
{
    public function __construct(private string $apiKey)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'resend';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $envelope = $message->getEnvelope();

        $respons = Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout(20)
            ->post('https://api.resend.com/emails', array_filter([
                'from' => $this->alamat($envelope->getSender()),
                'to' => $this->daftarAlamat($envelope->getRecipients()),
                'reply_to' => $this->daftarAlamat($email->getReplyTo()) ?: null,
                'subject' => $email->getSubject(),
                'html' => $email->getHtmlBody(),
                'text' => $email->getTextBody(),
            ]));

        if ($respons->failed()) {
            throw new TransportException(
                'Resend menolak pengiriman: '.($respons->json('message') ?? $respons->status())
            );
        }

        // Id inilah yang nanti dicocokkan dengan webhook delivered/opened/bounced.
        if ($id = $respons->json('id')) {
            $message->setMessageId($id);
        }
    }

    private function alamat(Address $alamat): string
    {
        return $alamat->getName()
            ? sprintf('%s <%s>', $alamat->getName(), $alamat->getAddress())
            : $alamat->getAddress();
    }

    /** @param  array<int, Address>  $daftar */
    private function daftarAlamat(array $daftar): array
    {
        return array_map(fn (Address $a) => $a->getAddress(), $daftar);
    }
}
