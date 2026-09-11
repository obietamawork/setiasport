<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\TemplateBalasan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Jawaban siap pakai di chat CS (KEP-030). Klien: pertanyaan pesanan custom
 * "tinggal dijawab sesuai template".
 */
class TemplateBalasanController extends Controller
{
    public function index()
    {
        return view('panel.cs.template', [
            'daftar' => TemplateBalasan::orderBy('urutan')->orderBy('judul')->get(),
            'template' => new TemplateBalasan,
        ]);
    }

    public function edit(TemplateBalasan $template)
    {
        return view('panel.cs.template', [
            'daftar' => TemplateBalasan::orderBy('urutan')->orderBy('judul')->get(),
            'template' => $template,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = TemplateBalasan::create($this->validasi($request));

        ActivityLog::catat('cs.template-dibuat', $template, ['judul' => $template->judul]);

        return redirect()->route('panel.cs.template.index')->with('sukses', 'Template "'.$template->judul.'" ditambahkan.');
    }

    public function update(Request $request, TemplateBalasan $template): RedirectResponse
    {
        $template->update($this->validasi($request));

        ActivityLog::catat('cs.template-diubah', $template, ['judul' => $template->judul]);

        return redirect()->route('panel.cs.template.index')->with('sukses', 'Template "'.$template->judul.'" disimpan.');
    }

    public function destroy(TemplateBalasan $template): RedirectResponse
    {
        $judul = $template->judul;
        $template->delete();

        ActivityLog::catat('cs.template-dihapus', null, ['judul' => $judul]);

        return redirect()->route('panel.cs.template.index')->with('sukses', 'Template "'.$judul.'" dihapus.');
    }

    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:100'],
            'isi' => ['required', 'string', 'max:2000'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [], ['judul' => 'judul template', 'isi' => 'isi template']);

        $data['urutan'] = $data['urutan'] ?? 0;

        return $data;
    }
}
