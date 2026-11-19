@extends('layouts.panel')
@section('judul', 'Stok barang')

@php
    $keadaan = fn ($v) => match (true) {
        $v->stok_tersedia <= 0 => 'habis',
        $v->stok_tersedia <= 3 => 'menipis',
        default => 'aman',
    };
    $chip = [
        'aman' => ['chip-ok', 'Ready stock'],
        'menipis' => ['chip-kuning', 'Stok menipis'],
        'habis' => ['chip-merah', 'Habis'],
        'sebagian' => ['chip-kuning', 'Sebagian menipis'],
    ];
    $adaSaringan = request()->filled('q') || $kategori || $status !== '';

    /*
     * Data untuk formulir barang masuk: semua produk ready stock beserta
     * variannya, bukan hanya yang sedang tersaring, supaya kiriman untuk
     * produk lain tetap bisa dicatat tanpa menghapus penyaring dulu.
     */
    $dataProduk = $semuaProduk->map(function ($p) {
        // Urutan varian sama dengan tabel: ukuran seperti orang membacanya, lalu warna.
        $urutan = urutkan_ukuran($p->variants->pluck('ukuran'));

        return [
            'id' => $p->id,
            'nama' => $p->nama,
            'varian' => $p->variants
                ->sortBy(fn ($v) => [array_search((string) $v->ukuran, $urutan, true) === false ? 999 : array_search((string) $v->ukuran, $urutan, true), (string) $v->warna])
                ->map(fn ($v) => ['id' => $v->id, 'label' => $v->label, 'sku' => $v->sku, 'stok' => $v->stok])
                ->values(),
        ];
    })->values();
    $produkLama = (int) old('produk_id');
@endphp

@section('isi')
<div x-data="barangMasuk(@js($dataProduk), {{ $produkLama ?: 'null' }}, {{ $errors->hasAny(['supplier', 'tanggal', 'harga_beli', 'baris', 'baris.*', 'baris.*.jumlah']) ? 'true' : 'false' }})">

<x-kop judul="Stok barang" ikon="stok" warna="jingga"
       catatan="Pantau, tambah, dan sesuaikan stok produk toko. Stok adalah jumlah di rak; yang bisa dijual sudah dikurangi pesanan.">
    <a href="{{ route('panel.stok.penyesuaian') }}" class="tombol tombol-garis">
        <x-ikon nama="muat-ulang" ukuran="size-4" />
        Penyesuaian stok
    </a>
    <button type="button" class="tombol tombol-navy" @click="buka()">
        <x-ikon nama="tambah" ukuran="size-4" />
        Barang masuk
    </button>
</x-kop>

<div class="kartu">
    <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line p-4 sm:p-5" data-saring-otomatis>
        <div class="w-full sm:w-52">
            <label for="saring-kategori" class="sr-only">Kategori</label>
            <select id="saring-kategori" name="kategori" class="kotak-input">
                <option value="">Semua kategori</option>
                @foreach ($pilihanKategori as $k)
                    <option value="{{ $k->id }}" @selected($kategori === $k->id)>{{ $k->nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-full sm:w-52">
            <label for="saring-status" class="sr-only">Status stok</label>
            <select id="saring-status" name="status" class="kotak-input">
                <option value="">Semua status</option>
                <option value="aman" @selected($status === 'aman')>Ready stock</option>
                <option value="menipis" @selected($status === 'menipis')>Stok menipis (sisa 1 sampai 3)</option>
                <option value="habis" @selected($status === 'habis')>Habis</option>
            </select>
        </div>
        <div class="relative min-w-0 flex-1 basis-56">
            <label for="cari-stok" class="sr-only">Cari produk</label>
            <x-ikon nama="cari" ukuran="size-4" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-navy-300" />
            <input type="search" id="cari-stok" name="q" value="{{ request('q') }}"
                   placeholder="Cari produk, SKU, atau varian&hellip;" class="kotak-input pl-9">
        </div>
        @if ($adaSaringan)
            <a href="{{ route('panel.stok.index') }}" class="tombol tombol-garis tombol-sm">
                <x-ikon nama="muat-ulang" ukuran="size-4" /> Reset
            </a>
        @endif
    </form>

    @if ($varian->isEmpty())
        <div class="p-8 text-center">
            <x-ikon nama="stok" ukuran="size-9" class="mx-auto text-navy-300" />
            <p class="mt-3 font-bold">{{ $adaSaringan ? 'Tidak ada varian yang cocok' : 'Belum ada barang ready stock' }}</p>
            <p class="mx-auto mt-2 max-w-sm text-sm text-muted">
                {{ $adaSaringan ? 'Coba kata kunci lain, atau tekan Reset.' : 'Produk berjalur Ready Stock muncul di sini beserta variannya.' }}
            </p>
        </div>
    @else
        {{-- Satu tbody per produk: barisnya bisa dibuka-tutup bersama tanpa
             memecah tabel. Saat ada penyaring, semuanya langsung terbuka. --}}
        <table class="tabel tabel-rapat">
            <thead>
                <tr>
                    <th class="w-8"><span class="sr-only">Buka</span></th>
                    <th>Produk / varian</th>
                    {{-- SKU menumpang di bawah nama di bawah 80rem (KEP-143). --}}
                    <th class="hidden xl:table-cell">SKU</th>
                    <th class="text-right">Stok</th>
                    <th class="hidden sm:table-cell">Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            @foreach ($varian as $grup)
                @php
                    $produk = $grup->first()->product;
                    $gambar = $produk->images->first();
                    $tunggal = $grup->count() === 1;
                    $keadaanGrup = $grup->map($keadaan);
                    $statusProduk = $keadaanGrup->every(fn ($k) => $k === 'habis') ? 'habis'
                        : ($keadaanGrup->contains(fn ($k) => $k !== 'aman') ? 'sebagian' : 'aman');
                @endphp
                <tbody x-data="{ terbuka: {{ $adaSaringan ? 'true' : 'false' }} }">
                    <tr class="bg-field">
                        <td>
                            @unless ($tunggal)
                                <button type="button" @click="terbuka = ! terbuka" class="tombol-ikon size-8 border-transparent"
                                        :aria-expanded="terbuka.toString()" aria-label="Tampilkan varian {{ $produk->nama }}">
                                    <x-ikon nama="lanjut" ukuran="size-4" x-bind:class="terbuka && 'rotate-90'" class="transition-transform" />
                                </button>
                            @endunless
                        </td>
                        <td>
                            <span class="flex items-center gap-3">
                                @if ($gambar)
                                    <img src="{{ $gambar->thumb_url }}" alt="" width="44" height="44" loading="lazy"
                                         class="hidden size-11 shrink-0 sm:block rounded-[8px] border border-line object-cover">
                                @else
                                    <span class="hidden size-11 shrink-0 items-center justify-center rounded-[8px] border border-dashed sm:flex border-line-strong text-navy-300">
                                        <x-ikon nama="gambar" ukuran="size-4" />
                                    </span>
                                @endif
                                <span class="min-w-0">
                                    <a href="{{ route('panel.produk.edit', $produk) }}" class="block font-bold text-ink hover:text-navy">{{ $produk->nama }}</a>
                                    <span class="text-xs text-muted">
                                        {{ $tunggal ? 'Varian: tunggal' : $grup->count().' varian' }}
                                        <span class="tabular hidden md:inline xl:hidden">· {{ $tunggal ? $grup->first()->sku : $produk->sku }}</span>
                                    </span>
                                </span>
                            </span>
                        </td>
                        <td class="hidden xl:table-cell">
                            <span class="chip chip-netral tabular border border-line">{{ $tunggal ? $grup->first()->sku : $produk->sku }}</span>
                        </td>
                        <td class="tabular text-right font-bold whitespace-nowrap">
                            {{ $grup->sum('stok') }} pcs
                            <span class="block text-xs font-semibold sm:hidden {{ ['aman' => 'text-ok', 'sebagian' => 'text-warn', 'habis' => 'text-merah-tua'][$statusProduk] }}">{{ $chip[$statusProduk][1] }}</span>
                            @if ($grup->sum('stok_dipesan') > 0)
                                <span class="block text-xs font-normal text-muted">{{ $grup->sum('stok_dipesan') }} dipesan</span>
                            @endif
                        </td>
                        <td class="hidden sm:table-cell"><span class="chip {{ $chip[$statusProduk][0] }}">{{ $chip[$statusProduk][1] }}</span></td>
                        <td>
                            <div class="flex items-center justify-end gap-1.5">
                                <button type="button" class="tombol tombol-garis tombol-sm" @click="buka({{ $produk->id }}, {{ $tunggal ? $grup->first()->id : 'null' }})">
                                    <x-ikon nama="tambah" ukuran="size-4" />
                                    <span class="sr-only sm:not-sr-only">Barang masuk</span>
                                </button>
                                <details class="relative hidden sm:block" x-data @click.outside="$el.removeAttribute('open')">
                                    <summary class="tombol-ikon" title="Aksi lain">
                                        <x-ikon nama="titik-tiga" ukuran="size-4" />
                                        <span class="sr-only">Aksi lain untuk {{ $produk->nama }}</span>
                                    </summary>
                                    <div class="menu-tarik menu-aksi-isi right-0 w-56">
                                        <a href="{{ route('panel.stok.penyesuaian', ['q' => $produk->nama]) }}"><x-ikon nama="muat-ulang" ukuran="size-4" class="text-navy-400" /> Sesuaikan stok</a>
                                        <a href="{{ route('panel.produk.edit', $produk) }}"><x-ikon nama="pensil" ukuran="size-4" class="text-navy-400" /> Ubah produk</a>
                                    </div>
                                </details>
                            </div>
                        </td>
                    </tr>
                    @unless ($tunggal)
                        @foreach ($grup as $v)
                            @php $k = $keadaan($v); @endphp
                            <tr x-show="terbuka" @unless ($adaSaringan) x-cloak @endunless class="bg-paper/60">
                                <td></td>
                                <td>
                                    <span class="flex items-center gap-2 pl-4 text-sm">
                                        <x-ikon nama="lanjut" ukuran="size-4" class="text-navy-300" />
                                        <span class="font-semibold">{{ $v->label }}</span>
                                        <span class="tabular hidden text-xs text-muted md:inline xl:hidden">{{ $v->sku }}</span>
                                    </span>
                                </td>
                                <td class="hidden xl:table-cell"><span class="chip chip-netral tabular border border-line">{{ $v->sku }}</span></td>
                                <td class="tabular text-right whitespace-nowrap">
                                    {{ $v->stok }} pcs
                                    <span class="block text-xs font-semibold sm:hidden {{ ['aman' => 'text-ok', 'menipis' => 'text-warn', 'habis' => 'text-merah-tua'][$k] }}">{{ $chip[$k][1] }}</span>
                                    @if ($v->stok_dipesan > 0)
                                        <span class="block text-xs text-muted">{{ $v->stok_dipesan }} dipesan</span>
                                    @endif
                                </td>
                                <td class="hidden sm:table-cell"><span class="chip {{ $chip[$k][0] }}">{{ $chip[$k][1] }}</span></td>
                                <td>
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" class="tombol tombol-garis tombol-sm" @click="buka({{ $produk->id }}, {{ $v->id }})">
                                            <x-ikon nama="tambah" ukuran="size-4" />
                                            <span class="sr-only sm:not-sr-only">Barang masuk</span>
                                        </button>
                                        <details class="relative hidden sm:block" x-data @click.outside="$el.removeAttribute('open')">
                                            <summary class="tombol-ikon" title="Aksi lain">
                                                <x-ikon nama="titik-tiga" ukuran="size-4" />
                                                <span class="sr-only">Aksi lain untuk {{ $v->sku }}</span>
                                            </summary>
                                            <div class="menu-tarik menu-aksi-isi right-0 w-56">
                                                <a href="{{ route('panel.stok.penyesuaian', ['q' => $v->sku]) }}"><x-ikon nama="muat-ulang" ukuran="size-4" class="text-navy-400" /> Sesuaikan stok</a>
                                            </div>
                                        </details>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @endunless
                </tbody>
            @endforeach
        </table>
    @endif
</div>

<div class="notis notis-info mt-5 text-sm leading-relaxed">
    <p class="font-semibold">Catatan</p>
    <ul class="mt-1 list-inside list-disc space-y-0.5">
        <li>Pakai <strong>Barang masuk</strong> untuk menambah stok dari kiriman supplier. Supplier, jumlah, dan harga belinya tercatat.</li>
        <li>Pakai <strong>Penyesuaian stok</strong> untuk koreksi: stok opname, barang rusak, atau selisih. Alasannya wajib ditulis.</li>
    </ul>
</div>

{{-- Formulir barang masuk (KEP-120). Satu kiriman bisa berisi beberapa
     varian; varian yang tidak dikirim cukup dikosongkan. --}}
<dialog x-ref="dialog" class="dialog w-[min(40rem,calc(100vw-2rem))]" aria-labelledby="judul-barang-masuk"
        @close="terbuka = false">
    <form method="POST" action="{{ route('panel.stok.masuk') }}" class="p-5 sm:p-6">
        @csrf
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 id="judul-barang-masuk" class="text-lg font-bold">Barang masuk</h2>
                <p class="mt-1 text-sm text-muted">Stok bertambah begitu disimpan.</p>
            </div>
            <button type="button" class="tombol-ikon border-transparent" @click="$refs.dialog.close()" aria-label="Tutup">
                <x-ikon nama="silang" ukuran="size-4" />
            </button>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="bm-produk" class="label-form">Produk</label>
                <select id="bm-produk" name="produk_id" x-model.number="produkId" class="kotak-input" required>
                    <option value="">Pilih produk</option>
                    <template x-for="p in produk" :key="p.id">
                        <option :value="p.id" x-text="p.nama" :selected="p.id === produkId"></option>
                    </template>
                </select>
            </div>

            <div class="sm:col-span-2" x-show="produkTerpilih" x-cloak>
                <p class="label-form">Jumlah yang datang</p>
                {{-- Produk berwarna-warni bisa punya dua puluh varian: daftarnya
                     digulir sendiri supaya kolom supplier tetap terlihat. --}}
                <div class="max-h-64 divide-y divide-line overflow-y-auto rounded-[10px] border border-line">
                    <template x-for="(v, i) in produkTerpilih?.varian ?? []" :key="v.id">
                        <label class="flex items-center gap-3 px-3 py-2">
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold" x-text="v.label"></span>
                                <span class="tabular text-xs text-muted" x-text="v.sku + ' · stok ' + v.stok"></span>
                            </span>
                            <input type="hidden" :name="`baris[${i}][variant_id]`" :value="v.id">
                            <input type="number" :name="`baris[${i}][jumlah]`" min="1" max="99999" inputmode="numeric"
                                   placeholder="0" class="kotak-input tabular w-24 text-right" :id="'bm-jumlah-' + v.id">
                        </label>
                    </template>
                </div>
                @error('baris') <p class="mt-1 text-sm text-merah-tua">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="bm-supplier" class="label-form">Supplier</label>
                <input type="text" id="bm-supplier" name="supplier" list="daftar-supplier" maxlength="100" required
                       value="{{ old('supplier') }}" class="kotak-input" placeholder="CV Sumber Kain">
                <datalist id="daftar-supplier">
                    @foreach ($supplier as $s)
                        <option value="{{ $s }}">
                    @endforeach
                </datalist>
            </div>
            <div>
                <label for="bm-tanggal" class="label-form">Tanggal datang</label>
                <input type="date" id="bm-tanggal" name="tanggal" required max="{{ now()->toDateString() }}"
                       value="{{ old('tanggal', now()->toDateString()) }}" class="kotak-input">
            </div>
            <div>
                <label for="bm-harga" class="label-form">Harga beli per pcs <span class="font-normal text-muted">(opsional)</span></label>
                <input type="number" id="bm-harga" name="harga_beli" min="0" inputmode="numeric"
                       value="{{ old('harga_beli') }}" class="kotak-input tabular" placeholder="45000">
            </div>
            <div>
                <label for="bm-catatan" class="label-form">Catatan <span class="font-normal text-muted">(opsional)</span></label>
                <input type="text" id="bm-catatan" name="catatan" maxlength="255"
                       value="{{ old('catatan') }}" class="kotak-input" placeholder="Nomor nota, kondisi barang">
            </div>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" class="tombol tombol-garis" @click="$refs.dialog.close()">Batal</button>
            <button type="submit" class="tombol tombol-navy" :disabled="! produkTerpilih">
                <x-ikon nama="centang" ukuran="size-4" /> Simpan barang masuk
            </button>
        </div>
    </form>
</dialog>
</div>
@endsection

@push('skrip')
<script nonce="{{ Vite::cspNonce() }}">
    document.addEventListener('alpine:init', () => {
        Alpine.data('barangMasuk', (produk, produkAwal, bukaAwal) => ({
            produk,
            produkId: produkAwal,
            terbuka: false,
            get produkTerpilih() {
                return this.produk.find((p) => p.id === this.produkId) ?? null;
            },
            init() {
                if (bukaAwal) this.$nextTick(() => this.buka(produkAwal));
            },
            buka(produkId = null, variantId = null) {
                if (produkId) this.produkId = produkId;
                this.$refs.dialog.showModal();
                this.terbuka = true;
                this.$nextTick(() => {
                    const sasaran = variantId ? document.getElementById('bm-jumlah-' + variantId) : null;
                    (sasaran ?? document.getElementById(this.produkId ? 'bm-supplier' : 'bm-produk'))?.focus();
                });
            },
        }));
    });
</script>
@endpush
