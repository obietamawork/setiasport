@extends('layouts.panel')
@section('judul', 'Testimoni')

@section('isi')
<div class="kop-halaman">
    <div>
        <h1 class="kop-judul">Testimoni</h1>
        <p class="kop-catatan">
            Kutipan pelanggan yang tampil di halaman depan. Diketik ulang di sini dari pesan
            WhatsApp atau percakapan di toko, jadi tidak ada antrian moderasi yang menumpuk.
        </p>
    </div>
    <a href="{{ route('panel.testimoni.create') }}" class="tombol tombol-navy">Tambah testimoni</a>
</div>

@if ($daftar->isEmpty())
    <div class="kartu mt-6 p-8 text-center">
        <p class="text-lg font-bold">Belum ada testimoni</p>
        <p class="mt-2 text-sm leading-relaxed text-muted">
            Halaman depan melewati bagian ini sampai ada isinya. Mintalah izin lebih dulu
            sebelum menuliskan nama seseorang di sini.
        </p>
        <a href="{{ route('panel.testimoni.create') }}" class="tombol tombol-navy mt-5">Tambah yang pertama</a>
    </div>
@else
    <div class="kartu mt-6 divide-y divide-line overflow-hidden">
        @foreach ($daftar as $t)
            <div class="flex items-start gap-4 px-4 py-3">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">
                        {{ $t->nama }}
                        @if ($t->peran)
                            <span class="font-normal text-muted">{{ $t->peran }}</span>
                        @endif
                    </p>
                    <p class="mt-1 line-clamp-2 text-sm leading-relaxed text-muted">{{ $t->isi }}</p>
                </div>

                @if ($t->bintang)
                    <span class="tabular shrink-0 text-sm text-muted">{{ $t->bintang }}/5</span>
                @endif

                @unless ($t->aktif)
                    <span class="chip chip-netral shrink-0">Disembunyikan</span>
                @endunless

                <a href="{{ route('panel.testimoni.edit', $t) }}" class="tautan shrink-0 text-sm">Ubah</a>
            </div>
        @endforeach
    </div>
@endif
@endsection
