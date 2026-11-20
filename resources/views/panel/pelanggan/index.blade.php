@extends('layouts.panel')
@section('judul', 'Pelanggan')

@section('isi')
<x-kop judul="Pelanggan"
       catatan="Semua pemesan, baik yang punya akun maupun tamu. Staf tidak pernah melihat kata sandi pelanggan; yang bisa dilakukan hanya mengirim tautan atur ulang ke email pelanggan itu sendiri." />

<form method="GET" class="mt-6 flex flex-wrap items-end gap-3">
    <div class="min-w-56 flex-1">
        <label for="q" class="label-form">Cari nama, email, atau nomor HP</label>
        <input type="search" id="q" name="q" value="{{ $kata }}" class="kotak-input" placeholder="Contoh: Budi, budi@gmail.com, 0812...">
    </div>
    <div>
        <label for="saring" class="label-form">Tampilkan</label>
        <select id="saring" name="saring" class="kotak-input">
            @foreach (['semua' => 'Semua', 'akun' => 'Punya akun', 'tamu' => 'Tamu (tanpa akun)', 'nonaktif' => 'Akun nonaktif'] as $nilai => $label)
                <option value="{{ $nilai }}" @selected($saring === $nilai)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="tombol tombol-navy">Cari</button>
</form>

<section class="kartu mt-6 overflow-hidden">
    @if ($daftar->isEmpty())
        <p class="p-5 text-sm text-muted">Tidak ada pelanggan yang cocok.</p>
    @else
        <div class="divide-y divide-line">
            @foreach ($daftar as $c)
                <a href="{{ route('panel.pelanggan.show', $c) }}" class="flex flex-wrap items-center justify-between gap-3 p-4 hover:bg-paper">
                    <div class="min-w-0">
                        <p class="font-semibold">
                            {{ $c->nama }}
                            @if ($c->aktif === false)
                                <span class="chip chip-merah">Nonaktif</span>
                            @elseif ($c->akunAktif())
                                <span class="chip chip-hijau">Akun</span>
                            @else
                                <span class="chip chip-netral">Tamu</span>
                            @endif
                            @if ($c->reseller)
                                <span class="chip chip-ungu">Reseller</span>
                            @endif
                        </p>
                        <p class="mt-0.5 truncate text-sm text-muted">{{ $c->email }} · {{ $c->no_hp }}</p>
                    </div>
                    <div class="text-right text-sm">
                        <p class="tabular">{{ $c->orders_count }} pesanan</p>
                        @if ($c->orders_max_created_at)
                            <p class="text-xs text-muted">Terakhir {{ \Illuminate\Support\Carbon::parse($c->orders_max_created_at)->translatedFormat('j M Y') }}</p>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</section>

<div class="mt-6">{{ $daftar->links() }}</div>
@endsection
