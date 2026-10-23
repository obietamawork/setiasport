@extends('layouts.publik')
@section('judul', $judul)
@section('deskripsi', $ringkas)

@section('isi')
<article class="mx-auto max-w-3xl px-5 py-12 sm:py-16">
    <p class="text-xs font-semibold tracking-[0.14em] text-muted uppercase">{{ setting('toko_nama', 'Setia Sport') }}</p>
    <h1 class="mt-2 text-3xl font-extrabold text-balance sm:text-4xl">{{ $judul }}</h1>
    @if ($diperbarui)
        <p class="mt-3 text-sm text-muted">Terakhir diperbarui {{ $diperbarui->translatedFormat('j F Y') }}</p>
    @endif

    {{-- Isinya dari Pengaturan, sudah dibersihkan dari HTML mentah oleh
         DrafHukum::html() (html_input=strip), jadi aman dicetak apa adanya. --}}
    <div class="isi-dokumen mt-8">{!! $isi !!}</div>

    <p class="mt-10 border-t border-line pt-5 text-sm text-muted">
        Ada pertanyaan tentang halaman ini? Hubungi kami
        @if (setting('toko_telepon'))
            lewat WhatsApp <span class="tabular">{{ setting('toko_telepon') }}</span> atau
        @endif
        datang langsung ke toko.
    </p>
</article>
@endsection
