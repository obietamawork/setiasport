@extends('layouts.publik')
@section('judul', 'Pesanan '.$order->kode)
@section('subjudul', 'Pesanan '.$order->kode)
@section('subcatatan', $order->status->label())

@section('isi')
@php
    use App\Enums\OrderStatus;
    use App\Enums\OrderItemStatus;
@endphp

<div class="mx-auto max-w-3xl px-5 py-10">

    @if (session('sukses'))
        <p class="mb-6 border-l-2 border-navy bg-navy-50 px-4 py-3 text-sm">{{ session('sukses') }}</p>
    @endif
    @if ($errors->any())
        <p class="mb-6 border-l-2 border-merah bg-merah-50 px-4 py-3 text-sm">{{ $errors->first() }}</p>
    @endif

    <p class="text-sm text-muted tabular">{{ $order->kode }}</p>
    <h1 class="mt-1 text-3xl font-extrabold">{{ $order->status->label() }}</h1>
    <p class="mt-2 text-sm text-muted">
        Atas nama {{ $order->customer->nama }} &middot; {{ $order->customer->email_samar }}
    </p>

    {{-- Tautan halaman ini adalah satu-satunya kunci pesanan (KEP-008), dan
         pelanggan sering perlu mengirimkannya ke bendahara tim atau menyimpannya
         sendiri. Menyalin dari bilah alamat di HP jauh lebih repot daripada
         satu tombol. --}}
    <div class="mt-4" x-data="{ tersalin: false }">
        <button type="button"
                @click="navigator.clipboard.writeText(@js(route('lacak.token', $order->access_token)))
                        .then(() => { tersalin = true; setTimeout(() => tersalin = false, 2500) })"
                class="tombol tombol-garis tombol-sm">
            <span x-show="!tersalin">Salin tautan pesanan</span>
            <span x-show="tersalin" x-cloak>Tautan tersalin</span>
        </button>
        <p class="mt-1.5 text-xs leading-relaxed text-muted">
            Boleh dibagikan ke anggota tim yang ingin ikut memantau; tidak ada data pribadi di sini.
        </p>
    </div>

    {{-- Chat yang dibuka dari halaman ini membawa tautan pesanannya, jadi CS
         tahu pesanan mana yang dibicarakan dan bahwa penanya memang pemegangnya. --}}
    @if (cs_aktif())
        <button type="button" x-data @click="$dispatch('buka-cs', {})" class="tautan mt-3 text-sm">
            Ada pertanyaan atau komplain soal pesanan ini? Chat dengan CS
        </button>
    @elseif ($waPesanan = tautan_wa('Halo '.setting('toko_nama', 'Setia Sport').', saya mau tanya soal pesanan '.$order->kode.'.'))
        <a href="{{ $waPesanan }}" target="_blank" rel="noopener" class="tautan mt-3 inline-block text-sm">
            Ada pertanyaan atau komplain soal pesanan ini? Hubungi lewat WhatsApp
        </a>
    @endif

    {{-- Tagihan yang sedang harus dibayar: instruksinya jadi hal pertama yang
         terlihat. Berlaku untuk DP di awal maupun pelunasan setelah barang jadi
         (KEP-028). Tidak ada gerbang pembayaran (KEP-023) dan tidak ada jalur
         WhatsApp (KEP-029): pelanggan transfer ke rekening yang tertera di sini,
         mengunggah buktinya di sini, dan admin mencocokkannya dengan mutasi. --}}
    @if ($tagihan)
        @php $buktiAda = $tagihan['pembayaran']?->bukti_path; @endphp

        <section class="mt-8 border border-line bg-field">
            <h2 class="border-b border-line px-5 py-3 font-semibold">
                {{ $tagihan['jenis'] === 'pelunasan' ? 'Pelunasan' : 'Yang perlu dibayar sekarang' }}
            </h2>
            <div class="space-y-5 p-5">
                <div>
                    @if ($tagihan['jenis'] === 'pelunasan')
                        <p class="mb-2 text-sm leading-relaxed">
                            Barang Anda sudah selesai dikerjakan. Setelah sisa ini lunas,
                            kode ambil terbit dan barang bisa diambil.
                        </p>
                    @endif

                    <p class="numeral text-4xl text-merah">{{ rupiah($tagihan['jumlah']) }}</p>

                    @if ($tagihan['batas'])
                        <p class="mt-2 text-sm">
                            Batas pembayaran
                            <strong>{{ $tagihan['batas']->translatedFormat('l, j F Y H:i') }}</strong>.
                            Lewat itu pesanan otomatis dibatalkan dan barangnya kembali dijual.
                        </p>
                    @elseif ($tagihan['jenis'] === 'pelunasan')
                        <p class="mt-2 text-sm text-muted">
                            Bisa juga dibayar tunai atau QRIS langsung di kasir saat datang.
                        </p>
                    @endif
                </div>

                {{-- Langkah 1 --}}
                <div class="border-t border-line pt-5">
                    <p class="text-xs font-semibold tracking-wide text-muted uppercase">Langkah 1</p>
                    <p class="mt-1 font-semibold">Transfer sejumlah di atas</p>

                    <div class="mt-4 grid gap-5 sm:grid-cols-2">
                        @if (setting('qris_path'))
                            <div>
                                <p class="text-sm text-muted">Scan QRIS</p>
                                <a href="{{ asset('storage/'.setting('qris_path')) }}" target="_blank" rel="noopener" class="mt-2 block">
                                    <img src="{{ asset('storage/'.setting('qris_path')) }}"
                                         alt="Kode QRIS {{ setting('toko_nama') }}"
                                         class="w-full max-w-72 border border-line bg-white p-2">
                                </a>
                                {{-- Membayar dari HP berarti tidak bisa memindai layar sendiri.
                                     Aplikasi bank di Indonesia umumnya bisa memindai dari galeri. --}}
                                <p class="mt-2 text-xs leading-relaxed text-muted">
                                    Bisa dari aplikasi bank atau e-wallet mana pun. Membayar dari HP ini?
                                    Simpan gambarnya (tekan lama, atau
                                    <a href="{{ asset('storage/'.setting('qris_path')) }}" target="_blank" rel="noopener" class="tautan">buka ukuran penuh</a>),
                                    lalu pilih pindai dari galeri di aplikasi bank.
                                </p>
                            </div>
                        @endif

                        @php
                            // Toko memakai dua rekening; yang kedua boleh kosong.
                            $rekening = array_values(array_filter([
                                ['nama' => setting('bank_nama'), 'no' => setting('bank_rekening'), 'an' => setting('bank_atas_nama')],
                                ['nama' => setting('bank2_nama'), 'no' => setting('bank2_rekening'), 'an' => setting('bank2_atas_nama')],
                            ], fn ($r) => trim((string) $r['no']) !== ''));
                        @endphp

                        @if ($rekening !== [])
                            <div>
                                <p class="text-sm text-muted">
                                    Transfer bank
                                    @if (count($rekening) > 1)
                                        <span class="text-muted">(pilih salah satu)</span>
                                    @endif
                                </p>

                                <div class="mt-2 space-y-4">
                                    @foreach ($rekening as $r)
                                        <div @class(['border-t border-line pt-4' => ! $loop->first])>
                                            <p class="font-semibold">{{ $r['nama'] }}</p>
                                            {{-- Yang disalin angkanya saja: sebagian aplikasi bank menolak
                                                 nomor berspasi saat ditempel. --}}
                                            <div class="mt-1 flex flex-wrap items-center gap-3" x-data="{ tersalin: false }">
                                                <p class="numeral text-2xl">{{ $r['no'] }}</p>
                                                <button type="button" class="tombol tombol-garis tombol-sm"
                                                        @click="navigator.clipboard.writeText('{{ preg_replace('/\D/', '', $r['no']) }}'); tersalin = true; setTimeout(() => tersalin = false, 2000)">
                                                    <span x-text="tersalin ? 'Tersalin' : 'Salin'">Salin</span>
                                                </button>
                                            </div>
                                            <p class="mt-1 text-sm text-muted">a.n. {{ $r['an'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    @if (! setting('qris_path') && $rekening === [])
                        <p class="mt-3 text-sm text-muted">
                            Tujuan transfer belum tercantum di sini. Tanyakan lebih dulu lewat {{ kanal_kontak() }},
                            dan jangan transfer ke rekening yang dikirim lewat jalur lain.
                        </p>
                    @endif

                    {{-- Penipu yang menyamar sebagai toko biasanya bekerja lewat
                         WhatsApp dengan rekening pengganti. Halaman ini, di
                         domain toko, adalah satu-satunya sumber rekening yang sah. --}}
                    <p class="mt-4 border-l-2 border-kuning bg-kuning-50 p-3 text-sm leading-relaxed">
                        <strong>Hanya transfer ke rekening dan QRIS di halaman ini.</strong>
                        Kami tidak pernah meminta pembayaran lewat WhatsApp atau ke rekening lain.
                        Kalau ada yang memintanya atas nama kami, jangan dibayar.
                    </p>
                </div>

                {{-- Langkah 2 --}}
                <div class="border-t border-line pt-5">
                    <p class="text-xs font-semibold tracking-wide text-muted uppercase">Langkah 2</p>
                    <p class="mt-1 font-semibold">Unggah bukti transfernya</p>

                    @if ($buktiAda)
                        <div class="mt-3 border-l-2 border-navy bg-navy-50 p-4 text-sm leading-relaxed">
                            Bukti sudah kami terima dan sedang dicocokkan dengan mutasi rekening.
                            Kami kabari lewat email begitu tercatat.
                            <a href="{{ route('lacak.bukti.lihat', [$order->access_token, $tagihan['pembayaran']]) }}"
                               target="_blank" rel="noopener" class="tautan">Lihat yang Anda kirim</a>
                        </div>
                    @else
                        <p class="mt-2 text-sm leading-relaxed text-muted">
                            Foto atau tangkapan layar dari aplikasi bank. Kami mencocokkannya dengan
                            mutasi rekening pada jam operasional
                            ({{ Str::lcfirst((string) setting('cs_jam_layanan')) }}), lalu mengabari lewat email.
                        </p>
                    @endif

                    <form method="POST" action="{{ route('lacak.bukti', $order->access_token) }}"
                          enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf
                        <div class="min-w-56 flex-1">
                            <label for="bukti" class="label-form">
                                {{ $buktiAda ? 'Salah kirim? Ganti buktinya' : 'Bukti transfer' }}
                            </label>
                            <input type="file" id="bukti" name="bukti" accept="image/*" class="kotak-input" required>
                            <p class="mt-1 text-xs text-muted">
                                JPG atau PNG, maksimal {{ setting('upload_maks_mb', 10) }} MB.
                            </p>
                        </div>
                        <button type="submit" class="tombol {{ $buktiAda ? 'tombol-garis' : 'tombol-utama' }}">
                            {{ $buktiAda ? 'Ganti bukti' : 'Kirim bukti' }}
                        </button>
                    </form>
                </div>
            </div>
        </section>
    @endif

    {{-- Sudah dikirim: nomor resi adalah satu-satunya hal yang dicari pelanggan
         di halaman ini, jadi ia yang paling besar (KEP-026). --}}
    @if ($order->dikirim_pada)
        <section class="mt-8 border border-line bg-field">
            <h2 class="border-b border-line px-5 py-3 font-semibold">
                {{ $order->diterima_pada ? 'Sudah sampai' : 'Sedang dikirim' }}
            </h2>
            <div class="space-y-4 p-5">
                <div>
                    <p class="text-sm text-muted">{{ $order->jasa_kirim }}</p>
                    <p class="numeral mt-2 text-4xl break-all">{{ $order->no_resi }}</p>
                    <p class="mt-3 text-sm text-muted">
                        Diserahkan ke kurir {{ $order->dikirim_pada->translatedFormat('l, j F Y, H:i') }}.
                    </p>
                </div>

                @if (setting('panduan_resi'))
                    <p class="border-l-2 border-navy bg-navy-50 p-4 text-sm leading-relaxed">
                        {{ setting('panduan_resi') }}
                    </p>
                @endif

                @if ($order->alamat_kirim)
                    <div class="border-t border-line pt-4 text-sm">
                        <p class="text-muted">Dikirim ke</p>
                        <p class="mt-1 leading-relaxed">{{ $order->alamat_kirim }}</p>
                    </div>
                @endif
            </div>
        </section>

    {{-- Siap diambil: kode ambil dan sisa tagihan adalah dua hal yang dicari
         pelanggan saat berdiri di depan toko. --}}
    @elseif ($order->status === OrderStatus::SIAP_DIAMBIL && ! $order->dikirim())
        <section class="mt-8 border border-line bg-field">
            <h2 class="border-b border-line px-5 py-3 font-semibold">Ambil di toko</h2>
            <div class="grid gap-5 p-5 sm:grid-cols-2">
                <div>
                    <p class="text-sm text-muted">Kode ambil</p>
                    <p class="numeral mt-2 text-4xl">{{ $order->kode_ambil }}</p>
                    <p class="mt-3 text-sm text-muted">
                        Tunjukkan kode ini di kasir. Boleh diwakilkan.
                    </p>
                </div>
                <div>
                    @if ($order->sisa_tagihan > 0)
                        <p class="text-sm text-muted">Dibayar saat mengambil</p>
                        <p class="numeral mt-2 text-4xl text-merah">{{ rupiah($order->sisa_tagihan) }}</p>
                        <p class="mt-3 text-sm text-muted">Tunai maupun non-tunai.</p>
                    @else
                        <p class="text-sm text-muted">Pembayaran</p>
                        <p class="numeral mt-2 text-3xl text-ok">Lunas</p>
                        <p class="mt-3 text-sm text-muted">Tinggal diambil.</p>
                    @endif
                </div>
            </div>
            {{-- Batas pengambilan disebut di tempat pelanggan pasti membacanya,
                 bukan di halaman syarat terpisah (KEP-043). --}}
            @if ($order->siap_pada)
                <p class="border-t border-line px-5 py-3 text-sm leading-relaxed">
                    Diambil paling lambat
                    <strong>{{ $order->siap_pada->copy()->addDays((int) setting('hangus_hari', 30))->translatedFormat('j F Y') }}</strong>.
                    Lewat dari itu pesanan dianggap hangus dan pembayaran tidak dikembalikan.
                    Kalau berhalangan, kabari kami sebelum tanggal tersebut.
                </p>
            @endif

            <div class="border-t border-line px-5 py-4 text-sm leading-relaxed text-muted">
                <span class="font-medium text-ink">Toko {{ setting('toko_nama', 'Setia Sport') }}</span><br>
                {{ setting('toko_alamat') }}<br>
                <span class="mt-2 inline-block">{{ baris_html(setting('toko_jam_buka')) }}</span><br>
                <a href="{{ setting('maps_embed') ?: tautan_peta((string) setting('toko_alamat')) }}" target="_blank" rel="noopener"
                   class="tautan mt-2 inline-block">Buka di Google Maps</a>
            </div>
        </section>

    {{-- Sudah siap tapi akan dikirim: yang ditunggu bukan kode ambil,
         melainkan nomor resi yang belum terbit. --}}
    @elseif ($order->status === OrderStatus::SIAP_DIAMBIL && $order->dikirim())
        <section class="mt-8 border border-line bg-field">
            <h2 class="border-b border-line px-5 py-3 font-semibold">Menunggu dikirim</h2>
            <div class="space-y-3 p-5 text-sm leading-relaxed">
                <p>
                    Barangnya sudah selesai dan sedang kami siapkan untuk dikirim.
                    Nomor resinya muncul di halaman ini, dan kami kirim juga lewat email,
                    begitu paket diserahkan ke kurir.
                </p>
                @if ($order->sisa_tagihan > 0)
                    <p class="border-l-2 border-merah bg-merah-50 p-4">
                        Masih ada sisa <strong>{{ rupiah($order->sisa_tagihan) }}</strong>.
                        Paket berangkat setelah pelunasannya kami terima.
                    </p>
                @endif
                @if ($order->alamat_kirim)
                    <div class="border-t border-line pt-3">
                        <p class="text-muted">Dikirim ke</p>
                        <p class="mt-1">{{ $order->alamat_kirim }}</p>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- Timeline per item, bukan satu timeline tunggal: satu order boleh memuat
         barang stok dan barang kustom yang jalannya berbeda (KEP-001).
         
         Sejak KEP-027 keduanya juga DIPISAH jadi dua kelompok. Menaruhnya
         berurutan membuat pelanggan mengira semuanya menunggu hal yang sama,
         padahal jersey menunggu persetujuan desain sementara bolanya sudah ada
         di rak sejak hari pertama. --}}
    @php
        $kelompok = $order->items->partition(fn ($i) => $i->kustom());
        $grup = array_values(array_filter([
            [
                'judul' => 'Dibuat di workshop',
                'catatan' => 'Melewati desain, persetujuan Anda, lalu produksi. Diambil di toko.',
                'items' => $kelompok[0],
            ],
            [
                'judul' => 'Stok toko',
                'catatan' => $order->dikirim()
                    ? 'Barang yang sudah ada di rak. Pesanan ini dikirim ke alamat Anda.'
                    : 'Barang yang sudah ada di rak, tinggal disiapkan.',
                'items' => $kelompok[1],
            ],
        ], fn ($g) => $g['items']->isNotEmpty()));
    @endphp

    <section class="mt-8">
        <h2 class="text-xl font-extrabold">Progres</h2>

        @foreach ($grup as $g)
        <div class="mt-6">
            {{-- Judul kelompok disembunyikan kalau isinya cuma satu jenis:
                 memberi label pada satu-satunya kelompok tidak menjelaskan apa pun. --}}
            @if (count($grup) > 1)
                <div class="border-b border-line pb-2">
                    <h3 class="font-bold">{{ $g['judul'] }}</h3>
                    <p class="mt-0.5 text-sm text-muted">{{ $g['catatan'] }}</p>
                </div>
            @endif

            <div class="mt-4 space-y-4">
            @foreach ($g['items'] as $item)
                @php $linimasa = App\Support\LinimasaItem::untuk($item, $order); @endphp

                <div class="border border-line bg-field">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-line px-5 py-3">
                        <span class="font-semibold">{{ $item->nama_produk }}</span>
                        <span class="text-sm text-muted tabular">{{ $item->qty }} pcs</span>
                    </div>

                    @if ($linimasa['batal'])
                        <p class="px-5 py-4 text-sm text-muted">Item ini dibatalkan.</p>
                    @else
                        {{-- Tahap besar saja (KEP-028). Status internal yang rinci
                             tetap ada, tapi hanya Produksi yang membuka subprogresnya:
                             di situlah penungguan paling lama terasa. --}}
                        <ol class="px-5 py-4">
                            @foreach ($linimasa['fase'] as $f)
                                @php
                                    $lewat = $f['keadaan'] === 'lewat';
                                    $kini = $f['keadaan'] === 'kini';
                                @endphp
                                <li class="flex gap-3 {{ $loop->last ? '' : 'pb-4' }}">
                                    <div class="flex flex-col items-center">
                                        <span class="mt-1 size-2.5 shrink-0 rounded-full
                                            {{ $kini ? 'bg-merah ring-4 ring-merah-50' : ($lewat ? 'bg-navy' : 'bg-line-strong') }}"></span>
                                        @unless ($loop->last)
                                            <span class="w-px flex-1 {{ $lewat ? 'bg-navy' : 'bg-line' }}"></span>
                                        @endunless
                                    </div>

                                    <div class="-mt-0.5 min-w-0 flex-1 pb-1">
                                        <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                                            <p class="text-sm {{ $kini ? 'font-bold' : ($lewat ? '' : 'text-muted') }}">
                                                {{ $f['label'] }}
                                            </p>
                                            @if ($f['waktu'])
                                                <p class="text-xs text-muted tabular">
                                                    {{ $f['waktu']->translatedFormat('j M Y, H:i') }}
                                                </p>
                                            @endif
                                        </div>

                                        @if ($f['catatan'])
                                            <p class="mt-1 text-sm leading-relaxed text-muted">{{ $f['catatan'] }}</p>
                                        @endif

                                        {{-- Subprogres ditampilkan selama produksi berjalan dan
                                             sesudahnya. Sebelum produksi dimulai, lima baris
                                             abu-abu hanya menambah panjang tanpa menjawab apa pun. --}}
                                        @if ($f['subtahap'] !== [] && $f['keadaan'] !== 'nanti')
                                            <ol class="mt-3 grid gap-1.5 border-l-2 border-line pl-3 text-sm">
                                                @foreach ($f['subtahap'] as $sub)
                                                    <li class="flex flex-wrap items-baseline justify-between gap-x-3">
                                                        <span class="flex items-center gap-2
                                                            {{ $sub['keadaan'] === 'kini' ? 'font-semibold text-ink' : ($sub['keadaan'] === 'lewat' ? 'text-ink' : 'text-muted') }}">
                                                            <span class="inline-block size-1.5 shrink-0 rounded-full
                                                                {{ $sub['keadaan'] === 'kini' ? 'bg-merah' : ($sub['keadaan'] === 'lewat' ? 'bg-navy' : 'bg-line-strong') }}"></span>
                                                            {{ $sub['label'] }}
                                                            @if ($sub['keadaan'] === 'kini')
                                                                <span class="text-xs font-normal text-merah">sedang dikerjakan</span>
                                                            @endif
                                                        </span>
                                                        @if ($sub['waktu'])
                                                            <span class="text-xs text-muted tabular">
                                                                {{ $sub['waktu']->translatedFormat('j M, H:i') }}
                                                            </span>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ol>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    @if ($item->kustom() && ! $linimasa['batal'])
                        @include('komponen.desain', ['item' => $item, 'order' => $order])

                        @if ($item->product->roster_wajib || $item->rosters->isNotEmpty())
                            @include('komponen.roster', ['item' => $item, 'order' => $order])
                        @endif
                    @endif
                </div>
            @endforeach
            </div>
        </div>
        @endforeach
    </section>

    <section class="mt-8 border border-line bg-field">
        <h2 class="border-b border-line px-5 py-3 font-semibold">Rincian biaya</h2>
        <ul class="divide-y divide-line text-sm">
            @foreach ($order->items as $item)
                <li class="px-5 py-3">
                    <div class="flex justify-between gap-3">
                        <span>{{ $item->nama_produk }} <span class="text-muted tabular">&times; {{ $item->qty }}</span></span>
                        <span class="tabular whitespace-nowrap">{{ rupiah($item->subtotal) }}</span>
                    </div>
                    @foreach ($item->options as $opsi)
                        <div class="mt-1 flex justify-between gap-3 text-xs text-muted">
                            <span>{{ $opsi->option_value }}</span>
                            <span class="tabular">
                                {{ $opsi->tambahan_harga < 0 ? '−' : '+' }}{{ rupiah(abs($opsi->tambahan_harga)) }}
                                {{ $opsi->basis->label() }}
                            </span>
                        </div>
                    @endforeach
                </li>
            @endforeach

            {{-- Perubahan pesanan tampil di sini SEBELUM pelanggan datang ke toko.
                 Itulah inti KEP-007: tidak ada kejutan angka di kasir. --}}
            @foreach ($order->adjustments as $p)
                <li class="flex justify-between gap-3 px-5 py-3">
                    <span>{{ $p->deskripsi }}</span>
                    <span class="tabular whitespace-nowrap">
                        {{ $p->jumlah < 0 ? '−' : '' }}{{ rupiah(abs($p->jumlah)) }}
                    </span>
                </li>
            @endforeach
        </ul>

        <dl class="space-y-1.5 border-t border-line px-5 py-4 text-sm">
            <div class="flex justify-between gap-3">
                <dt class="font-semibold">Total</dt>
                <dd class="tabular font-semibold">{{ rupiah($order->total) }}</dd>
            </div>
            @if ($order->sudah_dibayar > 0)
                <div class="flex justify-between gap-3 text-muted">
                    <dt>Sudah dibayar</dt>
                    <dd class="tabular">{{ rupiah($order->sudah_dibayar) }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="font-semibold">Sisa tagihan</dt>
                    <dd class="tabular font-semibold {{ $order->sisa_tagihan > 0 ? 'text-merah' : 'text-ok' }}">
                        {{ $order->sisa_tagihan > 0 ? rupiah($order->sisa_tagihan) : 'Lunas' }}
                    </dd>
                </div>
            @endif
        </dl>
    </section>

    @if ($order->statusLogs->isNotEmpty())
        <section class="mt-8">
            <h2 class="text-xl font-extrabold">Riwayat</h2>
            <ul class="mt-4 divide-y divide-line border border-line bg-field text-sm">
                @foreach ($order->statusLogs->take(20) as $log)
                    <li class="flex flex-wrap justify-between gap-3 px-5 py-2.5">
                        <span>
                            {{ $log->orderItem?->nama_produk ?? 'Pesanan' }}
                            {{ \App\Enums\OrderItemStatus::tryFrom($log->ke)?->label()
                                ?? OrderStatus::tryFrom($log->ke)?->label() ?? $log->ke }}
                        </span>
                        <span class="text-muted tabular">{{ $log->created_at?->translatedFormat('j M Y, H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Pembatalan (KEP-041). Ketentuannya ditulis apa adanya SEBELUM tombolnya,
         termasuk kalimat pahit bahwa uang muka bisa hangus: pelanggan yang baru
         tahu akibatnya setelah menekan tombol akan merasa dijebak, dan itu
         berakhir jadi pertengkaran di WhatsApp. --}}
    @php $batal = $pembatalan['terakhir']; @endphp

    @if ($batal && $batal->menunggu())
        <section class="mt-8 border border-kuning bg-kuning-50 p-5">
            <h2 class="font-bold">Pengajuan pembatalan sedang diperiksa</h2>
            <p class="mt-1 text-sm leading-relaxed">
                Diajukan {{ $batal->created_at->translatedFormat('j M Y, H:i') }}. Toko akan
                mengabari keputusannya di halaman ini. Selama belum diputuskan, pesanan Anda
                tetap berjalan seperti biasa.
            </p>
            <p class="mt-2 text-sm text-muted">Alasan yang Anda tulis: "{{ $batal->alasan }}"</p>
        </section>
    @elseif ($batal && $batal->status === \App\Enums\StatusPembatalan::DISETUJUI)
        <section class="mt-8 border border-line bg-field p-5">
            <h2 class="font-bold">Pesanan dibatalkan</h2>
            <p class="mt-1 text-sm leading-relaxed">
                @if ($batal->refund_jumlah > 0)
                    Pengembalian sebesar <strong>{{ rupiah($batal->refund_jumlah) }}</strong>
                    dikirim lewat {{ $batal->refund_metode === 'TUNAI' ? 'tunai di toko' : 'transfer' }}.
                    Kalau dalam 3 hari kerja belum masuk, hubungi toko.
                @else
                    Tidak ada pengembalian uang untuk pembatalan ini.
                @endif
            </p>
            @if ($batal->catatan_admin)
                <p class="mt-2 text-sm text-muted">Catatan toko: {{ $batal->catatan_admin }}</p>
            @endif
        </section>
    @elseif ($batal && $batal->status === \App\Enums\StatusPembatalan::DITOLAK)
        <section class="mt-8 border border-line bg-field p-5">
            <h2 class="font-bold">Pengajuan pembatalan ditolak</h2>
            <p class="mt-1 text-sm leading-relaxed">{{ $batal->catatan_admin }}</p>
            <p class="mt-2 text-sm text-muted">
                Pesanan Anda tetap berjalan. Kalau masih ingin membicarakannya, hubungi toko.
            </p>
        </section>
    @endif

    @if ($pembatalan['boleh'])
        <details class="mt-8 border border-line bg-field p-5">
            <summary class="cursor-pointer font-medium">Ingin membatalkan pesanan ini?</summary>

            <p class="mt-3 text-sm leading-relaxed">{{ $pembatalan['tahap']->ketentuan() }}</p>

            @if ($pembatalan['tahap']->adaPengembalian() && $order->sudah_dibayar > 0)
                <p class="mt-2 text-sm">
                    Perkiraan pengembalian:
                    <strong class="tabular">{{ rupiah($pembatalan['usulan']) }}</strong>
                    dari {{ rupiah($order->sudah_dibayar) }} yang sudah dibayar.
                    Angka akhirnya dipastikan toko saat menyetujui.
                </p>
            @endif

            <form method="POST" action="{{ route('lacak.pembatalan', $order->access_token) }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label for="alasan-batal" class="label-form">Alasan pembatalan</label>
                    <textarea id="alasan-batal" name="alasan" rows="3" class="kotak-input" required
                              minlength="10" maxlength="1000"
                              placeholder="Contoh: turnamennya diundur, tim belum bisa menentukan ukuran"></textarea>
                    @error('alasan')
                        <p class="mt-1 text-sm text-merah">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="tombol tombol-garis text-merah"
                        data-teks-proses="Mengirim...">
                    Ajukan pembatalan
                </button>
                <p class="text-xs leading-relaxed text-muted">
                    Pesanan belum langsung batal. Toko memeriksa dulu, dan Anda dikabari di halaman ini.
                </p>
            </form>
        </details>
    @endif

    {{-- Ditawarkan di sini karena inilah saat pelanggan paling mungkin mau:
         pesanannya sudah nyata dan mereka sedang melihat manfaatnya. --}}
    @if (! auth('pelanggan')->check() && ! $order->customer->akunAktif())
        <div class="notis notis-info mt-4">
            <p class="font-semibold">Sering memesan di sini?</p>
            <p class="mt-1">
                Buat akun sekali, lalu semua pesanan Anda tampil dalam satu halaman tanpa
                perlu mencari email. Pesanan ini ikut tercatat otomatis.
            </p>
            <a href="{{ route('daftar', ['email' => $order->customer->email]) }}"
               class="tombol tombol-garis tombol-sm mt-3">Buat akun</a>
        </div>
    @endif
</div>
@endsection
