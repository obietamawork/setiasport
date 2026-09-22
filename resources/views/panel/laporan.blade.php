@extends('layouts.panel')
@section('judul', 'Laporan')

@section('isi')
<x-kop judul="Laporan"
       :catatan="$labelPeriode.': '.$dari->translatedFormat('j M Y').' sampai '.$sampai->translatedFormat('j M Y')" />

<div class="segmen mt-1">
    @foreach (['30hari' => '30 hari', 'bulan-ini' => 'Bulan ini', 'bulan-lalu' => 'Bulan lalu', 'tahun-ini' => 'Tahun ini'] as $nilai => $label)
        <a href="{{ route('panel.laporan', ['periode' => $nilai]) }}"
           class="segmen-item {{ $pilihan === $nilai ? 'segmen-item-aktif' : '' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

{{-- Uang yang benar-benar diterima didahulukan, karena itulah satu-satunya
     angka yang bisa dicocokkan dengan rekening dan laci kasir. --}}
<div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="kartu p-5">
        <p class="flex items-center gap-1.5 text-sm text-muted"><x-ikon nama="uang" ukuran="size-4" class="text-navy-400" /> Uang diterima</p>
        <p class="numeral mt-2 text-2xl">{{ rupiah($uangMasuk) }}</p>
        <p class="mt-2 text-xs leading-relaxed text-muted">
            Pembayaran yang sudah lunas pada periode ini, tunai maupun transfer.
        </p>
    </div>

    <div class="kartu p-5">
        <p class="flex items-center gap-1.5 text-sm text-muted"><x-ikon nama="pesanan" ukuran="size-4" class="text-navy-400" /> Nilai pesanan masuk</p>
        <p class="numeral mt-2 text-2xl">{{ rupiah($nilaiPesanan) }}</p>
        <p class="tabular mt-2 text-xs text-muted">
            {{ $jumlahPesanan }} pesanan, {{ $jumlahSelesai }} selesai, {{ $jumlahBatal }} batal
        </p>
    </div>

    <div class="kartu p-5">
        <p class="flex items-center gap-1.5 text-sm text-muted"><x-ikon nama="jam" ukuran="size-4" class="text-navy-400" /> Sisa tagihan tertahan</p>
        <p class="numeral mt-2 text-2xl {{ $sisaTertahan > 0 ? 'text-merah' : '' }}">
            {{ rupiah($sisaTertahan) }}
        </p>
        <p class="tabular mt-2 text-xs leading-relaxed text-muted">
            Dari {{ $pesananBerjalan }} pesanan yang masih berjalan. Ditagih setelah barang jadi, atau saat pengambilan.
        </p>
    </div>

    <div class="kartu p-5">
        <p class="flex items-center gap-1.5 text-sm text-muted"><x-ikon nama="laporan" ukuran="size-4" class="text-navy-400" /> Rata-rata per pesanan</p>
        <p class="numeral mt-2 text-2xl">
            {{ rupiah($jumlahPesanan > 0 ? (int) round($nilaiPesanan / $jumlahPesanan) : 0) }}
        </p>
        <p class="mt-2 text-xs leading-relaxed text-muted">
            Termasuk pesanan yang batal, supaya tidak terlihat lebih baik dari kenyataan.
        </p>
    </div>
</div>

<div class="mt-8 grid gap-6 lg:grid-cols-2">
    <section class="kartu overflow-hidden">
        <h2 class="kartu-kop">Uang diterima per metode</h2>
        @if ($perMetode->isEmpty())
            <p class="p-5 text-sm text-muted">Belum ada pembayaran lunas pada periode ini.</p>
        @else
            <table class="tabel">
                <thead>
                    <tr>
                        <th>Metode</th>
                        <th class="text-right">Transaksi</th>
                        <th class="text-right">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($perMetode as $metode => $baris)
                        <tr>
                            <td>{{ $metode }}</td>
                            <td class="tabular text-right">{{ $baris['transaksi'] }}</td>
                            <td class="tabular text-right font-semibold">{{ rupiah($baris['jumlah']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="kartu overflow-hidden">
        <h2 class="kartu-kop">Produk terlaris</h2>
        @if ($terlaris->isEmpty())
            <p class="p-5 text-sm text-muted">Belum ada pesanan pada periode ini.</p>
        @else
            <table class="tabel">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th class="text-right">Pcs</th>
                        <th class="text-right">Pesanan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($terlaris as $b)
                        <tr>
                            <td>{{ $b->nama_produk }}</td>
                            <td class="tabular text-right font-semibold">{{ $b->total_qty }}</td>
                            <td class="tabular text-right text-muted">{{ $b->total_pesanan }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>

<p class="mt-6 max-w-2xl text-sm leading-relaxed text-muted">
    Uang diterima dihitung dari tanggal pembayaran, sedangkan nilai pesanan dari
    tanggal pesanan dibuat. Keduanya memang bisa berbeda: pesanan bulan lalu yang
    dilunasi bulan ini masuk ke uang bulan ini.
</p>
@endsection
