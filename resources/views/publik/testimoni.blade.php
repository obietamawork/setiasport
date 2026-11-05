@extends('layouts.publik')
@section('judul', $testimoni->currentPage() > 1 ? 'Testimoni pelanggan (halaman '.$testimoni->currentPage().')' : 'Testimoni pelanggan')
@section('deskripsi', 'Pengalaman sekolah, tim, dan instansi yang sudah memesan di '.setting('toko_nama', 'Setia Sport').'.')
@section('subjudul', 'Testimoni pelanggan')
@section('subcatatan', $testimoni->total().' testimoni')
@section('kanonik', route('testimoni', array_filter(request()->only('page'))))

@section('isi')
<div class="mx-auto max-w-6xl px-5 py-10 sm:py-12">
    <nav class="text-sm text-muted" aria-label="Remah roti">
        <a href="{{ route('beranda') }}" class="tautan">Beranda</a>
        <span class="mx-2 text-line-strong">/</span>
        <span class="text-ink">Testimoni</span>
    </nav>

    <h1 class="mt-3 text-3xl font-extrabold sm:text-4xl">Testimoni pelanggan</h1>
    <p class="kolom-baca mt-3 leading-relaxed text-muted">
        Cerita dari sekolah, tim, dan instansi yang sudah memesan di kami.
    </p>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($testimoni as $t)
            @include('komponen.kartu-testimoni', ['t' => $t])
        @endforeach
    </div>

    @if ($testimoni->hasPages())
        <div class="mt-8">{{ $testimoni->links() }}</div>
    @endif

    @include('komponen.ajakan-kustom')
</div>
@endsection
