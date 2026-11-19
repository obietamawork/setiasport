<?php

namespace App\Services;

use App\Enums\TopikCs;
use App\Models\FaqBot;
use App\Models\LogBot;
use App\Models\PercakapanCs;
use App\Models\PesanCs;

/**
 * Chatbot CS berlapis (KEP-151), seperti chatbot layanan pelanggan pada
 * umumnya: yang pasti dijawab pasti, AI hanya untuk sisanya, dan manusia
 * untuk yang memang butuh manusia.
 *
 *   1. Aturan pasti: minta admin, komplain, pembayaran bermasalah, perubahan
 *      pesanan, dan foto langsung ke admin. Salam dijawab dengan menu topik;
 *      status pesanan dijawab dari data, hanya untuk percakapan terverifikasi.
 *   2. FAQ: frasa kunci dari panel (PencocokFaq).
 *   3. AI (KEP-152): hanya bila tidak ada FAQ yang cocok.
 *   4. Admin: semua yang tersisa.
 *
 * Selama ditangani bot, percakapan tidak menunggu admin: tidak ada tanda
 * "Baru", bunyi, atau email peringatan. Begitu diserahkan, percakapan masuk
 * kotak masuk seperti chat biasa.
 */
class AsistenCs
{
    public const PILIHAN_ADMIN = 'Bicara dengan admin';

    public const PILIHAN_YA = 'Ya, terjawab';

    public const PILIHAN_TIDAK = 'Tidak membantu';

    private const POLA_ADMIN = '/\b(bicara (dengan|sama|ke) (admin|cs)|hubungi admin|hubungkan (ke|dengan) admin|minta admin|(mau|ingin) (ke|sama|dengan|bicara) admin|chat admin|ngobrol (dengan|sama) admin|cs manusia|orang asli|manusia|operator|customer service)\b/u';

    private const POLA_KOMPLAIN = '/\b(rusak|cacat|robek|sobek|luntur|pudar|bolong|salah kirim|salah ukuran|ukuran salah|kurang barang|barang kurang|komplain|keluhan|mengeluh|kecewa|refund|retur|uang kembali|dikembalikan|penipu|penipuan|ditipu|belum sampai|belum datang|belum diterima|telat|terlambat)\b/u';

    private const POLA_PEMBAYARAN = '/\b(sudah transfer|sudah bayar|sudah dibayar|bukti transfer|bukti bayar|bukti pembayaran|belum diverifikasi|belum dikonfirmasi|salah transfer|transfer dobel|double transfer|kelebihan bayar|bayar dua kali)\b/u';

    private const POLA_PERUBAHAN = '/\b(mau|minta|tolong|ingin) (revisi|ubah|ganti|tambah|kurangi|batal|batalkan)\b/u';

    private const POLA_TIDAK_MEMBANTU = '/\b(tidak membantu|belum terjawab|bukan itu|salah jawab|tidak menjawab)\b/u';

    private const POLA_TERIMA_KASIH = '/\b(terima kasih|makasih|thanks|thank you|terjawab|sudah jelas)\b/u';

    private const POLA_STATUS = '/\b(status|sampai mana|progres|progress|resi|pesanan saya|orderan saya|pesanan aku)\b/u';

    private const KATA_SALAM = [
        'halo', 'hallo', 'hai', 'hi', 'hello', 'selamat', 'pagi', 'siang', 'sore', 'malam', 'permisi', 'assalamualaikum',
        'kak', 'kakak', 'min', 'gan', 'sis', 'bro', 'p', 'ping', 'tes', 'test', 'om', 'bu', 'pak', 'mas', 'mbak', 'admin',
    ];

    public function __construct(
        private LayananCs $cs,
        private PencocokFaq $pencocok,
    ) {}

    /** Bot menjawab lebih dulu bila CS menyala dan asisten tidak dimatikan di Pengaturan. */
    public static function aktif(): bool
    {
        return cs_aktif() && (bool) setting('bot_aktif', true);
    }

    /** Siapa yang menangani percakapan baru: komplain selalu langsung ke admin. */
    public static function penanganAwal(TopikCs $topik): string
    {
        return self::aktif() && $topik !== TopikCs::KOMPLAIN ? 'BOT' : 'ADMIN';
    }

    /**
     * @return list<PesanCs> balasan bot untuk pesan pelanggan ini
     */
    public function tanggapi(PercakapanCs $percakapan, PesanCs $pesan): array
    {
        if ($percakapan->ditangani !== 'BOT') {
            return [];
        }

        $teks = trim((string) $pesan->isi);

        if ($teks === '') {
            return [$this->serahkan($percakapan, $pesan, 'Pelanggan mengirim foto', '')];
        }

        $norm = PencocokFaq::normalisasi($teks);

        foreach ([
            [self::POLA_ADMIN, 'Pelanggan meminta admin'],
            [self::POLA_KOMPLAIN, 'Komplain'],
            [self::POLA_PEMBAYARAN, 'Pembayaran'],
            [self::POLA_PERUBAHAN, 'Perubahan pesanan atau desain'],
        ] as [$pola, $alasan]) {
            if (preg_match($pola, $norm)) {
                return [$this->serahkan($percakapan, $pesan, $alasan, $teks)];
            }
        }

        if (preg_match(self::POLA_TIDAK_MEMBANTU, $norm)) {
            return [$this->tidakMembantu($percakapan, $pesan, $teks)];
        }

        if ($this->sekadar($norm, self::POLA_TERIMA_KASIH)) {
            return [$this->balas($percakapan, $pesan, $teks, 'sistem',
                'Sama-sama, senang bisa membantu! Kalau ada pertanyaan lain, tulis saja di sini.', [])];
        }

        if ($this->salam($norm)) {
            return [$this->balas($percakapan, $pesan, $teks, 'menu',
                'Halo, saya asisten otomatis '.setting('toko_nama', 'Setia Sport').'. Pilih topik di bawah atau tulis pertanyaan Anda. Admin kami siap membantu bila diperlukan.',
                $this->menu())];
        }

        if (preg_match(self::POLA_STATUS, $norm)) {
            return [$this->balas($percakapan, $pesan, $teks, 'sistem', $this->statusPesanan($percakapan), [self::PILIHAN_ADMIN])];
        }

        ['pasti' => $pasti, 'pilihan' => $pilihan] = $this->pencocok->putuskan(
            $this->pencocok->cari($teks, FaqBot::aktif()->orderBy('urutan')->get())
        );

        if ($pasti) {
            $pasti->increment('dipakai');

            return [$this->balas($percakapan, $pesan, $teks, 'faq', $pasti->jawabanTampil(),
                [self::PILIHAN_YA, self::PILIHAN_TIDAK, self::PILIHAN_ADMIN], $pasti->id)];
        }

        if ($pilihan !== []) {
            return [$this->balas($percakapan, $pesan, $teks, 'menu', 'Maksud Anda yang mana?',
                [...array_map(fn (FaqBot $f) => $f->pertanyaan, $pilihan), self::PILIHAN_ADMIN])];
        }

        return [$this->serahkan($percakapan, $pesan, 'Tidak ada jawaban', $teks)];
    }

    /**
     * @param  list<string>  $pilihan
     */
    private function balas(PercakapanCs $percakapan, PesanCs $pesan, string $pertanyaan, string $lapis, string $jawaban, array $pilihan, ?int $faqId = null): PesanCs
    {
        LogBot::create([
            'percakapan_id' => $percakapan->id,
            'pesan_id' => $pesan->id,
            'pertanyaan' => $pertanyaan,
            'lapis' => $lapis,
            'faq_id' => $faqId,
            'jawaban' => $jawaban,
        ]);

        return $this->cs->kirimDariBot($percakapan, $jawaban, $pilihan);
    }

    private function serahkan(PercakapanCs $percakapan, PesanCs $pesan, string $alasan, string $pertanyaan): PesanCs
    {
        LogBot::create([
            'percakapan_id' => $percakapan->id,
            'pesan_id' => $pesan->id,
            'pertanyaan' => $pertanyaan !== '' ? $pertanyaan : '(foto)',
            'lapis' => 'admin',
            'alasan' => $alasan,
        ]);

        $pembuka = match ($alasan) {
            'Komplain' => 'Mohon maaf atas kendalanya. Percakapan ini saya teruskan ke admin kami.',
            'Pembayaran' => 'Terima kasih. Admin kami akan memeriksa pembayaran Anda.',
            'Pelanggan mengirim foto' => 'Foto Anda sudah kami terima dan saya teruskan ke admin kami.',
            'Tidak ada jawaban' => 'Pertanyaan ini belum ada di jawaban otomatis saya, jadi saya teruskan ke admin kami.',
            default => 'Baik, percakapan ini saya teruskan ke admin kami.',
        };

        $kapan = $this->cs->adaYangHadir()
            ? 'Admin akan membalas di sini sebentar lagi.'
            : 'Admin akan membalas di sini pada jam operasional, '.lcfirst((string) setting('cs_jam_layanan')).'.';

        return $this->cs->serahkanKeAdmin($percakapan, $alasan, $pembuka.' '.$kapan);
    }

    private function tidakMembantu(PercakapanCs $percakapan, PesanCs $pesan, string $teks): PesanCs
    {
        $percakapan->increment('gagal_bot');

        if ($percakapan->gagal_bot >= 2) {
            return $this->serahkan($percakapan, $pesan, 'Jawaban bot tidak membantu', $teks);
        }

        return $this->balas($percakapan, $pesan, $teks, 'menu',
            'Maaf, jawabannya belum tepat. Coba tulis pertanyaannya dengan kata lain, atau pilih salah satu topik di bawah.',
            $this->menu());
    }

    /**
     * Status hanya dari pesanan yang tertaut DAN terverifikasi: kode yang
     * sekadar diketik tidak membuktikan penanya adalah pemesannya (KEP-030).
     */
    private function statusPesanan(PercakapanCs $percakapan): string
    {
        $order = $percakapan->order_terverifikasi ? $percakapan->order : null;

        if (! $order) {
            return 'Status pesanan bisa dilihat kapan saja di halaman pesanan Anda, lewat tautan di email konfirmasi pesanan. Kalau tautannya hilang, pilih Bicara dengan admin.';
        }

        $kalimat = ["Pesanan {$order->kode} saat ini berstatus {$order->status->label()}."];

        if ($order->target_siap_pada && ! in_array($order->status->value, ['SELESAI', 'DIBATALKAN', 'KADALUARSA'], true)) {
            $kalimat[] = 'Target selesai '.$order->target_siap_pada->translatedFormat('j F Y').'.';
        }

        if ($order->sisa_tagihan > 0) {
            $kalimat[] = 'Sisa tagihan '.rupiah($order->sisa_tagihan).'.';
        }

        $kalimat[] = 'Rinciannya ada di halaman pesanan Anda.';

        return implode(' ', $kalimat);
    }

    /** @return list<string> */
    private function menu(): array
    {
        return [
            ...FaqBot::aktif()->where('tampil_di_menu', true)->orderBy('urutan')->limit(5)->pluck('pertanyaan')->all(),
            self::PILIHAN_ADMIN,
        ];
    }

    /** Pesan pendek yang seluruhnya berupa salam, misalnya "halo kak, permisi". */
    private function salam(string $norm): bool
    {
        $kata = explode(' ', $norm);

        return count($kata) <= 6 && array_diff($kata, self::KATA_SALAM) === [];
    }

    /** Pesan pendek yang isinya hanya pola itu, misalnya "terima kasih kak". */
    private function sekadar(string $norm, string $pola): bool
    {
        return count(explode(' ', $norm)) <= 5 && (bool) preg_match($pola, $norm);
    }
}
