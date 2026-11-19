<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    {{-- Jadi nama berkas saat disimpan sebagai PDF. --}}
    <title>Lembar kerja {{ $pesanan->kode }} - {{ $pesanan->customer->nama }} - {{ setting('toko_nama', 'Setia Sport') }}</title>
    <style>
        /*
         * Lembar kerja seperti gambar klien (KEP-136): kepala dengan nomor
         * pesanan, status desain, spesifikasi, jumlah, daftar pemain dengan
         * rekap ukuran, lalu QR besar, catatan, dan pemeriksaan akhir. Gaya
         * ditulis di sini, bukan lewat Tailwind, supaya tetap tercetak benar
         * meski aset belum dibangun ulang.
         */
        * { box-sizing: border-box; }
        body { margin: 0; padding: 12mm 12mm; font: 11.5px/1.45 Arial, Helvetica, sans-serif; color: #000; }
        .cetak { margin-bottom: 12px; }
        @media print { .cetak { display: none; } body { padding: 8mm; } }

        .kop { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 10px; }
        .kop img { height: 15mm; width: auto; }
        .kop .merek { font-size: 20px; font-weight: 800; letter-spacing: .02em; }
        .no-pesanan { border: 1.5px solid #000; border-radius: 6px; overflow: hidden; text-align: center; min-width: 62mm; }
        .no-pesanan .label { background: #1f2937; color: #fff; font-weight: bold; padding: 3px 10px; }
        .no-pesanan .kode { font-size: 22px; font-weight: 800; letter-spacing: 1px; padding: 4px 10px; }

        .kotak { border: 1px solid #8a8a8a; border-radius: 6px; padding: 7px 9px; }
        .judul { display: inline-block; background: #d4d4d4; font-weight: bold; padding: 3px 10px;
                 border-radius: 4px; margin-bottom: 6px; }
        .info { display: grid; grid-template-columns: 1.15fr 1.15fr .9fr; gap: 0; margin-bottom: 10px; }
        .info > div { padding: 6px 10px; }
        .info > div + div { border-left: 1px solid #8a8a8a; }
        .baris { display: grid; grid-template-columns: 8.2em 1em 1fr; }
        .baris dt { color: #222; font-weight: bold; white-space: nowrap; }

        .centang { display: inline-flex; width: 13px; height: 13px; border: 1.2px solid #000; border-radius: 2px;
                   align-items: center; justify-content: center; font-size: 11px; line-height: 1; margin-right: 6px;
                   vertical-align: -2px; }
        .daftar-centang div { margin: 3px 0; }

        .item { margin-bottom: 12px; }
        .item-kop { display: flex; justify-content: space-between; font-weight: bold; font-size: 13px;
                    border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 8px; }
        .tiga { display: grid; grid-template-columns: 1.25fr 1fr .85fr; gap: 8px; margin-bottom: 8px; }
        .desain img { width: 100%; max-height: 62mm; object-fit: contain; border-radius: 4px; background: #f2f2f2; display: block; }
        .desain .ket { font-size: 10.5px; }

        .pemain { display: grid; grid-template-columns: 1fr 48mm; gap: 8px; align-items: start; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #9a9a9a; padding: 3px 6px; text-align: left; }
        th { background: #ececec; }
        .no { width: 26px; text-align: center; }
        .nomor { width: 64px; text-align: center; font-weight: bold; }
        .ukuran { white-space: nowrap; }
        .jml { width: 48px; text-align: center; font-weight: bold; }
        .ket-kolom { width: 44px; }
        /* Kepala tabel diulang di tiap halaman; baris ini membuat halaman
           lanjutan yang terlepas tetap ketahuan milik pesanan mana. */
        .judul-tabel th { background: #fff; font-weight: normal; font-size: 10px; }
        .total td { font-weight: bold; background: #f4f4f4; }

        /*
         * Barang boleh terbelah di antara halaman, bagian atasnya dan baris
         * tabelnya tidak (KEP-100). Daftar 26 pemain tidak muat satu halaman.
         */
        .atas, .bawah, .pindai, tr { break-inside: avoid; }

        .bawah { display: grid; grid-template-columns: 50mm 1fr 1fr; gap: 8px; }
        /* QR memenuhi kotaknya (KEP-136): tanpa tulisan di sampingnya. */
        .qr svg { display: block; width: 100%; height: auto; max-width: 44mm; margin: 0 auto; }
        .garis { border-bottom: 1px dotted #555; height: 16px; }
        .catatan-isi { font-size: 11px; margin-bottom: 4px; }

        .pindai { margin-top: 6px; font-size: 10.5px; line-height: 1.45; }
        .pindai strong { font-size: 12px; }
        .pindai .tautan { font-family: monospace; font-size: 9px; color: #444; word-break: break-all; }

        .batal { border: 3px solid #000; padding: 10px; margin-bottom: 12px;
                 font-size: 18px; font-weight: bold; text-align: center; }
    </style>
</head>
<body>
@php
    use App\Enums\KategoriUkuran;

    $kustom = $pesanan->items->filter(fn ($i) => $i->kustom());
    $semuaDisetujui = $kustom->isNotEmpty() && $kustom->every(fn ($i) => $i->sudahDisetujui());
    $revisi = (int) $kustom->sum('jumlah_revisi');
    $urutKategori = array_flip(array_map(fn (KategoriUkuran $k) => $k->value, KategoriUkuran::cases()));
@endphp

<div class="cetak">
    <button type="button" data-cetak>Cetak lembar kerja</button>
</div>

{{-- Lembar lama masih menempel di kain saat pesanannya dibatalkan (KEP-100). --}}
@if (in_array($pesanan->status, [\App\Enums\OrderStatus::DIBATALKAN, \App\Enums\OrderStatus::KADALUARSA], true))
    <div class="batal">PESANAN DIBATALKAN: jangan dikerjakan</div>
@endif

<div class="kop">
    <div>
        @if (is_file(public_path('logo.png')))
            <img src="{{ asset('logo.png') }}" alt="{{ setting('toko_nama', 'Setia Sport') }}">
        @else
            <div class="merek">{{ Str::upper(setting('toko_nama', 'Setia Sport')) }}</div>
        @endif
    </div>
    <div class="no-pesanan">
        <div class="label">No. Pesanan</div>
        <div class="kode">{{ $pesanan->kode }}</div>
    </div>
</div>

{{-- Nomor HP hanya tersamar (KEP-136, sebelumnya tidak dicetak sama sekali,
     KEP-056): lembar ini difoto dan dikirim ke grup WhatsApp konveksi. Yang
     butuh menghubungi pelanggan membukanya di panel. --}}
<div class="kotak info">
    <dl>
        <div class="baris"><dt>Pemesan</dt><span>:</span><dd>{{ $pesanan->customer->nama }}</dd></div>
        <div class="baris"><dt>No. Kontak</dt><span>:</span><dd>{{ $pesanan->customer->no_hp ? $pesanan->customer->no_hp_samar : '-' }}</dd></div>
        <div class="baris"><dt>Pengambilan</dt><span>:</span><dd>{{ $pesanan->metode_ambil->label() }}</dd></div>
    </dl>
    <dl>
        <div class="baris"><dt>Tanggal Pesan</dt><span>:</span><dd>{{ $pesanan->created_at->translatedFormat('j M Y') }}</dd></div>
        <div class="baris"><dt>Target Selesai</dt><span>:</span><dd><strong>{{ $pesanan->target_siap_pada?->translatedFormat('l, j M Y') ?? '-' }}</strong></dd></div>
        <div class="baris"><dt>Jenis Pesanan</dt><span>:</span><dd>{{ $pesanan->items->pluck('nama_produk')->unique()->implode(', ') }}</dd></div>
    </dl>
    <div class="daftar-centang">
        <div class="judul">Status Desain</div>
        @if ($kustom->isEmpty())
            <div>Barang stok, tanpa desain</div>
        @else
            <div><span class="centang">{{ $semuaDisetujui ? '✓' : '' }}</span>Disetujui pelanggan</div>
            <div><span class="centang">{{ $revisi > 0 ? '✓' : '' }}</span>Revisi ({{ $revisi > 0 ? $revisi : '....' }} kali)</div>
        @endif
    </div>
</div>

@foreach ($pesanan->items as $item)
    @php
        $rekap = $item->rosters
            ->groupBy(fn ($r) => $r->labelUkuran())
            ->map(fn ($grup, $label) => [
                'label' => $label,
                'jumlah' => (int) $grup->sum('jumlah'),
                'kategori' => $urutKategori[(string) $grup->first()->getRawOriginal('kategori')] ?? 99,
                'ukuran' => array_search($grup->first()->ukuran, urutkan_ukuran($item->rosters->pluck('ukuran')), true),
            ])
            ->sort(fn ($a, $b) => [$a['kategori'], $a['ukuran']] <=> [$b['kategori'], $b['ukuran']])
            ->values();
        $perModel = $item->rosters->whereNotNull('model')->groupBy('model')->map(fn ($g) => (int) $g->sum('jumlah'));
    @endphp

    <div class="item">
        <div class="atas">
            <div class="item-kop">
                <span>{{ $item->nama_produk }}</span>
                <span>{{ $item->qty }} pcs</span>
            </div>

            <div class="tiga">
                {{-- Mockup yang disetujui pelanggan dicetak di lembar ini (KEP-056). --}}
                <div class="kotak desain">
                    <div class="judul">Desain yang Disetujui</div>
                    @if ($item->approvedDesign?->path)
                        <img src="{{ asset('storage/'.$item->approvedDesign->path) }}" alt="Mockup {{ $item->nama_produk }}">
                        <div class="ket">
                            Versi {{ $item->approvedDesign->versi }}
                            @if ($item->approved_by_name)
                                &middot; disetujui {{ $item->approved_by_name }} {{ $item->approved_at?->translatedFormat('j M Y') }}
                            @endif
                            @if ($item->approvedDesign->external_url)
                                <br>File siap cetak: {{ $item->approvedDesign->external_url }}
                            @endif
                        </div>
                    @elseif ($item->sudahDisetujui())
                        {{-- Disetujui, tetapi gambarnya tidak tersimpan di sistem: desainnya
                             ditukar lewat tautan Drive (KEP-002). Jangan sampai lembar ini
                             menulis "belum disetujui". --}}
                        <div class="ket">
                            <strong>Desain sudah disetujui, gambarnya tidak ada di sistem</strong><br>
                            Minta file siap cetaknya ke admin konveksi sebelum mulai.
                            @if ($item->approvedDesign?->external_url)
                                <br>{{ $item->approvedDesign->external_url }}
                            @endif
                        </div>
                    @elseif ($item->kustom())
                        <div class="ket"><strong>Desain belum disetujui pelanggan</strong><br>Jangan dipotong atau dicetak dulu.</div>
                    @else
                        <div class="ket">Barang stok toko.</div>
                    @endif
                </div>

                <div class="kotak">
                    <div class="judul">Spesifikasi Produk</div>
                    <dl>
                        @forelse ($item->options as $opsi)
                            <div class="baris"><dt>{{ $opsi->option_nama }}</dt><span>:</span><dd>{{ $opsi->option_value }}</dd></div>
                        @empty
                            <div>{{ $item->variant?->label ?? '-' }}</div>
                        @endforelse
                    </dl>
                </div>

                <div class="kotak">
                    <div class="judul">Jumlah Pesanan</div>
                    <dl>
                        @foreach ($perModel as $model => $jumlah)
                            <div class="baris"><dt>{{ $model }}</dt><span>:</span><dd>{{ $jumlah }} pcs</dd></div>
                        @endforeach
                        <div class="baris"><dt>Total</dt><span>:</span><dd><strong>{{ $item->qty }} pcs</strong></dd></div>
                    </dl>
                </div>
            </div>
        </div>

        @if ($item->rosters->isNotEmpty())
            <div class="pemain">
                <div>
                    <div class="judul">Daftar Pemain / Detail Ukuran</div>
                    <table>
                        <thead>
                            <tr class="judul-tabel">
                                <th colspan="6">Daftar ukuran {{ $pesanan->kode }} &middot; {{ $item->nama_produk }}</th>
                            </tr>
                            <tr>
                                <th class="no">No</th>
                                <th>Nama Pemain</th>
                                <th class="nomor">No. Punggung</th>
                                <th class="ukuran">Ukuran</th>
                                <th class="jml">Jumlah</th>
                                <th class="ket-kolom">Ket.</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($item->rosters as $r)
                                <tr>
                                    <td class="no">{{ $loop->iteration }}</td>
                                    <td>{{ $r->nama_punggung ?: '-' }}</td>
                                    <td class="nomor">{{ $r->nomor ?: '-' }}</td>
                                    {{-- Dengan kategorinya (KEP-099): SD L dan dewasa L dua pola berbeda. --}}
                                    <td class="ukuran">{{ $r->labelUkuran() }}</td>
                                    {{-- Satu baris boleh mewakili banyak potong sekaligus: jumlahnya WAJIB terlihat. --}}
                                    <td class="jml">{{ $r->jumlah }}</td>
                                    <td></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div>
                    <div class="judul">Rekap Ukuran</div>
                    <table>
                        <thead><tr><th>Ukuran</th><th class="jml">Jumlah</th></tr></thead>
                        <tbody>
                            @foreach ($rekap as $baris)
                                <tr><td class="ukuran">{{ $baris['label'] }}</td><td class="jml">{{ $baris['jumlah'] }}</td></tr>
                            @endforeach
                            <tr class="total"><td>Total</td><td class="jml">{{ $rekap->sum('jumlah') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif ($item->product->roster_wajib)
            <p style="color:#a00;font-weight:bold">DAFTAR PEMAIN BELUM ADA: jangan dicetak sebelum lengkap.</p>
        @endif

        <div class="bawah">
            {{-- QR penanda tahap (KEP-047), sekarang memenuhi kotaknya. --}}
            <div class="kotak qr">
                <div class="judul">QR Pesanan</div>
                @isset($qr[$item->id])
                    {!! $qr[$item->id]['svg'] !!}
                @else
                    <div style="font-size:10.5px">QR muncul setelah barang masuk produksi.</div>
                @endisset
            </div>

            <div class="kotak">
                <div class="judul">Catatan</div>
                <div><strong>Catatan pelanggan:</strong></div>
                @if ($item->catatan || $pesanan->catatan)
                    @if ($pesanan->catatan && $loop->first)<div class="catatan-isi">{{ $pesanan->catatan }}</div>@endif
                    @if ($item->catatan)<div class="catatan-isi">{{ $item->catatan }}</div>@endif
                @else
                    <div class="garis"></div><div class="garis"></div>
                @endif
                <div style="margin-top:6px"><strong>Catatan internal:</strong></div>
                <div class="garis"></div><div class="garis"></div>
            </div>

            <div class="kotak daftar-centang">
                <div class="judul">Pemeriksaan Akhir (QC)</div>
                <div><span class="centang"></span>Desain sesuai</div>
                <div><span class="centang"></span>Ukuran sesuai</div>
                <div><span class="centang"></span>Jahit / finishing rapi</div>
                <div><span class="centang"></span>Jumlah sesuai ({{ $item->qty }} pcs)</div>
                <div><span class="centang"></span>Siap diserahkan / dikirim</div>
            </div>
        </div>

        {{-- Petunjuk pindai di bawah deretan kotak, bukan di samping QR (KEP-136). --}}
        @isset($qr[$item->id])
            <div class="pindai">
                <strong>Pindai setelah pekerjaan selesai</strong><br>
                Buka dengan kamera HP, masukkan PIN Anda, lalu tekan tandai selesai.
                Tandai hanya bagian yang benar-benar Anda kerjakan.
                <div class="tautan">{{ $qr[$item->id]['tautan'] }}</div>
            </div>
        @endisset
    </div>
@endforeach

{{-- Tanpa onclick sebaris: CSP hanya menjalankan skrip ber-nonce (KEP-088). --}}
<script nonce="{{ Vite::cspNonce() }}">
    document.querySelector('[data-cetak]')?.addEventListener('click', () => window.print());
</script>
</body>
</html>
