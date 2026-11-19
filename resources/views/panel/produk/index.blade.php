@extends('layouts.panel')
@section('judul', 'Produk')

@php
    use App\Enums\FulfillmentType;

    $adaSaringan = request()->filled('q') || $kategori || $stok !== '';

    // Tautan yang mempertahankan penyaring lain saat satu penyaring diganti.
    $tautan = fn (array $ubah) => route('panel.produk.index', array_filter(array_merge([
        'saring' => $saring, 'q' => request('q'), 'kategori' => $kategori, 'stok' => $stok,
    ], $ubah), fn ($v) => $v !== null && $v !== ''));

    /*
     * Warna kategori tetap per NAMA kategori (KEP-116), bukan acak per muat
     * halaman dan bukan per id: subkategori "Futsal" di bawah Jersey dan di
     * bawah Bola tertulis sama, jadi warnanya juga harus sama.
     */
    $warnaKategori = fn (string $nama) => ['chip-biru', 'chip-ungu', 'chip-hijau', 'chip-jingga', 'chip-pink', 'chip-kuning'][crc32(mb_strtolower($nama)) % 6];
@endphp

@section('isi')
<x-kop judul="Produk" ikon="produk" warna="ungu"
       catatan="Semua yang tayang di katalog. Harga, varian, dan gambarnya diatur di halaman masing-masing produk.">
    <a href="{{ route('panel.produk.create') }}" class="tombol tombol-navy">
        <x-ikon nama="tambah" ukuran="size-4" />
        <span class="sr-only sm:not-sr-only">Tambah produk</span>
    </a>
</x-kop>

{{-- "Belum lengkap" ditaruh sebagai tab, bukan sekadar peringatan di
     dashboard: memberi tahu ada sepuluh produk bermasalah tanpa menyediakan
     jalan melihatnya satu per satu hanya membuat orang menyerah. --}}
<nav class="segmen" aria-label="Status tayang">
    @foreach ([
        '' => ['Semua', null],
        'tayang' => ['Tayang', 'titik'],
        'sembunyi' => ['Disembunyikan', 'mata-coret'],
        'belum-lengkap' => ['Belum lengkap', 'peringatan'],
    ] as $nilai => [$label, $ikon])
        <a href="{{ $tautan(['saring' => $nilai]) }}" @if ($saring === $nilai) aria-current="page" @endif
           class="segmen-item segmen-pil {{ $saring === $nilai ? 'segmen-item-aktif' : '' }}">
            @if ($ikon === 'titik')
                <span class="size-2 rounded-full bg-ok" aria-hidden="true"></span>
            @elseif ($ikon)
                <x-ikon :nama="$ikon" ukuran="size-4" />
            @endif
            {{ $label }}
            <span class="tabular">({{ $jumlah[$nilai] ?? 0 }})</span>
        </a>
    @endforeach
</nav>

@if ($saring === 'belum-lengkap')
    <p class="kolom-baca mt-3 text-sm leading-relaxed text-muted">
        Produk di bawah ini tayang, tetapi belum punya gambar atau belum punya harga kustom.
        Tanpa gambar pelanggan jarang percaya; tanpa harga, ia harus bertanya ke admin dulu.
    </p>
@endif

{{-- Pilihan massal (KEP-116) dipegang Alpine: kotak centang di tiap baris
     mengisi daftar id, bilah aksinya muncul begitu ada yang dipilih. Kotak
     centangnya milik formulir massal lewat atribut form, jadi tabelnya tidak
     perlu dibungkus formulir. --}}
<div class="kartu mt-4" x-data="{ dipilih: [], aksi: '', semua: @js($produk->pluck('id')) }">
    <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line p-4 sm:p-5" data-saring-otomatis>
        <input type="hidden" name="saring" value="{{ $saring }}">

        <div class="relative min-w-0 flex-1 basis-60">
            <label for="cari-produk" class="sr-only">Cari produk</label>
            <x-ikon nama="cari" ukuran="size-4"
                    class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-navy-300" />
            <input type="search" id="cari-produk" name="q" value="{{ request('q') }}"
                   placeholder="Cari nama produk atau SKU&hellip;" class="kotak-input pl-9">
        </div>

        <div class="w-full sm:w-48">
            <label for="saring-kategori" class="label-form">Kategori</label>
            <select id="saring-kategori" name="kategori" class="kotak-input">
                <option value="">Semua kategori</option>
                @foreach ($pilihanKategori as $k)
                    <option value="{{ $k->id }}" @selected((int) $kategori === $k->id)>{{ $k->nama }}</option>
                @endforeach
            </select>
        </div>

        <div class="w-full sm:w-48">
            <label for="saring-stok" class="label-form">Status stok</label>
            <select id="saring-stok" name="stok" class="kotak-input">
                <option value="">Semua status</option>
                @foreach (FulfillmentType::cases() as $f)
                    <option value="{{ $f->value }}" @selected($stok === $f->value)>{{ $f->label() }}</option>
                @endforeach
                <option value="habis" @selected($stok === 'habis')>Stok habis</option>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="tombol tombol-navy tombol-sm">Saring</button>
            @if ($adaSaringan)
                <a href="{{ route('panel.produk.index', array_filter(['saring' => $saring])) }}" class="tombol tombol-garis tombol-sm">
                    <x-ikon nama="muat-ulang" ukuran="size-4" /> Reset
                </a>
            @endif
        </div>
    </form>

    <form id="form-massal" method="POST" action="{{ route('panel.produk.massal') }}"
          x-show="dipilih.length > 0" x-cloak
          class="flex flex-wrap items-center gap-2 border-b border-line bg-biru-50 px-4 py-3 sm:px-5">
        @csrf
        <span class="text-sm font-semibold"><span class="tabular" x-text="dipilih.length"></span> produk dipilih</span>
        <label for="aksi-massal" class="sr-only">Aksi untuk produk terpilih</label>
        <select id="aksi-massal" name="aksi" x-model="aksi" class="kotak-input w-auto" required>
            <option value="">Pilih aksi&hellip;</option>
            <option value="tayangkan">Tayangkan</option>
            <option value="sembunyikan">Sembunyikan</option>
            <option value="kategori">Pindahkan ke kategori&hellip;</option>
        </select>
        <label for="kategori-massal" class="sr-only">Kategori tujuan</label>
        <select id="kategori-massal" name="category_id" x-show="aksi === 'kategori'" x-cloak class="kotak-input w-auto"
                :required="aksi === 'kategori'">
            <option value="">Pilih kategori</option>
            @foreach ($pilihanKategori as $k)
                <option value="{{ $k->id }}">{{ $k->nama }}</option>
            @endforeach
        </select>
        <button type="submit" class="tombol tombol-navy tombol-sm">Terapkan</button>
        <button type="button" @click="dipilih = []" class="tombol tombol-halus tombol-sm">Batal pilih</button>
    </form>

    @if ($produk->isEmpty())
        <div class="p-8 text-center">
            <x-ikon nama="produk" ukuran="size-9" class="mx-auto text-navy-300" />
            <p class="mt-3 text-lg font-bold">
                {{ $adaSaringan || $saring ? 'Tidak ada produk yang cocok' : 'Belum ada produk' }}
            </p>
            <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted">
                {{ $adaSaringan || $saring
                    ? 'Coba kata kunci lain, atau tekan Reset.'
                    : 'Tambahkan produk pertama, lalu lengkapi varian dan harganya.' }}
            </p>
            <a href="{{ route('panel.produk.create') }}" class="tombol tombol-navy mt-5">Tambah produk</a>
        </div>
    @else
        {{-- Di bawah 48rem tabelnya berganti jadi kartu lewat .tabel-hp. Supaya
             tetap muat tanpa digeser (menu aksi terpotong oleh pembungkus geser),
             kategori dan status menumpang di bawah nama di bawah 96rem, dan
             gambar kecil baru tampil mulai 80rem (KEP-143). --}}
        <table class="tabel tabel-hp">
            <thead>
                <tr>
                    <th class="w-10">
                        <label class="sr-only" for="pilih-semua">Pilih semua produk di halaman ini</label>
                        <input type="checkbox" id="pilih-semua" class="kotak-centang"
                               :checked="dipilih.length === semua.length"
                               @change="dipilih = $event.target.checked ? [...semua] : []">
                    </th>
                    <th class="hidden w-16 xl:table-cell"><span class="sr-only">Gambar</span></th>
                    <th>Produk</th>
                    <th class="hidden 2xl:table-cell">Kategori</th>
                    <th class="hidden 2xl:table-cell">Status</th>
                    <th>Stok</th>
                    <th class="text-right">Harga</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($produk as $p)
                    @php
                        $gambar = $p->images->first();
                        $kurang = array_filter([
                            $p->images->isEmpty() ? 'gambar' : null,
                            $p->punyaJalurKustom() && $p->priceTiers->isEmpty() && $p->prices->isEmpty()
                                ? 'harga' : null,
                        ]);
                        $tersedia = (int) $p->variants_sum_stok - (int) $p->variants_sum_stok_dipesan;
                    @endphp
                    <tr class="hover:bg-navy-50 {{ $p->aktif ? '' : 'text-muted' }}"
                        :class="dipilih.includes({{ $p->id }}) && 'bg-biru-50'"
                        data-tautan="{{ route('panel.produk.edit', $p) }}">
                        <td data-kolom="Pilih">
                            <label class="sr-only" for="pilih-{{ $p->id }}">Pilih {{ $p->nama }}</label>
                            <input type="checkbox" id="pilih-{{ $p->id }}" name="produk[]" value="{{ $p->id }}" form="form-massal"
                                   x-model.number="dipilih" class="kotak-centang">
                        </td>
                        {{-- Gambar kecil di kolomnya sendiri: itu yang paling cepat
                             dikenali mata saat mencari satu produk di antara lima puluh. --}}
                        <td class="hidden w-16 xl:table-cell">
                            @if ($gambar)
                                <img src="{{ $gambar->thumb_url }}" alt="" width="48" height="48" loading="lazy"
                                     class="size-12 rounded-[8px] border border-line object-cover">
                            @else
                                <span class="flex size-12 items-center justify-center rounded-[8px] border border-dashed border-line-strong text-navy-300">
                                    <x-ikon nama="gambar" ukuran="size-4" />
                                </span>
                            @endif
                        </td>
                        <td data-utama>
                            <a href="{{ route('panel.produk.edit', $p) }}" class="font-semibold text-ink">{{ $p->nama }}</a>
                            <div class="tabular text-xs text-muted">{{ $p->sku }}</div>
                            <div class="mt-1.5 flex flex-wrap gap-1.5 font-normal 2xl:hidden">
                                <span class="chip {{ $warnaKategori($p->category->nama) }}">{{ $p->category->nama }}</span>
                                @if ($p->aktif)
                                    <span class="chip chip-ok"><span class="size-1.5 rounded-full bg-ok"></span> Tayang</span>
                                @else
                                    <span class="chip chip-netral"><x-ikon nama="mata-coret" ukuran="size-3.5" /> Disembunyikan</span>
                                @endif
                            </div>
                            @if ($kurang)
                                <div class="mt-1 flex flex-wrap gap-1.5">
                                    @foreach ($kurang as $apa)
                                        <span class="chip chip-kuning">
                                            <x-ikon nama="peringatan" ukuran="size-3.5" />
                                            Belum ada {{ $apa }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td data-kolom="Kategori" class="hidden 2xl:table-cell">
                            <span class="chip {{ $warnaKategori($p->category->nama) }}">{{ $p->category->nama }}</span>
                        </td>
                        <td data-kolom="Status" class="hidden 2xl:table-cell">
                            @if ($p->aktif)
                                <span class="chip chip-ok"><span class="size-1.5 rounded-full bg-ok"></span> Tayang</span>
                            @else
                                <span class="chip chip-netral"><x-ikon nama="mata-coret" ukuran="size-3.5" /> Disembunyikan</span>
                            @endif
                        </td>
                        <td data-kolom="Stok">
                            <span class="chip {{ $p->fulfillment_type === FulfillmentType::PREORDER ? 'chip-biru' : 'chip-ok' }}">
                                {{ $p->fulfillment_type->label() }}
                            </span>
                            @if ($p->punyaJalurReadyStock())
                                <span class="tabular mt-0.5 block text-xs {{ $tersedia <= 0 ? 'font-semibold text-merah' : 'text-muted' }}">
                                    {{ $tersedia <= 0 ? 'Stok habis' : 'sisa '.$tersedia }}
                                </span>
                            @endif
                        </td>
                        <td data-kolom="Harga" class="tabular text-right font-semibold whitespace-nowrap">
                            {{ $p->punyaJalurReadyStock() ? rupiah($p->harga_dasar) : '-' }}
                        </td>
                        <td data-kolom="Aksi">
                            <div class="flex items-center justify-end gap-1.5 md:justify-center">
                                <a href="{{ route('panel.produk.edit', $p) }}" class="tombol-ikon" title="Ubah {{ $p->nama }}">
                                    <x-ikon nama="pensil" ukuran="size-4" />
                                    <span class="sr-only">Ubah {{ $p->nama }}</span>
                                </a>
                                <details class="relative" x-data @click.outside="$el.removeAttribute('open')">
                                    <summary class="tombol-ikon" title="Aksi lain">
                                        <x-ikon nama="titik-tiga" ukuran="size-4" />
                                        <span class="sr-only">Aksi lain untuk {{ $p->nama }}</span>
                                    </summary>
                                    <div class="menu-tarik menu-aksi-isi right-0 w-56">
                                        <a href="{{ route('produk', $p) }}" target="_blank" rel="noopener">
                                            <x-ikon nama="buka-luar" ukuran="size-4" class="text-navy-400" /> Lihat di toko
                                        </a>
                                        <form method="POST" action="{{ route('panel.produk.tayang', $p) }}">
                                            @csrf
                                            <button type="submit">
                                                <x-ikon :nama="$p->aktif ? 'mata-coret' : 'mata'" ukuran="size-4" class="text-navy-400" />
                                                {{ $p->aktif ? 'Sembunyikan' : 'Tayangkan' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('panel.produk.destroy', $p) }}" class="border-t border-line"
                                              data-konfirmasi="Hapus produk {{ $p->nama }}? Produk yang pernah dipesan tidak bisa dihapus, hanya disembunyikan."
                                              data-konfirmasi-tombol="Hapus">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-merah-tua">
                                                <x-ikon nama="hapus" ukuran="size-4" /> Hapus
                                            </button>
                                        </form>
                                    </div>
                                </details>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @include('panel.komponen.halaman', ['daftar' => $produk, 'benda' => 'produk'])
    @endif
</div>
@endsection
