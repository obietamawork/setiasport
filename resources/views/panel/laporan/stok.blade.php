@extends('layouts.panel')
@section('judul', 'Stok barang')

@section('isi')
<x-kop judul="Stok barang"
       catatan="Isi rak barang ready stock saat ini, berikut nilainya menurut harga jual. Tersedia berarti stok dikurangi yang sudah dipesan pelanggan.">
    @if (auth('web')->user()->punyaIzin('stok.kelola'))
        <a href="{{ route('panel.stok.index') }}" class="tombol tombol-navy tombol-sm">
            <x-ikon nama="stok" ukuran="size-4" />
            Stok opname
        </a>
    @endif
</x-kop>

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <x-kartu-angka warna="hijau" ikon="uang" label="Nilai stok (harga jual)" :angka="rupiah_ringkas($nilaiStok)" />
    <x-kartu-angka warna="biru" ikon="stok" label="Unit di rak" :angka="number_format($totalUnit, 0, ',', '.')" />
    <x-kartu-angka warna="jingga" ikon="peringatan" label="Menipis (sisa 1 sampai 3)" :angka="$jumlahMenipis"
                   :href="route('panel.laporan.stok', ['status' => 'menipis'])" :aktif="$status === 'menipis'" />
    <x-kartu-angka warna="merah" ikon="silang" label="Habis" :angka="$jumlahHabis"
                   :href="route('panel.laporan.stok', ['status' => 'habis'])" :aktif="$status === 'habis'" />
</div>

<form method="GET" class="mt-6 flex flex-wrap items-center gap-2">
    <label for="status-stok" class="sr-only">Status stok</label>
    <select id="status-stok" name="status" class="kotak-input w-auto" onchange="this.form.submit()">
        @foreach (['' => 'Semua status', 'aman' => 'Aman', 'menipis' => 'Menipis', 'habis' => 'Habis'] as $nilai => $label)
            <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
        @endforeach
    </select>
    <div class="relative min-w-0 flex-1 basis-56 sm:max-w-xs">
        <label for="cari-stok" class="sr-only">Cari produk</label>
        <x-ikon nama="cari" ukuran="size-4" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-navy-300" />
        <input type="search" id="cari-stok" name="q" value="{{ request('q') }}" placeholder="Nama atau SKU&hellip;" class="kotak-input pl-9">
    </div>
    <button type="submit" class="tombol tombol-garis tombol-sm">Terapkan</button>
    @if ($status !== '' || request()->filled('q'))
        <a href="{{ route('panel.laporan.stok') }}" class="tombol tombol-halus tombol-sm">
            <x-ikon nama="muat-ulang" ukuran="size-4" /> Reset
        </a>
    @endif
</form>

@if ($varian->isEmpty())
    <div class="kartu mt-5 p-8 text-center">
        <x-ikon nama="stok" ukuran="size-9" class="mx-auto text-navy-300" />
        <p class="mt-3 font-bold">Tidak ada varian yang cocok</p>
        <p class="mx-auto mt-2 max-w-sm text-sm text-muted">Hapus penyaringnya untuk melihat seluruh rak.</p>
    </div>
@else
    <div class="kartu-md mt-5">
        <table class="tabel tabel-hp tabel-lekat">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th class="text-right">Stok</th>
                    <th class="text-right">Dipesan</th>
                    <th class="text-right">Tersedia</th>
                    <th class="text-right">Harga</th>
                    <th class="text-right">Nilai</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($varian as $v)
                    <tr>
                        <td data-utama>
                            <span class="font-medium">{{ $v->product->nama }}</span>
                            <span class="text-muted">{{ $v->label }}</span>
                            <div class="tabular text-xs text-muted">{{ $v->sku }}</div>
                        </td>
                        <td data-kolom="Stok" class="tabular text-right">{{ $v->stok }}</td>
                        <td data-kolom="Dipesan" class="tabular text-right text-muted">{{ $v->stok_dipesan }}</td>
                        <td data-kolom="Tersedia" class="text-right">
                            @if ($v->stok_tersedia <= 0)
                                <span class="chip chip-merah">Habis</span>
                            @elseif ($v->stok_tersedia <= 3)
                                <span class="chip chip-jingga tabular">{{ $v->stok_tersedia }}</span>
                            @else
                                <span class="tabular font-semibold">{{ $v->stok_tersedia }}</span>
                            @endif
                        </td>
                        <td data-kolom="Harga" class="tabular text-right whitespace-nowrap">{{ rupiah($v->harga) }}</td>
                        <td data-kolom="Nilai" class="tabular text-right font-semibold whitespace-nowrap">{{ rupiah(max(0, $v->stok) * $v->harga) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- Riwayat penyesuaian diambil dari log aktivitas (stok.disesuaikan), jadi
     tidak ada catatan kedua yang bisa berselisih dengan yang pertama. --}}
<section class="kartu mt-8 overflow-hidden">
    <h2 class="kartu-kop flex items-center gap-2">
        <span class="lencana-bagian warna-jingga"><x-ikon nama="jam" ukuran="size-4" /></span>
        Riwayat penyesuaian stok
    </h2>
    @if ($riwayat->isEmpty())
        <p class="p-5 text-sm text-muted">Belum ada penyesuaian stok yang tercatat.</p>
    @else
        <ul class="divide-y divide-line text-sm">
            @foreach ($riwayat as $log)
                <li class="px-5 py-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <span class="font-semibold">{{ $log->data_json['alasan'] ?? 'Tanpa alasan' }}</span>
                        <span class="tabular text-xs text-muted">
                            {{ $log->created_at?->translatedFormat('j M Y, H:i') }} &middot; {{ $log->user?->name ?? 'Sistem' }}
                        </span>
                    </div>
                    <p class="tabular mt-1 text-muted">
                        @foreach ($log->data_json['perubahan'] ?? [] as $ubah)
                            <span class="whitespace-nowrap">{{ $ubah['sku'] }}: {{ $ubah['dari'] }} &rarr; {{ $ubah['ke'] }}</span>@if (! $loop->last), @endif
                        @endforeach
                    </p>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection
