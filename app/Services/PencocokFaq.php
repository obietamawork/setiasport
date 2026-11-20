<?php

namespace App\Services;

use App\Models\FaqBot;
use Illuminate\Support\Collection;

/**
 * Lapis deterministik chatbot CS (KEP-151): mencocokkan pertanyaan dengan FAQ
 * lewat frasa kunci, tanpa AI. Gratis, seketika, dan selalu bisa ditebak:
 * jawaban yang keluar persis jawaban yang ditulis pemilik toko.
 *
 * Skor = jumlah kata dari frasa kunci yang muncul utuh di pertanyaan (frasa
 * yang lebih panjang lebih khusus, jadi lebih berat), ditambah sedikit bobot
 * dari kata yang sama dengan kalimat pertanyaan FAQ-nya. Tanpa satu pun frasa
 * kunci, FAQ itu tidak dianggap cocok.
 */
class PencocokFaq
{
    /** Singkatan dan ejaan sehari-hari yang paling sering muncul di chat. */
    private const SINGKATAN = [
        'brp' => 'berapa', 'brapa' => 'berapa', 'gmn' => 'bagaimana', 'gimana' => 'bagaimana', 'bgmn' => 'bagaimana',
        'gmna' => 'bagaimana', 'tdk' => 'tidak', 'gak' => 'tidak', 'ga' => 'tidak', 'gk' => 'tidak', 'nggak' => 'tidak',
        'ngga' => 'tidak', 'enggak' => 'tidak', 'udh' => 'sudah', 'udah' => 'sudah', 'sdh' => 'sudah', 'blm' => 'belum',
        'bs' => 'bisa', 'bsa' => 'bisa', 'dmn' => 'dimana', 'tf' => 'transfer', 'trf' => 'transfer', 'pesen' => 'pesan',
        'psn' => 'pesan', 'hrg' => 'harga', 'krm' => 'kirim', 'jg' => 'juga', 'yg' => 'yang', 'utk' => 'untuk',
        'dgn' => 'dengan', 'sy' => 'saya', 'aq' => 'aku', 'mksh' => 'makasih', 'trims' => 'terima kasih',
        'makasi' => 'makasih', 'thx' => 'thanks', 'msh' => 'masih', 'trs' => 'terus', 'kpn' => 'kapan',
        'skrg' => 'sekarang', 'y' => 'ya', 'dr' => 'dari', 'kmrn' => 'kemarin', 'almt' => 'alamat', 'tko' => 'toko',
        'tp' => 'tapi', 'sm' => 'sama', 'lg' => 'lagi', 'aja' => 'saja', 'bgt' => 'banget', 'krn' => 'karena',
        'norek' => 'rekening',
        // Bahasa Jawa sehari-hari (uji tekanan KEP-153): supaya aturan pasti
        // seperti "sudah transfer" dan "rusak" ikut menangkapnya.
        'wis' => 'sudah', 'uwis' => 'sudah', 'wes' => 'sudah', 'durung' => 'belum', 'urung' => 'belum',
        'piye' => 'bagaimana', 'kepiye' => 'bagaimana', 'pripun' => 'bagaimana', 'pira' => 'berapa', 'piro' => 'berapa',
        'pinten' => 'berapa', 'sakpira' => 'berapa', 'regane' => 'harga', 'regine' => 'harga', 'rego' => 'harga',
        'iso' => 'bisa', 'isa' => 'bisa', 'saged' => 'bisa', 'ora' => 'tidak', 'ra' => 'tidak', 'mboten' => 'tidak',
        'nang' => 'di', 'ning' => 'di', 'teng' => 'di', 'ndi' => 'mana', 'pundi' => 'mana', 'dina' => 'hari',
        'dinten' => 'hari', 'suwe' => 'lama', 'suwene' => 'lama', 'dangu' => 'lama', 'rampung' => 'selesai',
        'tuku' => 'beli', 'tumbas' => 'beli', 'tokone' => 'toko', 'bodol' => 'rusak', 'ambrol' => 'rusak',
        'suwek' => 'sobek', 'elek' => 'jelek', 'nuwun' => 'kasih', 'matur' => 'terima', 'suwun' => 'makasih',
    ];

    /** Kata yang tidak membedakan satu pertanyaan dari yang lain. */
    private const KATA_UMUM = [
        'apakah', 'apa', 'bisa', 'berapa', 'bagaimana', 'yang', 'di', 'ke', 'dari', 'dan', 'atau', 'untuk', 'saya',
        'aku', 'kak', 'kakak', 'min', 'gan', 'sis', 'ini', 'itu', 'ada', 'mau', 'ingin', 'tolong', 'ya', 'dong', 'nih',
        'sih', 'deh', 'kalau', 'kalo', 'dengan', 'pada', 'juga', 'tidak', 'sudah', 'belum', 'kah', 'nya', 'mohon',
    ];

    public static function normalisasi(string $teks): string
    {
        $teks = mb_strtolower($teks);
        $teks = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $teks);

        $kata = array_map(function (string $k) {
            $k = self::SINGKATAN[$k] ?? $k;

            // Akhiran -nya: "harganya", "tokonya", "pembayarannya". Kata pendek
            // seperti "punya" dan "hanya" dibiarkan.
            return mb_strlen($k) > 5 && str_ends_with($k, 'nya') ? mb_substr($k, 0, -3) : $k;
        }, preg_split('/\s+/u', trim($teks)) ?: []);

        return trim(implode(' ', $kata));
    }

    /** @return list<string> kata pembeda, tanpa kata umum */
    public static function token(string $teks): array
    {
        $kata = explode(' ', self::normalisasi($teks));

        return array_values(array_unique(array_filter($kata, fn ($k) => $k !== '' && ! in_array($k, self::KATA_UMUM, true))));
    }

    /**
     * @param  Collection<int, FaqBot>  $faq
     * @return list<array{faq: FaqBot, skor: float, frasa: list<string>}> urut dari skor tertinggi
     */
    public function cari(string $pertanyaan, Collection $faq): array
    {
        $teks = ' '.self::normalisasi($pertanyaan).' ';
        $tokenTanya = self::token($pertanyaan);
        $hasil = [];

        foreach ($faq as $f) {
            // Pertanyaan yang sama persis dengan FAQ (cip yang diketuk pelanggan)
            // selalu menang.
            if (trim($teks) === self::normalisasi($f->pertanyaan)) {
                return [['faq' => $f, 'skor' => 100.0, 'frasa' => []]];
            }

            $skor = 0.0;
            $cocok = [];
            foreach ($f->frasaKunci() as $frasa) {
                $frasa = self::normalisasi($frasa);
                if ($frasa !== '' && str_contains($teks, ' '.$frasa.' ')) {
                    $skor += count(explode(' ', $frasa));
                    $cocok[] = $frasa;
                }
            }

            if ($skor <= 0) {
                continue;
            }

            $tokenFaq = self::token($f->pertanyaan);
            if ($tokenFaq !== []) {
                $skor += 0.5 * count(array_intersect($tokenTanya, $tokenFaq)) / count($tokenFaq);
            }

            $hasil[] = ['faq' => $f, 'skor' => $skor, 'frasa' => $cocok];
        }

        usort($hasil, fn ($a, $b) => $b['skor'] <=> $a['skor']);

        return $hasil;
    }

    /**
     * Satu FAQ yang jelas di depan menjadi jawaban. Beberapa yang skornya
     * berdekatan menjadi pilihan "Maksud Anda yang mana?".
     *
     * @param  list<array{faq: FaqBot, skor: float, frasa: list<string>}>  $kandidat
     * @return array{pasti: ?FaqBot, pilihan: list<FaqBot>}
     */
    public function putuskan(array $kandidat): array
    {
        if ($kandidat === []) {
            return ['pasti' => null, 'pilihan' => []];
        }

        $atas = $kandidat[0];
        $kedua = $kandidat[1] ?? null;

        if ($kedua === null || $kedua['skor'] < $atas['skor'] * 0.8) {
            return ['pasti' => $atas['faq'], 'pilihan' => []];
        }

        return ['pasti' => null, 'pilihan' => array_map(fn ($k) => $k['faq'], array_slice($kandidat, 0, 3))];
    }

    /**
     * Cocok kuat (KEP-153): pertanyaannya persis FAQ, frasa kuncinya dua kata
     * atau lebih, atau hampir semua kata pembedanya tercakup FAQ itu. Satu kata
     * umum tidak cukup: "harga" di pertanyaan tentang bola futsal bukan alasan
     * menjawab dengan harga jersey custom, dan "ukuran" di pertanyaan tentang
     * sepatu bukan alasan menjawab dengan panduan ukuran jersey.
     *
     * @param  array{faq: FaqBot, skor: float, frasa: list<string>}  $kandidat
     */
    public function kuat(array $kandidat, string $pertanyaan): bool
    {
        if ($kandidat['skor'] >= 100) {
            return true;
        }

        $tercakup = self::token($kandidat['faq']->pertanyaan);

        foreach ($kandidat['frasa'] as $frasa) {
            $kata = explode(' ', $frasa);

            if (count($kata) > 1) {
                return true;
            }

            $tercakup = [...$tercakup, ...$kata];
        }

        return count(array_diff(self::token($pertanyaan), $tercakup)) <= 1;
    }
}
