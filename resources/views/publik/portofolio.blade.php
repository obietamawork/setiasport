@extends('layouts.publik')
@section('judul', $portofolio->currentPage() > 1 ? 'Galeri portofolio (halaman '.$portofolio->currentPage().')' : 'Galeri portofolio')
@section('deskripsi', 'Jersey dan seragam yang pernah dikerjakan '.setting('toko_nama', 'Setia Sport').' untuk sekolah, instansi, dan komunitas.')
@section('subjudul', 'Galeri portofolio')
@section('subcatatan', $portofolio->total().' pekerjaan')
@section('kanonik', route('portofolio', array_filter(request()->only('page'))))

@section('isi')
<div class="mx-auto max-w-6xl px-5 py-10 sm:py-12">
    <nav class="text-sm text-muted" aria-label="Remah roti">
        <a href="{{ route('beranda') }}" class="tautan">Beranda</a>
        <span class="mx-2 text-line-strong">/</span>
        <span class="text-ink">Portofolio</span>
    </nav>

    <h1 class="mt-3 text-3xl font-extrabold sm:text-4xl">Galeri portofolio</h1>
    <p class="kolom-baca mt-3 leading-relaxed text-muted">
        Sebagian pekerjaan yang pernah kami selesaikan untuk sekolah, instansi, dan komunitas
        di sekitar Wonogiri.
    </p>

    {{-- Halaman ini punya ruang untuk ringkasan pekerjaan, jadi judulnya di
         bawah foto, bukan di atas bayangan seperti ubin beranda (KEP-106). --}}
    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($portofolio as $p)
            <article class="kartu flex flex-col overflow-hidden">
                <div class="relative aspect-4/3 shrink-0 bg-navy-50">
                    @if ($p->gambar_path)
                        <img src="{{ asset('storage/'.($p->thumb_path ?: $p->gambar_path)) }}"
                             alt="{{ $p->judul }}" loading="{{ $loop->index < 3 ? 'eager' : 'lazy' }}" decoding="async"
                             class="absolute inset-0 size-full object-cover">
                    @else
                        <span aria-hidden="true" class="absolute inset-0 flex items-center justify-center px-4 text-center text-sm font-semibold text-muted">
                            {{ $p->judul }}
                        </span>
                    @endif
                </div>
                <div class="flex flex-1 flex-col p-4 sm:p-5">
                    @if ($p->kategori)
                        <p class="text-xs font-semibold tracking-[0.08em] text-muted uppercase">{{ $p->kategori }}</p>
                    @endif
                    <h2 class="mt-1 leading-snug font-bold">{{ $p->judul }}</h2>
                    @if ($p->keterangan())
                        <p class="mt-1 text-sm text-muted">{{ $p->keterangan() }}</p>
                    @endif
                    @if ($p->ringkasan)
                        <p class="mt-3 text-sm leading-relaxed text-ink">{{ $p->ringkasan }}</p>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    @if ($portofolio->hasPages())
        <div class="mt-8">{{ $portofolio->links() }}</div>
    @endif

    @include('komponen.ajakan-kustom')
</div>
@endsection
