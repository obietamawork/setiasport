{{-- Pemilih periode, sama untuk ketiga halaman laporan (KEP-117): berpindah
     dari Penjualan ke Produk terlaris tidak boleh diam-diam mengganti
     periodenya. --}}
<div class="segmen mt-1">
    @foreach (['30hari' => '30 hari', 'bulan-ini' => 'Bulan ini', 'bulan-lalu' => 'Bulan lalu', 'tahun-ini' => 'Tahun ini'] as $nilai => $label)
        <a href="{{ route($rute, ['periode' => $nilai]) }}"
           class="segmen-item {{ $pilihan === $nilai ? 'segmen-item-aktif' : '' }}">
            {{ $label }}
        </a>
    @endforeach
</div>
