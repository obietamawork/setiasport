@extends('layouts.panel')
@section('judul', 'Banner beranda')

@section('isi')
<x-kop judul="Banner beranda"
       catatan="Promo di puncak halaman depan. Satu banner tampil diam; dua atau lebih bisa digeser pengunjung, tidak pernah bergeser sendiri. Tanpa banner yang tayang, halaman depan menampilkan judul dan tombol seperti biasa.">
    <a href="{{ route('panel.banner.create') }}" class="tombol tombol-navy tombol-sm">
        <x-ikon nama="tambah" ukuran="size-4" />
        Tambah banner
    </a>
</x-kop>

@php $tayang = $daftar->filter(fn ($b) => $b->keadaan()[0] === 'Tayang')->count(); @endphp

{{-- Banner keempat dan seterusnya hampir tidak pernah dilihat: kebanyakan
     orang berhenti di yang pertama. Diingatkan, tidak dilarang. --}}
@if ($tayang > 3)
    <p class="notis notis-perhatian mt-6">
        {{ $tayang }} banner sedang tayang. Pengunjung jarang menggeser lebih dari dua atau tiga kali;
        taruh promo terpenting di urutan pertama.
    </p>
@endif

@if ($daftar->isEmpty())
    <div class="kartu mt-6 p-8 text-center">
        <p class="text-lg font-bold">Belum ada banner</p>
        <p class="mt-2 text-sm leading-relaxed text-muted">
            Halaman depan menampilkan judul dan tombol dari Pengaturan sampai ada banner yang tayang.
            Cocok untuk promo yang berganti: diskon jersey tim, produk baru, atau jadwal libur toko.
        </p>
        <a href="{{ route('panel.banner.create') }}" class="tombol tombol-navy mt-5">Tambah yang pertama</a>
    </div>
@else
    <div class="kartu mt-6 divide-y divide-line overflow-hidden">
        @foreach ($daftar as $b)
            @php [$keadaan, $chip] = $b->keadaan(); @endphp
            <div class="flex items-center gap-4 px-4 py-3">
                <img src="{{ asset('storage/'.$b->gambar_path) }}" alt=""
                     class="aspect-[8/3] w-24 shrink-0 border border-line object-cover sm:w-32">

                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold">{{ $b->judul }}</p>
                    <p class="mt-0.5 truncate text-xs text-muted">
                        {{ $b->mulai_pada || $b->selesai_pada
                            ? ($b->mulai_pada?->translatedFormat('j M Y') ?? 'Sekarang').' sampai '.($b->selesai_pada?->translatedFormat('j M Y') ?? 'seterusnya')
                            : 'Tanpa batas waktu' }}
                        &middot; urutan {{ $b->urutan }}
                    </p>
                    <span class="chip {{ $chip }} mt-1.5 sm:hidden">{{ $keadaan }}</span>
                </div>

                <span class="chip {{ $chip }} hidden whitespace-nowrap sm:inline-flex">{{ $keadaan }}</span>
                <a href="{{ route('panel.banner.edit', $b) }}" class="tautan text-sm">Ubah</a>
            </div>
        @endforeach
    </div>
@endif
@endsection
