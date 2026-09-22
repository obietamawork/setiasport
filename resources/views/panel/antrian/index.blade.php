@extends('layouts.panel')
@section('judul', 'Antrian kerja')

@section('isi')
<x-kop judul="Antrian kerja"
       catatan="Divisi yang menjadi tanggung jawab Anda. Angka merah berarti ada yang menunggu dikerjakan." />

@if (empty($tahap))
    <div class="kartu mt-6 p-8 text-center">
        <x-ikon nama="antrian" ukuran="size-9" class="mx-auto text-navy-300" />
        <p class="mt-3 text-lg font-bold">Anda belum memegang antrian mana pun</p>
        <p class="mx-auto mt-2 max-w-sm text-sm text-muted">
            Minta pemilik toko menambahkan peran produksi ke akun Anda.
        </p>
    </div>
@else
    {{-- Kalimat pembuka menjawab pertanyaan pertama orang konveksi tiap pagi:
         ada kerjaan tidak hari ini. Sebelumnya ia harus membaca tujuh kotak
         berisi angka nol untuk menyimpulkan hal yang sama. --}}
    <div class="langkah-berikut mt-1 {{ $totalKerjaan > 0 ? 'langkah-berikut-mendesak' : 'langkah-berikut-selesai' }}">
        <div class="min-w-0 flex-1">
            @if ($totalKerjaan > 0)
                <p class="font-bold">{{ $totalKerjaan }} pekerjaan menunggu di meja Anda</p>
                <p class="mt-0.5 text-sm text-muted">
                    Buka divisi yang angkanya merah. Menandai selesai di sana sekaligus menaikkan
                    status pesanan pelanggan, jadi tidak perlu dicatat dua kali.
                </p>
            @else
                <p class="font-bold">Semua antrian kosong</p>
                <p class="mt-0.5 text-sm text-muted">
                    Tidak ada yang menunggu dikerjakan. Pekerjaan baru muncul sendiri di sini
                    begitu tahap sebelumnya selesai.
                </p>
            @endif
        </div>
    </div>

    {{-- Urutannya mengikuti meja kerja konveksi (KEP-037), bukan diurutkan
         menurut yang paling ramai: orang mencari divisinya di tempat yang
         sama setiap hari. --}}
    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($tahap as $t)
            @php
                $r = $ringkas[$t->value] ?? ['jumlah' => 0, 'tertua' => null, 'pesanan' => []];
                $ada = $r['jumlah'] > 0;
            @endphp

            <a href="{{ route('panel.antrian.tahap', strtolower($t->value)) }}"
               class="kartu kartu-tautan flex flex-col gap-3 p-5 {{ $ada ? '' : 'opacity-70' }}">
                <div class="flex items-center gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-[10px]
                                 {{ $ada ? 'bg-merah-50 text-merah-tua' : 'bg-navy-50 text-navy-400' }}">
                        <x-ikon :nama="$t->ikon()" />
                    </span>
                    <span class="min-w-0 flex-1 font-bold">{{ $t->label() }}</span>
                    <span class="numeral text-3xl {{ $ada ? 'text-merah' : 'text-line-strong' }}">
                        {{ $r['jumlah'] }}
                    </span>
                </div>

                @if ($ada)
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                        @if ($r['tertua'])
                            <span class="inline-flex items-center gap-1">
                                <x-ikon nama="jam" ukuran="size-3.5" />
                                Paling lama menunggu {{ $r['tertua']->diffForHumans(short: true) }}
                            </span>
                        @endif
                        @foreach ($r['pesanan'] as $kode)
                            <span class="tabular">{{ $kode }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-muted">Kosong.</p>
                @endif
            </a>
        @endforeach
    </div>
@endif
@endsection
