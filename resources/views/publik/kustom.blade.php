@extends('layouts.publik')
@section('judul', 'Pesan '.$produk->nama)
@section('deskripsi', 'Atur bahan, model, ukuran, dan daftar pemain, lalu lihat harganya langsung.')
@section('subjudul', 'Pesan '.$produk->nama)
@section('subcatatan', 'Empat langkah: produk, desain, data pemain, cek pesanan.')

@section('isi')
@php
    /** @var \App\Models\Product $produk */
    $dataOpsi = $produk->options->map(fn ($o) => [
        'id' => $o->id,
        'nama' => $o->nama,
        'wajib' => $o->wajib,
        'values' => $o->values->map(fn ($v) => [
            'id' => $v->id,
            'label' => $v->label,
            'harga' => $v->tambahan_harga,
            'basis' => $v->basis->value,
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

    // Panduan ukuran dalam sentimeter, dikelompokkan per kategori.
    $panduan = $produk->sizeCharts->groupBy(fn ($b) => $b->kategori?->label() ?? 'Semua ukuran');

    $dataTambahan = $produk->sizeSurcharges->mapWithKeys(fn ($t) => [$t->ukuran => $t->tambahan_harga]);

    $langkah = ['Pilih Produk', 'Desain & Detail', 'Data Pemain', 'Cek Pesanan'];
@endphp

<div class="mx-auto max-w-6xl px-5 py-6"
     x-data="pesanKustom({
        opsi: @js($dataOpsi),
        harga: @js($dataHarga),
        kategori: @js($dataKategori),
        tambahanUkuran: @js($dataTambahan),
        moq: {{ $produk->moq }},
        dpPersen: {{ (int) setting('dp_minimum_persen', 50) }},
     })">

    <nav class="text-sm text-muted" aria-label="Remah roti">
        <a href="{{ route('katalog') }}" class="tautan">Katalog</a>
        <span class="mx-2 text-line-strong">/</span>
        <a href="{{ route('produk', $produk) }}" class="tautan">{{ $produk->nama }}</a>
        <span class="mx-2 text-line-strong">/</span>
        <span class="text-ink">Buat pesanan</span>
    </nav>

    {{-- Penanda langkah. Langkah yang sudah dilewati bisa diklik untuk mundur:
         memperbaiki bahan tidak boleh berarti mengetik ulang daftar pemain. --}}
    <ol class="mt-5 flex items-center gap-2 overflow-x-auto pb-1 text-sm">
        @foreach ($langkah as $i => $nama)
            <li class="flex shrink-0 items-center gap-2">
                <button type="button" @click="ke({{ $i + 1 }})" :disabled="{{ $i + 1 }} > tertinggi"
                        class="flex items-center gap-2 rounded-full py-1 pr-3 pl-1 transition-colors disabled:cursor-not-allowed"
                        :class="langkah === {{ $i + 1 }} ? 'bg-navy text-white' : ({{ $i + 1 }} <= tertinggi ? 'text-ink hover:bg-navy-50' : 'text-muted')">
                    <span class="flex size-6 items-center justify-center rounded-full text-xs font-bold"
                          :class="langkah === {{ $i + 1 }} ? 'bg-white text-navy' : 'bg-navy-100 text-navy-800'">{{ $i + 1 }}</span>
                    <span class="whitespace-nowrap">{{ $nama }}</span>
                </button>
                @unless ($loop->last)
                    <span class="h-px w-6 bg-line-strong" aria-hidden="true"></span>
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

                <div class="kartu p-5">
                    <p class="font-semibold">Yang akan dipesan</p>
                    <p class="mt-1 text-sm text-muted">
                        Minimal {{ $produk->moq }} pcs. Harga mengikuti bahan, model, dan ukuran yang dipilih.
                    </p>

                    @if ($produkLain->isNotEmpty())
                        <p class="mt-5 text-sm font-medium">Mau memesan yang lain?</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($produkLain as $lain)
                                <a href="{{ route('kustom.buat', $lain) }}"
                                   class="rounded-[8px] border border-line px-3 py-2 text-sm hover:border-navy hover:bg-navy-50">
                                    {{ $lain->nama }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            {{-- LANGKAH 2 --------------------------------------------------- --}}
            <section x-show="langkah === 2" x-cloak class="space-y-4">
                <h2 class="text-xl font-bold">Desain & detail</h2>

                @foreach ($produk->options as $opsi)
                    <div class="kartu p-5">
                        <p class="font-semibold">
                            {{ $opsi->nama }}
                            @unless ($opsi->wajib)
                                <span class="text-sm font-normal text-muted">(opsional)</span>
                            @endunless
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($opsi->values as $v)
                                <button type="button" @click="pilihan[{{ $opsi->id }}] = {{ $v->id }}"
                                        class="rounded-[8px] border px-3 py-2 text-left text-sm transition-colors"
                                        :class="pilihan[{{ $opsi->id }}] === {{ $v->id }}
                                            ? 'border-navy bg-navy-50 font-medium text-navy-800'
                                            : 'border-line hover:border-navy-300'">
                                    {{ $v->label }}
                                    @if ($v->tambahan_harga !== 0)
                                        <span class="block text-xs text-muted">
                                            + {{ rupiah($v->tambahan_harga) }} / pcs
                                        </span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                        <input type="hidden" name="opsi[{{ $opsi->id }}]" :value="pilihan[{{ $opsi->id }}] ?? ''">
                    </div>
                @endforeach

                <div class="kartu p-5">
                    <p class="font-semibold">Desain</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-3">
                        @foreach (['katalog' => 'Pilih dari katalog', 'sendiri' => 'Kirim desain sendiri', 'admin' => 'Dibantu admin'] as $nilai => $label)
                            <button type="button" @click="desainSumber = '{{ $nilai }}'"
                                    class="rounded-[8px] border px-3 py-2 text-sm transition-colors"
                                    :class="desainSumber === '{{ $nilai }}'
                                        ? 'border-navy bg-navy-50 font-medium text-navy-800'
                                        : 'border-line hover:border-navy-300'">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                    <input type="hidden" name="desain_sumber" :value="desainSumber">

                    <label for="catatan-desain" class="label-form mt-5">Permintaan desain (opsional)</label>
                    <textarea id="catatan-desain" name="catatan_desain" rows="3" class="kotak-input"
                              x-model="catatanDesain"
                              placeholder="Contoh: warna dasar navy, logo tim di dada kiri, nomor punggung putih"></textarea>
                    <p class="mt-2 text-xs leading-relaxed text-muted">
                        Gambar desainnya diunggah setelah pesanan dibuat, di halaman pesanan Anda.
                        Mockup dikirim untuk disetujui sebelum apa pun dicetak.
                    </p>
                </div>
            </section>

            {{-- LANGKAH 3 --------------------------------------------------- --}}
            <section x-show="langkah === 3" x-cloak class="space-y-4">
                <div>
                    <h2 class="text-xl font-bold">Data pemain</h2>
                    <p class="mt-1 text-sm leading-relaxed text-muted">
                        Jumlah pesanan dihitung dari daftar ini. Nama dan nomor boleh dikosongkan dulu,
                        dan masih bisa dilengkapi sampai desain Anda setujui.
                    </p>
                </div>

                {{-- Satu kartu per baris, bukan tabel: di HP tabel lima kolom
                     menyisakan kotak selebar dua huruf, dan operator mengisinya
                     sambil menebak. Di layar lebar kartunya memanjang jadi satu
                     baris, jadi tampilannya tetap padat. --}}
                <div class="space-y-3">
                    <template x-for="(b, i) in baris" :key="i">
                        <div class="kartu p-4">
                            <div class="grid gap-3 {{ $pakaiKategori
                                ? 'sm:grid-cols-[minmax(0,1fr)_5.5rem_10rem_7rem_5.5rem]'
                                : 'sm:grid-cols-[minmax(0,1fr)_5.5rem_7rem_5.5rem]' }}">
                                <label class="block">
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
                                                @change="betulkanUkuran(b)" class="kotak-input">
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
                                    <select x-model="b.ukuran" :name="`baris[${i}][ukuran]`" class="kotak-input">
                                        <template x-for="u in ukuranKategori(b.kategori)" :key="u">
                                            <option :value="u" x-text="u"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="label-form">Jumlah</span>
                                    <input type="number" x-model.number="b.jumlah" :name="`baris[${i}][jumlah]`"
                                           min="1" max="5000" class="kotak-input tabular">
                                </label>
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
                        @foreach ($panduan as $namaKategori => $baris)
                            @php $kolom = array_keys($baris->first()->ukuran_cm); @endphp
                            <div class="mt-3 overflow-x-auto">
                                @if ($panduan->count() > 1)
                                    <p class="mb-1 text-sm font-medium">{{ $namaKategori }}</p>
                                @endif
                                <table class="w-full min-w-72 text-sm">
                                    <thead>
                                        <tr class="border-b border-line text-left">
                                            <th class="py-1.5 pr-3 font-medium">Ukuran</th>
                                            @foreach ($kolom as $k)
                                                <th class="py-1.5 pr-3 font-medium">{{ $k }} (cm)</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($baris as $b)
                                            <tr class="border-b border-line last:border-0">
                                                <td class="py-1.5 pr-3 font-medium">{{ $b->ukuran }}</td>
                                                @foreach ($kolom as $k)
                                                    <td class="tabular py-1.5 pr-3">{{ $b->ukuran_cm[$k] ?? '-' }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                        <p class="mt-2 text-xs text-muted">Ukuran bisa berbeda 1-2 cm karena proses produksi.</p>
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
                    <p class="mt-3 text-xs leading-relaxed text-muted">
                        Ragu ukurannya? Tanyakan lewat tombol bantuan, atau datang ke toko untuk mencoba
                        contoh ukurannya sebelum dicetak.
                    </p>
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

                    <table class="mt-4 w-full text-sm">
                        <tbody>
                            <template x-for="(b, i) in baris" :key="i">
                                <tr class="border-t border-line">
                                    <td class="py-1.5">
                                        <span x-text="b.nama_punggung || 'Tanpa nama'"></span>
                                        <span class="text-muted" x-show="b.nomor" x-text="' #' + b.nomor"></span>
                                    </td>
                                    <td class="py-1.5 text-muted" x-text="labelUkuran(b)"></td>
                                    <td class="py-1.5 text-right tabular" x-text="b.jumlah + ' pcs'"></td>
                                    <td class="py-1.5 text-right tabular" x-text="rp(hargaPotong(b) * (b.jumlah || 0))"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <p class="mt-4 text-xs leading-relaxed text-muted">
                        Setelah pesanan dibuat, Anda mendapat halaman pesanan sendiri berisi tagihan,
                        mockup untuk disetujui, dan progres pengerjaannya.
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

                <button type="submit" x-show="langkah === 4" x-cloak
                        class="tombol tombol-navy ml-auto" :disabled="qty < moq"
                        data-teks-proses="Menyimpan...">
                    Masukkan ke keranjang
                </button>
            </div>
        </div>

        {{-- Ringkasan: menempel di layar lebar, dan di HP ikut mengalir di bawah. --}}
        <aside class="mt-6 lg:sticky lg:top-24 lg:mt-0">
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

                <div class="mt-4 border-t border-line pt-4">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-sm text-muted">Perkiraan total</span>
                        <span class="text-xl font-bold tabular" x-text="rp(total)"></span>
                    </div>
                    <p class="mt-1 text-xs text-muted">
                        Uang muka <span x-text="rp(dp)"></span> ({{ (int) setting('dp_minimum_persen', 50) }}%),
                        sisanya dilunasi sebelum barang diambil.
                    </p>
                </div>

                <p x-show="qty < moq" x-cloak class="mt-3 text-xs text-merah">
                    Minimal pemesanan {{ $produk->moq }} pcs.
                </p>
            </div>

            {{-- WhatsApp di sini BANTUAN, bukan jalur pemesanan (KEP-040).
                 Pesanan tetap dibuat di web supaya punya kode, tagihan, dan
                 halaman lacak; tanpa itu produksi kehilangan sumber datanya. --}}
            <div class="kartu mt-4 p-5">
                <p class="font-semibold">Butuh bantuan?</p>
                <p class="mt-1 text-sm leading-relaxed text-muted">
                    Bingung memilih bahan atau ukuran? Tanya admin dulu, pesanannya tetap diselesaikan di sini.
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
<script>
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

            ke(n) {
                if (n <= this.tertinggi) this.langkah = n;
            },

            maju() {
                if (!this.bisaLanjut) return;
                this.langkah = Math.min(4, this.langkah + 1);
                this.tertinggi = Math.max(this.tertinggi, this.langkah);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },

            mundur() {
                this.langkah = Math.max(1, this.langkah - 1);
                window.scrollTo({ top: 0, behavior: 'smooth' });
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

            betulkanUkuran(b) {
                const tersedia = this.ukuranKategori(b.kategori);
                if (!tersedia.includes(b.ukuran)) b.ukuran = tersedia[0] ?? '';
            },

            tambahBaris() {
                const k = this.kategori[this.kategori.length - 1];

                this.baris.push({
                    nama_punggung: '', nomor: '',
                    kategori: k.value, ukuran: k.ukuran[1] ?? k.ukuran[0], jumlah: 1,
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

                    hasil.push({
                        nama_punggung: sel[0] && iUkuran > 0 ? sel[0] : '',
                        nomor: iUkuran > 1 && /^\d+$/.test(sel[1]) ? sel[1] : '',
                        kategori,
                        ukuran: besar,
                        jumlah: Math.max(1, parseInt(sel[iUkuran + 1], 10) || 1),
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
                return { katalog: 'Pilih dari katalog', sendiri: 'Kirim desain sendiri', admin: 'Dibantu admin' }[this.desainSumber];
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
                    + (this.tambahanUkuran[b.ukuran] ?? 0);
            },

            get qty() {
                return this.baris.reduce((s, b) => s + (parseInt(b.jumlah, 10) || 0), 0);
            },

            get total() {
                return this.baris.reduce((s, b) => s + this.hargaPotong(b) * (parseInt(b.jumlah, 10) || 0), 0)
                    + this.komponenPerItem.reduce((s, k) => s + k.harga, 0);
            },

            get dp() {
                return Math.ceil(this.total * this.dpPersen / 100);
            },
        };
    }
</script>
@endpush
@endsection
