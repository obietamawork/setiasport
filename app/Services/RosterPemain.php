<?php

namespace App\Services;

use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nama punggung, nomor, dan ukuran per pemain — inti pekerjaan toko jersey.
 *
 * Diisi pelanggan sendiri dan bisa diubah sampai desain disetujui. Kalau data
 * ini dibiarkan mengalir lewat WhatsApp, admin mengetik ulang, salah ketik,
 * dan jersey dicetak dua kali (docs/03).
 */
class RosterPemain
{
    /**
     * @param  array<int, array{nama_punggung?:string, nomor?:string, ukuran?:string, catatan?:string}>  $baris
     */
    public function simpan(OrderItem $item, array $baris): void
    {
        abort_if($item->rosterTerkunci(), 422,
            'Desain sudah disetujui, jadi daftar pemain terkunci. Hubungi toko kalau ada yang perlu diubah.');

        $bersih = $this->validasi($item, $baris);

        DB::transaction(function () use ($item, $bersih) {
            $item->rosters()->delete();

            foreach ($bersih as $urutan => $b) {
                $item->rosters()->create([
                    'urutan' => $urutan + 1,
                    'nama_punggung' => $b['nama_punggung'],
                    'nomor' => $b['nomor'],
                    'ukuran' => $b['ukuran'],
                    'catatan' => $b['catatan'],
                ]);
            }
        });
    }

    /** Apakah roster sudah layak dikirim ke produksi. */
    public function lengkap(OrderItem $item): bool
    {
        if (! $item->product->roster_wajib) {
            return true;
        }

        return $item->rosters()->count() === $item->qty;
    }

    /**
     * @return array<int, array{nama_punggung:?string, nomor:?string, ukuran:string, catatan:?string}>
     */
    private function validasi(OrderItem $item, array $baris): array
    {
        $bersih = [];
        $nomorTerpakai = [];

        foreach ($baris as $i => $b) {
            $ukuran = trim((string) ($b['ukuran'] ?? ''));
            $nama = trim((string) ($b['nama_punggung'] ?? ''));
            $nomor = trim((string) ($b['nomor'] ?? ''));

            // Baris yang benar-benar kosong dibuang tanpa protes.
            if ($ukuran === '' && $nama === '' && $nomor === '') {
                continue;
            }

            if ($ukuran === '') {
                throw ValidationException::withMessages([
                    "roster.{$i}.ukuran" => 'Ukuran baris ke-'.($i + 1).' belum diisi.',
                ]);
            }

            if ($nomor !== '') {
                if (! ctype_digit($nomor)) {
                    throw ValidationException::withMessages([
                        "roster.{$i}.nomor" => 'Nomor punggung baris ke-'.($i + 1).' harus berupa angka.',
                    ]);
                }

                // Dua pemain bernomor sama adalah kesalahan yang baru ketahuan
                // setelah jersey tercetak — mahal, dan tidak bisa dijual ulang.
                if (isset($nomorTerpakai[$nomor])) {
                    throw ValidationException::withMessages([
                        "roster.{$i}.nomor" => "Nomor {$nomor} dipakai dua kali "
                            .'(baris ke-'.($nomorTerpakai[$nomor] + 1).' dan ke-'.($i + 1).').',
                    ]);
                }

                $nomorTerpakai[$nomor] = $i;
            }

            $bersih[] = [
                'nama_punggung' => $nama !== '' ? mb_substr($nama, 0, 60) : null,
                'nomor' => $nomor !== '' ? mb_substr($nomor, 0, 5) : null,
                'ukuran' => mb_substr($ukuran, 0, 20),
                'catatan' => trim((string) ($b['catatan'] ?? '')) ?: null,
            ];
        }

        if (count($bersih) > $item->qty) {
            throw ValidationException::withMessages([
                'roster' => 'Ada '.count($bersih).' baris terisi, padahal pesanannya '
                    .$item->qty.' pcs. Hapus kelebihannya atau hubungi toko untuk menambah jumlah.',
            ]);
        }

        return $bersih;
    }
}
