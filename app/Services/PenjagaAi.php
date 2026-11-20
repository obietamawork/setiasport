<?php

namespace App\Services;

/**
 * Pagar lapis AI chatbot CS (KEP-153).
 *
 * Model bahasa bisa dibujuk, jadi keamanan tidak diserahkan kepadanya. Upaya
 * prompt injection dikenali dengan aturan pasti SEBELUM pesan sampai ke AI,
 * dan jawabannya diperiksa lagi SESUDAHNYA. AI juga tidak punya akses apa pun
 * selain menulis teks: ia tidak bisa mengonfirmasi pembayaran, mengubah
 * pesanan, atau membaca data pelanggan lain. Yang dijaga di sini adalah isi
 * kalimatnya, supaya tidak menjadi jalan penipuan atau janji palsu atas nama
 * toko.
 *
 * Di sini juga gaya bahasanya dirapikan: pelanggan membaca balasan CS, bukan
 * teks khas AI dengan tanda pisah panjang, markdown, dan emoji.
 */
class PenjagaAi
{
    public const PANJANG_MAKS_JAWABAN = 600;

    /** Angka dan simbol yang dipakai untuk menyamarkan huruf ("4b41k4n"). */
    private const LEET = ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's'];

    /**
     * Pola pada teks yang dirapatkan: huruf kecil a-z saja, tanpa spasi dan
     * tanda baca, sehingga "a b a i k a n" dan "4b41k4n" ikut tertangkap.
     */
    private const POLA_RAPAT = [
        // Menyuruh mengabaikan aturan, dalam bahasa Indonesia, Inggris, dan Jawa.
        '/(abaikan|abaikn|lupakan|lupakn|lupain|hiraukan|cuekin|acuhkan|langgar|lewati|bypass|ignore|disregard|forget|override)(semua|seluruh|all|any|the|your|kamu|mu|sebelumnya|previous|prior|above|tadi|yang|ini|itu)*(instruksi|instruction|perintah|aturan|rule|prompt|petunjuk|arahan|batasan|sistem|system)/',
        '/(ojo|orausah|rasah|ojok|gausah|tidakusah|janganikuti|jangannurut)(nggatekke|ngurus|manut|nuruti|ngrungokke|ikuti|nurut|patuhi|pedulikan)?(aturan|perintah|instruksi|prompt)/',
        // Nama mode dan petunjuk sistem.
        '/(promptsistem|systemprompt|instruksisistem|pesansistem|petunjuksistem|systemmessage|developermode|modedeveloper|modepengembang|jailbreak|doanythingnow|godmode|modedewa)/',
        // Meminta isi petunjuk.
        '/(tulisulang|ulangi|tampilkan|bocorkan|sebutkan|kasihtau|kasihlihat|print|reveal|repeat|show|output)(semua|seluruh|isi|your|the|kamu|apa)*(prompt|instruksi|instruction)/',
        '/(aturan|petunjuk|instruksi)(mu|kamu|asli|sistem|awal)(apa|apasaja|lengkap|semuanya)/',
        // Berganti peran.
        '/(kamusekarang|kamuadalah|kamujadi|andasekarang|andaadalah|mulaisekarangkamu|youarenow|actas|bertindaksebagai|berperansebagai|anggapkamu|anggapdirimu|saikikowe|kowesaiki)(dadi|jadi|adalah|sebagai)?(admin|owner|pemilik|developer|pengembang|programmer|chatgpt|gpt|claude|gemini|dan|bebas|tanpaaturan|bos)/',
        '/(berpurapura|purapura)(jadi|menjadi|sebagai)|roleplay/',
    ];

    /** Pola pada teks asli (huruf kecil): penanda peran, token model, dan kunci JSON. */
    private const POLA_ASLI = [
        '/<\|[^|]*\|>/u',
        '/\[\/?inst\]|<\/?s>|<\/?(system|assistant|user|developer)>/u',
        '/(^|\n)\s*(system|assistant|developer|sistem)\s*:/u',
        '/###\s*(instruction|instruksi|system|sistem)/u',
        '/begin (system|prompt)|end of (system|prompt)/u',
        '/"(jenis|jawaban|alasan|serahkan_ke_admin)"\s*:|serahkan_ke_admin/u',
        // Pesan yang mengaku dari sistem: "[PESAN DARI SISTEM]", "(system)".
        '/[\[(]\s*((pesan|info|perintah|pengumuman|message)\s+)?((dari|from)\s+)?(sistem|system|admin|developer|server)\s*[\])]/u',
        '/\b(base64|rot13)\b/u',
    ];

    private const ANGKA_EJAAN = '(nol|kosong|satu|dua|tiga|empat|lima|enam|tujuh|delapan|sembilan)';

    public static function upayaManipulasi(string $teks): bool
    {
        $rendah = mb_strtolower((string) preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{2064}\x{FEFF}]/u', '', $teks));

        foreach (self::POLA_ASLI as $pola) {
            if (preg_match($pola, $rendah)) {
                return true;
            }
        }

        $rapat = (string) preg_replace('/[^a-z]/', '', strtr($rendah, self::LEET));

        foreach (self::POLA_RAPAT as $pola) {
            if (preg_match($pola, $rapat)) {
                return true;
            }
        }

        return false;
    }

    /** Pesan pelanggan sebelum dikirim ke AI: tanpa karakter tak terlihat dan token peran. */
    public static function bersihkanMasukan(string $teks): string
    {
        $teks = str_replace("\t", ' ', $teks);
        // Kategori C (kontrol, format seperti spasi nol-lebar, pribadi), kecuali baris baru.
        $teks = (string) preg_replace('/[^\P{C}\n]/u', '', $teks);
        $teks = str_replace(['<|', '|>'], '', $teks);
        $teks = (string) preg_replace('/ {2,}/u', ' ', $teks);
        $teks = (string) preg_replace('/\n{3,}/u', "\n\n", $teks);

        return trim($teks);
    }

    /**
     * Alasan jawaban AI ditahan, atau null bila aman ditampilkan. Jawaban yang
     * ditahan diteruskan ke admin, tidak pernah sampai ke pelanggan.
     */
    public static function alasanDitahan(string $jawaban, string $penanda): ?string
    {
        $rendah = mb_strtolower($jawaban);

        if (($penanda !== '' && str_contains($rendah, mb_strtolower($penanda)))
            || preg_match('/\b(prompt|system prompt|petunjuk sistem|instruksi sistem|serahkan_ke_admin|json)\b/u', $rendah)) {
            return 'Membocorkan petunjuk';
        }

        if (AsistenAi::mencurigakan($jawaban)
            || preg_match('/\b'.self::ANGKA_EJAAN.'(\s+'.self::ANGKA_EJAAN.'\b){5,}/u', $rendah)) {
            return 'Nomor panjang';
        }

        if (preg_match('/https?:\/\/|www\.|wa\.me|t\.me|bit\.ly|[\w.+-]+@[\w-]+\.[a-z]{2,}/u', $rendah) || self::adaDomainLain($rendah)) {
            return 'Tautan atau kontak';
        }

        if (preg_match('/\b(bca|bri|bni|mandiri|bsi|btn|cimb|niaga|permata|danamon|seabank|ovo|gopay|shopeepay|linkaja)\b/u', $rendah)) {
            return 'Menyebut bank';
        }

        if (preg_match('/\b(diskon|discount|potongan harga|promo|cashback|voucher|kupon|gratis ongkir|free ongkir|harga khusus)\b/u', $rendah)) {
            return 'Menjanjikan potongan';
        }

        if (preg_match('/\b(anjing|anjg|bangsat|goblok|goblog|tolol|bego|bodoh|kampret|brengsek|bajingan|keparat|asu|jancok|jancuk|kontol|memek|ngentot|taik|babi)\b/u', $rendah)) {
            return 'Kata kasar';
        }

        if (mb_strlen($jawaban) > self::PANJANG_MAKS_JAWABAN) {
            return 'Terlalu panjang';
        }

        return null;
    }

    /** Gaya CS manusia: tanpa tanda pisah panjang, markdown, emoji, dan frasa khas AI. */
    public static function rapikanGaya(string $teks): string
    {
        $t = str_replace(
            ["\u{201C}", "\u{201D}", "\u{201E}", "\u{201F}", "\u{2018}", "\u{2019}", "\u{2026}"],
            ['"', '"', '"', '"', "'", "'", '...'],
            $teks,
        );

        $ganti = [
            // Rentang angka memakai tanda hubung biasa; tanda pisah lain menjadi koma.
            '/(\d)\s*[\x{2012}-\x{2015}]\s*(\d)/u' => '$1-$2',
            '/(\p{L})\x{2013}(\p{L})/u' => '$1-$2',
            '/\s*[\x{2012}-\x{2015}]+\s*/u' => ', ',
            '/\s+-{2,}\s+/u' => ', ',
            // Markdown.
            '/\*\*(.+?)\*\*/su' => '$1',
            '/__(.+?)__/su' => '$1',
            '/(?<![\w*])\*(?=\S)(.+?)(?<=\S)\*(?![\w*])/u' => '$1',
            '/^[ \t]*#{1,6}[ \t]*/mu' => '',
            '/^[ \t]*[-*\x{2022}\x{25CF}\x{25AA}][ \t]+/mu' => '',
            '/`+/u' => '',
            // Emoji dan simbol bergambar.
            '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}\x{20E3}]/u' => '',
            // Pembuka dan penutup khas AI.
            '/^\s*(tentu( saja)?|dengan senang hati|pertanyaan (yang )?bagus|baik,? tentu)\s*[!.,]*\s*/iu' => '',
            '/\s*(semoga (informasi ini |jawaban ini )?(membantu|bermanfaat)|jangan ragu|senang bisa membantu)[^.!?\n]*[.!?]*\s*$/iu' => '',
            // Tanda baca.
            '/!{2,}/u' => '!',
            '/\?{2,}/u' => '?',
            '/[ \t]{2,}/u' => ' ',
            '/[ \t]+([,.!?])/u' => '$1',
            '/,\s*,/u' => ',',
            '/,([.!?])/u' => '$1',
        ];

        $t = (string) preg_replace(array_keys($ganti), array_values($ganti), $t);
        $t = trim($t, " \t\n,");

        return mb_strtoupper(mb_substr($t, 0, 1)).mb_substr($t, 1);
    }

    /** Nama domain selain situs toko sendiri. */
    private static function adaDomainLain(string $rendah): bool
    {
        preg_match_all('/\b[a-z0-9-]+(\.[a-z0-9-]+)*\.(com|id|net|org|xyz|site|link|me|ly|io|shop|store|online|app|co)\b/u', $rendah, $cocok);

        foreach ($cocok[0] as $domain) {
            if ($domain !== 'setiasport.com' && ! str_ends_with($domain, '.setiasport.com')) {
                return true;
            }
        }

        return false;
    }
}
