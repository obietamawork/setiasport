<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\FaqBot;
use App\Models\LogBot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * FAQ chatbot CS dan kurasi pertanyaan yang belum terjawab (KEP-151).
 *
 * Pertanyaan yang dijawab AI atau diteruskan ke admin karena bot tidak tahu
 * jawabannya muncul di "Belum terjawab". Menjadikannya FAQ sekali berarti
 * pertanyaan yang sama berikutnya dijawab seketika, tanpa AI dan tanpa admin.
 */
class FaqBotController extends Controller
{
    public function index(Request $request): View
    {
        $awalBulan = now()->startOfMonth();
        $bulanIni = LogBot::where('created_at', '>=', $awalBulan);

        return view('panel.faq.index', [
            'tab' => $request->query('tab') === 'belum' ? 'belum' : 'faq',
            'faq' => FaqBot::orderBy('urutan')->orderBy('id')->get(),
            'belum' => LogBot::belumTerjawab()->latest('id')->limit(100)->get(),
            'jumlahBelum' => LogBot::belumTerjawab()->count(),
            'ubah' => $request->filled('ubah') ? FaqBot::find($request->integer('ubah')) : null,
            'dariLog' => $request->filled('log') ? LogBot::find($request->integer('log')) : null,
            'statistik' => [
                'faq' => (clone $bulanIni)->where('lapis', 'faq')->count(),
                'otomatis' => (clone $bulanIni)->whereIn('lapis', ['menu', 'sistem'])->count(),
                'ai' => (clone $bulanIni)->where('lapis', 'ai')->count(),
                'admin' => (clone $bulanIni)->where('lapis', 'admin')->count(),
                'biaya' => (float) (clone $bulanIni)->where('lapis', 'ai')->sum('biaya'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        FaqBot::create([...$this->validasi($request), 'urutan' => (int) FaqBot::max('urutan') + 1]);

        if ($request->filled('log_id')) {
            LogBot::whereKey($request->integer('log_id'))->update(['dikurasi_pada' => now()]);
        }

        return redirect()->route('panel.faq.index')->with('sukses', 'FAQ ditambahkan. Pertanyaan serupa berikutnya dijawab otomatis.');
    }

    public function update(Request $request, FaqBot $faq): RedirectResponse
    {
        $faq->update($this->validasi($request));

        return redirect()->route('panel.faq.index')->with('sukses', 'FAQ disimpan.');
    }

    public function destroy(FaqBot $faq): RedirectResponse
    {
        $faq->delete();

        return redirect()->route('panel.faq.index')->with('sukses', 'FAQ dihapus.');
    }

    /** Pertanyaan yang tidak perlu dijadikan FAQ, misalnya salah ketik atau pertanyaan sekali lewat. */
    public function abaikan(LogBot $log): RedirectResponse
    {
        $log->update(['dikurasi_pada' => now()]);

        return redirect()->route('panel.faq.index', ['tab' => 'belum'])->with('sukses', 'Pertanyaan diabaikan.');
    }

    /** @return array{pertanyaan: string, jawaban: string, kata_kunci: ?string, tampil_di_menu: bool, aktif: bool} */
    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'pertanyaan' => ['required', 'string', 'max:200'],
            'jawaban' => ['required', 'string', 'max:2000'],
            'kata_kunci' => ['nullable', 'string', 'max:1000'],
            'tampil_di_menu' => ['nullable', 'boolean'],
            'aktif' => ['nullable', 'boolean'],
            'log_id' => ['nullable', 'integer'],
        ], [], ['kata_kunci' => 'kata kunci', 'tampil_di_menu' => 'tampil di menu']);

        return [
            'pertanyaan' => $data['pertanyaan'],
            'jawaban' => $data['jawaban'],
            'kata_kunci' => $data['kata_kunci'] ?? null,
            'tampil_di_menu' => $request->boolean('tampil_di_menu'),
            'aktif' => $request->boolean('aktif'),
        ];
    }
}
