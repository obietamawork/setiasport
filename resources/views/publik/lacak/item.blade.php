{{--
    Satu barang di halaman progres pesanan, dengan tata letak gambar contoh
    klien (KEP-122): kartu tahap, detail, desain, daftar pemain, dan catatan.
    Ringkas dan sedikit tulisan; yang masih bisa diubah pelanggan diberi
    tanda pensil.

    Tahapnya tetap tahap besar dari LinimasaItem (KEP-028). Hanya Produksi
    yang membuka subtahap, karena di situlah penungguan paling lama terasa.

    @var \App\Models\OrderItem $item
    @var \App\Models\Order $order
--}}
@php
    $linimasa = App\Support\LinimasaItem::untuk($item, $order);
    $kini = collect($linimasa['fase'])->firstWhere('keadaan', 'kini');
    $gambar = $item->approvedDesign?->path
        ? asset('storage/'.$item->approvedDesign->path)
        : $item->product?->images->first()?->thumb_url;
    $terkunci = $item->rosterTerkunci();
    $adaPemain = $item->rosters->isNotEmpty();
@endphp

<article class="kartu-bayar" id="item-{{ $item->id }}">
    <div class="flex flex-wrap items-center gap-4">
        @if ($gambar)
            <img src="{{ $gambar }}" alt="" width="64" height="64" class="size-16 shrink-0 rounded-full border border-line object-cover">
        @else
            <span class="flex size-16 shrink-0 items-center justify-center rounded-full bg-navy-50 text-navy-300">
                <x-ikon :nama="$item->kustom() ? 'kaos' : 'produk'" ukuran="size-7" />
            </span>
        @endif
        <div class="min-w-0 flex-1">
            <h3 class="text-lg font-extrabold">{{ $item->nama_produk }}</h3>
            <p class="mt-0.5 flex items-center gap-1.5 text-sm text-muted">
                <x-ikon nama="kalender" ukuran="size-4" />
                Pesanan dibuat: <span class="tabular">{{ $order->created_at->translatedFormat('j M Y, H.i') }}</span>
            </p>
        </div>
        @if ($linimasa['batal'])
            <span class="chip chip-merah px-3 py-1.5 text-sm"><x-ikon nama="silang" ukuran="size-4" /> Dibatalkan</span>
        @elseif ($kini)
            <span class="chip chip-biru px-3 py-1.5 text-sm"><x-ikon nama="pengaturan" ukuran="size-4" /> {{ $kini['label'] }}</span>
        @else
            <span class="chip chip-ok px-3 py-1.5 text-sm"><x-ikon nama="centang" ukuran="size-4" /> Selesai</span>
        @endif
    </div>

    @if ($linimasa['batal'])
        <p class="mt-4 text-sm text-muted">Item ini dibatalkan.</p>
    @else
        {{-- Tahap mendatar di layar lebar, menurun di HP. --}}
        <ol class="tahap-datar mt-6 hidden sm:grid" style="grid-template-columns: repeat({{ count($linimasa['fase']) }}, minmax(0, 1fr))">
            @foreach ($linimasa['fase'] as $f)
                <li class="tahap-datar-{{ $f['keadaan'] }}">
                    <span class="tahap-datar-bulat">
                        @if ($f['keadaan'] === 'lewat')
                            <x-ikon nama="centang" ukuran="size-5" />
                        @elseif ($f['keadaan'] === 'kini')
                            <x-ikon nama="pengaturan" ukuran="size-5" />
                        @else
                            <span class="tabular text-sm font-bold">{{ $loop->iteration }}</span>
                        @endif
                    </span>
                    <span class="mt-2 block text-sm font-semibold">{{ $f['label'] }}</span>
                    <span class="block text-xs text-muted tabular">
                        @if ($f['keadaan'] === 'kini')
                            Sedang berjalan
                        @elseif ($f['waktu'])
                            {{ $f['waktu']->translatedFormat('j M') }}
                        @endif
                    </span>
                </li>
            @endforeach
        </ol>

        <ol class="mt-5 sm:hidden">
            @foreach ($linimasa['fase'] as $f)
                <li class="flex gap-3 {{ $loop->last ? '' : 'pb-3' }}">
                    <div class="flex flex-col items-center">
                        <span class="mt-1 size-2.5 shrink-0 rounded-full
                            {{ $f['keadaan'] === 'kini' ? 'bg-biru-terang ring-4 ring-biru-100' : ($f['keadaan'] === 'lewat' ? 'bg-ok' : 'bg-line-strong') }}"></span>
                        @unless ($loop->last)
                            <span class="w-px flex-1 {{ $f['keadaan'] === 'lewat' ? 'bg-ok' : 'bg-line' }}"></span>
                        @endunless
                    </div>
                    <div class="-mt-0.5 flex min-w-0 flex-1 flex-wrap items-baseline justify-between gap-x-3">
                        <p class="text-sm {{ $f['keadaan'] === 'kini' ? 'font-bold' : ($f['keadaan'] === 'lewat' ? '' : 'text-muted') }}">{{ $f['label'] }}</p>
                        @if ($f['waktu'])
                            <p class="text-xs text-muted tabular">{{ $f['waktu']->translatedFormat('j M, H.i') }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>

        @if ($kini && $kini['catatan'])
            <p class="mt-4 rounded-[10px] bg-biru-50 px-4 py-2.5 text-sm leading-relaxed">{{ $kini['catatan'] }}</p>
        @endif

        {{-- Subtahap produksi sebagai deret singkat, hanya setelah produksi mulai. --}}
        @php $produksi = collect($linimasa['fase'])->first(fn ($f) => $f['subtahap'] !== [] && $f['keadaan'] !== 'nanti'); @endphp
        @if ($produksi)
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($produksi['subtahap'] as $sub)
                    <span class="chip {{ $sub['keadaan'] === 'kini' ? 'chip-biru' : ($sub['keadaan'] === 'lewat' ? 'chip-ok' : 'chip-netral') }}">
                        @if ($sub['keadaan'] === 'lewat') <x-ikon nama="centang" ukuran="size-3.5" /> @endif
                        {{ $sub['label'] }}{{ $sub['keadaan'] === 'kini' ? ', sedang dikerjakan' : '' }}
                    </span>
                @endforeach
            </div>
        @endif
    @endif
</article>

@unless ($linimasa['batal'])
    <div class="grid gap-4 md:grid-cols-2">
        <section class="kartu-bayar">
            <h3 class="flex items-center gap-2.5 text-lg font-extrabold"><x-ikon nama="pesanan" class="text-navy-400" /> Detail pesanan</h3>
            <dl class="mt-4 divide-y divide-line text-sm">
                @foreach (array_filter([
                    ['Jenis produk', $item->product?->nama ?? $item->nama_produk],
                    ['Jumlah', $item->qty.' pcs'],
                    ['Kategori', $item->product?->category?->nama],
                    ...$item->options->map(fn ($o) => [$o->option_nama, $o->option_value])->all(),
                    $item->kustom() ? ['Estimasi selesai', $order->target_siap_pada?->translatedFormat('j M Y') ?? 'Ditentukan setelah desain disetujui'] : null,
                ]) as [$label, $nilai])
                    @continue($nilai === null || $nilai === '')
                    <div class="grid grid-cols-[8.5rem_minmax(0,1fr)] gap-3 py-2.5">
                        <dt class="text-muted">{{ $label }}</dt>
                        <dd class="font-semibold">{{ $nilai }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        @if ($item->kustom())
            <section class="kartu-bayar">
                <h3 class="flex items-center gap-2.5 text-lg font-extrabold"><x-ikon nama="gambar" class="text-navy-400" /> Desain</h3>
                @include('komponen.desain', ['item' => $item, 'order' => $order, 'dalamKartu' => true])
            </section>
        @endif
    </div>

    @if ($item->kustom() && ($item->product->roster_wajib || $adaPemain))
        {{-- Daftar pemain ringkas: lima baris dulu, sisanya lewat "Lihat
             seluruhnya". Pensil menandai baris yang masih bisa diubah; menekannya
             membuka formulir pemain yang sudah ada (KEP-122). --}}
        <section class="kartu-bayar" x-data="{ semua: false, ubah: {{ $adaPemain ? 'false' : 'true' }} }">
            <h3 class="flex items-center gap-2.5 text-lg font-extrabold"><x-ikon nama="pekerja" class="text-navy-400" /> Daftar pemain</h3>

            @if ($adaPemain)
                <table class="tabel mt-4 text-sm" x-show="! ubah">
                    <thead>
                        <tr>
                            <th class="w-12">No.</th>
                            <th>Nama pemain</th>
                            <th class="text-center">Nomor</th>
                            <th class="text-center">Ukuran</th>
                            @unless ($terkunci)
                                <th class="w-12"><span class="sr-only">Ubah</span></th>
                            @endunless
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($item->rosters as $r)
                            <tr @if ($loop->index >= 5) x-show="semua" x-cloak @endif>
                                <td class="tabular text-muted">{{ $loop->iteration }}</td>
                                <td class="font-medium">{{ $r->nama_punggung ?: '-' }}</td>
                                <td class="tabular text-center font-semibold">{{ $r->nomor ?: '-' }}</td>
                                <td class="text-center">{{ $r->labelUkuran() }}{{ $r->jumlah > 1 ? ' × '.$r->jumlah : '' }}</td>
                                @unless ($terkunci)
                                    <td class="text-right">
                                        <button type="button" class="tombol-ikon" title="Ubah"
                                                @click="ubah = true; $nextTick(() => $refs.editor?.scrollIntoView({ block: 'start', behavior: 'smooth' }))">
                                            <x-ikon nama="pensil" ukuran="size-4" />
                                            <span class="sr-only">Ubah data {{ $r->nama_punggung ?: 'pemain '.$loop->iteration }}</span>
                                        </button>
                                    </td>
                                @endunless
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <p class="mt-3 flex flex-wrap items-center gap-2 rounded-[10px] bg-biru-50 px-4 py-2.5 text-sm" x-show="! ubah">
                    <x-ikon nama="bantuan" ukuran="size-4" class="text-biru-700" />
                    Total {{ $item->rosters->sum('jumlah') }} pemain.
                    @if ($item->rosters->count() > 5)
                        <button type="button" class="tautan-lihat" @click="semua = ! semua">
                            <span x-text="semua ? 'Ringkas lagi' : 'Lihat seluruhnya'">Lihat seluruhnya</span>
                            <x-ikon nama="lanjut" ukuran="size-4" />
                        </button>
                    @endif
                    @if ($terkunci)
                        <span class="text-muted">Terkunci sejak desain disetujui.</span>
                    @endif
                </p>
            @endif

            @unless ($terkunci)
                <div x-ref="editor" x-show="ubah" @if ($adaPemain) x-cloak @endif>
                    @include('komponen.roster', ['item' => $item, 'order' => $order])
                    @if ($adaPemain)
                        <button type="button" class="tombol tombol-garis tombol-sm mt-3" @click="ubah = false">Tutup</button>
                    @endif
                </div>
            @endunless
        </section>
    @endif

    @if ($item->kustom())
        {{-- Catatan desain dari pelanggan, bisa diubah sampai desain disetujui. --}}
        <section class="kartu-bayar" x-data="{ ubah: false }">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <h3 class="flex items-center gap-2.5 text-lg font-extrabold"><x-ikon nama="chat" class="text-navy-400" /> Catatan pesanan</h3>
                @unless ($item->sudahDisetujui())
                    <button type="button" class="tombol tombol-garis tombol-sm" x-show="! ubah" @click="ubah = true">
                        <x-ikon nama="pensil" ukuran="size-4" /> Ubah catatan
                    </button>
                @endunless
            </div>
            <p class="mt-3 rounded-[10px] bg-paper px-4 py-3 text-sm leading-relaxed" x-show="! ubah">
                {{ $item->catatan ?: 'Belum ada catatan untuk desainer.' }}
            </p>
            @unless ($item->sudahDisetujui())
                <form method="POST" action="{{ route('lacak.item.catatan', [$order->access_token, $item]) }}" class="mt-3" x-show="ubah" x-cloak>
                    @csrf
                    <label for="catatan-{{ $item->id }}" class="sr-only">Catatan untuk desainer</label>
                    <textarea id="catatan-{{ $item->id }}" name="catatan" rows="3" maxlength="1000" class="kotak-input leading-relaxed"
                              placeholder="Warna, letak logo, ukuran nomor punggung">{{ old('catatan', $item->catatan) }}</textarea>
                    <div class="mt-2 flex gap-2">
                        <button type="submit" class="tombol tombol-navy tombol-sm">Simpan catatan</button>
                        <button type="button" class="tombol tombol-garis tombol-sm" @click="ubah = false">Batal</button>
                    </div>
                </form>
            @endunless
        </section>
    @endif
@endunless
