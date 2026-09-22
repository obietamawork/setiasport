<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Worksheet {{ $pesanan->kode }}</title>
    <style>
        /*
         * Selembar kertas yang menempel di kain saat dikerjakan. Gaya ditulis
         * di sini, bukan lewat Tailwind, supaya halaman ini tetap tercetak benar
         * meski aset belum dibangun ulang.
         */
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 16mm 14mm;
            font: 12px/1.45 Arial, Helvetica, sans-serif; color: #000;
        }
        h1 { margin: 0; font-size: 20px; letter-spacing: -.02em; }
        h2 { margin: 0 0 6px; font-size: 14px; }
        .kop { display: flex; justify-content: space-between; align-items: flex-start;
               border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 14px; }
        .kode { font-size: 22px; font-weight: bold; letter-spacing: 1px; }
        .item { border: 1px solid #000; margin-bottom: 12px; page-break-inside: avoid; }
        .item-kop { display: flex; justify-content: space-between; gap: 12px;
                    border-bottom: 1px solid #000; padding: 8px 10px; font-weight: bold; }
        .meta { padding: 8px 10px; border-bottom: 1px solid #ccc; }
        .meta span { display: inline-block; margin-right: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
        th { background: #eee; }
        .no { width: 30px; text-align: center; }
        .nomor { width: 60px; text-align: center; font-weight: bold; font-size: 14px; }
        .ukuran { width: 70px; text-align: center; font-weight: bold; }
        .ringkas { padding: 8px 10px; font-weight: bold; }
        .catatan { border: 1px solid #000; padding: 8px 10px; margin-top: 4px; min-height: 40px; }
        .cetak { margin-bottom: 14px; }
        .desain { display: flex; gap: 12px; padding: 10px; border-bottom: 1px solid #ccc;
                  align-items: flex-start; }
        .desain img { width: 46mm; border: 1px solid #000; }
        .desain .ket { font-size: 11px; line-height: 1.5; }
        .desain .ket strong { font-size: 13px; }
        .kerja { display: flex; gap: 12px; align-items: center; padding: 10px;
                 border-top: 1px solid #000; }
        .kerja .petunjuk { font-size: 11px; line-height: 1.5; }
        .kerja .petunjuk strong { font-size: 13px; }
        .kerja .tautan { font-family: monospace; font-size: 9px; color: #444; word-break: break-all; }
        @media print { .cetak { display: none; } body { padding: 10mm; } }
    </style>
</head>
<body>

<div class="cetak">
    <button type="button" onclick="window.print()">Cetak worksheet</button>
</div>

{{-- Nomor HP pelanggan sengaja TIDAK dicetak (KEP-056). Lembar ini difoto dan
     dikirim ke grup WhatsApp konveksi, dan nomor pelanggan tidak ada gunanya di
     meja potong. Yang butuh menghubungi pelanggan membukanya di panel. --}}
<div class="kop">
    <div>
        <h1>{{ $pesanan->customer->nama }}</h1>
        @if ($pesanan->target_siap_pada)
            <div><strong>Target siap: {{ $pesanan->target_siap_pada->translatedFormat('l, j F Y') }}</strong></div>
        @endif
    </div>
    <div style="text-align:right">
        <div class="kode">{{ $pesanan->kode }}</div>
        <div>{{ $pesanan->created_at->translatedFormat('j M Y') }}</div>
    </div>
</div>

@foreach ($pesanan->items as $item)
    <div class="item">
        <div class="item-kop">
            <span>{{ $item->nama_produk }}</span>
            <span>{{ $item->qty }} pcs</span>
        </div>

        <div class="meta">
            @foreach ($item->options as $opsi)
                <span>{{ $opsi->option_nama }}: <strong>{{ $opsi->option_value }}</strong></span>
            @endforeach
            @if ($item->approvedDesign)
                <span>Mockup: <strong>v{{ $item->approvedDesign->versi }}</strong></span>
            @endif
            @if ($item->approved_by_name)
                <span>Disetujui: <strong>{{ $item->approved_by_name }}</strong>
                    ({{ $item->approved_at->translatedFormat('j M Y') }})</span>
            @endif
        </div>

        {{-- Mockup yang disetujui pelanggan dicetak di lembar ini (KEP-056).
             Sebelumnya hanya tertulis "Mockup: v2", dan orang potong maupun
             jahit tetap harus membuka HP untuk melihat desainnya. --}}
        @if ($item->approvedDesign?->path)
            <div class="desain">
                <img src="{{ asset('storage/'.$item->approvedDesign->path) }}"
                     alt="Mockup {{ $item->nama_produk }}">
                <div class="ket">
                    <strong>Desain yang disetujui</strong><br>
                    Versi {{ $item->approvedDesign->versi }}
                    @if ($item->approved_by_name)
                        &middot; disetujui {{ $item->approved_by_name }}
                        {{ $item->approved_at?->translatedFormat('j M Y') }}
                    @endif
                    <br>
                    Kerjakan persis seperti gambar ini. Kalau ada yang tidak jelas,
                    tanyakan ke admin konveksi sebelum memotong kain.
                    @if ($item->approvedDesign->external_url)
                        <br>File siap cetak: {{ $item->approvedDesign->external_url }}
                    @endif
                </div>
            </div>
        @elseif ($item->sudahDisetujui())
            {{-- Disetujui, tetapi gambarnya tidak tersimpan di sistem: desainnya
                 ditukar lewat tautan Drive (KEP-002). Jangan sampai lembar ini
                 menulis "belum disetujui", karena itu menghentikan pekerjaan
                 yang sebenarnya sudah boleh jalan. --}}
            <div class="desain">
                <div class="ket">
                    <strong>Desain sudah disetujui, gambarnya tidak ada di sistem</strong><br>
                    Minta file siap cetaknya ke admin konveksi sebelum mulai.
                    @if ($item->approvedDesign?->external_url)
                        <br>{{ $item->approvedDesign->external_url }}
                    @endif
                </div>
            </div>
        @elseif ($item->kustom())
            <div class="desain">
                <div class="ket">
                    <strong>Desain belum disetujui pelanggan</strong><br>
                    Jangan dipotong atau dicetak dulu.
                </div>
            </div>
        @endif

        @if ($item->rosters->isNotEmpty())
            <div class="ringkas">{{ $item->ringkasanUkuran() }}</div>
            <table>
                <thead>
                    <tr>
                        <th class="no">#</th>
                        <th>Nama punggung</th>
                        <th class="nomor">No</th>
                        <th class="ukuran">Ukuran</th>
                        <th style="width:90px">Cek</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($item->rosters as $r)
                        <tr>
                            <td class="no">{{ $r->urutan }}</td>
                            <td>{{ $r->nama_punggung ?: '-' }}</td>
                            <td class="nomor">{{ $r->nomor ?: '-' }}</td>
                            <td class="ukuran">{{ $r->ukuran }}</td>
                            <td></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @elseif ($item->product->roster_wajib)
            <div class="ringkas" style="color:#a00">
                DAFTAR PEMAIN BELUM ADA: jangan dicetak sebelum lengkap.
            </div>
        @endif

        @if ($item->catatan)
            <div class="meta">Catatan: {{ $item->catatan }}</div>
        @endif

        {{-- QR penanda tahap (KEP-047). Lembar ini berjalan bersama kainnya dari
             meja ke meja; pekerja memindai, memasukkan PIN, lalu tahapnya
             berpindah tanpa perlu melapor ke admin konveksi. --}}
        @if (isset($qr[$item->id]))
            <div class="kerja">
                <div>{!! $qr[$item->id]['svg'] !!}</div>
                <div class="petunjuk">
                    <strong>Pindai setelah pekerjaan selesai</strong><br>
                    Buka dengan kamera HP, masukkan PIN Anda, lalu tekan tandai selesai.
                    Tandai hanya bagian yang benar-benar Anda kerjakan.
                    <div class="tautan">{{ $qr[$item->id]['tautan'] }}</div>
                </div>
            </div>
        @endif
    </div>
@endforeach

@if ($pesanan->catatan)
    <h2>Catatan pesanan</h2>
    <div class="catatan">{{ $pesanan->catatan }}</div>
@endif

<h2 style="margin-top:14px">Catatan produksi</h2>
<div class="catatan"></div>

</body>
</html>
