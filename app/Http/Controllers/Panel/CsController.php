<?php

namespace App\Http\Controllers\Panel;

use App\Enums\TopikCs;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PercakapanCs;
use App\Models\PesanCs;
use App\Models\TemplateBalasan;
use App\Services\LayananCs;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ruang CS (KEP-031): antarmuka kerja tersendiri untuk chat, terpisah dari
 * tata letak panel tapi memakai akun dan izin yang sama (cs.balas).
 *
 * Halaman dimuat sekali. Setelah itu berpindah percakapan, mencari, dan
 * menyaring berjalan lewat JSON, karena CS dibuka seharian dan memuat ulang
 * halaman setiap kali pindah percakapan memutus sambungan realtime-nya.
 */
class CsController extends Controller
{
    public function __construct(private LayananCs $cs) {}

    public function index(Request $request, ?PercakapanCs $percakapan = null): View
    {
        // Membuka ruang CS berarti siap menerima chat; staf bisa mematikannya.
        if (cs_aktif()) {
            $this->cs->tandaiHadir($request->user('web'), true);
        }

        return view('panel.cs.ruang', [
            'awal' => [
                'daftar' => $this->kueri('semua', '')->get()->map->untukDaftar()->values(),
                'jumlah' => $this->jumlah(),
                'terpilih' => $percakapan ? $this->detailUntuk($percakapan) : null,
                'template' => TemplateBalasan::orderBy('urutan')->orderBy('judul')->get(['id', 'judul', 'isi']),
                'topik' => collect(TopikCs::cases())->map(fn (TopikCs $t) => ['nilai' => $t->value, 'label' => $t->label()])->values(),
                'slaMenit' => (int) setting('cs_peringatan_menit', 15),
                'batasFotoMb' => (int) setting('upload_maks_mb', 10),
                // CS dimatikan (KEP-033): ruang ini jadi arsip baca-saja.
                'aktif' => cs_aktif(),
                'realtime' => config('broadcasting.default') === 'pusher' ? config('broadcasting.klien') : null,
                'url' => [
                    'daftar' => route('panel.cs.daftar'),
                    'dasar' => url('/panel/cs'),
                    'auth' => route('panel.cs.auth'),
                    'hadir' => route('panel.cs.hadir'),
                ],
            ],
        ]);
    }

    public function daftar(Request $request): JsonResponse
    {
        $saring = in_array($request->query('saring'), ['menunggu', 'terbuka', 'selesai', 'semua'], true)
            ? $request->query('saring')
            : 'semua';
        // Tombol saring di samping pencarian (KEP-150): per topik percakapan.
        $topik = TopikCs::tryFrom((string) $request->query('topik'));

        return response()->json([
            'daftar' => $this->kueri($saring, trim((string) $request->query('q')), $topik)->get()->map->untukDaftar()->values(),
            'jumlah' => $this->jumlah(),
        ]);
    }

    public function detail(PercakapanCs $percakapan): JsonResponse
    {
        return response()->json($this->detailUntuk($percakapan));
    }

    public function balas(Request $request, PercakapanCs $percakapan): JsonResponse
    {
        abort_unless(cs_aktif(), 422, 'Chat CS sedang dimatikan, jadi balasan tidak akan terlihat pelanggan.');

        $data = $request->validate([
            'isi' => ['required_without:lampiran', 'nullable', 'string', 'max:2000'],
            'lampiran' => LayananCs::aturanLampiran(),
        ], ['isi.required_without' => 'Balasannya masih kosong.'], ['isi' => 'balasan']);

        $pesan = $this->cs->balasDariStaf($percakapan, $request->user('web'), $data['isi'] ?? null, $request->file('lampiran'));

        return response()->json([
            'pesan' => $pesan->untukKlien('staf'),
            'percakapan' => $percakapan->fresh()->untukDaftar($pesan),
        ], 201);
    }

    /** Catatan internal staf (KEP-150); tidak pernah dikirim ke pelanggan. */
    public function catatan(Request $request, PercakapanCs $percakapan): JsonResponse
    {
        $data = $request->validate([
            'catatan' => ['nullable', 'string', 'max:2000'],
        ], [], ['catatan' => 'catatan internal']);

        $percakapan->update(['catatan_internal' => $data['catatan'] ?? null]);

        return response()->json(['percakapan' => $percakapan->fresh()?->untukDetail()]);
    }

    public function tutup(PercakapanCs $percakapan): JsonResponse
    {
        $this->cs->tutup($percakapan, auth('web')->user());

        return response()->json($this->detailUntuk($percakapan->fresh()));
    }

    public function tautkan(Request $request, PercakapanCs $percakapan): JsonResponse
    {
        $data = $request->validate(['kode' => ['required', 'string', 'max:20']], [], ['kode' => 'kode pesanan']);

        $order = Order::where('kode', strtoupper(trim($data['kode'])))->first();

        abort_unless($order, 422, 'Tidak ada pesanan dengan kode '.$data['kode'].'.');

        $this->cs->tautkanPesanan($percakapan, $order);

        return response()->json($this->detailUntuk($percakapan->fresh()));
    }

    /** Cadangan polling saat realtime tidak tersambung. */
    public function pesanBaru(Request $request, PercakapanCs $percakapan): JsonResponse
    {
        $pesan = $percakapan->pesan()->with(['percakapan', 'staf'])
            ->where('id', '>', (int) $request->query('setelah', 0))
            ->orderBy('id')
            ->limit(50)
            ->get();

        if ($pesan->contains('pengirim', 'PELANGGAN')) {
            $percakapan->update(['belum_dibaca_staf' => 0]);
        }

        return response()->json(['pesan' => $pesan->map->untukKlien('staf')]);
    }

    /** Untuk badge menu panel saat realtime tidak tersambung. */
    public function ringkasan(): JsonResponse
    {
        return response()->json([
            'belum_dibaca' => PercakapanCs::where('belum_dibaca_staf', '>', 0)->pluck('id'),
        ]);
    }

    /** Denyut "Menerima chat" dari ruang CS (KEP-031). */
    public function hadir(Request $request): JsonResponse
    {
        $this->cs->tandaiHadir($request->user('web'), $request->boolean('menerima', true));

        return response()->json(['online' => $this->cs->adaYangHadir()]);
    }

    /** Staf hanya perlu satu channel: semua pesan pelanggan lewat sana. */
    public function otorisasi(Request $request): JsonResponse
    {
        $channel = (string) $request->input('channel_name');

        abort_unless($channel === 'private-cs.staf', 403);

        $auth = $this->cs->otorisasi($channel, (string) $request->input('socket_id'));

        abort_unless($auth, 403);

        return response()->json($auth);
    }

    public function lampiran(Request $request, PercakapanCs $percakapan, PesanCs $pesan): Response
    {
        abort_unless($pesan->percakapan_id === $percakapan->id && $pesan->lampiran_path, 404);

        // Tombol unduh di Ruang CS (KEP-150): desain dari pelanggan disimpan
        // sebagai berkas, bukan hanya dibuka di tab.
        $cara = $request->boolean('unduh') ? 'download' : 'response';

        return Storage::disk('local')->{$cara}($pesan->lampiran_path, null, LayananCs::HEADER_LAMPIRAN);
    }

    private function detailUntuk(PercakapanCs $percakapan): array
    {
        if ($percakapan->belum_dibaca_staf > 0) {
            $percakapan->update(['belum_dibaca_staf' => 0]);
        }

        return [
            'percakapan' => $percakapan->untukDetail(),
            'pesan' => $percakapan->pesan()->with(['percakapan', 'staf'])
                ->orderByDesc('id')->limit(200)->get()
                ->reverse()->values()->map->untukKlien('staf'),
        ];
    }

    private function kueri(string $saring, string $cari, ?TopikCs $topik = null): Builder
    {
        return PercakapanCs::query()
            ->with('pesanTerakhir')
            ->when($topik, fn ($q) => $q->where('topik', $topik))
            // Tab "Baru" dan "Diproses" di gambar klien (KEP-150): yang menunggu
            // balasan dan yang sedang ditangani tidak tumpang tindih.
            ->when($saring === 'menunggu', fn ($q) => $q->where('status', 'TERBUKA')->whereNotNull('menunggu_sejak'))
            ->when($saring === 'terbuka', fn ($q) => $q->where('status', 'TERBUKA')->whereNull('menunggu_sejak'))
            ->when($saring === 'selesai', fn ($q) => $q->where('status', 'SELESAI'))
            ->when($cari !== '', function ($q) use ($cari) {
                $pola = '%'.$cari.'%';
                $q->where(fn ($w) => $w->where('nama', 'like', $pola)
                    ->orWhere('kontak', 'like', $pola)
                    ->orWhereHas('order', fn ($o) => $o->where('kode', 'like', $pola)));
            })
            ->untukKotakMasuk()
            ->limit(100);
    }

    /** @return array{semua: int, menunggu: int, terbuka: int, selesai: int} */
    private function jumlah(): array
    {
        return [
            'semua' => PercakapanCs::count(),
            'menunggu' => PercakapanCs::where('status', 'TERBUKA')->whereNotNull('menunggu_sejak')->count(),
            'terbuka' => PercakapanCs::where('status', 'TERBUKA')->whereNull('menunggu_sejak')->count(),
            'selesai' => PercakapanCs::where('status', 'SELESAI')->count(),
        ];
    }
}
