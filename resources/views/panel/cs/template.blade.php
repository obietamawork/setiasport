@extends('layouts.panel')
@section('judul', 'Template balasan')

@section('isi')
<x-kop judul="Template balasan" :kembali="route('panel.cs.index')" kembali-label="Chat"
       catatan="Jawaban siap pakai untuk pertanyaan yang berulang. Muncul sebagai tombol di atas kolom balasan; menekannya menyalin isinya, dan Anda masih bisa menyuntingnya sebelum dikirim." />

<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,24rem)] lg:items-start">
    <div class="kartu divide-y divide-line overflow-hidden">
        @forelse ($daftar as $t)
            <div class="flex items-start gap-4 px-4 py-3">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">{{ $t->judul }}</p>
                    <p class="mt-1 line-clamp-2 text-sm leading-relaxed whitespace-pre-line text-muted">{{ $t->isi }}</p>
                </div>
                <a href="{{ route('panel.cs.template.edit', $t) }}" class="tautan shrink-0 text-sm">Ubah</a>
            </div>
        @empty
            <p class="px-4 py-8 text-center text-sm text-muted">
                Belum ada template. Mulai dari pertanyaan yang paling sering datang, misalnya
                harga jersey custom atau minimal pemesanan.
            </p>
        @endforelse
    </div>

    <form method="POST"
          action="{{ $template->exists ? route('panel.cs.template.update', $template) : route('panel.cs.template.store') }}"
          class="kartu space-y-4 p-5">
        @csrf
        @if ($template->exists) @method('PUT') @endif

        <h2 class="font-semibold">{{ $template->exists ? 'Ubah template' : 'Template baru' }}</h2>

        <div>
            <label for="judul" class="label-form">Judul tombol</label>
            <input id="judul" name="judul" value="{{ old('judul', $template->judul) }}" class="kotak-input"
                   maxlength="100" required placeholder="Harga jersey custom">
        </div>

        <div>
            <label for="isi" class="label-form">Isi balasan</label>
            <textarea id="isi" name="isi" rows="7" class="kotak-input leading-relaxed" maxlength="2000" required>{{ old('isi', $template->isi) }}</textarea>
        </div>

        <div>
            <label for="urutan" class="label-form">Urutan</label>
            <input type="number" id="urutan" name="urutan" value="{{ old('urutan', $template->urutan ?? 0) }}"
                   class="kotak-input tabular max-w-28" min="0" max="999">
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-line pt-4">
            <button type="submit" class="tombol tombol-navy">Simpan template</button>
            @if ($template->exists)
                <a href="{{ route('panel.cs.template.index') }}" class="tautan text-sm">Batal</a>
            @endif
        </div>
    </form>
</div>

@if ($template->exists)
    <form method="POST" action="{{ route('panel.cs.template.destroy', $template) }}" class="mt-4"
          onsubmit="return confirm('Hapus template ini?')">
        @csrf @method('DELETE')
        <button type="submit" class="tautan text-sm text-muted">Hapus template ini</button>
    </form>
@endif
@endsection
