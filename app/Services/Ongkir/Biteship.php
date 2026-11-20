<?php

namespace App\Services\Ongkir;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Biteship: hitung ongkir sekaligus pelacakan paket (KEP-057).
 *
 * Dipilih karena satu penyedia mengerjakan keduanya, dibayar per panggilan
 * tanpa langganan bulanan, dan pelacakannya bisa dipakai untuk resi yang dibuat
 * sendiri di konter JNE atau J&T. Toko mengantar paketnya sendiri, jadi
 * penyedia yang hanya bisa melacak paket yang dipesan lewat mereka tidak cocok.
 *
 * Seluruh kegagalan di sini ditelan dan dicatat, bukan dilempar ke atas:
 * checkout yang gagal karena API pihak ketiga sedang mati adalah pesanan yang
 * hilang, sementara ongkir yang dihitung admin sudah jadi kebiasaan toko.
 */
class Biteship implements PenyediaOngkir
{
    public function __construct(
        private ?string $kunci,
        private string $url,
        private ?string $areaAsal,
        private array $kurir,
    ) {}

    public function aktif(): bool
    {
        return filled($this->kunci) && filled($this->areaAsal);
    }

    public function tarif(string $areaTujuan, int $beratGram): array
    {
        if (! $this->aktif() || blank($areaTujuan) || $beratGram < 1) {
            return [];
        }

        // Tarif untuk tujuan dan berat yang sama tidak berubah tiap menit.
        // Menyimpannya sebentar menekan biaya panggilan sekaligus mempercepat
        // halaman, dan satu jam masih jauh lebih segar daripada daftar tarif
        // yang ditempel di dinding toko.
        $kunciCache = 'ongkir:'.$areaTujuan.':'.$beratGram.':'.implode(',', $this->kurir);

        return Cache::remember($kunciCache, now()->addHour(), function () use ($areaTujuan, $beratGram) {
            $jawaban = $this->panggil('post', '/v1/rates/couriers', [
                'origin_area_id' => $this->areaAsal,
                'destination_area_id' => $areaTujuan,
                'couriers' => implode(',', $this->kurir),
                'items' => [[
                    'name' => 'Pesanan',
                    'value' => 0,
                    'weight' => $beratGram,
                    'quantity' => 1,
                ]],
            ]);

            return collect($jawaban['pricing'] ?? [])
                ->map(fn (array $t) => new TarifKirim(
                    kurir: (string) ($t['courier_code'] ?? ''),
                    layanan: (string) ($t['courier_service_code'] ?? ''),
                    nama: $this->namaLayanan($t),
                    harga: (int) ($t['price'] ?? 0),
                    estimasi: $this->estimasi($t),
                ))
                ->filter(fn (TarifKirim $t) => $t->harga > 0 && $t->kurir !== '')
                ->values()
                ->all();
        });
    }

    public function lacak(string $noResi, string $kurir): ?StatusPaket
    {
        if (! $this->aktif() || blank($noResi) || blank($kurir)) {
            return null;
        }

        $jawaban = $this->panggil('get', '/v1/trackings/'.urlencode($noResi).'/couriers/'.urlencode($kurir));

        if ($jawaban === [] || ! isset($jawaban['status'])) {
            return null;
        }

        $riwayat = collect($jawaban['history'] ?? [])
            ->map(fn (array $h) => [
                'waktu' => $h['updated_at'] ?? null,
                'keterangan' => (string) ($h['note'] ?? $h['status'] ?? ''),
            ])
            ->filter(fn (array $h) => $h['keterangan'] !== '')
            ->values()
            ->all();

        return new StatusPaket(
            status: (string) $jawaban['status'],
            keterangan: $jawaban['note'] ?? null,
            riwayat: $riwayat,
            diterima: in_array(strtolower((string) $jawaban['status']), ['delivered', 'received'], true),
        );
    }

    /**
     * Mencari area butuh kunci saja, BUKAN `aktif()` penuh: `aktif()` juga
     * mensyaratkan area asal sudah terisi, padahal pencarian ini justru yang
     * dipakai mengisi area asal itu sendiri pertama kali (dari Pengaturan)
     * maupun area tujuan pelanggan saat checkout.
     *
     * Berlapis (KEP-155): Biteship hanya mengenali teks tertentu. "Wonosari
     * Gunungkidul" kosong karena namanya "Gunung Kidul", alamat lengkap dengan
     * nama jalan kosong, dan "Solo" bukan nama resminya. Bila satu cara gagal,
     * cara berikutnya dicoba: kode pos, teks utuh, teks dipisah koma, lalu
     * kata demi kata yang diurutkan menurut kecocokan dengan kata lainnya.
     */
    public function cariArea(string $kata): array
    {
        if (blank($this->kunci) || mb_strlen(trim($kata)) < 3) {
            return [];
        }

        $kataPenting = $this->kataPenting($kata);

        // Kode pos lima angka paling pasti, di mana pun letaknya di alamat.
        if (preg_match('/\b(\d{5})\b/', $kata, $m)) {
            $hasil = $this->areaDari($m[1]);

            if ($hasil !== []) {
                return $this->urutkan($hasil, [...$kataPenting, $m[1]]);
            }
        }

        if ($kataPenting === []) {
            return [];
        }

        $percobaan = array_values(array_unique([
            implode(' ', $kataPenting),
            implode(', ', $kataPenting),
            ...collect($kataPenting)->filter(fn (string $k) => mb_strlen($k) >= 4)->sortByDesc(fn (string $k) => mb_strlen($k))->all(),
        ]));

        foreach (array_slice($percobaan, 0, self::MAKS_PERCOBAAN_AREA) as $teks) {
            $hasil = $this->areaDari($teks);

            if ($hasil !== []) {
                return $this->urutkan($hasil, $kataPenting);
            }
        }

        return [];
    }

    private const MAKS_PERCOBAAN_AREA = 5;

    /** Nama populer ke nama resmi yang dipakai Biteship. */
    private const ALIAS_DAERAH = [
        'solo' => 'surakarta', 'jogja' => 'yogyakarta', 'jogjakarta' => 'yogyakarta', 'yogya' => 'yogyakarta',
        'djogja' => 'yogyakarta', 'gunungkidul' => 'gunung kidul', 'kulonprogo' => 'kulon progo',
        'jaksel' => 'jakarta selatan', 'jakbar' => 'jakarta barat', 'jaktim' => 'jakarta timur',
        'jakut' => 'jakarta utara', 'jakpus' => 'jakarta pusat',
    ];

    /** Bagian alamat yang tidak pernah menjadi nama area. */
    private const KATA_ALAMAT = [
        'jl', 'jln', 'jalan', 'gg', 'gang', 'no', 'nomor', 'rt', 'rw', 'blok', 'ds', 'desa', 'dsn', 'dusun',
        'kel', 'kelurahan', 'kec', 'kecamatan', 'kab', 'kabupaten', 'kota', 'prov', 'provinsi', 'perum',
        'perumahan', 'komplek', 'kompleks', 'raya', 'dan', 'di', 'kode', 'pos',
    ];

    /** @return list<string> kata yang mungkin nama daerah, alias sudah diganti */
    private function kataPenting(string $kata): array
    {
        $kata = (string) preg_replace('/[^\p{L}\s]+/u', ' ', mb_strtolower($kata));

        return collect(preg_split('/\s+/u', trim($kata)) ?: [])
            ->filter(fn (string $k) => mb_strlen($k) >= 3 && ! in_array($k, self::KATA_ALAMAT, true))
            ->map(fn (string $k) => self::ALIAS_DAERAH[$k] ?? $k)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Yang paling banyak memuat kata lain dari pencarian di atas. Dibanding
     * tanpa spasi, supaya "gunungkidul" cocok dengan "Gunung Kidul".
     *
     * @param  list<array{kode: string, nama: string, kode_pos: string}>  $area
     * @param  list<string>  $kata
     * @return list<array{kode: string, nama: string, kode_pos: string}>
     */
    private function urutkan(array $area, array $kata): array
    {
        $rapat = fn (string $s) => str_replace(' ', '', mb_strtolower($s));

        return collect($area)
            ->sortByDesc(fn (array $a) => collect($kata)->filter(fn (string $k) => str_contains($rapat($a['nama']), $rapat($k)))->count())
            ->values()
            ->all();
    }

    /** @return list<array{kode: string, nama: string, kode_pos: string}> */
    private function areaDari(string $teks): array
    {
        $jawaban = Cache::remember('area:'.mb_strtolower(trim($teks)), now()->addDay(),
            fn () => $this->panggil('get', '/v1/maps/areas', [
                'countries' => 'ID',
                'input' => trim($teks),
                'type' => 'single',
            ]));

        return collect($jawaban['areas'] ?? [])
            ->map(fn (array $a) => [
                'kode' => (string) ($a['id'] ?? ''),
                'nama' => (string) ($a['name'] ?? ''),
                'kode_pos' => (string) ($a['postal_code'] ?? ''),
            ])
            ->filter(fn (array $a) => $a['kode'] !== '')
            ->values()
            ->all();
    }

    /**
     * Nama layanan kadang sudah memuat nama kurirnya ("JNE" + "JNE Trucking"),
     * kadang tidak ("J&T" + "EZ").
     *
     * @param  array<string, mixed>  $t
     */
    private function namaLayanan(array $t): string
    {
        $kurir = trim((string) ($t['courier_name'] ?? ''));
        $layanan = trim((string) ($t['courier_service_name'] ?? ''));

        if ($kurir !== '' && str_starts_with(mb_strtolower($layanan), mb_strtolower($kurir))) {
            return $layanan;
        }

        return trim($kurir.' '.$layanan);
    }

    /**
     * Biteship menjawab dalam bahasa Inggris ("1 - 2 days", "1 - 1 days"). Yang
     * dibaca pembeli: "1-2 hari" dan "1 hari". Bentuk lain diteruskan apa adanya.
     *
     * @param  array<string, mixed>  $t
     */
    private function estimasi(array $t): ?string
    {
        $teks = trim((string) ($t['duration']
            ?? trim(($t['shipment_duration_range'] ?? '').' '.($t['shipment_duration_unit'] ?? ''))));

        if (! preg_match('/^(\d+)\s*-\s*(\d+)\s*(days?|hours?)$/i', $teks, $m)) {
            return $teks !== '' ? $teks : null;
        }

        $satuan = str_starts_with(strtolower($m[3]), 'hour') ? 'jam' : 'hari';

        return ($m[1] === $m[2] ? $m[1] : $m[1].'-'.$m[2]).' '.$satuan;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function panggil(string $cara, string $jalur, array $data = []): array
    {
        try {
            $permintaan = Http::withHeaders(['authorization' => (string) $this->kunci])
                ->timeout(8)
                ->retry(2, 200);

            $jawaban = $cara === 'post'
                ? $permintaan->post($this->url.$jalur, $data)
                : $permintaan->get($this->url.$jalur, $data);

            if ($jawaban->failed()) {
                Log::warning('Biteship menolak permintaan', [
                    'jalur' => $jalur,
                    'kode' => $jawaban->status(),
                    'pesan' => $jawaban->json('error') ?? $jawaban->body(),
                ]);

                return [];
            }

            return $jawaban->json() ?? [];
        } catch (Throwable $e) {
            // Jaringan hosting putus atau penyedianya sedang mati. Dicatat
            // supaya terlihat di log, tetapi tidak menggagalkan pemesanan.
            Log::warning('Biteship tidak bisa dihubungi', [
                'jalur' => $jalur,
                'pesan' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
