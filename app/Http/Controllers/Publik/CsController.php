<?php

namespace App\Http\Controllers\Publik;

use App\Enums\TopikCs;
use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Routing\Controllers\HasMiddleware;
use App\Models\Order;
use App\Models\PercakapanCs;
use App\Models\PesanCs;
use App\Services\LayananCs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

    public function __construct(private LayananCs $cs) {}

    /** Keadaan widget saat dibuka: percakapan yang sedang berjalan, kalau ada. */
    public function status(Request $request): JsonResponse
    {
        $percakapan = $this->percakapan($request);

        if ($percakapan && $percakapan->belum_dibaca_pelanggan > 0) {
            $percakapan->update(['belum_dibaca_pelanggan' => 0]);
        }

        return response()->json([
            'percakapan' => $percakapan ? [
                'channel' => $percakapan->channel(),
                'status' => $percakapan->status,
                'nama' => $percakapan->nama,
            ] : null,
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

        // Kode yang diketik belum membuktikan apa-apa; tautan pesanan bertoken iya.
        $dariTautan = ! empty($data['token_pesanan'])
            ? Order::where('access_token', $data['token_pesanan'])->first()
            : null;

        [$percakapan, $token] = $this->cs->mulai($data, $customer, $dariTautan, (string) $request->ip());

        $pesan = $this->cs->kirimDariPelanggan($percakapan, $data['isi'] ?? null, $request->file('lampiran'));

        return response()->json([
            'percakapan' => ['channel' => $percakapan->channel(), 'status' => $percakapan->status, 'nama' => $percakapan->nama],
            'pesan' => $pesan->untukKlien('pelanggan'),
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

        $pesan = $this->cs->kirimDariPelanggan($percakapan, $data['isi'] ?? null, $request->file('lampiran'));

        return response()->json(['pesan' => $pesan->untukKlien('pelanggan')], 201);
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

        return Storage::disk('local')->response($pesan->lampiran_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function percakapan(Request $request): ?PercakapanCs
    {
        return PercakapanCs::dariToken($request->cookie(self::COOKIE));
    }

    /** Foto untuk komplain: barang cacat, ukuran salah. Hanya gambar, di-encode ulang. */
    private function aturanLampiran(): array
    {
        return ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(setting('upload_maks_mb', 10) * 1024)];
    }
}
