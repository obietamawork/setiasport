<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Lembar roll {{ $roll->kode }} - {{ setting('toko_nama', 'Setia Sport') }}</title>
    <style>
        /* Ditempel di roll kertasnya, jadi dicetak besar dan ringkas. */
        * { box-sizing: border-box; }
        body { margin: 0; padding: 14mm 12mm; font: 12px/1.5 Arial, Helvetica, sans-serif; }
        .kop { display: flex; justify-content: space-between; gap: 16px;
               border-bottom: 2px solid #000; padding-bottom: 10px; }
        .kode { font-size: 28px; font-weight: bold; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #999; padding: 6px 8px; text-align: left; }
        th { background: #eee; }
        .petunjuk { font-size: 11px; line-height: 1.6; max-width: 62ch; }
        .tautan { font-family: monospace; font-size: 9px; color: #444; word-break: break-all; }
        .cetak { margin-bottom: 12px; }
        @media print { .cetak { display: none; } body { padding: 8mm; } }
    </style>
</head>
<body>

<div class="cetak">
    <button type="button" onclick="window.print()">Cetak lembar roll</button>
    <a href="{{ route('panel.antrian.tahap', 'print') }}">Kembali ke antrian printing</a>
</div>

<div class="kop">
    <div>
        <div class="kode">{{ $roll->kode }}</div>
        <div>{{ $roll->created_at->translatedFormat('l, j F Y, H:i') }}</div>
        @if ($roll->catatan)
            <div><strong>{{ $roll->catatan }}</strong></div>
        @endif
        @if ($roll->selesai())
            <div><strong>Sudah ditandai selesai
                {{ $roll->selesai_pada->translatedFormat('j M, H:i') }}
                oleh {{ $roll->pekerja?->nama ?? 'admin' }}</strong></div>
        @endif
    </div>

    <div style="text-align:center">
        {!! $qr !!}
        <div class="petunjuk" style="margin-top:4px">Pindai setelah roll selesai dicetak</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:120px">Pesanan</th>
            <th>Barang</th>
            <th style="width:70px">Jumlah</th>
            <th style="width:150px">Rekap ukuran</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($roll->items as $item)
            <tr>
                <td class="tabular">{{ $item->order->kode }}</td>
                <td>{{ $item->nama_produk }}</td>
                <td>{{ $item->qty }} pcs</td>
                <td>{{ $item->rosters->isNotEmpty() ? $item->ringkasanUkuran() : '-' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<p class="petunjuk" style="margin-top:12px">
    Satu roll ini memuat {{ $roll->items->count() }} pesanan. Pindai QR di atas setelah
    seluruh isinya selesai dicetak, masukkan PIN Anda, lalu tekan tandai selesai. Seluruh
    pesanan di lembar ini naik ke tahap berikutnya sekaligus.
    <span class="tautan">{{ $tautan }}</span>
</p>

</body>
</html>
