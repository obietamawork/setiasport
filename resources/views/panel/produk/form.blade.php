@extends('layouts.panel')
@section('judul', $produk->exists ? $produk->nama : 'Tambah produk')

@section('isi')
<x-kop :judul="$produk->exists ? $produk->nama : 'Tambah produk'"
       :kembali="route('panel.produk.index')" kembali-label="Daftar produk"
       :catatan="$produk->exists
            ? $produk->sku.', '.$produk->fulfillment_type->label()
            : 'Isi keterangan dasarnya dulu. Gambar, varian, harga, dan opsi diisi setelah produk dibuat.'">
    @if ($produk->exists)
        <a href="{{ route('produk', $produk) }}" target="_blank" rel="noopener"
           class="tombol tombol-garis tombol-sm">
            <x-ikon nama="buka-luar" ukuran="size-4" />
            Lihat di toko
        </a>
    @endif
</x-kop>

{{-- Halaman ini panjang. Tautan lompat membuat bagian yang dituju bisa dicapai
     tanpa menggulir lima layar. --}}
@if ($produk->exists)
    {{-- Menempel di bawah kop. Halaman produk panjangnya enam layar; daftar
         bagian yang ikut tergulung ke atas tidak menolong siapa pun. Bagian yang
         sedang dibaca ikut ditandai, supaya orang tahu ada di mana. --}}
    <nav class="segmen sticky z-10 mb-6 bg-paper py-1.5"
         style="top: calc(var(--bilah-atas) + var(--kop-lekat, 4.25rem))"
         aria-label="Bagian halaman"
         x-data="{
             aktif: '',
             init() {
                 const bagian = [...document.querySelectorAll('section[id], form[id]')];
                 const pengawas = new IntersectionObserver((baris) => {
                     baris.filter((b) => b.isIntersecting)
                         .forEach((b) => { this.aktif = b.target.id });
                 }, { rootMargin: '-30% 0px -60% 0px' });
                 bagian.forEach((b) => pengawas.observe(b));
             },
         }">
        @php
            $bagian = array_filter([
                ['#detail', 'Keterangan'],
                ['#gambar', 'Gambar'],
                $produk->punyaJalurReadyStock() ? ['#varian', 'Varian & stok'] : null,
                $produk->punyaJalurKustom() ? ['#harga', 'Tier harga'] : null,
                $produk->punyaJalurKustom() ? ['#opsi', 'Opsi'] : null,
                $produk->punyaJalurKustom() ? ['#matriks', 'Matriks harga'] : null,
                $produk->punyaJalurKustom() ? ['#panduan', 'Panduan ukuran'] : null,
            ]);
        @endphp
        @foreach ($bagian as [$anchor, $label])
            <a href="{{ $anchor }}" class="segmen-item"
               :class="aktif === '{{ ltrim($anchor, '#') }}' ? 'segmen-item-aktif' : ''">{{ $label }}</a>
        @endforeach
    </nav>
@endif

<form method="POST"
      action="{{ $produk->exists ? route('panel.produk.update', $produk) : route('panel.produk.store') }}"
      id="detail" class="kartu max-w-2xl space-y-4 scroll-mt-20 p-6"
      x-data="{ jalur: '{{ old('fulfillment_type', $produk->fulfillment_type?->value ?? 'READY_STOCK') }}' }">
    @csrf
    @if ($produk->exists) @method('PUT') @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="nama" class="label-form">Nama produk</label>
            <input type="text" id="nama" name="nama" value="{{ old('nama', $produk->nama) }}"
                   class="kotak-input" required autofocus>
        </div>
        <div>
            <label for="sku" class="label-form">Kode produk (SKU)</label>
            <input type="text" id="sku" name="sku" value="{{ old('sku', $produk->sku) }}"
                   class="kotak-input tabular" required placeholder="JRS-FUTSAL-FP">
        </div>
    </div>

    <div>
        <label for="category_id" class="label-form">Kategori</label>
        <select id="category_id" name="category_id" class="kotak-input" required>
            <option value="">Pilih kategori</option>
            @foreach ($kategori as $k)
                <option value="{{ $k->id }}" @selected(old('category_id', $produk->category_id) == $k->id)>
                    {{ $k->parent?->nama ? $k->parent->nama.' / ' : '' }}{{ $k->nama }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="fulfillment_type" class="label-form">Cara beli</label>
        <select id="fulfillment_type" name="fulfillment_type" x-model="jalur" class="kotak-input" required>
            <option value="READY_STOCK">Ready Stock (diambil dari rak)</option>
            <option value="PREORDER">Kustom (dikerjakan di workshop)</option>
            <option value="BOTH">Keduanya (pelanggan memilih sendiri)</option>
        </select>
        <p class="mt-1 text-xs text-muted">
            Pilihan ini menentukan bagian mana yang muncul di halaman produk, dan tidak bisa
            diubah lagi setelah produk pernah dipesan.
        </p>
    </div>

    {{-- Harga dasar hanya bermakna di jalur ready stock; jalur kustom memakai tier. --}}
    <div x-show="jalur !== 'PREORDER'" x-cloak>
        <label for="harga_dasar" class="label-form">Harga dasar (ready stock)</label>
        <input type="number" id="harga_dasar" name="harga_dasar"
               value="{{ old('harga_dasar', $produk->harga_dasar) }}" class="kotak-input tabular" min="0">
        <p class="mt-1 text-xs text-muted">Rupiah, tanpa titik. Selisih tiap varian diatur terpisah di bawah.</p>
    </div>

    {{-- Berat dipakai menghitung ongkir (KEP-050). Berlaku untuk kedua jalur:
         barang stok pun dikirim, dan kurir menagih per kilogram. --}}
    <div>
        <label for="berat_gram" class="label-form">Berat satuan (gram)</label>
        <input type="number" id="berat_gram" name="berat_gram"
               value="{{ old('berat_gram', $produk->berat_gram) }}"
               class="kotak-input tabular" min="0" max="100000">
        <p class="mt-1 text-xs leading-relaxed text-muted">
            Termasuk packing. Tanpa angka ini, ongkos kirim tidak bisa dihitung otomatis
            dan pesanan kirim harus dihitung manual admin.
        </p>
    </div>

    <div x-show="jalur !== 'READY_STOCK'" x-cloak class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="diskon_min_qty" class="label-form">Potongan borongan mulai (pcs)</label>
                <input type="number" id="diskon_min_qty" name="diskon_min_qty"
                       value="{{ old('diskon_min_qty', $produk->diskon_min_qty) }}"
                       class="kotak-input tabular" min="0" max="10000">
                <p class="mt-1 text-xs text-muted">Isi 0 kalau produk ini tanpa potongan.</p>
            </div>
            <div>
                <label for="diskon_per_pcs" class="label-form">Potongan per pcs (Rp)</label>
                <input type="number" id="diskon_per_pcs" name="diskon_per_pcs"
                       value="{{ old('diskon_per_pcs', $produk->diskon_per_pcs) }}"
                       class="kotak-input tabular" min="0" max="10000000">
                <p class="mt-1 text-xs text-muted">Tidak berlaku kelipatan, tarifnya tetap.</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="moq" class="label-form">Minimum pemesanan (pcs)</label>
                <input type="number" id="moq" name="moq" value="{{ old('moq', $produk->moq) }}"
                       class="kotak-input tabular" min="1">
            </div>
            <div>
                <label for="lead_time_hari" class="label-form">Waktu pengerjaan (hari kerja)</label>
                <input type="number" id="lead_time_hari" name="lead_time_hari"
                       value="{{ old('lead_time_hari', $produk->lead_time_hari) }}"
                       class="kotak-input tabular" min="1">
                <p class="mt-1 text-xs text-muted">Dihitung sejak pelanggan menyetujui desain.</p>
            </div>
        </div>

        <label class="flex items-start gap-2 text-sm">
            <input type="checkbox" name="roster_wajib" value="1" class="mt-0.5 size-4 accent-navy"
                   @checked(old('roster_wajib', $produk->roster_wajib))>
            <span>
                Wajib isi nama &amp; nomor punggung
                <span class="block text-xs text-muted">
                    Aktifkan untuk jersey tim. Matikan untuk kaos seragam yang tanpa nama.
                </span>
            </span>
        </label>
    </div>

    <div>
        <label for="deskripsi" class="label-form">Deskripsi</label>
        <textarea id="deskripsi" name="deskripsi" rows="4" class="kotak-input">{{ old('deskripsi', $produk->deskripsi) }}</textarea>
    </div>

    <div>
        <label for="slug" class="label-form">Slug URL</label>
        <input type="text" id="slug" name="slug" value="{{ old('slug', $produk->slug) }}"
               class="kotak-input" placeholder="Dibuat otomatis dari nama">
    </div>

    <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="aktif" value="1" class="size-4 accent-navy"
               @checked(old('aktif', $produk->aktif ?? true))>
        Tampilkan di katalog
    </label>

    <div class="flex flex-wrap items-center gap-3 border-t border-line pt-5">
        <button type="submit" class="tombol tombol-navy">
            {{ $produk->exists ? 'Simpan perubahan' : 'Buat produk' }}
        </button>
        <a href="{{ route('panel.produk.index') }}" class="tautan text-sm">Kembali ke daftar</a>
    </div>
</form>

@if ($produk->exists)
    {{-- Bagian di bawah ini menyimpan sendiri-sendiri: gambar, varian, harga,
         dan opsi masing-masing punya tombolnya. Disebutkan terang-terangan,
         karena formulir panjang yang punya satu tombol simpan di puncak membuat
         orang ragu apakah perubahannya sudah tercatat. --}}
    <p class="kolom-baca mt-3 text-sm leading-relaxed text-muted">
        Tombol di atas hanya menyimpan keterangan dasar ini. Gambar, varian, harga, dan opsi
        di bawah punya tombol simpannya masing-masing.
    </p>
@endif

@unless ($produk->exists)
    <p class="mt-4 max-w-2xl text-sm text-muted">
        Varian, tier harga, dan opsi diisi setelah produk dibuat.
    </p>
@endunless

@if ($produk->exists)
<section id="gambar" class="mt-10 max-w-4xl scroll-mt-20">
    <h2 class="flex items-center gap-2 text-lg font-extrabold"><x-ikon nama="gambar" class="text-navy-400" />Gambar produk</h2>
    <p class="mt-1 text-sm text-muted">
        Bisa diunggah langsung ke hosting, atau ditunjuk ke tautan CDN. Yang diunggah
        otomatis dikecilkan dan dikonversi ke WebP supaya katalog tetap ringan dibuka
        dari jaringan seluler. Gambar pertama dipakai sebagai gambar utama.
    </p>

    <div class="kartu mt-4 overflow-hidden">
        @if ($produk->images->isEmpty())
            <p class="p-5 text-sm text-muted">
                Belum ada gambar. Produk tanpa foto tetap tayang, tapi pelanggan jauh lebih
                jarang membukanya.
            </p>
        @else
            <ul class="grid gap-px bg-line sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($produk->images as $g)
                    <li class="bg-field p-3">
                        <div class="relative aspect-square overflow-hidden rounded-[7px] bg-navy-50">
                            <img src="{{ $g->thumb_url }}" alt="{{ $g->alt }}"
                                 class="absolute inset-0 h-full w-full object-cover" loading="lazy">
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            @if ($loop->first)
                                <span class="chip chip-navy">Utama</span>
                            @endif
                            <span class="chip {{ $g->dariCdn() ? 'chip-kuning' : 'chip-netral' }}">
                                {{ $g->dariCdn() ? 'CDN' : 'Diunggah' }}
                            </span>
                            @if ($g->warna)
                                <span class="chip chip-netral">Warna: {{ $g->warna }}</span>
                            @endif
                            @if ($g->lebar)
                                <span class="tabular text-xs text-muted">{{ $g->lebar }}px</span>
                            @endif
                        </div>

                        <div class="mt-2 flex items-center gap-3 text-sm">
                            @unless ($loop->first)
                                <form method="POST" action="{{ route('panel.produk.gambar.utama', [$produk, $g]) }}">
                                    @csrf
                                    <button type="submit" class="tautan">Jadikan utama</button>
                                </form>
                            @endunless
                            <form method="POST" action="{{ route('panel.produk.gambar.hapus', [$produk, $g]) }}"
                                  class="ml-auto" onsubmit="return confirm('Hapus gambar ini?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="tautan text-muted">Hapus</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('panel.produk.gambar.simpan', $produk) }}"
              enctype="multipart/form-data"
              class="grid gap-4 border-t border-line bg-navy-50 p-4 sm:grid-cols-2"
              x-data="{ sumber: 'unggah' }">
            @csrf

            <div class="sm:col-span-2">
                <div class="inline-flex rounded-[7px] border border-line-strong bg-field p-0.5 text-sm">
                    <button type="button" @click="sumber = 'unggah'"
                            :class="sumber === 'unggah' ? 'bg-navy text-white' : 'text-muted hover:text-ink'"
                            class="rounded-[5px] px-3 py-1.5 font-medium">Unggah berkas</button>
                    <button type="button" @click="sumber = 'cdn'"
                            :class="sumber === 'cdn' ? 'bg-navy text-white' : 'text-muted hover:text-ink'"
                            class="rounded-[5px] px-3 py-1.5 font-medium">Tautan CDN</button>
                </div>
            </div>

            <div x-show="sumber === 'unggah'" x-cloak>
                <label for="berkas" class="label-form">Berkas gambar</label>
                <input type="file" id="berkas" name="berkas" accept=".jpg,.jpeg,.png,.webp" class="kotak-input">
                <p class="bantuan-form">
                    JPG, PNG, atau WebP sampai {{ setting('upload_maks_mb', 10) }} MB.
                    Foto dari HP boleh langsung, nanti dikecilkan sendiri.
                </p>
            </div>

            <div x-show="sumber === 'cdn'" x-cloak>
                <label for="external_url" class="label-form">Tautan gambar</label>
                <input type="url" id="external_url" name="external_url" class="kotak-input"
                       placeholder="https://cdn.contoh.com/jersey.webp">
                <p class="bantuan-form">
                    Dipakai apa adanya, tidak disalin ke hosting. Berguna saat katalog
                    membesar dan kuota disk mulai terasa.
                </p>
            </div>

            <div>
                <label for="alt" class="label-form">Keterangan gambar</label>
                <input type="text" id="alt" name="alt" class="kotak-input" maxlength="150"
                       placeholder="{{ $produk->nama }}">
                <p class="bantuan-form">Dibaca pembaca layar dan mesin pencari. Boleh dikosongkan.</p>
            </div>

            @php $warnaVarian = $produk->variants->pluck('warna')->filter()->unique()->sort()->values(); @endphp
            @if ($warnaVarian->isNotEmpty())
                <div>
                    <label for="warna" class="label-form">Mewakili warna (opsional)</label>
                    <select id="warna" name="warna" class="kotak-input">
                        <option value="">Semua warna / bukan foto warna tertentu</option>
                        @foreach ($warnaVarian as $w)
                            <option value="{{ $w }}">{{ $w }}</option>
                        @endforeach
                    </select>
                    <p class="bantuan-form">
                        Kalau diisi, gambar ini otomatis tampil begitu pelanggan memilih warna
                        itu di halaman produk &mdash; seperti toko online pada umumnya.
                    </p>
                </div>
            @endif

            <div class="sm:col-span-2">
                <button type="submit" class="tombol tombol-navy">Tambah gambar</button>
            </div>
        </form>
    </div>
</section>
@endif

@if ($produk->exists)

@if ($produk->punyaJalurReadyStock())
<section id="varian" class="mt-10 max-w-4xl scroll-mt-20">
    <h2 class="flex items-center gap-2 text-lg font-extrabold"><x-ikon nama="stok" class="text-navy-400" />Varian &amp; stok</h2>
    <p class="mt-1 text-sm text-muted">
        Setiap kombinasi ukuran dan warna berdiri sendiri, lengkap dengan stoknya.
    </p>

    <div class="kartu mt-4 overflow-hidden">
        @if ($produk->variants->isEmpty())
            <p class="p-5 text-sm text-muted">
                Belum ada varian. Produk tanpa varian tidak bisa dibeli dari stok: tambahkan
                minimal satu di bawah.
            </p>
        @else
            <table class="tabel">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Ukuran</th>
                        <th>Warna</th>
                        <th class="text-right">Selisih harga</th>
                        <th class="text-right">Stok</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($produk->variants as $v)
                        <tr>
                            <td class="tabular">{{ $v->sku }}</td>
                            <td>{{ $v->ukuran ?? '-' }}</td>
                            <td>{{ $v->warna ?? '-' }}</td>
                            <td class="text-right tabular">
                                {{ $v->tambahan_harga === 0 ? '-' : ($v->tambahan_harga > 0 ? '+' : '−').rupiah(abs($v->tambahan_harga)) }}
                            </td>
                            <td class="text-right tabular">
                                {{ $v->stok }}
                                @if ($v->stok_dipesan > 0)
                                    <span class="text-xs text-muted">({{ $v->stok_dipesan }} dipesan)</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('panel.produk.varian.hapus', [$produk, $v]) }}"
                                      onsubmit="return confirm('Hapus varian {{ $v->sku }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="tautan text-muted">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <form method="POST" action="{{ route('panel.produk.varian.simpan', $produk) }}"
              class="flex flex-wrap items-end gap-3 border-t border-line bg-paper p-4">
            @csrf
            <div class="w-40"><label class="label-form">SKU varian</label>
                <input type="text" name="sku" class="kotak-input tabular" required></div>
            <div class="w-24"><label class="label-form">Ukuran</label>
                <input type="text" name="ukuran" class="kotak-input" placeholder="M"></div>
            <div class="w-28"><label class="label-form">Warna</label>
                <input type="text" name="warna" class="kotak-input" placeholder="Hitam"></div>
            <div class="w-32"><label class="label-form">Selisih harga</label>
                <input type="number" name="tambahan_harga" class="kotak-input tabular" value="0"></div>
            <div class="w-24"><label class="label-form">Stok</label>
                <input type="number" name="stok" class="kotak-input tabular" value="0" min="0" required></div>
            <button type="submit" class="tombol tombol-garis">Tambah varian</button>
        </form>
    </div>
</section>
@endif

@if ($produk->punyaJalurKustom())
<section id="harga" class="mt-10 max-w-4xl scroll-mt-20">
    <h2 class="flex items-center gap-2 text-lg font-extrabold"><x-ikon nama="uang" class="text-navy-400" />Tier harga kustom</h2>
    <p class="mt-1 text-sm text-muted">
        Harga per pcs menurut jumlah pesanan. Tanpa ini, pelanggan tidak bisa menghitung
        biayanya sendiri dan akan bertanya ke admin.
    </p>

    <div class="kartu mt-4 overflow-hidden">
        @if ($produk->priceTiers->isEmpty())
            <p class="border-l-2 border-merah p-5 text-sm">
                Belum ada tier harga. Produk kustom tidak bisa dihitung harganya sampai
                minimal satu tier diisi.
            </p>
        @else
            <table class="tabel">
                <thead>
                    <tr>
                        <th>Jumlah</th>
                        <th class="text-right">Harga per pcs</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($produk->priceTiers as $t)
                        <tr>
                            <td class="tabular">{{ $t->label }}</td>
                            <td class="text-right tabular">{{ rupiah($t->harga_satuan) }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('panel.produk.tier.hapus', [$produk, $t]) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="tautan text-muted">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <form method="POST" action="{{ route('panel.produk.tier.simpan', $produk) }}"
              class="flex flex-wrap items-end gap-3 border-t border-line bg-paper p-4">
            @csrf
            <div class="w-32"><label class="label-form">Dari (pcs)</label>
                <input type="number" name="min_qty" class="kotak-input tabular" min="1" required></div>
            <div class="w-36"><label class="label-form">Sampai (pcs)</label>
                <input type="number" name="max_qty" class="kotak-input tabular" min="1" placeholder="tak terbatas"></div>
            <div class="w-40"><label class="label-form">Harga per pcs</label>
                <input type="number" name="harga_satuan" class="kotak-input tabular" min="1" required></div>
            <button type="submit" class="tombol tombol-garis">Tambah tier</button>
        </form>
    </div>
</section>

<section id="opsi" class="mt-10 max-w-4xl scroll-mt-20">
    <h2 class="flex items-center gap-2 text-lg font-extrabold"><x-ikon nama="kategori" class="text-navy-400" />Opsi produk</h2>
    <p class="mt-1 text-sm text-muted">
        Bahan, lengan, kerah, nameset, dan biaya setup. Perhatikan kolom cara hitung: biaya setup dikenakan sekali per pesanan, bukan per potong.
    </p>

    <div class="mt-4 space-y-4">
        @foreach ($produk->options as $opsi)
            <div class="kartu overflow-hidden">
                <div class="flex items-center gap-3 border-b border-line px-4 py-2.5">
                    <span class="font-semibold">{{ $opsi->nama }}</span>
                    @unless ($opsi->wajib)
                        <span class="chip chip-netral">Opsional</span>
                    @endunless
                    <form method="POST" action="{{ route('panel.produk.opsi.hapus', [$produk, $opsi]) }}"
                          class="ml-auto" onsubmit="return confirm('Hapus opsi {{ $opsi->nama }}?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="tautan text-sm text-muted">Hapus opsi</button>
                    </form>
                </div>

                <ul class="divide-y divide-line text-sm">
                    @forelse ($opsi->values as $nilai)
                        <li class="flex flex-wrap items-center gap-3 px-4 py-2">
                            <span>{{ $nilai->label }}</span>
                            <span class="tabular text-muted">
                                {{ $nilai->tambahan_harga === 0 ? 'tanpa tambahan biaya'
                                    : ($nilai->tambahan_harga > 0 ? '+' : '−').rupiah(abs($nilai->tambahan_harga)) }}
                            </span>
                            <span class="chip {{ $nilai->basis === \App\Enums\PriceBasis::PER_ITEM ? 'chip-merah' : 'chip-navy' }}">
                                {{ $nilai->basis->label() }}
                            </span>
                            <form method="POST" action="{{ route('panel.produk.nilai.hapus', [$produk, $opsi, $nilai]) }}" class="ml-auto">
                                @csrf @method('DELETE')
                                <button type="submit" class="tautan text-muted">Hapus</button>
                            </form>
                        </li>
                    @empty
                        <li class="text-muted">Belum ada pilihan untuk opsi ini.</li>
                    @endforelse
                </ul>

                <form method="POST" action="{{ route('panel.produk.nilai.simpan', [$produk, $opsi]) }}"
                      class="flex flex-wrap items-end gap-3 border-t border-line bg-paper p-4">
                    @csrf
                    <div class="w-48"><label class="label-form">Nama pilihan</label>
                        <input type="text" name="label" class="kotak-input" required placeholder="Milano"></div>
                    <div class="w-36"><label class="label-form">Selisih harga</label>
                        <input type="number" name="tambahan_harga" class="kotak-input tabular" value="0" required></div>
                    <div class="w-44"><label class="label-form">Cara hitung</label>
                        <select name="basis" class="kotak-input">
                            <option value="PER_PCS">Dikali jumlah pcs</option>
                            <option value="PER_ITEM">Sekali per pesanan</option>
                        </select></div>
                    <button type="submit" class="tombol tombol-garis">Tambah pilihan</button>
                </form>
            </div>
        @endforeach

        <form method="POST" action="{{ route('panel.produk.opsi.simpan', $produk) }}"
              class="kartu flex flex-wrap items-end gap-3 p-4">
            @csrf
            <div class="w-56"><label class="label-form">Nama opsi baru</label>
                <input type="text" name="nama" class="kotak-input" required placeholder="Bahan"></div>
            <label class="flex items-center gap-2 pb-2.5 text-sm">
                <input type="checkbox" name="wajib" value="1" class="size-4 accent-navy" checked>
                Wajib dipilih
            </label>
            <button type="submit" class="tombol tombol-garis">Tambah opsi</button>
        </form>
    </div>
</section>

{{-- MATRIKS HARGA (KEP-052) ------------------------------------------------ --}}
<section id="matriks" class="mt-10 max-w-4xl scroll-mt-20">
    <h2 class="flex items-center gap-2 text-lg font-extrabold"><x-ikon nama="laporan" class="text-navy-400" />Matriks harga per ukuran</h2>
    <p class="kolom-baca mt-1 text-sm leading-relaxed text-muted">
        Dipakai produk yang harganya berbeda menurut umur pemakainya, misalnya jersey SD,
        SMP, dan dewasa. Isi harga dasar tiap bahan; tambahan kerah, lengan panjang, dan
        saku diatur di bagian Opsi, jadi tidak perlu diulang di sini.
    </p>

    @php
        $opsiHarga = $produk->options->first(fn ($o) => $o->values->contains(
            fn ($v) => $produk->prices->contains('option_value_id', $v->id)
        )) ?? $produk->options->first();

        $kolomKategori = collect(\App\Enums\KategoriUkuran::cases())
            ->mapWithKeys(fn ($k) => [$k->value => $k->label()])
            ->prepend('Semua kategori', 'SEMUA');
    @endphp

    <div class="kartu mt-4 p-5">
        @if ($produk->options->isEmpty())
            <p class="text-sm leading-relaxed text-muted">
                Buat dulu opsi penentu harga di bagian Opsi, biasanya "Bahan". Matriks ini
                mengisi harga untuk tiap pilihan di opsi itu.
            </p>
        @else
            <form method="POST" action="{{ route('panel.produk.matriks.simpan', $produk) }}"
                  x-data="{ opsi: {{ $opsiHarga?->id ?? 0 }} }">
                @csrf

                <label for="opsi_id" class="label-form">Opsi penentu harga</label>
                <select id="opsi_id" name="opsi_id" x-model.number="opsi" class="kotak-input max-w-64">
                    @foreach ($produk->options as $o)
                        <option value="{{ $o->id }}">{{ $o->nama }}</option>
                    @endforeach
                </select>

                @foreach ($produk->options as $o)
                    <div x-show="opsi === {{ $o->id }}" class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-120 text-sm">
                            <thead>
                                <tr class="border-b border-line text-left">
                                    <th class="py-2 pr-3 font-medium">{{ $o->nama }}</th>
                                    @foreach ($kolomKategori as $nilai => $label)
                                        <th class="py-2 pr-3 font-medium">{{ $label }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($o->values as $v)
                                    <tr class="border-b border-line last:border-0">
                                        <td class="py-2 pr-3 font-medium">{{ $v->label }}</td>
                                        @foreach ($kolomKategori as $nilai => $label)
                                            @php
                                                $sel = $produk->prices->first(fn ($h) => $h->option_value_id === $v->id
                                                    && ($h->kategori?->value ?? 'SEMUA') === $nilai);
                                            @endphp
                                            <td class="py-1.5 pr-3">
                                                <input type="number" min="0" max="999999999"
                                                       name="harga[{{ $nilai }}][{{ $v->id }}]"
                                                       value="{{ $sel?->harga_satuan }}"
                                                       class="kotak-input tabular w-28" placeholder="-">
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button type="submit" class="tombol tombol-navy">Simpan matriks harga</button>
                    <p class="text-xs leading-relaxed text-muted">
                        Kolom yang dikosongkan berarti kombinasi itu tidak dijual.
                        Pakai kolom "Semua kategori" untuk produk yang harganya sama untuk semua umur.
                    </p>
                </div>
            </form>
        @endif
    </div>
</section>

{{-- PANDUAN UKURAN (KEP-052) ----------------------------------------------- --}}
<section id="panduan" class="mt-10 max-w-4xl scroll-mt-20">
    <h2 class="flex items-center gap-2 text-lg font-extrabold"><x-ikon nama="produk" class="text-navy-400" />Panduan ukuran</h2>
    <p class="kolom-baca mt-1 text-sm leading-relaxed text-muted">
        Daftar ini yang menentukan ukuran mana yang boleh dipesan pelanggan, sekaligus
        tampil sebagai tabel sentimeter di halaman pemesanan. Selama kosong, sistem memakai
        daftar ukuran bawaan.
    </p>

    @php
        $kategoriPanduan = collect([['', 'Semua kategori']])->concat(
            collect(\App\Enums\KategoriUkuran::cases())->map(fn ($k) => [$k->value, $k->label()])
        );
    @endphp

    <div class="mt-4 space-y-4">
        @foreach ($kategoriPanduan as [$nilai, $label])
            @php
                $baris = $produk->sizeCharts
                    ->filter(fn ($b) => ($b->kategori?->value ?? '') === $nilai)
                    ->values();
                $kolom = $baris->isNotEmpty() ? $baris->first()->kolom() : ['Lebar', 'Panjang'];
            @endphp

            <details class="kartu p-5" @if ($baris->isNotEmpty()) open @endif>
                <summary class="cursor-pointer font-semibold">
                    {{ $label }}
                    <span class="font-normal text-muted">
                        &middot; {{ $baris->count() }} ukuran
                    </span>
                </summary>

                <form method="POST" action="{{ route('panel.produk.panduan.simpan', $produk) }}"
                      class="mt-4 space-y-3"
                      x-data="{ baris: {{ Js::from($baris->map(fn ($b) => [
                          'ukuran' => $b->ukuran,
                          'nilai' => collect($b->ukuran_cm ?? [])->pluck('nilai')->all(),
                      ])->values()) }} }">
                    @csrf
                    <input type="hidden" name="kategori" value="{{ $nilai }}">

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="label-form">Nama kolom 1</span>
                            <input type="text" name="kolom[0]" value="{{ $kolom[0] ?? 'Lebar' }}"
                                   class="kotak-input" maxlength="20">
                        </label>
                        <label class="block">
                            <span class="label-form">Nama kolom 2 (opsional)</span>
                            <input type="text" name="kolom[1]" value="{{ $kolom[1] ?? 'Panjang' }}"
                                   class="kotak-input" maxlength="20">
                        </label>
                    </div>

                    <template x-for="(b, i) in baris" :key="i">
                        <div class="grid gap-2 sm:grid-cols-[6rem_7rem_7rem_auto] sm:items-end">
                            <label class="block">
                                <span class="label-form">Ukuran</span>
                                <input type="text" x-model="b.ukuran" :name="`baris[${i}][ukuran]`"
                                       class="kotak-input" maxlength="10" placeholder="L">
                            </label>
                            <label class="block">
                                <span class="label-form">Kolom 1 (cm)</span>
                                <input type="number" x-model="b.nilai[0]" :name="`baris[${i}][nilai][0]`"
                                       class="kotak-input tabular" min="0" max="1000">
                            </label>
                            <label class="block">
                                <span class="label-form">Kolom 2 (cm)</span>
                                <input type="number" x-model="b.nilai[1]" :name="`baris[${i}][nilai][1]`"
                                       class="kotak-input tabular" min="0" max="1000">
                            </label>
                            <button type="button" @click="baris.splice(i, 1)"
                                    class="tautan pb-2 text-xs text-merah">Hapus</button>
                        </div>
                    </template>

                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" @click="baris.push({ ukuran: '', nilai: [null, null] })"
                                class="tombol tombol-garis tombol-sm">+ Tambah ukuran</button>
                        <button type="submit" class="tombol tombol-navy tombol-sm">Simpan panduan</button>
                        <p class="text-xs text-muted">Urutan baris menentukan urutan pilihan ukuran.</p>
                    </div>
                </form>
            </details>
        @endforeach
    </div>

    {{-- TAMBAHAN UKURAN BESAR --}}
    <h3 class="mt-8 font-extrabold">Tambahan ukuran besar</h3>
    <p class="kolom-baca mt-1 text-sm leading-relaxed text-muted">
        Ukuran besar makan kain lebih banyak. Tambahan ini berlaku per potong, di luar
        harga matriks.
    </p>

    <div class="kartu mt-3 p-5">
        @if ($produk->sizeSurcharges->isEmpty())
            <p class="text-sm text-muted">Belum ada tambahan ukuran.</p>
        @else
            <ul class="divide-y divide-line text-sm">
                @foreach ($produk->sizeSurcharges as $t)
                    <li class="flex items-center gap-3 py-2">
                        <span class="w-16 font-medium">{{ $t->ukuran }}</span>
                        <span class="tabular">+ {{ rupiah($t->tambahan_harga) }} / pcs</span>
                        <form method="POST" action="{{ route('panel.produk.tambahan.hapus', [$produk, $t]) }}"
                              class="ml-auto">
                            @csrf @method('DELETE')
                            <button type="submit" class="tautan text-xs text-muted">Hapus</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('panel.produk.tambahan.simpan', $produk) }}"
              class="mt-4 flex flex-wrap items-end gap-3 border-t border-line pt-4">
            @csrf
            <label class="block">
                <span class="label-form">Ukuran</span>
                <input type="text" name="ukuran" class="kotak-input w-24" maxlength="10" placeholder="3XL" required>
            </label>
            <label class="block">
                <span class="label-form">Tambahan (Rp / pcs)</span>
                <input type="number" name="tambahan_harga" class="kotak-input tabular w-32" min="0" required>
            </label>
            <button type="submit" class="tombol tombol-garis">Simpan tambahan</button>
        </form>
    </div>
</section>
@endif

<form method="POST" action="{{ route('panel.produk.destroy', $produk) }}"
      class="zona-bahaya max-w-4xl"
      onsubmit="return confirm('Hapus produk {{ $produk->nama }}?')">
    @csrf @method('DELETE')
    <button type="submit" class="tautan text-sm text-muted">Hapus produk ini</button>
    <p class="mt-1 text-xs text-muted">
        Produk yang pernah dipesan tidak bisa dihapus: riwayat pesanan akan ikut rusak.
        Sembunyikan saja dari katalog.
    </p>
</form>

@endif
@endsection
