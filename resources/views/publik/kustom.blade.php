@extends('layouts.publik')
@section('judul', 'Pesan '.$produk->nama)
@section('deskripsi', 'Atur bahan, model, ukuran, dan daftar pemain, lalu lihat harganya langsung.')
@if ($produk->images->first()?->url)
    @section('og_gambar', url($produk->images->first()->url))
@endif
@section('subjudul', 'Pesan '.$produk->nama)
@section('subcatatan', 'Empat langkah: produk, desain, data pemain, cek pesanan.')

@section('isi')
@php
    /** @var \App\Models\Product $produk */
    // Opsi yang dipilih per pemain (KEP-123), misalnya Model Baju, tampil
    // sebagai kolom di daftar ukuran, bukan tombol pilihan barang.
    $opsiPemain = $produk->options->firstWhere('per_pemain', true);
    $dataPemain = $opsiPemain ? [
        'id' => $opsiPemain->id,
        'nama' => $opsiPemain->nama,
        'values' => $opsiPemain->values->where('aktif', true)->map(fn ($v) => [
            'id' => $v->id, 'label' => $v->label, 'harga' => $v->tambahan_harga,
        ])->values(),
    ] : null;

    $dataOpsi = $produk->options->reject(fn ($o) => $o->per_pemain)->map(fn ($o) => [
        'id' => $o->id,
        'nama' => $o->nama,
        'wajib' => $o->wajib,
        'values' => $o->values->map(fn ($v) => [
            'id' => $v->id,
            'label' => $v->label,
            'harga' => $v->tambahan_harga,
            'basis' => $v->basis->value,
            // Kamus bahan (KEP-072): penjelasan ditampilkan begitu nilai ini
            // dipilih, supaya pelanggan awam tahu "Milano" itu bahan seperti apa.
            'deskripsi' => $v->material?->deskripsi,
            // Foto tekstur bahan (KEP-110), tampil bersama penjelasannya.
            'gambar' => $v->material?->thumb_url,
            'gambarBesar' => $v->material?->gambar_url,
        ])->values(),
    ])->values();

    // Matriks harga dikirim apa adanya; rumus di layar harus sama persis dengan
    // PriceCalculator di server, dan server tetap yang berhak memutuskan.
    $dataHarga = $produk->prices->map(fn ($h) => [
        'kategori' => $h->kategori?->value ?? '',
        'value_id' => $h->option_value_id ?? 0,
        'harga' => $h->harga_satuan,
    ])->values();

    /*
     * Ukurannya bersumber dari panduan ukuran produk (KEP-049). Celana pendek
     * tidak punya kategori umur sama sekali, jadi pemilih kategorinya ikut
     * hilang, bukan diisi pilihan yang tidak berarti apa-apa.
     */
    $pakaiKategori = $produk->pakaiKategoriUkuran();

    $dataKategori = $pakaiKategori
        ? collect($kategori)->map(fn ($k) => [
            'value' => $k->value,
            'label' => $k->label(),
            'ukuran' => $produk->ukuranTersedia($k),
        ])->values()
        : collect([[
            'value' => '',
            'label' => 'Semua ukuran',
            'ukuran' => $produk->ukuranTersedia(null),
        ]]);

    // Panduan ukuran dalam sentimeter, satu tab per kategori (KEP-146). Kuncinya
    // nilai kategori, sama dengan isian kategori di baris pemain, supaya tab
    // bisa mengikuti pemain yang sedang diisi.
    // Urutan tab tetap SD, SMP, SMA / Dewasa; urutan baris di basis data
    // tidak menjamin itu karena tiap kategori mulai dari urutan 0.
    $urutanKategori = collect($kategori)->pluck('value')->flip();
    $panduan = $produk->sizeCharts
        ->groupBy(fn ($b) => $b->kategori?->value ?? '')
        ->sortBy(fn ($baris, $nilai) => $urutanKategori[$nilai] ?? -1)
        ->map(fn ($baris) => [
            'label' => $baris->first()->kategori?->label() ?? 'Semua ukuran',
            'kolom' => $baris->first()->kolom(),
            'baris' => $baris,
        ]);
    // Baris pemain pertama memakai kategori terakhir (tambahBaris), jadi tab itu
    // yang terbuka sejak awal, tanpa berkedip dari tab pertama.
    $panduanAwal = $panduan->has($dataKategori->last()['value'] ?? '')
        ? $dataKategori->last()['value']
        : (string) $panduan->keys()->first();

    $dataTambahan = $produk->sizeSurcharges->mapWithKeys(fn ($t) => [$t->ukuran => $t->tambahan_harga]);

    $langkah = ['Pilih Produk', 'Desain & Detail', 'Data Pemain', 'Cek Pesanan'];

    // Jersey yang celananya opsi tambahan menggambar celana begitu dipilih (KEP-106).
    $adaOpsiCelana = $produk->options->contains(fn ($o) => Str::contains(Str::lower($o->nama), 'celana'));
@endphp

<div class="mx-auto max-w-6xl px-5 py-6"
     x-data="pesanKustom({
        opsi: @js($dataOpsi),
        opsiPemain: @js($dataPemain),
        harga: @js($dataHarga),
        kategori: @js($dataKategori),
        tambahanUkuran: @js($dataTambahan),
        panduanKategori: @js($panduan->keys()->map(fn ($k) => (string) $k)->values()),
        panduanAktif: @js($panduanAwal),
        moq: {{ $produk->moq }},
        dpPersen: {{ (int) setting('dp_minimum_persen', 50) }},
        diskonMinQty: {{ $produk->diskon_min_qty }},
        diskonPerPcs: {{ $produk->diskon_per_pcs }},
        leherBawaan: @js($produk->pola?->leherBawaan() ?? 'bulat'),
     })">

    <nav class="text-sm text-muted" aria-label="Remah roti">
        <a href="{{ route('katalog') }}" class="tautan">Katalog</a>
        <span class="mx-2 text-line-strong">/</span>
        <a href="{{ route('produk', $produk) }}" class="tautan">{{ $produk->nama }}</a>
        <span class="mx-2 text-line-strong">/</span>
        <span class="text-ink">Buat pesanan</span>
    </nav>

    {{-- Penanda langkah. Langkah yang sudah dilewati bisa diklik untuk mundur:
         memperbaiki bahan tidak boleh berarti mengetik ulang daftar pemain.
         Di HP hanya langkah yang aktif yang berlabel, yang lain cukup nomornya
         (labelnya tetap dibaca pembaca layar), supaya keempatnya muat tanpa
         terpotong di tepi layar (KEP-143). --}}
    <ol x-ref="penanda" class="mt-5 flex items-center gap-1 overflow-x-auto pb-1 text-sm md:gap-2">
        @foreach ($langkah as $i => $nama)
            <li class="flex shrink-0 items-center gap-1 md:gap-2">
                <button type="button" data-langkah="{{ $i + 1 }}" @click="ke({{ $i + 1 }})" :disabled="{{ $i + 1 }} > tertinggi"
                        class="flex items-center gap-2 rounded-full py-1 pl-1 transition-colors disabled:cursor-not-allowed"
                        :class="langkah === {{ $i + 1 }} ? 'bg-navy pr-3 text-white' : (({{ $i + 1 }} <= tertinggi ? 'text-ink hover:bg-navy-50' : 'text-muted') + ' pr-1 md:pr-3')">
                    <span class="flex size-6 items-center justify-center rounded-full text-xs font-bold"
                          :class="langkah === {{ $i + 1 }} ? 'bg-white text-navy' : 'bg-navy-100 text-navy-800'">{{ $i + 1 }}</span>
                    <span class="whitespace-nowrap" :class="langkah === {{ $i + 1 }} ? '' : 'sr-only md:not-sr-only'">{{ $nama }}</span>
                </button>
                @unless ($loop->last)
                    <span class="h-px w-3 bg-line-strong md:w-6" aria-hidden="true"></span>
                @endunless
            </li>
        @endforeach
    </ol>

    <form method="POST" action="{{ route('kustom.simpan', $produk) }}" class="mt-6 lg:grid lg:grid-cols-[1fr_20rem] lg:items-start lg:gap-8">
        @csrf

        <div class="min-w-0 space-y-6">

            @if ($errors->any())
                <div class="notis notis-galat">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $pesan)
                            <li>{{ $pesan }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- LANGKAH 1 --------------------------------------------------- --}}
            <section x-show="langkah === 1" class="space-y-4">
                <div>
                    <h1 class="text-xl font-bold">{{ $produk->nama }}</h1>
                    @if ($produk->deskripsi)
                        <p class="mt-1 text-sm leading-relaxed text-muted">{{ $produk->deskripsi }}</p>
                    @endif
                </div>

                {{-- Gambar pola (KEP-106): pemesan awam melihat bentuk barangnya
                     sebelum ada desain. Model leher dan lengannya ikut pilihan di
                     langkah 2, jadi yang dilihat adalah yang akan dipesan. --}}
                @if ($produk->pola)
                    <div class="kartu p-5">
                        {{-- Labelnya selalu di baris sendiri. Sebaris dengan flex-wrap,
                             ia muat dengan font cadangan lalu turun begitu font web
                             dimuat, dan sketsa di bawahnya melompat 20 px (CLS 0,09). --}}
                        <p class="font-semibold">Gambaran bentuk</p>
                        <p class="mt-0.5 text-xs text-muted">{{ $produk->pola->label() }}</p>
                        <x-pola :pola="$produk->pola" reaktif :celana-opsional="$adaOpsiCelana" class="mx-auto mt-4 max-w-md" />
                        <p class="mt-4 text-sm leading-relaxed text-muted">
                            Sketsa tanpa desain. Warna, motif, logo, dan nomor mengikuti desain di langkah 2;
                            model leher dan lengan ikut berubah sesuai pilihan Anda.
                        </p>
                    </div>
                @endif

                <div class="kartu p-5">
                    <p class="font-semibold">Yang akan dipesan</p>
                    <p class="mt-1 text-sm text-muted">
                        Minimal {{ $produk->moq }} pcs. Harga mengikuti bahan, model, dan ukuran yang dipilih.
                    </p>
                </div>
            </section>

            {{-- LANGKAH 2 --------------------------------------------------- --}}
            <section x-show="langkah === 2" x-cloak class="space-y-4">
                <h2 class="text-xl font-bold">Desain & detail</h2>

                @if ($produk->pola)
                    <div class="kartu p-5">
                        <p class="text-sm text-muted">Bentuknya ikut berubah begitu model dipilih di bawah.</p>
                        <x-pola :pola="$produk->pola" reaktif :celana-opsional="$adaOpsiCelana" class="mx-auto mt-3 max-w-xs" />
                    </div>
                @endif

                @if ($opsiPemain)
                    {{-- Opsi per pemain pindah ke daftar pemain (KEP-123); kalimat
                         ini mencegah pelanggan mencarinya di sini. --}}
                    <p class="rounded-[8px] border-l-2 border-navy-200 bg-navy-50 px-4 py-3 text-sm leading-relaxed">
                        <strong>{{ $opsiPemain->nama }}</strong> ({{ $opsiPemain->values->where('aktif', true)->pluck('label')->implode(' atau ') }})
                        dipilih per pemain di langkah Data Pemain, jadi satu pesanan boleh campuran.
                    </p>
                @endif

                @foreach ($produk->options->reject(fn ($o) => $o->per_pemain) as $opsi)
                    <div class="kartu p-5">
                        <p class="font-semibold">
                            {{ $opsi->nama }}
                            @unless ($opsi->wajib)
                                <span class="text-sm font-normal text-muted">(opsional)</span>
                            @endunless
                        </p>
                        {{-- Foto bahan di tiap tombol (KEP-140): pelanggan membandingkan
                             tekstur sebelum memilih. Opsi tanpa foto sama sekali (model,
                             lengan) tetap berupa tombol teks. --}}
                        @php $adaFoto = $opsi->values->contains(fn ($v) => $v->material?->thumb_url); @endphp
                        <div class="mt-3 {{ $adaFoto ? 'grid grid-cols-2 gap-2 sm:grid-cols-3' : 'flex flex-wrap gap-2' }}">
                            @foreach ($opsi->values as $v)
                                <button type="button" @click="pilihan[{{ $opsi->id }}] = {{ $v->id }}"
                                        :aria-pressed="pilihan[{{ $opsi->id }}] === {{ $v->id }}"
                                        class="rounded-[8px] border text-left text-sm transition-colors {{ $adaFoto ? 'flex min-w-0 items-center gap-2.5 p-2' : 'px-3 py-2' }}"
                                        :class="pilihan[{{ $opsi->id }}] === {{ $v->id }}
                                            ? 'border-navy bg-navy-50 font-medium text-navy-800'
                                            : 'border-line hover:border-navy-300'">
                                    @if ($adaFoto)
                                        @if ($v->material?->thumb_url)
                                            <img src="{{ $v->material->thumb_url }}" alt="Tekstur bahan {{ $v->label }}"
                                                 width="48" height="48" loading="lazy"
                                                 class="size-12 shrink-0 rounded-[6px] border border-line object-cover">
                                        @else
                                            <span class="size-12 shrink-0 rounded-[6px] bg-navy-50" aria-hidden="true"></span>
                                        @endif
                                    @endif
                                    <span class="min-w-0">
                                        {{ $v->label }}
                                        @if ($v->tambahan_harga !== 0)
                                            <span class="block text-xs font-normal text-muted">
                                                + {{ rupiah($v->tambahan_harga) }} / pcs
                                            </span>
                                        @endif
                                    </span>
                                </button>
                            @endforeach
                        </div>

                        {{-- Kamus bahan (KEP-072): penjelasan bahan yang dipilih, dengan
                             fotonya lebih besar (KEP-140); diketuk untuk ukuran penuh. --}}
                        <div x-show="deskripsiTerpilih({{ $opsi->id }}) || nilaiTerpilih({{ $opsi->id }})?.gambar" x-cloak
                             class="mt-3 flex flex-col gap-3 border-l-2 border-navy-200 bg-navy-50 p-3 text-sm leading-relaxed text-ink sm:flex-row sm:items-start">
                            <template x-if="nilaiTerpilih({{ $opsi->id }})?.gambar">
                                <a :href="nilaiTerpilih({{ $opsi->id }}).gambarBesar" target="_blank" rel="noopener" class="shrink-0"
                                   :title="'Lihat tekstur ' + nilaiTerpilih({{ $opsi->id }}).label + ' lebih dekat'">
                                    <img :src="nilaiTerpilih({{ $opsi->id }}).gambar" :alt="'Tekstur bahan ' + nilaiTerpilih({{ $opsi->id }}).label"
                                         width="128" height="128" class="size-32 rounded-[6px] border border-line object-cover">
                                </a>
                            </template>
                            <div class="min-w-0">
                                <p class="font-semibold" x-text="nilaiTerpilih({{ $opsi->id }})?.label"></p>
                                <p class="mt-1" x-text="deskripsiTerpilih({{ $opsi->id }})"></p>
                                <p x-show="nilaiTerpilih({{ $opsi->id }})?.gambar" class="mt-1 text-xs text-muted">Ketuk foto untuk melihat tekstur lebih dekat.</p>
                            </div>
                        </div>

                        <input type="hidden" name="opsi[{{ $opsi->id }}]" :value="pilihan[{{ $opsi->id }}] ?? ''">
                    </div>
                @endforeach

                <div class="kartu p-5">
                    <p class="font-semibold">Desain</p>
                    {{-- Tiap pilihan membawa penjelasannya (KEP-138): klien sendiri
                         bingung "pilih dari katalog" itu maksudnya apa. --}}
                    <div class="mt-3 grid gap-2 sm:grid-cols-3">
                        @foreach ([
                            'katalog' => ['Pilih dari katalog', 'Pakai desain dari galeri kami. Sebutkan desain yang Anda suka di Permintaan desain; warna, nama tim, dan logo kami sesuaikan.'],
                            'sendiri' => ['Kirim desain sendiri', 'Sudah punya desain jadi? Kirim filenya, kami siapkan mockup-nya.'],
                            'admin' => ['Dibantu admin', 'Belum punya desain? Ceritakan keinginan Anda, desainer kami membuatkan dari awal.'],
                        ] as $nilai => [$label, $penjelasan])
                            <button type="button" @click="desainSumber = '{{ $nilai }}'"
                                    class="rounded-[10px] border px-3 py-2.5 text-left text-sm transition-colors"
                                    :aria-pressed="(desainSumber === '{{ $nilai }}').toString()"
                                    :class="desainSumber === '{{ $nilai }}'
                                        ? 'border-navy bg-navy-50 text-navy-800'
                                        : 'border-line hover:border-navy-300'">
                                <span class="block font-semibold">{{ $label }}</span>
                                <span class="mt-1 block text-xs leading-relaxed text-muted">{{ $penjelasan }}</span>
                            </button>
                        @endforeach
                    </div>
                    <p x-show="desainSumber === 'katalog'" class="mt-3 text-sm">
                        <a href="{{ route('portofolio') }}" target="_blank" rel="noopener" class="tautan">Lihat galeri desain</a>
                        untuk memilih desain yang Anda suka.
                    </p>
                    <input type="hidden" name="desain_sumber" :value="desainSumber">

                    {{-- Dijelaskan di sini, sebelum data pemesan dan pembayaran
                         (KEP-095). File desain hanya lewat WhatsApp: tidak ada
                         kotak unggah di website, termasuk di halaman pesanan. --}}
                    <div x-show="desainSumber === 'sendiri'" x-cloak
                         class="mt-4 border-l-2 border-navy-200 bg-navy-50 p-3 text-sm leading-relaxed">
                        <p class="font-semibold">Kirim file desain lewat tautan atau WhatsApp</p>
                        <p class="mt-1">
                            Semua format diterima: CDR, AI, PSD, PDF, PNG, atau JPG. Tempel tautan Google Drive
                            atau Dropbox di bawah, atau kirim lewat WhatsApp. Boleh juga nanti: tautannya bisa
                            dikirim dari halaman pesanan, dan admin kami menghubungi nomor WhatsApp Anda.
                        </p>
                        {{-- Tautan saja (KEP-139): tidak ada berkas yang masuk ke server. --}}
                        <label for="tautan-desain" class="label-form mt-3">Tautan Google Drive atau Dropbox <span class="font-normal text-muted">(opsional)</span></label>
                        <input type="url" id="tautan-desain" name="tautan_desain" maxlength="500"
                               value="{{ old('tautan_desain') }}" placeholder="https://drive.google.com/..."
                               class="kotak-input bg-field" :disabled="desainSumber !== 'sendiri'">
                        <p class="mt-1 text-xs text-muted">Atur aksesnya "Siapa saja yang memiliki link" supaya desainer bisa membukanya.</p>
                        @error('tautan_desain')
                            <p class="mt-1 text-sm text-merah">{{ $message }}</p>
                        @enderror
                        @if ($waDesain = tautan_wa('Halo '.setting('toko_nama', 'Setia Sport').', saya mau mengirim file desain untuk pesanan '.$produk->nama.'. Pesanannya saya buat di website.'))
                            <p class="mt-2 text-xs text-muted">
                                Kirim dari nomor yang sama dengan yang nanti Anda isi, supaya desainnya
                                langsung kami cocokkan dengan pesanan Anda.
                            </p>
                            <a href="{{ $waDesain }}" target="_blank" rel="noopener"
                               class="tombol tombol-garis tombol-sm mt-3">
                                <x-ikon nama="whatsapp" ukuran="size-4" />
                                Kirim desain lewat WhatsApp
                            </a>
                        @endif
                    </div>

                    <label for="catatan-desain" class="label-form mt-5">Permintaan desain (opsional)</label>
                    <textarea id="catatan-desain" name="catatan_desain" rows="3" class="kotak-input"
                              x-model="catatanDesain"
                              placeholder="Contoh: warna dasar navy, logo tim di dada kiri, nomor punggung putih"></textarea>
                    <p class="mt-2 text-xs leading-relaxed text-muted">
                        Mockup desain akan ditampilkan pada halaman pesanan untuk diperiksa dan disetujui sebelum proses produksi dimulai.
                    </p>
                </div>
            </section>

            {{-- LANGKAH 3 --------------------------------------------------- --}}
            <section x-show="langkah === 3" x-cloak class="space-y-4">
                <div>
                    <h2 class="text-xl font-bold">Data pemain</h2>
                    <p class="mt-1 text-sm leading-relaxed text-muted">
                        Jumlah pesanan mengikuti daftar yang diisi. Nama dan nomor punggung dapat dilengkapi kemudian, sebelum desain disetujui.
                    </p>
                </div>

                {{-- Satu kartu per baris, bukan tabel: di HP tabel lima kolom
                     menyisakan kotak selebar dua huruf, dan operator mengisinya
                     sambil menebak. Di layar lebar kartunya memanjang jadi satu
                     baris, jadi tampilannya tetap padat. --}}
                <div class="space-y-3">
                    <template x-for="(b, i) in baris" :key="i">
                        <div class="kartu p-4">
                            {{-- Kolom mengikuti isinya (KEP-138): kolom jumlah dulu 5,5rem,
                                 lebih sempit dari tombol − 1 +, dan kolom model per pemain
                                 menambah satu isian ke grid lima kolom. Enam isian dibagi dua
                                 tingkat empat kolom; di layar sempit dua kolom, nama selebar
                                 penuh. --}}
                            <div class="grid grid-cols-2 gap-3 {{ match (true) {
                                $pakaiKategori && $opsiPemain => 'lg:grid-cols-4',
                                $pakaiKategori => 'lg:grid-cols-[minmax(7rem,1fr)_4.5rem_9rem_6rem_9rem]',
                                (bool) $opsiPemain => 'lg:grid-cols-[minmax(7rem,1fr)_4.5rem_6rem_9rem_9rem]',
                                default => 'lg:grid-cols-[minmax(7rem,1fr)_4.5rem_6rem_9rem]',
                            } }}">
                                <label class="col-span-2 block {{ $pakaiKategori && $opsiPemain ? '' : 'lg:col-span-1' }}">
                                    <span class="label-form">Nama punggung</span>
                                    <input type="text" x-model="b.nama_punggung" :name="`baris[${i}][nama_punggung]`"
                                           maxlength="60" placeholder="Tanpa nama" class="kotak-input">
                                </label>
                                <label class="block">
                                    <span class="label-form">Nomor</span>
                                    <input type="text" x-model="b.nomor" :name="`baris[${i}][nomor]`"
                                           maxlength="5" inputmode="numeric" class="kotak-input tabular">
                                </label>
                                @if ($pakaiKategori)
                                    <label class="block">
                                        <span class="label-form">Kategori</span>
                                        <select x-model="b.kategori" :name="`baris[${i}][kategori]`"
                                                @change="betulkanUkuran(b); ikutiPanduan(b.kategori)" class="kotak-input">
                                            <template x-for="k in kategori" :key="k.value">
                                                <option :value="k.value" x-text="k.label"></option>
                                            </template>
                                        </select>
                                    </label>
                                @else
                                    <input type="hidden" :name="`baris[${i}][kategori]`" value="">
                                @endif
                                <label class="block">
                                    <span class="label-form">Ukuran</span>
                                    <select x-model="b.ukuran" :name="`baris[${i}][ukuran]`" class="kotak-input"
                                            @change="ikutiPanduan(b.kategori)">
                                        <template x-for="u in ukuranKategori(b.kategori)" :key="u">
                                            <option :value="u" x-text="u"></option>
                                        </template>
                                    </select>
                                </label>
                                <template x-if="opsiPemain">
                                    <label class="block">
                                        <span class="label-form" x-text="opsiPemain.nama"></span>
                                        <select x-model.number="b.model" :name="`baris[${i}][model]`" class="kotak-input">
                                            <template x-for="v in opsiPemain.values" :key="v.id">
                                                <option :value="v.id" :selected="v.id === b.model"
                                                        x-text="v.label + (v.harga > 0 ? ' (+' + rp(v.harga) + ')' : '')"></option>
                                            </template>
                                        </select>
                                    </label>
                                </template>
                                <div class="min-w-0">
                                    <span class="label-form" aria-hidden="true">Jumlah</span>
                                    <x-jumlah x-model.number="b.jumlah" ::name="`baris[${i}][jumlah]`" max="5000" />
                                </div>
                            </div>

                            <div class="mt-3 flex items-center justify-between gap-3 border-t border-line pt-3 text-sm">
                                <p class="tabular text-muted">
                                    <span x-text="rp(hargaPotong(b))"></span> / pcs
                                    <span x-show="(b.jumlah || 0) > 1">
                                        &times; <span x-text="b.jumlah"></span> =
                                        <span class="font-medium text-ink" x-text="rp(hargaPotong(b) * (b.jumlah || 0))"></span>
                                    </span>
                                </p>
                                <button type="button" @click="hapusBaris(i)" class="tautan text-xs text-merah">
                                    Hapus baris
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" @click="tambahBaris()" class="tombol tombol-garis text-sm">
                        + Tambah baris
                    </button>
                    <button type="button" @click="tempelBuka = !tempelBuka" class="tautan text-sm">
                        Tempel dari Excel
                    </button>
                    <p class="ml-auto text-sm" :class="qty < moq ? 'font-medium text-merah' : 'text-muted'">
                        <span x-text="qty"></span> pcs
                        <span x-show="qty < moq"> &middot; minimal <span x-text="moq"></span></span>
                    </p>
                </div>

                {{-- Tempel, bukan unggah berkas: pelanggan menyalin dari Excel atau
                     Sheets, hasilnya langsung terlihat dan masih bisa diperbaiki
                     sebelum disimpan. Berkas yang judul kolomnya diubah sendiri
                     selalu berakhir jadi keluhan ke admin. --}}
                <div x-show="tempelBuka" x-cloak class="kartu p-5">
                    <label for="tempel" class="label-form">Salin dari Excel, lalu tempel di sini</label>
                    <textarea id="tempel" rows="5" x-model="tempelIsi" class="kotak-input font-mono text-xs"
                              placeholder="ANDI&#9;10&#9;Dewasa L&#9;1&#10;BUDI&#9;7&#9;SMP M&#9;1&#10;&#9;&#9;SD L&#9;2"></textarea>
                    <p class="mt-2 text-xs leading-relaxed text-muted">
                        Urutan kolom: nama, nomor, ukuran, jumlah. Ukuran ditulis seperti
                        "SD L", "SMP M", atau "Dewasa XL".
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <button type="button" @click="prosesTempel()" class="tombol tombol-navy text-sm">
                            Masukkan ke daftar
                        </button>
                        <p x-show="tempelGagal" x-cloak class="text-sm text-merah" x-text="tempelGagal"></p>
                    </div>
                </div>

                <div class="kartu p-5">
                    <p class="font-semibold">Panduan ukuran</p>

                    @if ($panduan->isNotEmpty())
                        {{-- Satu tab per kategori (KEP-146): tiga tabel bertumpuk membuat
                             langkah ini terlalu penuh. Ini tab sungguhan karena panelnya
                             ada di halaman ini (beda dengan katalog, KEP-104). Tabnya
                             mengikuti pemain yang terakhir diubah, dan ukuran yang sudah
                             dipesan ditandai jumlahnya. --}}
                        @if ($panduan->count() > 1)
                            <div role="tablist" aria-label="Kategori panduan ukuran"
                                 class="mt-3 flex gap-1 rounded-full bg-navy-50 p-1"
                                 @keydown.right.prevent="geserPanduan(1)" @keydown.left.prevent="geserPanduan(-1)">
                                @foreach ($panduan as $nilai => $grup)
                                    <button type="button" role="tab" id="tab-panduan-{{ $loop->index }}"
                                            aria-controls="panel-panduan-{{ $loop->index }}"
                                            :aria-selected="(panduanAktif === @js((string) $nilai)).toString()"
                                            :tabindex="panduanAktif === @js((string) $nilai) ? 0 : -1"
                                            @click="panduanAktif = @js((string) $nilai)"
                                            class="flex-auto rounded-full px-3 py-1.5 text-sm font-semibold whitespace-nowrap transition-colors"
                                            :class="panduanAktif === @js((string) $nilai) ? 'bg-white text-navy shadow-sm' : 'text-muted hover:text-ink'">
                                        {{ $grup['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        @foreach ($panduan as $nilai => $grup)
                            <div class="mt-3 overflow-x-auto"
                                 @if ($panduan->count() > 1)
                                     role="tabpanel" id="panel-panduan-{{ $loop->index }}" aria-labelledby="tab-panduan-{{ $loop->index }}"
                                     x-show="panduanAktif === @js((string) $nilai)" @if ((string) $nilai !== $panduanAwal) x-cloak @endif
                                 @endif>
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-line text-left">
                                            <th class="py-2 pr-3 font-semibold">Ukuran</th>
                                            @foreach ($grup['kolom'] as $k)
                                                <th class="py-2 pr-3 font-semibold">{{ $k }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($grup['baris'] as $b)
                                            <tr class="border-b border-line transition-colors last:border-0"
                                                :class="dipesan(@js((string) $nilai), @js($b->ukuran)) > 0 && 'bg-navy-50'">
                                                <td class="py-2 pr-3 font-medium whitespace-nowrap">
                                                    {{ $b->ukuran }}
                                                    <span x-show="dipesan(@js((string) $nilai), @js($b->ukuran)) > 0" x-cloak
                                                          class="chip chip-navy tabular ml-1"
                                                          x-text="dipesan(@js((string) $nilai), @js($b->ukuran)) + ' pcs'"></span>
                                                </td>
                                                @foreach ($grup['kolom'] as $k)
                                                    <td class="tabular py-2 pr-3">{{ $b->nilai($k) !== null ? $b->nilai($k).' cm' : '-' }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                        {{-- Teks klien (KEP-146). --}}
                        <div class="mt-4 rounded-[10px] bg-paper px-3 py-2.5 text-xs leading-relaxed text-muted">
                            <p class="font-semibold text-ink">Catatan</p>
                            <p class="mt-0.5">Toleransi ukuran ±1–2 cm dapat terjadi selama proses produksi.</p>
                            <p>Untuk hasil yang lebih sesuai, bandingkan ukuran pada tabel dengan jersey yang biasa Anda gunakan.</p>
                        </div>
                    @else
                        <div class="mt-3 space-y-2 text-sm">
                            <template x-for="k in kategori" :key="k.value">
                                <p>
                                    <span class="font-medium" x-text="k.label"></span>:
                                    <span class="text-muted" x-text="k.ukuran.join(', ')"></span>
                                </p>
                            </template>
                        </div>
                    @endif
                </div>
            </section>

            {{-- LANGKAH 4 --------------------------------------------------- --}}
            <section x-show="langkah === 4" x-cloak class="space-y-4">
                <h2 class="text-xl font-bold">Cek pesanan</h2>

                <div class="kartu p-5">
                    <p class="font-semibold">{{ $produk->nama }}</p>

                    <dl class="mt-3 space-y-1.5 text-sm">
                        <template x-for="t in terpilih" :key="t.id">
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted" x-text="t.opsiNama"></dt>
                                <dd x-text="t.label"></dd>
                            </div>
                        </template>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Desain</dt>
                            <dd x-text="labelDesain"></dd>
                        </div>
                    </dl>

                    {{-- Tabel bernomor seperti gambar klien (KEP-133). Harga yang
                         ditulis harga per pcs baris itu; totalnya di Ringkasan
                         pesanan di bawah. --}}
                    <h3 class="mt-5 flex items-center gap-2 font-bold">
                        <x-ikon nama="daftar" ukuran="size-5" class="text-navy-400" /> Detail pesanan
                    </h3>
                    <div class="mt-3 overflow-x-auto">
                        <table class="tabel tabel-rapat min-w-[30rem] text-sm">
                            <thead>
                                <tr>
                                    <th class="w-10">No.</th>
                                    <th>Nama / Nomor</th>
                                    <th>Ukuran</th>
                                    <th class="text-right">Jumlah</th>
                                    <th class="text-right">Harga</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(b, i) in baris" :key="i">
                                    <tr>
                                        <td class="tabular text-muted" x-text="i + 1"></td>
                                        <td class="font-medium">
                                            <span x-text="b.nama_punggung || 'Tanpa nama'"></span><span class="tabular" x-show="b.nomor" x-text="' #' + b.nomor"></span>
                                        </td>
                                        <td x-text="labelUkuran(b)"></td>
                                        <td class="tabular text-right" x-text="(b.jumlah || 0) + ' pcs'"></td>
                                        <td class="tabular text-right" x-text="rp(hargaPotong(b))"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <p class="mt-3 flex items-start gap-2 rounded-[10px] bg-biru-50 px-3 py-2.5 text-sm">
                        <x-ikon nama="bantuan" ukuran="size-4" class="mt-0.5 shrink-0 text-biru-700" />
                        Pastikan detail pesanan sudah benar sebelum lanjut ke pembayaran.
                    </p>

                    {{-- Teks klien (KEP-144). --}}
                    <p class="mt-3 text-xs leading-relaxed text-muted">
                        Setelah pesanan dibuat, Anda dapat mengakses halaman khusus untuk melihat rincian tagihan, menyetujui desain (mockup), dan memantau progres pengerjaan pesanan.
                    </p>
                </div>
            </section>

            {{-- Navigasi langkah --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <button type="button" @click="mundur()" x-show="langkah > 1" x-cloak
                        class="tombol tombol-garis">Kembali</button>

                <button type="button" @click="maju()" x-show="langkah < 4"
                        class="tombol tombol-navy ml-auto" :disabled="!bisaLanjut"
                        data-izinkan-ganda>Lanjut</button>

                {{-- Dua pilihan di langkah terakhir (KEP-091): "Pesan sekarang"
                     langsung ke data pemesan, atau simpan dulu di keranjang. --}}
                <div x-show="langkah === 4" x-cloak class="kelompok-tombol ml-auto">
                    <button type="submit" name="lanjut" value="keranjang" class="tombol tombol-garis"
                            :disabled="qty < moq" data-teks-proses="Menyimpan...">
                        <x-ikon nama="tambah" ukuran="size-4" />
                        Masukkan ke keranjang
                    </button>
                    <button type="submit" name="lanjut" value="checkout" class="tombol tombol-utama"
                            :disabled="qty < moq" data-teks-proses="Menyimpan...">
                        Pesan sekarang
                    </button>
                </div>
            </div>
        </div>

        {{-- Ringkasan: menempel di layar lebar, dan di HP ikut mengalir di bawah. --}}
        {{-- Di tablet ringkasan dan kotak bantuan berdampingan; ditumpuk selebar
             layar keduanya hanya berisi beberapa baris dengan ruang kosong lebar. --}}
        <aside class="mt-6 md:grid md:grid-cols-2 md:items-start md:gap-4 lg:sticky lg:top-24 lg:mt-0 lg:block">

            <div class="kartu p-5">
                <p class="font-semibold">Ringkasan pesanan</p>

                <dl class="mt-3 space-y-1.5 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted">Jumlah</dt>
                        <dd class="tabular"><span x-text="qty"></span> pcs</dd>
                    </div>
                    <template x-for="k in komponenPerPcs" :key="k.id">
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted" x-text="k.label"></dt>
                            <dd class="tabular" x-text="'+ ' + rp(k.harga) + ' / pcs'"></dd>
                        </div>
                    </template>
                    <template x-for="k in komponenPerItem" :key="k.id">
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted" x-text="k.label"></dt>
                            <dd class="tabular" x-text="rp(k.harga)"></dd>
                        </div>
                    </template>
                </dl>

                {{-- Potongan borongan (KEP-050) ditampilkan sebagai baris tersendiri,
                     bukan disembunyikan ke dalam harga per potong: pelanggan yang
                     tahu potongannya justru terdorong menambah jumlah. --}}
                <template x-if="potongan > 0">
                    <div class="mt-3 flex justify-between gap-3 border-t border-line pt-3 text-sm">
                        <span class="text-muted">
                            Potongan <span x-text="diskonMinQty"></span> pcs ke atas
                        </span>
                        <span class="tabular font-medium text-ok" x-text="'− ' + rp(potongan)"></span>
                    </div>
                </template>

                <div class="mt-4 border-t border-line pt-4">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-sm text-muted">Perkiraan total</span>
                        <span class="text-xl font-bold tabular" x-text="rp(total)"></span>
                    </div>
                    <p class="mt-1 text-xs text-muted">
                        Pembayaran DP sebesar {{ (int) setting('dp_minimum_persen', 50) }}% (<span x-text="rp(dp)"></span>). Sisa pembayaran dilunasi sebelum pesanan diambil.
                    </p>
                </div>

                <p x-show="qty < moq" x-cloak class="mt-3 text-xs text-merah">
                    Minimal pemesanan {{ $produk->moq }} pcs.
                </p>
            </div>

            {{-- WhatsApp di sini BANTUAN, bukan jalur pemesanan (KEP-040).
                 Pesanan tetap dibuat di web supaya punya kode, tagihan, dan
                 halaman lacak; tanpa itu produksi kehilangan sumber datanya. --}}
            <div class="kartu mt-4 p-5 md:mt-0 lg:mt-4">
                <p class="font-semibold">Butuh bantuan?</p>
                <p class="mt-1 text-sm leading-relaxed text-muted">
                    Masih ragu menentukan bahan atau ukuran? Konsultasikan dengan admin, lalu lanjutkan pemesanan melalui website.
                </p>
                <a href="{{ tautan_wa('Halo, saya mau tanya soal pesanan '.$produk->nama) }}"
                   target="_blank" rel="noopener"
                   class="tombol tombol-garis mt-3 w-full justify-center">
                    Tanya lewat WhatsApp
                </a>
            </div>
        </aside>
    </form>
</div>

@push('skrip')
<script nonce="{{ Vite::cspNonce() }}">
    function pesanKustom(cfg) {
        return {
            ...cfg,
            langkah: 1,
            tertinggi: 1,
            pilihan: Object.fromEntries(
                cfg.opsi.map(o => [o.id, o.wajib ? (o.values[0]?.id ?? '') : ''])
            ),
            baris: [],
            desainSumber: 'katalog',
            catatanDesain: '',
            tempelBuka: false,
            tempelIsi: '',
            tempelGagal: '',

            init() {
                this.tambahBaris();
            },

            rp(n) {
                return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n || 0));
            },

            /** Nilai yang sedang dipilih untuk satu opsi, kalau ada. */
            nilaiTerpilih(opsiId) {
                const dipilih = this.pilihan[opsiId];
                if (!dipilih) return null;

                return this.opsi.find(o => o.id === opsiId)?.values.find(v => v.id === dipilih) ?? null;
            },

            /** Kamus bahan (KEP-072): deskripsi nilai yang sedang dipilih untuk satu opsi, kalau ada. */
            deskripsiTerpilih(opsiId) {
                return this.nilaiTerpilih(opsiId)?.deskripsi ?? '';
            },

            ke(n) {
                if (n <= this.tertinggi) this.langkah = n;
                this.gulirPenanda();
            },

            maju() {
                if (!this.bisaLanjut) return;
                this.langkah = Math.min(4, this.langkah + 1);
                this.tertinggi = Math.max(this.tertinggi, this.langkah);
                window.scrollTo({ top: 0, behavior: 'smooth' });
                this.gulirPenanda();
            },

            mundur() {
                this.langkah = Math.max(1, this.langkah - 1);
                window.scrollTo({ top: 0, behavior: 'smooth' });
                this.gulirPenanda();
            },

            /*
             * Penanda langkah bisa digeser (overflow-x-auto, KEP-071): di HP
             * sempit, langkah 3 dan 4 mulai tersembunyi di luar layar. Tanpa
             * ini, label yang tersisa terlihat masih di langkah lama padahal
             * isinya sudah pindah -- pelanggan kehilangan jejak sedang di
             * langkah mana.
             */
            gulirPenanda() {
                // $refs, bukan $el: dipanggil dari dalam maju()/mundur() yang
                // dipicu @click tombol Lanjut/Kembali, $el di sini adalah
                // tombolnya sendiri (ikut konteks direktif yang memanggilnya),
                // bukan akar komponen -- querySelector dari situ tidak akan
                // pernah menemukan <ol> yang posisinya di luar tombol.
                this.$nextTick(() => {
                    this.$refs.penanda.querySelector(`button[data-langkah="${this.langkah}"]`)
                        ?.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'instant' });
                });
            },

            get bisaLanjut() {
                // Langkah 3 adalah satu-satunya yang benar-benar bisa salah:
                // jumlahnya kurang dari minimum pemesanan.
                if (this.langkah === 3) return this.qty >= this.moq;
                return true;
            },

            // ---------------------------------------------------- daftar ukuran

            ukuranKategori(nilai) {
                return this.kategori.find(k => k.value === nilai)?.ukuran ?? [];
            },

            /** Tab panduan ukuran mengikuti kategori pemain yang terakhir diubah (KEP-146). */
            ikutiPanduan(kategori) {
                if (this.panduanKategori.includes(kategori ?? '')) this.panduanAktif = kategori ?? '';
            },

            /** Panah kiri/kanan di deretan tab, pola tab yang lazim untuk papan ketik. */
            geserPanduan(arah) {
                const n = this.panduanKategori.length;
                const i = this.panduanKategori.indexOf(this.panduanAktif);
                this.panduanAktif = this.panduanKategori[(i + arah + n) % n];
                this.$nextTick(() => this.$root.querySelector('[role="tab"][aria-selected="true"]')?.focus());
            },

            /** Jumlah potong yang sudah dipesan untuk satu ukuran, untuk penanda di panduan. */
            dipesan(kategori, ukuran) {
                return this.baris
                    .filter(b => (b.kategori ?? '') === kategori && b.ukuran === ukuran)
                    .reduce((n, b) => n + (parseInt(b.jumlah, 10) || 0), 0);
            },

            betulkanUkuran(b) {
                const tersedia = this.ukuranKategori(b.kategori);
                if (!tersedia.includes(b.ukuran)) b.ukuran = tersedia[0] ?? '';
            },

            tambahBaris() {
                const k = this.kategori[this.kategori.length - 1];

                this.baris.push({
                    nama_punggung: '', nomor: '',
                    kategori: k.value, ukuran: k.ukuran[1] ?? k.ukuran[0], jumlah: 1,
                    // Baris baru mengikuti model baris terakhir: tim biasanya
                    // mengetik yang berkerah dulu, lalu yang oblong.
                    model: this.baris.at(-1)?.model ?? this.opsiPemain?.values[0]?.id ?? null,
                });
            },

            hapusBaris(i) {
                this.baris.splice(i, 1);
                if (this.baris.length === 0) this.tambahBaris();
            },

            labelUkuran(b) {
                if (!b.kategori) return b.ukuran;

                const k = this.kategori.find(k => k.value === b.kategori);
                return k ? k.label + ' ' + b.ukuran : b.ukuran;
            },

            /*
             * Menempel dari Excel: kolom dipisah tab, koma, atau titik koma.
             * Sel ukuran dikenali dari isinya, bukan dari posisinya, supaya
             * daftar tanpa nomor punggung tetap terbaca benar.
             */
            prosesTempel() {
                const pola = /^(sd|smp|sma|dewasa)?\s*(s|m|l|xl|xxl|3xl|4xl|5xl)$/i;
                const hasil = [];
                let ditolak = 0;

                for (const baris of this.tempelIsi.split(/\r?\n/)) {
                    const sel = baris.split(/\t|;|,/).map(s => s.trim());
                    if (sel.every(s => s === '')) continue;

                    const iUkuran = sel.findIndex(s => pola.test(s));

                    if (iUkuran === -1) { ditolak++; continue; }

                    const [, kata, ukuran] = sel[iUkuran].match(pola);
                    const kategori = this.kategoriDari(kata);
                    const daftar = this.ukuranKategori(kategori);
                    const besar = ukuran.toUpperCase();

                    if (!daftar.includes(besar)) { ditolak++; continue; }

                    // Model per pemain (KEP-123) dikenali dari kata terakhir labelnya,
                    // misalnya "kerah" atau "oblong" di salah satu kolom.
                    const modelTempel = this.opsiPemain?.values.find(v =>
                        sel.some(s => s.toLowerCase().includes(v.label.toLowerCase().split(' ').pop())));

                    hasil.push({
                        nama_punggung: sel[0] && iUkuran > 0 ? sel[0] : '',
                        nomor: iUkuran > 1 && /^\d+$/.test(sel[1]) ? sel[1] : '',
                        kategori,
                        ukuran: besar,
                        jumlah: Math.max(1, parseInt(sel[iUkuran + 1], 10) || 1),
                        model: modelTempel?.id ?? hasil.at(-1)?.model ?? this.baris.at(-1)?.model ?? this.opsiPemain?.values[0]?.id ?? null,
                    });
                }

                if (hasil.length === 0) {
                    this.tempelGagal = 'Tidak ada baris yang terbaca. Pastikan ada kolom ukuran, misalnya "SD L".';
                    return;
                }

                // Baris kosong bawaan dibuang supaya tidak ikut terhitung.
                const adaIsi = this.baris.filter(b => b.nama_punggung || b.nomor);

                this.baris = [...adaIsi, ...hasil];
                this.tempelIsi = '';
                this.tempelBuka = false;
                this.tempelGagal = ditolak > 0 ? ditolak + ' baris tidak terbaca dan dilewati.' : '';
            },

            kategoriDari(kata) {
                // Produk tanpa kategori umur hanya punya satu pilihan ukuran
                // generik (value kosong); kata apa pun yang tertulis di depan
                // ukuran tidak relevan buat produk seperti ini.
                const nilaiTersedia = this.kategori.map(k => k.value);
                if (nilaiTersedia.length === 1) return nilaiTersedia[0];

                const k = (kata || '').toLowerCase();
                if (k === 'sd') return 'SD';
                if (k === 'smp') return 'SMP';
                return 'DEWASA';
            },

            // ---------------------------------------------------------- harga

            get terpilih() {
                return this.opsi.flatMap(o => {
                    const v = o.values.find(v => v.id === Number(this.pilihan[o.id]));
                    return v ? [{ ...v, opsiNama: o.nama }] : [];
                });
            },

            // ------------------------------------------- gambar pola (KEP-106)
            // Dibaca dari label pilihan, karena opsi tiap produk diisi pemilik
            // toko sendiri: "Model Baju: Pakai Kerah", "Kerah: V-neck", dst.

            get polaLeher() {
                const modelPertama = this.opsiPemain?.values.find(v => v.id === Number(this.baris[0]?.model));
                for (const v of [...(modelPertama ? [modelPertama] : []), ...this.terpilih]) {
                    const l = v.label.toLowerCase();
                    if (/tanpa|tidak/.test(l)) continue;
                    if (/v-?neck|leher v/.test(l)) return 'v';
                    if (/kerah|polo/.test(l)) return 'kerah';
                    if (/o-?neck|oblong|bulat/.test(l)) return 'bulat';
                }
                return this.leherBawaan;
            },

            get polaLenganPanjang() {
                return this.terpilih.some(v => /lengan/i.test(v.opsiNama) && /panjang/i.test(v.label));
            },

            get polaSaku() {
                return this.terpilih.some(v => /saku/i.test(v.opsiNama) && !/tanpa|tidak/i.test(v.label));
            },

            get polaCelana() {
                return this.terpilih.some(v => /celana/i.test(v.opsiNama) && !/tanpa|tidak/i.test(v.label));
            },

            get bahanId() {
                // Bahan adalah opsi yang nilainya muncul di matriks harga.
                const v = this.terpilih.find(v => this.harga.some(h => h.value_id === v.id));
                return v ? v.id : 0;
            },

            get komponenPerPcs() {
                return this.terpilih
                    .filter(v => v.basis === 'PER_PCS' && v.harga !== 0 && v.id !== this.bahanId)
                    .map(v => ({ id: v.id, label: v.opsiNama + ': ' + v.label, harga: v.harga }));
            },

            get komponenPerItem() {
                return this.terpilih.filter(v => v.basis === 'PER_ITEM' && v.harga !== 0)
                    .map(v => ({ id: v.id, label: v.label, harga: v.harga }));
            },

            get labelDesain() {
                return { katalog: 'Pilih dari katalog', sendiri: 'Kirim desain sendiri, lewat WhatsApp', admin: 'Dibantu admin' }[this.desainSumber];
            },

            /*
             * Urutan pencarian sel harga sama persis dengan Product::hargaPerPotong
             * di server: dari yang paling khusus ke yang paling umum.
             */
            hargaDasar(kategori) {
                const cari = (kat, val) => this.harga.find(h => h.kategori === kat && h.value_id === val);
                const h = cari(kategori, this.bahanId) ?? cari('', this.bahanId)
                    ?? cari(kategori, 0) ?? cari('', 0);

                return h ? h.harga : 0;
            },

            hargaPotong(b) {
                return this.hargaDasar(b.kategori)
                    + this.komponenPerPcs.reduce((s, k) => s + k.harga, 0)
                    + (this.tambahanUkuran[b.ukuran] ?? 0)
                    + this.hargaModel(b);
            },

            /** Tambahan model baju per pemain (KEP-123), sama dengan PriceCalculator. */
            hargaModel(b) {
                if (!this.opsiPemain) return 0;

                return this.opsiPemain.values.find(v => v.id === Number(b.model))?.harga ?? 0;
            },

            get qty() {
                return this.baris.reduce((s, b) => s + (parseInt(b.jumlah, 10) || 0), 0);
            },

            get hargaPenuh() {
                return this.baris.reduce((s, b) => s + this.hargaPotong(b) * (parseInt(b.jumlah, 10) || 0), 0)
                    + this.komponenPerItem.reduce((s, k) => s + k.harga, 0);
            },

            // Tarifnya tetap, tidak berlaku kelipatan: 24 pcs dipotong dengan
            // tarif yang sama dengan 12 pcs.
            get potongan() {
                if (this.diskonMinQty < 1 || this.diskonPerPcs < 1) return 0;

                return this.qty >= this.diskonMinQty ? this.qty * this.diskonPerPcs : 0;
            },

            get total() {
                return this.hargaPenuh - this.potongan;
            },

            // Uang muka dihitung dari harga SEBELUM potongan (KEP-050): toko
            // memberi potongannya saat pelunasan.
            get dp() {
                return Math.ceil(this.hargaPenuh * this.dpPersen / 100);
            },
        };
    }
</script>
@endpush
@endsection
