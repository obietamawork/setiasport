@extends('layouts.panel')
@section('judul', 'Kesiapan tayang')

@section('isi')
{{--
    Daftar apa yang masih kurang sebelum website dibuka untuk umum.

    Semuanya dihitung dari data, bukan dari centang manual: centang manual selalu
    berbohong lebih cepat daripada datanya. Yang "belum" pun disertai alasan,
    supaya pemilik toko bisa memutuskan sendiri mana yang benar-benar menghalangi
    dan mana yang bisa menyusul.
--}}
<x-kop judul="Kesiapan tayang"
       catatan="Kurang apa lagi sebelum website dibuka untuk umum. Dihitung dari isi sistem, bukan dari ingatan." />

@php
    $persen = $ringkasan['total'] > 0
        ? (int) round($ringkasan['siap'] / $ringkasan['total'] * 100)
        : 100;
    $tuntas = $ringkasan['siap'] === $ringkasan['total'];
@endphp

<div class="langkah-berikut {{ $tuntas ? 'langkah-berikut-selesai' : '' }}">
    <div class="min-w-0 grow basis-64">
        <p class="font-bold">
            @if ($tuntas)
                Semua yang wajib sudah siap
            @else
                {{ $ringkasan['siap'] }} dari {{ $ringkasan['total'] }} hal wajib sudah siap
            @endif
        </p>
        <p class="kolom-baca mt-0.5 text-sm leading-relaxed text-muted">
            @if ($tuntas)
                Website bisa dibuka untuk umum. Yang bertanda "sebaiknya" di bawah tidak menghalangi,
                tapi mengerjakannya membuat halaman depan jauh lebih meyakinkan.
            @else
                Yang bertanda <strong>wajib</strong> perlu beres dulu: tanpa itu ada pelanggan yang
                salah datang, salah transfer, atau melihat isi karangan.
            @endif
        </p>

        {{-- Batang kemajuan dibaca sekilas, angkanya dibaca kalau perlu tepat. --}}
        <div class="mt-3 h-2 w-full max-w-sm overflow-hidden rounded-full bg-navy-100">
            <div class="h-full rounded-full transition-[width] duration-500 {{ $tuntas ? 'bg-ok' : 'bg-navy' }}"
                 style="width: {{ max($persen, 3) }}%"></div>
        </div>
    </div>
</div>

<div class="mt-6 space-y-4">
    @foreach ($bagian as $b)
        @php
            $belum = collect($b['isi'])->where('siap', false);
            $wajibBelum = $belum->where('wajib', true)->count();
        @endphp

        <section id="{{ $b['kunci'] }}" class="kartu scroll-mt-32 overflow-hidden">
            <div class="kartu-kop flex flex-wrap items-start gap-2.5">
                <x-ikon :nama="$b['ikon']" class="mt-0.5 text-navy-400" />
                <span class="min-w-0 flex-1">
                    {{ $b['judul'] }}
                    <span class="block text-sm leading-relaxed font-normal text-muted">{{ $b['catatan'] }}</span>
                </span>

                @if ($belum->isEmpty())
                    <span class="chip chip-ok">
                        <x-ikon nama="centang" ukuran="size-3.5" />
                        Beres
                    </span>
                @elseif ($wajibBelum > 0)
                    <span class="chip chip-merah">{{ $wajibBelum }} wajib</span>
                @else
                    <span class="chip chip-kuning">{{ $belum->count() }} sebaiknya</span>
                @endif
            </div>

            <ul class="divide-y divide-line">
                @foreach ($b['isi'] as $i)
                    <li class="flex flex-wrap items-start gap-x-4 gap-y-2 px-5 py-4">
                        <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full
                                     {{ $i['siap'] ? 'bg-ok-50 text-ok' : ($i['wajib'] ? 'bg-merah-50 text-merah' : 'bg-kuning-50 text-warn') }}">
                            <x-ikon :nama="$i['siap'] ? 'centang' : ($i['wajib'] ? 'peringatan' : 'jam')" ukuran="size-3.5" />
                        </span>

                        <div class="min-w-48 flex-1">
                            <p class="flex flex-wrap items-center gap-2 font-semibold">
                                {{ $i['judul'] }}
                                @unless ($i['wajib'])
                                    <span class="chip chip-netral">sebaiknya</span>
                                @endunless
                            </p>
                            <p class="kolom-baca mt-1 text-sm leading-relaxed text-muted">{{ $i['kenapa'] }}</p>
                        </div>

                        <div class="flex items-center gap-3 text-sm">
                            <span class="{{ $i['siap'] ? 'text-muted' : 'font-medium text-ink' }}">
                                {{ $i['keadaan'] }}
                            </span>

                            @if ($i['rute'] && ! $i['siap'])
                                <a href="{{ route($i['rute'], $i['tautan'] ?? []) }}"
                                   class="tombol tombol-garis tombol-sm">
                                    Perbaiki
                                    <x-ikon nama="lanjut" ukuran="size-4" />
                                </a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</div>

<p class="kolom-baca mt-6 text-sm leading-relaxed text-muted">
    Yang tidak bisa dilihat sistem sendiri tidak ada di daftar ini: domain sudah dibeli, hosting
    sudah disiapkan, cron sudah dipasang, dan email pengirim sudah diverifikasi. Langkah-langkah itu
    ada di catatan deploy, dan dikerjakan sekali saat website dipindah ke hosting sungguhan.
</p>
@endsection
