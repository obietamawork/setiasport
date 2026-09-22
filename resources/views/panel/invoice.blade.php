<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $pesanan->kode }}</title>
    <style>
        /*
         * Gaya ditulis di sini, bukan lewat Tailwind, supaya lembar ini tetap
         * tercetak benar meski aset belum dibangun ulang. Sama alasannya dengan
         * worksheet (docs/04).
         */
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 14mm 12mm;
            font: 12px/1.5 Arial, Helvetica, sans-serif; color: #000;
        }
        h1 { margin: 0; font-size: 26px; letter-spacing: 2px; }
        .kop { display: flex; justify-content: space-between; gap: 20px; align-items: flex-start; }
        .toko { font-size: 11px; line-height: 1.5; }
        .toko strong { font-size: 14px; }
        .meta { text-align: right; font-size: 11px; }
        .meta .nomor { font-size: 15px; font-weight: bold; letter-spacing: 1px; }
        .pihak { display: flex; gap: 20px; margin: 14px 0; }
        .pihak > div { flex: 1; border: 1px solid #000; padding: 8px 10px; }
        .pihak dt { font-size: 10px; text-transform: uppercase; color: #444; }
        .pihak dd { margin: 2px 0 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #999; padding: 5px 7px; text-align: left; vertical-align: top; }
        th { background: #eee; }
        .angka { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .rincian { font-size: 11px; color: #444; }
        .total td { border: none; padding: 3px 7px; }
        .total .garis { border-top: 1px solid #000; font-weight: bold; font-size: 14px; }
        .bayar { display: flex; gap: 20px; margin-top: 14px; align-items: flex-start; }
        .bayar > div { flex: 1; }
        .kotak { border: 1px solid #000; padding: 8px 10px; }
        .lunas {
            border: 3px solid #b31f16; color: #b31f16; font-size: 22px; font-weight: bold;
            letter-spacing: 3px; padding: 6px 14px; transform: rotate(-6deg);
            display: inline-block; margin-top: 8px;
        }
        .catatan { margin-top: 14px; font-size: 11px; color: #333; }
        .cetak { margin-bottom: 12px; }
        @media print { .cetak { display: none; } body { padding: 8mm; } }
    </style>
</head>
<body>

<div class="cetak">
    <button type="button" onclick="window.print()">Cetak atau simpan PDF</button>
</div>

@php
    $lunas = $pesanan->lunas();

    // Jatuh tempo: selama masih ada tagihan berjalan, itulah batasnya. Sesudah
    // barang jadi, yang berlaku batas pengambilan.
    $jatuhTempo = $pesanan->kadaluarsa_pada;
@endphp

<div class="kop">
    <div class="toko">
        <strong>{{ setting('toko_nama', 'Setia Sport') }}</strong><br>
        {{ setting('toko_alamat') }}<br>
        {{ setting('toko_telepon') }}
    </div>
    <div class="meta">
        <h1>INVOICE</h1>
        <div class="nomor">{{ $pesanan->kode }}</div>
        <div>Tanggal: {{ $pesanan->created_at->translatedFormat('j F Y') }}</div>
        @if (! $lunas && $jatuhTempo)
            <div>Jatuh tempo: <strong>{{ $jatuhTempo->translatedFormat('j F Y, H:i') }}</strong></div>
        @endif
    </div>
</div>

<div class="pihak">
    <div>
        <dl>
            <dt>Ditagihkan kepada</dt>
            <dd>
                <strong>{{ $pesanan->customer->nama }}</strong>
                @if ($pesanan->customer->reseller)
                    &middot; Reseller
                @endif
                <br>
                {{ $pesanan->customer->no_hp }}<br>
                {{ $pesanan->customer->email }}
            </dd>
        </dl>
    </div>
    <div>
        <dl>
            <dt>{{ $pesanan->dikirim() ? 'Dikirim ke' : 'Diambil di' }}</dt>
            <dd>
                @if ($pesanan->dikirim())
                    {{ $pesanan->alamat_kirim ?: 'Alamat menyusul' }}
                    @if ($pesanan->no_resi)
                        <br>Resi {{ $pesanan->jasa_kirim }} {{ $pesanan->no_resi }}
                    @endif
                @else
                    Toko {{ setting('toko_nama', 'Setia Sport') }}<br>
                    {{ setting('toko_alamat') }}
                @endif
            </dd>
        </dl>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Keterangan</th>
            <th style="width:70px" class="angka">Jumlah</th>
            <th style="width:110px" class="angka">Harga</th>
            <th style="width:120px" class="angka">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($pesanan->items as $item)
            <tr>
                <td>
                    <strong>{{ $item->nama_produk }}</strong>
                    @if ($item->options->isNotEmpty())
                        <div class="rincian">
                            {{ $item->options->pluck('option_value')->implode(', ') }}
                        </div>
                    @endif

                    {{-- Harga per potong berbeda menurut ukuran (KEP-039), jadi
                         rinciannya ikut tercetak. Tanpa ini pelanggan tidak bisa
                         memeriksa sendiri dari mana totalnya. --}}
                    @if ($item->hargaPerUkuran())
                        <div class="rincian">
                            @foreach ($item->rosters as $r)
                                {{ $r->jumlah }} × {{ $r->labelUkuran() }} @ {{ rupiah($r->harga_satuan) }}@if (! $loop->last), @endif
                            @endforeach
                        </div>
                    @endif
                </td>
                <td class="angka">{{ $item->qty }} pcs</td>
                <td class="angka">
                    {{ $item->hargaPerUkuran() ? 'bervariasi' : rupiah($item->harga_satuan) }}
                </td>
                <td class="angka">{{ rupiah($item->subtotal) }}</td>
            </tr>
        @endforeach

        @foreach ($pesanan->adjustments as $p)
            <tr>
                <td colspan="3">{{ $p->deskripsi }}</td>
                <td class="angka">{{ rupiah($p->jumlah) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="total" style="margin-top:8px">
    <tr>
        <td></td>
        <td style="width:140px">Subtotal</td>
        <td style="width:130px" class="angka">{{ rupiah($pesanan->subtotal) }}</td>
    </tr>
    @if ($pesanan->biaya_layanan > 0)
        <tr>
            <td></td>
            <td>Biaya layanan</td>
            <td class="angka">{{ rupiah($pesanan->biaya_layanan) }}</td>
        </tr>
    @endif
    @if ($pesanan->ongkir > 0)
        <tr>
            <td></td>
            <td>Ongkos kirim</td>
            <td class="angka">{{ rupiah($pesanan->ongkir) }}</td>
        </tr>
    @endif
    @if ($pesanan->diskon > 0)
        <tr>
            <td></td>
            <td>Diskon</td>
            <td class="angka">− {{ rupiah($pesanan->diskon) }}</td>
        </tr>
    @endif
    <tr>
        <td></td>
        <td class="garis">Total</td>
        <td class="garis angka">{{ rupiah($pesanan->total) }}</td>
    </tr>
    <tr>
        <td></td>
        <td>Sudah dibayar</td>
        <td class="angka">{{ rupiah($pesanan->sudah_dibayar) }}</td>
    </tr>
    <tr>
        <td></td>
        <td class="garis">{{ $pesanan->sudah_dibayar > $pesanan->total ? 'Kelebihan bayar' : 'Sisa tagihan' }}</td>
        <td class="garis angka">
            {{ rupiah(abs($pesanan->total - $pesanan->sudah_dibayar)) }}
        </td>
    </tr>
</table>

<div class="bayar">
    <div class="kotak">
        <strong>Pembayaran</strong>
        <div style="margin-top:6px">
            {{ setting('bank_nama') }} {{ setting('bank_rekening') }}<br>
            a.n. {{ setting('bank_atas_nama') }}
        </div>
        @if (setting('bank2_rekening'))
            <div style="margin-top:6px">
                {{ setting('bank2_nama') }} {{ setting('bank2_rekening') }}<br>
                a.n. {{ setting('bank2_atas_nama') }}
            </div>
        @endif
        @if (setting('qris_path'))
            <div style="margin-top:6px">QRIS tersedia di toko dan di halaman pesanan.</div>
        @endif
    </div>
    <div style="text-align:center">
        @if ($lunas)
            <div class="lunas">LUNAS</div>
            <div class="rincian" style="margin-top:8px">
                {{ $pesanan->payments->where('status', \App\Enums\PaymentStatus::LUNAS)->last()?->dibayar_pada?->translatedFormat('j F Y') }}
            </div>
        @else
            <div style="margin-top:28px">Hormat kami,</div>
            <div style="margin-top:38px">( ________________ )</div>
            <div class="rincian">{{ setting('toko_nama', 'Setia Sport') }}</div>
        @endif
    </div>
</div>

<div class="catatan">
    @if ($pesanan->catatan)
        <div>Catatan: {{ $pesanan->catatan }}</div>
    @endif
    <div>
        Pembayaran hanya melalui rekening atau QRIS yang tertera di lembar ini.
        Simpan invoice ini sebagai bukti pemesanan.
    </div>
</div>

</body>
</html>
