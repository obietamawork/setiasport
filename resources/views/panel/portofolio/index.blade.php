@extends('layouts.panel')
@section('judul', 'Portofolio')

@section('isi')
<x-kop judul="Portofolio"
       catatan="Pekerjaan yang pernah selesai, dipajang di halaman depan. Sekolah dan instansi memilih berdasarkan bukti pernah mengerjakan yang serupa, jadi isi ini yang paling sering dibaca calon pemesan.">
    <a href="{{ route('panel.portofolio.create') }}" class="tombol tombol-navy tombol-sm">
        <x-ikon nama="tambah" ukuran="size-4" />
        Tambah pekerjaan
    </a>
</x-kop>

@include('panel.komponen.banner-contoh')

@if ($daftar->isEmpty())
    <div class="kartu mt-6 p-8 text-center">
        <p class="text-lg font-bold">Belum ada portofolio</p>
        <p class="mt-2 text-sm leading-relaxed text-muted">
            Halaman depan akan melewati bagian ini sampai ada isinya. Mulai dari satu
            pekerjaan yang paling membanggakan, lengkap dengan fotonya.
        </p>
        <a href="{{ route('panel.portofolio.create') }}" class="tombol tombol-navy mt-5">Tambah yang pertama</a>
    </div>
@else
    <div class="kartu mt-6 divide-y divide-line overflow-hidden">
        @foreach ($daftar as $p)
            <div class="flex items-center gap-4 px-4 py-3">
                @if ($p->thumb_path)
                    <img src="{{ asset('storage/'.$p->thumb_path) }}" alt=""
                         class="size-14 shrink-0 border border-line object-cover">
                @endif

                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold">{{ $p->judul }}</p>
                    <p class="mt-0.5 truncate text-xs text-muted">
                        {{ $p->keterangan() ?: 'Tanpa keterangan' }}
                    </p>
                </div>

                @if ($p->contoh)
                    <span class="chip chip-kuning">Contoh</span>
                @endif
                @unless ($p->aktif)
                    <span class="chip chip-netral">Disembunyikan</span>
                @endunless

                <a href="{{ route('panel.portofolio.edit', $p) }}" class="tautan text-sm">Ubah</a>
            </div>
        @endforeach
    </div>
@endif
@endsection
