<?php

namespace App\Http\Controllers\Publik;

use App\Enums\TopikCs;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PercakapanCs;
use App\Models\PesanCs;
use App\Services\AsistenCs;
use App\Services\LayananCs;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chat CS dari sisi pelanggan (KEP-030).
 *
 * Pelanggan tanpa akun dikenali lewat cookie berisi token acak. Cookie itu
 * httpOnly dan terenkripsi, jadi skrip di halaman tidak bisa membacanya, dan
 * yang tersimpan di database hanya hash-nya. Semua endpoint di sini memeriksa
 * token itu; nomor percakapan saja tidak membuka apa pun.
 */
class CsController extends Controller implements HasMiddleware
{
    private const COOKIE = 'cs_percakapan';

    private const UMUR_COOKIE_MENIT = 60 * 24 * 60;

    private const FOTO_PER_JAM = 10;

    /*
     * SEMUA endpoint pelanggan ikut saklar, bukan hanya pembuka percakapan.
     * Kalau hanya "mulai" yang dijaga, pelanggan yang sudah punya percakapan
     * tetap bisa mengirim pesan ke kotak yang tidak dibaca siapa pun (KEP-033).
     */
    public static function middleware(): array
    {
        return [
            function ($request, Closure $next) {
                abort_unless(cs_aktif(), 404);

                return $next($request);
            },
        ];
    }

    public function __construct(
        private LayananCs $cs,
        private AsistenCs $asisten,
    ) {}

    /** Keadaan widget saat dibuka: percakapan yang sedang berjalan, kalau ada. */
    public function status(Request $request): JsonResponse
    {
        $percakapan = $this->percakapan($request);

        if ($percakapan && $percakapan->belum_dibaca_pelanggan > 0) {
            $percakapan->update(['belum_dibaca_pelanggan' => 0]);
        }

        return response()->json([
            'percakapan' => $percakapan ? $this->ringkas($percakapan) : null,
            'pesan' => $percakapan
                ? $percakapan->pesan()->with(['percakapan', 'staf'])->orderByDesc('id')->limit(100)->get()
                    ->reverse()->values()->map->untukKlien('pelanggan')
                : [],
            'pelanggan' => ($c = Auth::guard('pelanggan')->user()) ? ['nama' => $c->nama] : null,
            'online' => $this->cs->adaYangHadir(),
            // Widget yang tabnya dibiarkan terbuka lama memakai ini untuk
            // memperbarui token CSRF yang basi, lalu mengulang permintaannya.
            'csrf' => csrf_token(),
        ]);
    }

    /** Pesan pertama sekaligus membuka percakapan. */
    public function mulai(Request $request): JsonResponse
    {
        $customer = Auth::guard('pelanggan')->user();

        $data = $request->validate([
            'nama' => [$customer ? 'nullable' : 'required', 'string', 'max:100'],
            'kontak' => [$customer ? 'nullable' : 'required', 'string', 'max:100'],
            'topik' => ['required', Rule::enum(TopikCs::class)],
            'kode_pesanan' => ['nullable', 'string', 'max:20'],
            'token_pesanan' => ['nullable', 'string', 'max:80'],
            'isi' => ['required_without:lampiran', 'nullable', 'string', 'max:2000'],
            'lampiran' => $this->aturanLampiran(),
        ], [
            'nama.required' => 'Tulis nama Anda supaya CS tahu siapa yang diajak bicara.',
            'kontak.required' => 'Tulis email atau nomor HP, supaya kami bisa membalas kalau Anda menutup halaman ini.',
            'isi.required_without' => 'Tulis pertanyaannya dulu.',
        ], ['kontak' => 'email atau nomor HP', 'isi' => 'pesan']);

        $this->batasiFoto($request);

        // Kode yang diketik belum membuktikan apa-apa; tautan pesanan bertoken iya.
        $dariTautan = ! empty($data['token_pesanan'])
            ? Order::where('access_token', $data['token_pesanan'])->first()
            : null;

        [$percakapan, $token] = $this->cs->mulai($data, $customer, $dariTautan, (string) $request->ip());

        $pesan = $this->cs->kirimDariPelanggan($percakapan, $data['isi'] ?? null, $request->file('lampiran'));
        $balasan = $this->asisten->tanggapi($percakapan, $pesan);

        return response()->json([
            'percakapan' => $this->ringkas($percakapan),
            'pesan' => $pesan->untukKlien('pelanggan'),
            // Balasan asisten otomatis langsung ikut, tanpa menunggu realtime (KEP-151).
            'balasan' => array_map(fn (PesanCs $b) => $b->untukKlien('pelanggan'), $balasan),
        ], 201)->withCookie(cookie(
            self::COOKIE, $token, self::UMUR_COOKIE_MENIT,
            httpOnly: true, sameSite: 'lax', secure: $request->isSecure(),
        ));
    }

    public function kirim(Request $request): JsonResponse
    {
        $percakapan = $this->percakapan($request);

        abort_unless($percakapan, 404, 'Percakapan tidak ditemukan. Mulai percakapan baru.');

        $data = $request->validate([
            'isi' => ['required_without:lampiran', 'nullable', 'string', 'max:2000'],
            'lampiran' => $this->aturanLampiran(),
        ], ['isi.required_without' => 'Pesannya masih kosong.'], ['isi' => 'pesan']);

        $this->batasiFoto($request);

        $pesan = $this->cs->kirimDariPelanggan($percakapan, $data['isi'] ?? null, $request->file('lampiran'));
        $balasan = $this->asisten->tanggapi($percakapan, $pesan);

        return response()->json([
            'pesan' => $pesan->untukKlien('pelanggan'),
            'balasan' => array_map(fn (PesanCs $b) => $b->untukKlien('pelanggan'), $balasan),
            'percakapan' => $this->ringkas($percakapan),
        ], 201);
    }

    /**
     * Cadangan kalau realtime tidak tersambung: kuota Pusher habis, kunci belum
     * diisi, atau jaringan memblokir websocket. Widget memanggilnya jarang dan
     * hanya selama jendela chat terbuka, jadi bebannya ke hosting tetap kecil.
     */
    public function pesanBaru(Request $request): JsonResponse
    {
        $percakapan = $this->percakapan($request);

        abort_unless($percakapan, 404);

        $pesan = $percakapan->pesan()->with(['percakapan', 'staf'])
            ->where('id', '>', (int) $request->query('setelah', 0))
            ->orderBy('id')
            ->limit(50)
            ->get();

        if ($pesan->contains('pengirim', 'STAF')) {
            $percakapan->update(['belum_dibaca_pelanggan' => 0]);
        }

        return response()->json([
            'status' => $percakapan->status,
            'pesan' => $pesan->map->untukKlien('pelanggan'),
        ]);
    }

    /** @return array{channel: string, status: string, nama: string, ditangani: string} */
    private function ringkas(PercakapanCs $percakapan): array
    {
        return [
            'channel' => $percakapan->channel(),
            'status' => $percakapan->status,
            'nama' => $percakapan->nama,
            // Widget menampilkan "Bicara dengan admin" selama bot yang menjawab (KEP-151).
            'ditangani' => $percakapan->ditangani,
        ];
    }

    /** Otorisasi channel privat: hanya channel percakapan milik cookie ini. */
    public function otorisasi(Request $request): JsonResponse
    {
        $percakapan = $this->percakapan($request);
        $channel = (string) $request->input('channel_name');

        abort_unless($percakapan && hash_equals($percakapan->channel(), $channel), 403);

        $auth = $this->cs->otorisasi($channel, (string) $request->input('socket_id'));

        abort_unless($auth, 403);

        return response()->json($auth);
    }

    public function lampiran(Request $request, PesanCs $pesan): Response
    {
        $percakapan = $this->percakapan($request);

        abort_unless($percakapan && $pesan->percakapan_id === $percakapan->id && $pesan->lampiran_path, 404);

        return Storage::disk('local')->response($pesan->lampiran_path, null, LayananCs::HEADER_LAMPIRAN);
    }

    private function percakapan(Request $request): ?PercakapanCs
    {
        return PercakapanCs::dariToken($request->cookie(self::COOKIE));
    }

    /** Foto untuk komplain: barang cacat, ukuran salah. Hanya gambar, di-encode ulang. */
    /** @return list<mixed> */
    private function aturanLampiran(): array
    {
        return LayananCs::aturanLampiran();
    }

    /**
     * Satu alamat IP paling banyak sekian foto per jam (KEP-153). Setiap foto
     * tersimpan di disk shared hosting yang terbatas, dan setiap foto juga
     * diteruskan ke admin.
     */
    private function batasiFoto(Request $request): void
    {
        if (! $request->hasFile('lampiran')) {
            return;
        }

        $kunci = 'cs-foto:'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunci, self::FOTO_PER_JAM)) {
            throw ValidationException::withMessages([
                'lampiran' => 'Terlalu banyak foto dalam satu jam. Coba lagi nanti, atau kirim lewat WhatsApp toko.',
            ]);
        }

        RateLimiter::hit($kunci, 3600);
    }
}
