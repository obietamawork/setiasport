@extends('layouts.publik')
@section('judul', $produk->nama)
@section('deskripsi', Str::limit(strip_tags($produk->deskripsi ?? $produk->nama), 150))

@section('isi')
@php
    $adaStok = $produk->punyaJalurReadyStock();
    $adaKustom = $produk->punyaJalurKustom();
    $jalurAwal = $adaStok ? 'stok' : 'kustom';

    $ukuran = collect(urutkan_ukuran($produk->variants->pluck('ukuran')));
    $warna = $produk->variants->pluck('warna')->filter()->unique()->sort()->values();
    $matriks = $ukuran->isNotEmpty() && $warna->isNotEmpty();
@endphp

<div class="mx-auto max-w-6xl px-5 py-8">

    <nav class="text-sm text-muted" aria-label="Remah roti">
        <a href="{{ route('katalog') }}" class="tautan">Katalog</a>
        @if ($produk->category->parent)
            <span class="mx-2 text-line-strong">/</span>
            <a href="{{ route('katalog', ['kategori' => $produk->category->parent->slug]) }}" class="tautan">
                {{ $produk->category->parent->nama }}
            </a>
        @endif
        <span class="mx-2 text-line-strong">/</span>
        <a href="{{ route('katalog', ['kategori' => $produk->category->slug]) }}" class="tautan">
            {{ $produk->category->nama }}
        </a>
    </nav>

    {{-- Di layar sempit urutannya gambar, kotak beli, lalu spesifikasi. Harga
         dan tombol beli tidak boleh terkubur di bawah tabel rujukan yang
         panjang; itu pertanyaan pertama pelanggan. --}}
    <div class="mt-6 grid gap-8 lg:grid-cols-[minmax(0,1fr)_390px] lg:items-start lg:gap-10">

        <div class="min-w-0 lg:col-start-1 lg:row-start-1">
            @if ($produk->images->isNotEmpty())
                <div x-data="{ aktif: 0 }">
                    <div class="kartu relative aspect-4/3 overflow-hidden bg-navy-50">
                        @foreach ($produk->images as $g)
                            <img x-show="aktif === {{ $loop->index }}" src="{{ $g->url }}"
                                 alt="{{ $g->alt ?? $produk->nama }}"
                                 width="{{ $g->lebar }}" height="{{ $g->tinggi }}"
                                 class="absolute inset-0 h-full w-full object-cover"
                                 {{ $loop->first ? '' : 'loading=lazy' }} decoding="async">
                        @endforeach
                    </div>

                    @if ($produk->images->count() > 1)
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($produk->images as $g)
                                <button type="button" @click="aktif = {{ $loop->index }}"
                                        :class="aktif === {{ $loop->index }} ? 'border-navy' : 'border-line hover:border-navy-300'"
                                        class="relative size-16 overflow-hidden rounded-[7px] border-2 bg-navy-50"
                                        aria-label="Lihat gambar {{ $loop->iteration }}">
                                    <img src="{{ $g->thumb_url }}" alt=""
                                         class="absolute inset-0 h-full w-full object-cover" loading="lazy">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                <div class="kartu flex aspect-4/3 items-center justify-center bg-navy-50">
                    <span class="text-6xl font-extrabold tracking-tight text-navy-300">
                        {{ Str::upper(Str::substr($produk->nama, 0, 2)) }}
                    </span>
                </div>
            @endif
        </div>

        <aside x-data="{ jalur: '{{ $jalurAwal }}' }"
               class="min-w-0 lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:sticky lg:top-6 lg:self-start">
            <div class="border border-line bg-field">
                <div class="border-b border-line p-5">
                    <h1 class="text-2xl leading-tight font-extrabold">{{ $produk->nama }}</h1>
                    <p class="mt-1 text-sm text-muted tabular">{{ $produk->sku }}</p>
                </div>

                @if ($adaStok && $adaKustom)
                    {{-- Produk hybrid: dua jalur benar-benar terpisah, termasuk harganya.
                         Harga kustom pada jumlah besar bisa LEBIH MURAH daripada stok
                         satuan, dan itu memang wajar. --}}
                    <div class="grid grid-cols-2 border-b border-line">
                        <button type="button" @click="jalur = 'stok'"
                                :class="jalur === 'stok' ? 'bg-navy text-white' : 'bg-field hover:bg-navy-50'"
                                class="px-4 py-3 text-sm font-semibold">
                            Ambil dari stok
                        </button>
                        <button type="button" @click="jalur = 'kustom'"
                                :class="jalur === 'kustom' ? 'bg-navy text-white' : 'bg-field hover:bg-navy-50'"
                                class="border-l border-line px-4 py-3 text-sm font-semibold">
                            Pesan kustom
                        </button>
                    </div>
                @endif

                @if ($adaStok)
                    <div x-show="jalur === 'stok'" class="space-y-4 p-5">
                        <div>
                            <p class="numeral text-4xl text-merah">{{ rupiah($produk->harga_dasar) }}</p>
                            <p class="mt-2 text-sm text-muted">Siap diambil besok pada jam buka.</p>
                        </div>

                        @php
                            $tersedia = $produk->variants->filter(fn ($v) => $v->stok_tersedia > 0);
                        @endphp

                        @if ($tersedia->isEmpty())
                            <p class="border-l-2 border-line-strong bg-paper p-3 text-sm leading-relaxed">
                                Semua ukuran sedang habis. Kami bisa membuatkannya lewat pesanan
                                kustom, atau hubungi toko untuk menanyakan stok masuk berikutnya.
                            </p>
                        @else
                            <form method="POST" action="{{ route('keranjang.tambah') }}" class="space-y-3">
                                @csrf

                                @if ($tersedia->count() === 1 && ! $matriks)
                                    <input type="hidden" name="variant_id" value="{{ $tersedia->first()->id }}">
                                    <p class="text-sm tabular">
                                        Stok tersedia: <strong>{{ $tersedia->first()->stok_tersedia }}</strong>
                                    </p>
                                @else
                                    <div>
                                        <label for="variant_id" class="label-form">Pilih ukuran &amp; warna</label>
                                        <select id="variant_id" name="variant_id" class="kotak-input" required>
                                            @foreach ($tersedia as $v)
                                                <option value="{{ $v->id }}">
                                                    {{ $v->label }} ({{ $v->stok_tersedia }} tersedia)
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <div>
                                    <label for="qty" class="label-form">Jumlah</label>
                                    <input type="number" id="qty" name="qty" value="1" min="1"
                                           class="kotak-input w-24 tabular text-right" required>
                                </div>

                                <button type="submit" class="tombol tombol-utama w-full">
                                    Masukkan ke keranjang
                                </button>
                            </form>
                        @endif
                    </div>
                @endif

                @if ($adaKustom)
                    @php $termurah = $produk->priceTiers->min('harga_satuan'); @endphp
                    <div x-show="jalur === 'kustom'" class="space-y-5 p-5">
                        <div>
                            <p class="numeral text-4xl text-merah">
                                {{ rupiah($termurah) }}<span class="text-base font-medium text-muted"> / pcs</span>
                            </p>
                            <p class="mt-2 text-sm text-muted">
                                Harga terendah, berlaku mulai
                                {{ $produk->priceTiers->firstWhere('harga_satuan', $termurah)?->min_qty }} pcs.
                            </p>
                        </div>

                        @if ($produk->priceTiers->isNotEmpty())
                            <div>
                                <p class="text-sm font-semibold">Harga per jumlah</p>
                                <ul class="mt-2 divide-y divide-line border border-line">
                                    @foreach ($produk->priceTiers as $t)
                                        <li class="flex items-baseline justify-between gap-4 px-3 py-2 text-sm">
                                            <span class="tabular">{{ $t->label }}</span>
                                            <span class="tabular font-semibold">{{ rupiah($t->harga_satuan) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted">Minimum pesan</dt>
                                <dd class="tabular font-semibold">{{ $produk->moq }} pcs</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted">Waktu pengerjaan</dt>
                                <dd class="tabular font-semibold">{{ $produk->lead_time_hari }} hari kerja</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted">Nama &amp; nomor punggung</dt>
                                <dd class="font-semibold">{{ $produk->roster_wajib ? 'Wajib diisi' : 'Opsional' }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted">Uang muka</dt>
                                <dd class="tabular font-semibold">{{ setting('dp_minimum_persen', 50) }}%</dd>
                            </div>
                        </dl>

                        <p class="border-l-2 border-merah bg-merah-50 p-3 text-sm leading-relaxed">
                            Waktu pengerjaan dihitung sejak Anda menyetujui mockup, bukan sejak
                            pesanan masuk. Sisa pembayaran dilunasi setelah barang selesai dikerjakan,
                            lewat transfer atau di kasir, lalu barang siap diambil.
                        </p>

                        @if ($produk->priceTiers->isEmpty())
                            <p class="border-l-2 border-line-strong bg-paper p-3 text-sm leading-relaxed">
                                Harga produk ini belum lengkap di sistem. Hubungi toko untuk penawaran.
                            </p>
                        @else
                            @include('komponen.konfigurator', ['produk' => $produk])
                        @endif
                    </div>
                @endif
            </div>
        </aside>

        <div class="min-w-0 space-y-10 lg:col-start-1 lg:row-start-2">
            @if ($produk->deskripsi)
                <div class="kolom-baca mt-8 leading-relaxed text-muted">{!! nl2br(e($produk->deskripsi)) !!}</div>
            @endif

            @if ($adaStok && $matriks)
                {{-- Matriks ukuran x warna dengan stok sebenarnya. Dropdown akan
                     menyembunyikan justru informasi yang paling menentukan
                     keputusan: ukuran mana yang masih ada. --}}
                <section class="mt-10">
                    <h2 class="text-xl font-extrabold">Stok per ukuran</h2>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-100 border-collapse border border-line bg-field text-sm">
                            <thead>
                                <tr>
                                    <th class="border border-line px-3 py-2 text-left font-semibold">Ukuran</th>
                                    @foreach ($warna as $w)
                                        <th class="border border-line px-3 py-2 text-left font-semibold">{{ $w }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ukuran as $u)
                                    <tr>
                                        <th class="border border-line px-3 py-2 text-left font-semibold">{{ $u }}</th>
                                        @foreach ($warna as $w)
                                            @php
                                                $v = $produk->variants->first(fn ($x) => $x->ukuran === $u && $x->warna === $w);
                                                $sisa = $v?->stok_tersedia ?? 0;
                                            @endphp
                                            <td class="border border-line px-3 py-2 tabular {{ $sisa === 0 ? 'bg-paper text-muted' : '' }}">
                                                @if (! $v)
                                                   
                                                @elseif ($sisa === 0)
                                                    Habis
                                                @else
                                                    {{ $sisa }}
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($adaKustom && $produk->options->isNotEmpty())
                <section class="mt-10">
                    <h2 class="text-xl font-extrabold">Pilihan untuk pesanan kustom</h2>
                    <dl class="mt-4 divide-y divide-line border border-line bg-field">
                        @foreach ($produk->options as $opsi)
                            <div class="grid gap-2 p-4 sm:grid-cols-[160px_1fr]">
                                <dt class="font-semibold">{{ $opsi->nama }}</dt>
                                <dd class="flex flex-wrap gap-x-5 gap-y-1.5 text-sm">
                                    @foreach ($opsi->values as $v)
                                        <span>
                                            {{ $v->label }}
                                            @if ($v->tambahan_harga !== 0)
                                                <span class="tabular text-muted">
                                                    {{ $v->tambahan_harga > 0 ? '+' : '−' }}{{ rupiah(abs($v->tambahan_harga)) }}
                                                    @if ($v->basis === \App\Enums\PriceBasis::PER_ITEM)
                                                        <span class="text-merah">sekali</span>
                                                    @else
                                                        / pcs
                                                    @endif
                                                </span>
                                            @endif
                                        </span>
                                    @endforeach
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="mt-3 text-sm text-muted">
                        Biaya bertanda <span class="text-merah">sekali</span> dikenakan satu kali
                        untuk seluruh pesanan, bukan per potong.
                    </p>
                </section>
            @endif
        </div>
    </div>
</div>
@endsection
