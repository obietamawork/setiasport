<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panduan Kerja Konveksi - {{ setting('toko_nama', 'Setia Sport') }}</title>
    <style>
        /*
         * Panduan kerja konveksi siap cetak A4 (KEP-162), untuk ditempel di
         * workshop dan dipakai pemilik atau admin saat mengajari karyawan.
         * Gaya ditulis di sini, bukan lewat Tailwind, seperti lembar kerja.
         */
        * { box-sizing: border-box; }
        body { margin: 0; padding: 12mm; font: 12px/1.5 Arial, Helvetica, sans-serif; color: #000; }
        .cetak { margin-bottom: 14px; display: flex; gap: 8px; }
        .cetak button, .cetak a { font: inherit; padding: 6px 14px; border: 1px solid #16255b; border-radius: 6px;
                                  background: #16255b; color: #fff; text-decoration: none; cursor: pointer; }
        .cetak a { background: #fff; color: #16255b; }
        @media print { .cetak { display: none; } body { padding: 8mm; } @page { size: A4; margin: 0; } }

        h1 { margin: 0; font-size: 22px; }
        .kop { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #000;
               padding-bottom: 6px; margin-bottom: 12px; }
        .kop .toko { font-weight: 800; letter-spacing: .04em; }
        h2 { font-size: 14px; margin: 16px 0 8px; background: #e5e7eb; padding: 4px 8px; border-radius: 4px; }

        ol.langkah { margin: 0; padding: 0; list-style: none; counter-reset: n; }
        ol.langkah li { counter-increment: n; display: grid; grid-template-columns: 26px 1fr; gap: 8px;
                        padding: 5px 0; border-bottom: 1px solid #d4d4d4; break-inside: avoid; }
        ol.langkah li::before { content: counter(n); width: 22px; height: 22px; border-radius: 50%; background: #16255b;
                                color: #fff; font-weight: bold; display: flex; align-items: center; justify-content: center; }
        ol.langkah b { display: block; }

        table { width: 100%; border-collapse: collapse; }
        td { border: 1px solid #8a8a8a; padding: 6px 8px; vertical-align: middle; }
        td.ikon { width: 52px; text-align: center; color: #16255b; }
        td.meja { width: 130px; font-weight: bold; }
        tr { break-inside: avoid; }

        .penting { margin-top: 14px; border: 2px solid #000; border-radius: 6px; padding: 8px 10px; }
        .penting b { font-size: 13px; }
    </style>
</head>
<body>
    <div class="cetak">
        <button type="button" data-cetak>Cetak</button>
        <a href="{{ route('panel.bantuan') }}#konveksi">Kembali ke panduan</a>
    </div>

    <div class="kop">
        <div>
            <div class="toko">{{ strtoupper(setting('toko_nama', 'Setia Sport')) }}</div>
            <h1>Panduan Kerja Konveksi</h1>
        </div>
        <div>Tempel di meja produksi</div>
    </div>

    <h2>Cara menandai pekerjaan</h2>
    <ol class="langkah">
        @foreach ($langkah as $l)
            <li><span><b>{{ $l['t'] }}</b>{{ $l['j'] }}</span></li>
        @endforeach
    </ol>

    <h2>Meja dan kapan ditandai selesai</h2>
    <table>
        @foreach ($tahap as $t)
            <tr>
                <td class="ikon"><x-ikon-tahap :tahap="$t" ukuran="" style="width:34px;height:34px" /></td>
                <td class="meja">{{ $t->label() }}</td>
                <td>
                    @switch($t)
                        @case(\App\Enums\ProductionStage::DESAIN)
                            Tidak lewat QR. Tim desain mengunggah mockup di panel, lalu pelanggan yang menyetujuinya.
                            Produksi baru mulai setelah desain disetujui.
                            @break
                        @case(\App\Enums\ProductionStage::PRINT)
                            Tandai setelah semua desain pesanan selesai dicetak ke kertas. Satu roll berisi beberapa
                            pesanan ditandai sekali lewat QR di lembar roll.
                            @break
                        @case(\App\Enums\ProductionStage::PRESS)
                            Tandai setelah semua kertas selesai dipress ke kain dan warnanya sudah diperiksa.
                            @break
                        @case(\App\Enums\ProductionStage::POTONG)
                            Tandai setelah semua kain dipotong sesuai daftar ukuran dan model: potongan pria dan
                            wanita berbeda.
                            @break
                        @case(\App\Enums\ProductionStage::JAHIT)
                            Tandai setelah semua potongan dijahit sesuai model kerah, lengan, dan saku di lembar kerja.
                            @break
                        @default
                            Tandai setelah jahitan, nama, dan nomor punggung diperiksa, lalu barang dilipat dan dikemas.
                    @endswitch
                </td>
            </tr>
        @endforeach
    </table>

    <div class="penting">
        <b>PIN adalah tanda tangan Anda.</b> Jangan dipinjamkan dan jangan menandai pekerjaan orang lain.
        Lupa PIN atau PIN tidak bisa dipakai? Minta pemilik toko menggantinya.
    </div>

{{-- Tanpa onclick sebaris: CSP hanya menjalankan skrip ber-nonce (KEP-088). --}}
<script nonce="{{ Vite::cspNonce() }}">
    document.querySelector('[data-cetak]')?.addEventListener('click', () => window.print());
</script>
</body>
</html>
