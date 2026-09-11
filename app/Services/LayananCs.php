<?php

namespace App\Services;

use App\Enums\TopikCs;
use App\Events\PesanCsTerkirim;
use App\Mail\PeringatanCs;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PercakapanCs;
use App\Models\PesanCs;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Semua perubahan percakapan CS lewat sini (KEP-030), supaya penghitung belum
 * dibaca, penanda "menunggu jawaban", dan kabar realtime tidak pernah tertinggal
 * di salah satu tempat pemanggilan.
 */
class LayananCs
{
    /** Detik sebelum staf dianggap pergi tanpa pamit (tab ditutup, HP mati). */
    private const UMUR_DENYUT = 150;

    public function __construct(private PengunggahGambar $pengunggah) {}

    /**
     * @param  array{nama: string, kontak: ?string, topik: string, kode_pesanan?: ?string}  $data
     * @return array{0: PercakapanCs, 1: string} percakapan dan token mentah untuk cookie
     */
    public function mulai(array $data, ?Customer $customer, ?Order $orderDariTautan, string $ip): array
    {
        $token = PercakapanCs::tokenBaru();

        [$order, $terverifikasi] = $this->kaitkanPesanan($data['kode_pesanan'] ?? null, $customer, $orderDariTautan);

        $percakapan = PercakapanCs::create([
            'token_hash' => PercakapanCs::hashToken($token),
            'customer_id' => $customer?->id,
            'order_id' => $order?->id,
            'order_terverifikasi' => $terverifikasi,
            'nama' => $customer?->nama ?? $data['nama'],
            'kontak' => $customer?->email ?? $data['kontak'],
            'topik' => TopikCs::from($data['topik']),
            'ip' => $ip,
        ]);

        return [$percakapan, $token];
    }

    public function kirimDariPelanggan(PercakapanCs $percakapan, ?string $isi, ?UploadedFile $lampiran): PesanCs
    {
        $pesan = DB::transaction(function () use ($percakapan, $isi, $lampiran) {
            $pesan = $percakapan->pesan()->create([
                'pengirim' => 'PELANGGAN',
                'isi' => $this->rapikan($isi),
                'lampiran_path' => $this->simpanLampiran($lampiran),
            ]);

            $percakapan->update([
                // Pelanggan yang menulis lagi membuka kembali percakapan yang ditutup.
                'status' => 'TERBUKA',
                'belum_dibaca_staf' => $percakapan->belum_dibaca_staf + 1,
                'pesan_terakhir_pada' => now(),
                'menunggu_sejak' => $percakapan->menunggu_sejak ?? now(),
            ]);

            return $pesan;
        });

        $this->siarkan($pesan, ['staf']);

        return $pesan;
    }

    public function balasDariStaf(PercakapanCs $percakapan, User $staf, ?string $isi, ?UploadedFile $lampiran): PesanCs
    {
        $pesan = DB::transaction(function () use ($percakapan, $staf, $isi, $lampiran) {
            $pesan = $percakapan->pesan()->create([
                'pengirim' => 'STAF',
                'user_id' => $staf->id,
                'isi' => $this->rapikan($isi),
                'lampiran_path' => $this->simpanLampiran($lampiran),
            ]);

            $percakapan->update([
                'status' => 'TERBUKA',
                'belum_dibaca_staf' => 0,
                'belum_dibaca_pelanggan' => $percakapan->belum_dibaca_pelanggan + 1,
                'pesan_terakhir_pada' => now(),
                'menunggu_sejak' => null,
                'diperingatkan_pada' => null,
            ]);

            return $pesan;
        });

        // Staf lain ikut melihat balasannya, supaya tidak ada yang menjawab dua kali.
        $this->siarkan($pesan, ['pelanggan', 'staf']);

        return $pesan;
    }

    public function tutup(PercakapanCs $percakapan, User $staf): void
    {
        $pesan = DB::transaction(function () use ($percakapan, $staf) {
            $pesan = $percakapan->pesan()->create([
                'pengirim' => 'SISTEM',
                'user_id' => $staf->id,
                'isi' => 'Percakapan ditutup. Kirim pesan lagi kapan saja untuk membukanya kembali.',
            ]);

            $percakapan->update([
                'status' => 'SELESAI',
                'belum_dibaca_staf' => 0,
                'menunggu_sejak' => null,
                'diperingatkan_pada' => null,
            ]);

            return $pesan;
        });

        ActivityLog::catat('cs.ditutup', $percakapan);

        $this->siarkan($pesan, ['pelanggan', 'staf']);
    }

    /**
     * Staf menautkan pesanan yang dibicarakan. Tautan dari staf dianggap
     * terverifikasi: staf yang menentukannya setelah memeriksa sendiri.
     */
    public function tautkanPesanan(PercakapanCs $percakapan, Order $order): void
    {
        $percakapan->update(['order_id' => $order->id, 'order_terverifikasi' => true]);

        ActivityLog::catat('cs.pesanan-ditautkan', $percakapan, ['kode' => $order->kode]);
    }

    /**
     * Mengirim email ke staf untuk chat yang menunggu terlalu lama (KEP-030).
     * Tiap masa tunggu diperingatkan sekali saja: penandanya baru dikosongkan
     * lagi saat staf membalas. Email yang gagal terkirim tidak menandai apa
     * pun, supaya dicoba lagi di putaran berikutnya.
     *
     * @return int jumlah percakapan yang diperingatkan
     */
    public function peringatkanYangMenunggu(): int
    {
        // Di luar jam operasional tidak ada yang diharapkan menjawab. Chat yang
        // masuk malam hari tetap tercatat menunggu, dan diperingatkan begitu jam
        // operasional dimulai keesokan paginya.
        if (! $this->dalamJamOperasional()) {
            return 0;
        }

        $menunggu = PercakapanCs::query()
            ->where('status', 'TERBUKA')
            ->whereNull('diperingatkan_pada')
            ->where('menunggu_sejak', '<', now()->subMinutes((int) setting('cs_peringatan_menit', 15)))
            ->orderBy('menunggu_sejak')
            ->get();

        if ($menunggu->isEmpty()) {
            return 0;
        }

        $penerima = User::where('aktif', true)->get()
            ->filter(fn (User $u) => $u->punyaIzin('cs.balas'))
            ->pluck('email');

        if ($penerima->isEmpty()) {
            return 0;
        }

        try {
            Mail::to($penerima->all())->send(new PeringatanCs($menunggu));
        } catch (Throwable $e) {
            report($e);

            return 0;
        }

        PercakapanCs::whereKey($menunggu->modelKeys())->update(['diperingatkan_pada' => now()]);

        return $menunggu->count();
    }

    /**
     * Klien: website beroperasi setiap hari 07.00-16.00. Nilai yang tidak bisa
     * dibaca (salah ketik di pengaturan) dianggap selalu buka, supaya kesalahan
     * ketik tidak diam-diam mematikan peringatan.
     */
    public function dalamJamOperasional(): bool
    {
        $mulai = (string) setting('cs_jam_mulai', '07:00');
        $selesai = (string) setting('cs_jam_selesai', '16:00');
        $pola = '/^([01]?\d|2[0-3])[:.]([0-5]\d)$/';

        if (! preg_match($pola, $mulai, $m) || ! preg_match($pola, $selesai, $s)) {
            return true;
        }

        $sekarang = now()->hour * 60 + now()->minute;

        return $sekarang >= $m[1] * 60 + $m[2] && $sekarang < $s[1] * 60 + $s[2];
    }

    /**
     * Kehadiran CS (KEP-031). Ruang CS mengirim denyut tiap menit selama staf
     * menyalakan "Menerima chat"; widget pelanggan menampilkan online kalau
     * ada denyut dalam dua setengah menit terakhir.
     *
     * Disimpan per staf, bukan satu tanda bersama: kalau satu admin istirahat
     * sementara yang lain masih menjawab, pelanggan tetap harus melihat online.
     */
    public function tandaiHadir(User $staf, bool $menerima): void
    {
        $hadir = array_filter(
            Cache::get('cs_hadir', []),
            fn (int $sampai) => $sampai > now()->timestamp
        );

        if ($menerima) {
            $hadir[$staf->id] = now()->addSeconds(self::UMUR_DENYUT)->timestamp;
        } else {
            unset($hadir[$staf->id]);
        }

        Cache::forever('cs_hadir', $hadir);
    }

    public function adaYangHadir(): bool
    {
        foreach (Cache::get('cs_hadir', []) as $sampai) {
            if ($sampai > now()->timestamp) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tanda tangan otorisasi channel privat, dengan rumus protokol Pusher.
     * Ditulis langsung, bukan lewat SDK, supaya tetap bisa dijawab dengan jelas
     * ketika kuncinya belum diisi dan broadcaster sedang 'null'.
     */
    public function otorisasi(string $channel, string $socketId): ?array
    {
        $key = config('broadcasting.connections.pusher.key');
        $secret = config('broadcasting.connections.pusher.secret');

        if (! $key || ! $secret || ! preg_match('/^\d+\.\d+$/', $socketId)) {
            return null;
        }

        return ['auth' => $key.':'.hash_hmac('sha256', $socketId.':'.$channel, $secret)];
    }

    /**
     * Kode pesanan yang diketik pelanggan belum membuktikan apa pun: siapa saja
     * bisa menyebut kode orang lain lalu meminta staf mengubah nama pengambil.
     * Yang dianggap terverifikasi hanya tautan dari halaman pesanan bertoken,
     * atau pesanan milik akun yang sedang masuk.
     *
     * @return array{0: ?Order, 1: bool}
     */
    private function kaitkanPesanan(?string $kode, ?Customer $customer, ?Order $dariTautan): array
    {
        if ($dariTautan) {
            return [$dariTautan, true];
        }

        $kode = strtoupper(trim((string) $kode));

        if ($kode === '') {
            return [null, false];
        }

        $order = Order::where('kode', $kode)->first();

        if (! $order) {
            return [null, false];
        }

        return [$order, $customer !== null && $order->customer_id === $customer->id];
    }

    private function rapikan(?string $isi): ?string
    {
        $isi = trim((string) $isi);

        return $isi === '' ? null : $isi;
    }

    private function simpanLampiran(?UploadedFile $berkas): ?string
    {
        return $berkas
            ? $this->pengunggah->simpan($berkas, 'cs', 'local', denganThumb: false)['path']
            : null;
    }

    /** @param  list<'pelanggan'|'staf'>  $pihak */
    private function siarkan(PesanCs $pesan, array $pihak): void
    {
        $pesan->loadMissing(['percakapan', 'staf']);

        foreach ($pihak as $untuk) {
            try {
                event(new PesanCsTerkirim($pesan, $untuk));
            } catch (Throwable $e) {
                // Pesan sudah tersimpan. Kabar realtime yang gagal hanya berarti
                // pihak lain melihatnya sedikit lebih lambat, lewat polling.
                report($e);
            }
        }
    }
}
