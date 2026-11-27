@extends('layouts.panel')
@section('judul', 'Produk terlaris')

@section('isi')
<x-kop judul="Produk terlaris"
       :catatan="$labelPeriode.': '.$dari->translatedFormat('j M Y').' sampai '.$sampai->translatedFormat('j M Y').'. Dihitung dari jumlah potong, tanpa pesanan yang batal atau kedaluwarsa.'" />

@include('panel.laporan.periode', ['rute' => 'panel.laporan.terlaris'])

<div class="mt-6 grid gap-3 sm:grid-cols-3">
    <x-kartu-angka warna="ungu" ikon="keranjang" label="Potong terjual" :angka="number_format($totalQty, 0, ',', '.')" />
    <x-kartu-angka warna="biru" ikon="produk" label="Produk berbeda" :angka="$terlaris->count()" />
    <x-kartu-angka warna="hijau" ikon="pesanan" label="Pesanan yang memuatnya" :angka="$totalPesanan" />
</div>

@if ($terlaris->isEmpty())
    <div class="kartu mt-6 p-8 text-center">
        <x-ikon nama="keranjang" ukuran="size-9" class="mx-auto text-navy-300" />
        <p class="mt-3 font-bold">Belum ada pesanan pada periode ini</p>
        <p class="mx-auto mt-2 max-w-sm text-sm text-muted">Coba periode yang lebih panjang, misalnya tahun ini.</p>
    </div>
@else
    <section class="kartu mt-6 overflow-hidden">
        <h2 class="kartu-kop">Peringkat produk</h2>
        <table class="tabel tabel-hp">
            <thead>
                <tr>
                    <th class="w-12">#</th>
                    <th>Produk</th>
                    <th class="text-right">Pcs</th>
                    <th class="text-right">Pesanan</th>
                    <th class="hidden w-1/4 md:table-cell">Bagian dari total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($terlaris as $i => $b)
                    @php $porsi = $totalQty > 0 ? round($b->total_qty / $totalQty * 100) : 0; @endphp
                    <tr>
                        <td data-kolom="Peringkat" class="tabular font-semibold text-muted">{{ $i + 1 }}</td>
                        <td data-utama class="font-medium">{{ $b->nama_produk }}</td>
                        <td data-kolom="Pcs" class="tabular text-right font-semibold">{{ $b->total_qty }}</td>
                        <td data-kolom="Pesanan" class="tabular text-right text-muted">{{ $b->total_pesanan }}</td>
                        <td class="hidden md:table-cell">
                            <span class="flex items-center gap-2">
                                {{-- Navy seperti batang kemajuan lain (KEP-179); warna bagian
                                     KEP-111 hanya untuk ikon dan chip (KEP-171). --}}
                                <span class="h-2 flex-1 overflow-hidden rounded-full bg-navy-100">
                                    <span class="block h-full rounded-full bg-navy" style="width: {{ $porsi }}%"></span>
                                </span>
                                <span class="tabular w-10 text-right text-xs text-muted">{{ $porsi }}%</span>
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endif
@endsection
