@extends('layouts.panel')
@section('judul', $produk->exists ? $produk->nama : 'Tambah produk')

@section('isi')
<a href="{{ route('panel.produk.index') }}" class="tautan text-sm text-muted">Kembali ke daftar produk</a>

<div class="kop-halaman mt-3">
    <div>
        <h1 class="kop-judul">{{ $produk->exists ? $produk->nama : 'Tambah produk' }}</h1>
        @if ($produk->exists)
            <p class="kop-catatan tabular">
                {{ $produk->sku }}, {{ $produk->fulfillment_type->label() }}
            </p>
        @else
            <p class="kop-catatan">
                Isi keterangan dasarnya dulu. Gambar, varian, harga, dan opsi diisi
                setelah produk dibuat.
            </p>
        @endif
    </div>

    @if ($produk->exists)
        <a href="{{ route('produk', $produk) }}" target="_blank" rel="noopener" class="tombol tombol-garis tombol-sm">
            Lihat di toko
        </a>
    @endif
</div>

{{-- Halaman ini panjang. Tautan lompat membuat bagian yang dituju bisa dicapai
     tanpa menggulir lima layar. --}}
@if ($produk->exists)
    <nav class="mb-6 flex flex-wrap gap-1 text-sm" aria-label="Bagian halaman">
        @php
            $bagian = array_filter([
                ['#detail', 'Keterangan'],
                ['#gambar', 'Gambar'],
                $produk->punyaJalurReadyStock() ? ['#varian', 'Varian & stok'] : null,
                $produk->punyaJalurKustom() ? ['#harga', 'Tier harga'] : null,
                $produk->punyaJalurKustom() ? ['#opsi', 'Opsi'] : null,
            ]);
        @endphp
        @foreach ($bagian as [$anchor, $label])
            <a href="{{ $anchor }}"
               class="rounded-[7px] bg-field px-3 py-1.5 font-medium text-muted transition-colors hover:bg-navy-50 hover:text-ink">
                {{ $label }}
            </a>
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

    <div x-show="jalur !== 'READY_STOCK'" x-cloak class="space-y-4">
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

@unless ($produk->exists)
    <p class="mt-4 max-w-2xl text-sm text-muted">
        Varian, tier harga, dan opsi diisi setelah produk dibuat.
    </p>
@endunless

@if ($produk->exists)
<section id="gambar" class="mt-10 max-w-4xl scroll-mt-20">
    <h2 class="text-lg font-extrabold">Gambar produk</h2>
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
    <h2 class="text-lg font-extrabold">Varian &amp; stok</h2>
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
    <h2 class="text-lg font-extrabold">Tier harga kustom</h2>
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
    <h2 class="text-lg font-extrabold">Opsi produk</h2>
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
