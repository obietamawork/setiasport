<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\OrderItemRoster;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nama punggung, nomor, dan ukuran per pemain, inti pekerjaan toko jersey.
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

        if ($item->hargaPerUkuran()) {
            $this->simpanPerUkuran($item, $baris);

            return;
        }

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

        // Satu baris boleh mewakili beberapa potong (KEP-039), jadi yang
        // dihitung jumlah potongnya, bukan jumlah barisnya.
        return (int) $item->rosters()->sum('jumlah') === $item->qty;
    }

    /**
     * Daftar pemain untuk pesanan yang harganya per ukuran (KEP-039).
     *
     * Ukuran di sini sudah ditagihkan, jadi yang boleh berubah hanya nama dan
     * nomornya. Barisnya sendiri boleh dipecah atau digabung, misalnya satu
     * baris "tanpa nama 2 pcs" jadi dua baris bernama, ASALKAN jumlah potong
     * per ukuran tetap sama. Begitu susunan ukurannya berubah, tagihannya ikut
     * berubah, dan itu pekerjaan admin lewat penyesuaian biaya, bukan sesuatu
     * yang boleh terjadi diam-diam dari halaman pelanggan.
     *
     * @param  array<int, array<string, mixed>>  $baris
     */
    private function simpanPerUkuran(OrderItem $item, array $baris): void
    {
        $lama = $item->rosters()->orderBy('urutan')->get();

        $harga = $lama->mapWithKeys(fn (OrderItemRoster $r) => [
            $this->kunciUkuran($r->kategori?->value, $r->ukuran) => $r->harga_satuan,
        ]);

        $bersih = $this->validasiPerUkuran($baris, $harga->keys()->all());

        $sebelum = $this->susunan($lama->map(fn (OrderItemRoster $r) => [
            'kunci' => $this->kunciUkuran($r->kategori?->value, $r->ukuran),
            'jumlah' => $r->jumlah,
        ])->all());

        if ($sebelum !== $this->susunan($bersih)) {
            throw ValidationException::withMessages([
                'roster' => 'Jumlah dan ukurannya harus tetap sama seperti yang dipesan. '
                    .'Nama dan nomor boleh diubah. Untuk menambah atau mengurangi pesanan, hubungi toko.',
            ]);
        }

        DB::transaction(function () use ($item, $bersih, $harga) {
            $item->rosters()->delete();

            foreach ($bersih as $urutan => $b) {
                [$kategori, $ukuran] = $this->pecahKunci($b['kunci']);

                $item->rosters()->create([
                    'urutan' => $urutan + 1,
                    'kategori' => $kategori,
                    'nama_punggung' => $b['nama_punggung'],
                    'nomor' => $b['nomor'],
                    'ukuran' => $ukuran,
                    'jumlah' => $b['jumlah'],
                    'harga_satuan' => $harga[$b['kunci']],
                    'catatan' => $b['catatan'],
                ]);
            }
        });
    }

    /**
     * Jumlah potong per ukuran, diurutkan supaya dua daftar yang isinya sama
     * tetapi urutan barisnya berbeda tetap dianggap sama.
     *
     * @param  array<int, array{kunci:string, jumlah:int}>  $baris
     * @return array<string, int>
     */
    private function susunan(array $baris): array
    {
        $hasil = [];

        foreach ($baris as $b) {
            $hasil[$b['kunci']] = ($hasil[$b['kunci']] ?? 0) + $b['jumlah'];
        }

        ksort($hasil);

        return $hasil;
    }

    private function kunciUkuran(?string $kategori, string $ukuran): string
    {
        return ($kategori ?? '').'|'.$ukuran;
    }

    /** @return array{0: ?string, 1: string} */
    private function pecahKunci(string $kunci): array
    {
        [$kategori, $ukuran] = explode('|', $kunci, 2);

        return [$kategori !== '' ? $kategori : null, $ukuran];
    }

    /**
     * @param  array<int, array<string, mixed>>  $baris
     * @param  list<string>  $ukuranDipesan
     * @return array<int, array{kunci:string, jumlah:int, nama_punggung:?string, nomor:?string, catatan:?string}>
     */
    private function validasiPerUkuran(array $baris, array $ukuranDipesan): array
    {
        $bersih = [];
        $nomorTerpakai = [];

        foreach ($baris as $i => $b) {
            $jumlah = (int) ($b['jumlah'] ?? 1);
            $nama = trim((string) ($b['nama_punggung'] ?? ''));
            $nomor = trim((string) ($b['nomor'] ?? ''));
            $kunci = $this->kunciUkuran(
                trim((string) ($b['kategori'] ?? '')) ?: null,
                trim((string) ($b['ukuran'] ?? '')),
            );

            if ($jumlah < 1) {
                continue;
            }

            if (! in_array($kunci, $ukuranDipesan, true)) {
                throw ValidationException::withMessages([
                    "roster.{$i}.ukuran" => 'Ukuran baris ke-'.($i + 1).' tidak ada dalam pesanan ini.',
                ]);
            }

            if ($nomor !== '') {
                if (! ctype_digit($nomor)) {
                    throw ValidationException::withMessages([
                        "roster.{$i}.nomor" => 'Nomor punggung baris ke-'.($i + 1).' harus berupa angka.',
                    ]);
                }

                // Satu nomor punggung tidak bisa dipakai beberapa potong sekaligus.
                if ($jumlah > 1) {
                    throw ValidationException::withMessages([
                        "roster.{$i}.nomor" => 'Baris ke-'.($i + 1).' berisi '.$jumlah
                            .' potong, jadi tidak bisa punya satu nomor punggung. Pisahkan barisnya.',
                    ]);
                }

                if (isset($nomorTerpakai[$nomor])) {
                    throw ValidationException::withMessages([
                        "roster.{$i}.nomor" => "Nomor {$nomor} dipakai dua kali "
                            .'(baris ke-'.($nomorTerpakai[$nomor] + 1).' dan ke-'.($i + 1).').',
                    ]);
                }

                $nomorTerpakai[$nomor] = $i;
            }

            $bersih[] = [
                'kunci' => $kunci,
                'jumlah' => $jumlah,
                'nama_punggung' => $nama !== '' ? mb_substr($nama, 0, 60) : null,
                'nomor' => $nomor !== '' ? mb_substr($nomor, 0, 5) : null,
                'catatan' => trim((string) ($b['catatan'] ?? '')) ?: null,
            ];
        }

        return $bersih;
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
                // setelah jersey tercetak, mahal, dan tidak bisa dijual ulang.
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
