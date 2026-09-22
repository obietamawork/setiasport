@extends('layouts.panel')
@section('judul', 'Stok')

@section('isi')
<x-kop judul="Stok"
       catatan="Hanya produk yang dijual dari rak. Angka dalam kurung adalah stok yang sudah direservasi pesanan dan tidak boleh dijual ulang." />

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
    <div class="kartu mt-6 p-8 text-center">
        <p class="text-lg font-bold">Tidak ada varian yang cocok</p>
        <p class="mt-2 text-sm text-muted">
            Varian ditambahkan dari halaman ubah produk, di bagian Varian &amp; stok.
        </p>
    </div>
@else
    <form method="POST" action="{{ route('panel.stok.update') }}" class="mt-6">
        @csrf @method('PUT')

        <div class="space-y-6 pb-4">
            @foreach ($varian as $grup)
                <div class="border border-line bg-field">
                    <div class="flex items-baseline gap-3 border-b border-line px-4 py-2.5">
                        <span class="font-semibold">{{ $grup->first()->product->nama }}</span>
                        <span class="text-xs text-muted tabular">{{ $grup->first()->product->sku }}</span>
                    </div>
                    {{-- Baris yang melipat, bukan tabel: opname biasanya dilakukan
                         sambil berdiri di depan rak, dari HP. --}}
                    <div class="divide-y divide-line">
                        @foreach ($grup as $v)
                            <div class="flex flex-wrap items-center gap-3 px-4 py-2.5 {{ $v->stok_tersedia === 0 ? 'bg-paper' : '' }}">
                                <div class="min-w-40 flex-1">
                                    <p class="text-sm font-medium">{{ $v->label ?: 'Varian tunggal' }}</p>
                                    <p class="tabular text-xs text-muted">{{ $v->sku }}</p>
                                </div>

                                <p class="tabular text-sm text-muted">
                                    tersedia {{ $v->stok_tersedia }}
                                    @if ($v->stok_dipesan > 0)
                                        <span class="text-merah">({{ $v->stok_dipesan }} dipesan)</span>
                                    @endif
                                </p>

                                <div class="w-24">
                                    <label class="sr-only" for="stok-{{ $v->id }}">
                                        Stok {{ $v->product->nama }} {{ $v->label }}
                                    </label>
                                    <input type="number" id="stok-{{ $v->id }}"
                                           name="stok[{{ $v->id }}]" value="{{ $v->stok }}"
                                           class="kotak-input tabular text-right" min="0">
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Stok yang berubah tanpa jejak adalah sumber perselisihan internal,
             jadi alasan wajib diisi sebelum penyimpanan diterima. --}}
        {{-- Di HP bilah ini duduk di atas bilah navigasi bawah, jadi jarak
             amannya diambil dari tinggi bilah itu, bukan dari tepi layar. --}}
        <div class="bilah-aksi mt-6 items-end">
            <div class="min-w-56 flex-1">
                <label for="alasan" class="label-form">Alasan penyesuaian</label>
                <input type="text" id="alasan" name="alasan" class="kotak-input" required
                       placeholder="Opname 7 September / barang rusak / retur supplier">
            </div>
            <button type="submit" class="tombol tombol-navy">
                <x-ikon nama="centang" ukuran="size-4" />
                Simpan stok
            </button>
        </div>
    </form>
@endif
@endsection
