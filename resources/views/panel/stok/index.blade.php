@extends('layouts.panel')
@section('judul', 'Stok')

@section('isi')
<h1 class="text-2xl font-extrabold">Stok</h1>
<p class="mt-1 text-sm text-muted">
    Hanya produk yang dijual dari rak. Angka dalam kurung adalah stok yang sudah
    direservasi pesanan dan tidak boleh dijual ulang.
</p>

<form method="GET" class="mt-5 flex flex-wrap gap-2">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari produk atau SKU"
           class="kotak-input max-w-xs">
    <select name="saring" class="kotak-input max-w-45">
        <option value="">Semua varian</option>
        <option value="habis" @selected(request('saring') === 'habis')>Hanya yang habis</option>
    </select>
    <button type="submit" class="tombol tombol-garis">Terapkan</button>
</form>

@if ($varian->isEmpty())
    <div class="mt-6 border border-line bg-field p-8 text-center">
        <p class="text-lg font-bold">Tidak ada varian yang cocok</p>
        <p class="mt-2 text-sm text-muted">
            Varian ditambahkan dari halaman ubah produk, di bagian Varian &amp; stok.
        </p>
    </div>
@else
    <form method="POST" action="{{ route('panel.stok.update') }}" class="mt-6">
        @csrf @method('PUT')

        <div class="space-y-6">
            @foreach ($varian as $grup)
                <div class="border border-line bg-field">
                    <div class="flex items-baseline gap-3 border-b border-line px-4 py-2.5">
                        <span class="font-semibold">{{ $grup->first()->product->nama }}</span>
                        <span class="text-xs text-muted tabular">{{ $grup->first()->product->sku }}</span>
                    </div>
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-line">
                            @foreach ($grup as $v)
                                <tr class="{{ $v->stok_tersedia === 0 ? 'bg-paper' : '' }}">
                                    <td class="w-56 px-4 py-2 tabular whitespace-nowrap text-muted">{{ $v->sku }}</td>
                                    <td class="px-4 py-2">{{ $v->label ?: 'Varian tunggal' }}</td>
                                    <td class="w-40 px-4 py-2 text-right text-muted tabular">
                                        tersedia {{ $v->stok_tersedia }}
                                        @if ($v->stok_dipesan > 0)
                                            ({{ $v->stok_dipesan }})
                                        @endif
                                    </td>
                                    <td class="w-28 px-4 py-2">
                                        <label class="sr-only" for="stok-{{ $v->id }}">
                                            Stok {{ $v->product->nama }} {{ $v->label }}
                                        </label>
                                        <input type="number" id="stok-{{ $v->id }}"
                                               name="stok[{{ $v->id }}]" value="{{ $v->stok }}"
                                               class="kotak-input tabular text-right" min="0">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>

        {{-- Stok yang berubah tanpa jejak adalah sumber perselisihan internal,
             jadi alasan wajib diisi sebelum penyimpanan diterima. --}}
        <div class="sticky bottom-0 mt-6 flex flex-wrap items-end gap-3 border border-line bg-field p-4">
            <div class="min-w-64 flex-1">
                <label for="alasan" class="label-form">Alasan penyesuaian</label>
                <input type="text" id="alasan" name="alasan" class="kotak-input" required
                       placeholder="Opname 7 September / barang rusak / retur supplier">
            </div>
            <button type="submit" class="tombol tombol-navy">Simpan stok</button>
        </div>
    </form>
@endif
@endsection
