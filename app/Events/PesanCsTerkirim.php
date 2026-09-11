<?php

namespace App\Events;

use App\Models\PesanCs;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Kabar "ada pesan baru" untuk satu pihak (KEP-030).
 *
 * ShouldBroadcastNow, bukan antrian: di shared hosting antrian dijalankan cron
 * sekali per menit, dan chat yang tertunda semenit bukan chat. Pesannya sendiri
 * sudah tersimpan sebelum event ini dibuat, jadi kalau Pusher gagal, yang
 * hilang hanya kecepatannya; widget menyusul lewat polling.
 *
 * Dua pihak menerima payload berbeda (pelanggan tidak melihat nama pribadi
 * staf), jadi event ini dibuat dua kali, sekali untuk tiap channel.
 */
class PesanCsTerkirim implements ShouldBroadcastNow
{
    /** @param  'pelanggan'|'staf'  $untuk */
    public function __construct(
        public PesanCs $pesan,
        public string $untuk,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel(
            $this->untuk === 'staf' ? 'cs.staf' : 'cs.percakapan.'.$this->pesan->percakapan_id
        )];
    }

    public function broadcastAs(): string
    {
        return 'pesan';
    }

    public function broadcastWith(): array
    {
        $data = ['pesan' => $this->pesan->untukKlien($this->untuk)];

        // Daftar di ruang CS diperbarui dengan bentuk baris yang sama persis
        // dengan yang dimuat server, jadi tidak perlu memuat ulang halaman.
        if ($this->untuk === 'staf') {
            $data['percakapan'] = $this->pesan->percakapan->untukDaftar($this->pesan);
        }

        return $data;
    }
}
