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
    {{-- Di HP chip tahap turun ke bawah nama barang. Dulu nama dan tanggalnya
         terhimpit jadi satu-dua kata per baris karena kolom teks boleh menyusut
         sampai nol sebelum chip mau turun baris. --}}
    <div class="flex items-center gap-3 sm:gap-4">
        @if ($gambar)
            <img src="{{ $gambar }}" alt="" width="64" height="64" class="size-12 shrink-0 rounded-full border border-line object-cover sm:size-16">
        @else
            <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-navy-50 text-navy-300 sm:size-16">
                <x-ikon :nama="$item->kustom() ? 'kaos' : 'produk'" ukuran="size-6 sm:size-7" />
            </span>
        @endif
        <div class="flex min-w-0 flex-1 flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <div class="min-w-0 flex-1 basis-52">
                <h3 class="text-base leading-snug font-extrabold sm:text-lg">{{ $item->nama_produk }}</h3>
                <p class="mt-0.5 text-sm text-muted">
                    <x-ikon nama="kalender" ukuran="size-4" class="mr-1 inline-block align-[-3px]" />Pesanan dibuat:
                    <span class="tabular whitespace-nowrap">{{ $order->created_at->translatedFormat('j M Y, H.i') }}</span>
                </p>
            </div>
            @if ($linimasa['batal'])
                <span class="chip chip-merah shrink-0 px-3 py-1.5 text-sm"><x-ikon nama="silang" ukuran="size-4" /> Dibatalkan</span>
            @elseif ($kini)
                <span class="chip chip-navy shrink-0 px-3 py-1.5 text-sm"><x-ikon nama="pengaturan" ukuran="size-4" /> {{ $kini['label'] }}</span>
            @else
                <span class="chip chip-ok shrink-0 px-3 py-1.5 text-sm"><x-ikon nama="centang" ukuran="size-4" /> Selesai</span>
            @endif
        </div>
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

        <x-tahap-menurun :fase="$linimasa['fase']" class="mt-5 sm:hidden" />

        @if ($kini && $kini['catatan'])
            <p class="mt-4 rounded-[10px] bg-navy-50 px-4 py-2.5 text-sm leading-relaxed">{{ $kini['catatan'] }}</p>
        @endif

    @endif
</article>

{{-- Progres produksi di bawah deret tahap (KEP-126), seperti gambar klien:
     persen, bilah, dan lima meja produksi. Hanya setelah produksi mulai. --}}
@php $produksi = $linimasa['batal'] ? null : collect($linimasa['fase'])->first(fn ($f) => $f['subtahap'] !== [] && $f['keadaan'] !== 'nanti'); @endphp
@if ($produksi)
    <section class="kartu-bayar" aria-label="Progres produksi {{ $item->nama_produk }}">
        <div class="flex items-baseline justify-between gap-3">
            <h3 class="text-lg font-extrabold">Progres produksi</h3>
            <p class="tabular text-lg font-extrabold">{{ $produksi['persen'] }}%</p>
        </div>
        <div class="mt-3 h-3 overflow-hidden rounded-full bg-navy-50" role="progressbar"
             aria-valuenow="{{ $produksi['persen'] }}" aria-valuemin="0" aria-valuemax="100">
            <div class="h-full rounded-full bg-navy" style="width: {{ $produksi['persen'] }}%"></div>
        </div>

        <ol class="mt-5 grid gap-3 sm:grid-cols-5 sm:gap-2">
            @foreach ($produksi['subtahap'] as $sub)
                <li class="flex items-center gap-3 sm:flex-col sm:gap-1.5 sm:text-center">
                    <span @class([
                        'flex size-10 shrink-0 items-center justify-center rounded-full',
                        'bg-ok text-white' => $sub['keadaan'] === 'lewat',
                        'bg-navy-100 text-navy-700' => $sub['keadaan'] === 'kini',
                        'text-muted' => $sub['keadaan'] === 'nanti',
                    ])>
                        <x-ikon :nama="match ($sub['keadaan']) { 'lewat' => 'centang', 'kini' => 'muat-ulang', default => 'jam' }" ukuran="size-5" />
                    </span>
                    <span class="min-w-0">
                        <span @class([
                            'block font-bold',
                            'rounded-[8px] bg-navy-50 px-2 text-navy-700' => $sub['keadaan'] === 'kini',
                        ])>{{ $sub['label'] }}</span>
                        <span class="block text-sm text-muted tabular">
                            @if ($sub['keadaan'] === 'lewat')
                                {{ $sub['waktu']?->translatedFormat('j M') ?? 'Selesai' }}
                            @elseif ($sub['keadaan'] === 'kini')
                                Sedang dikerjakan
                            @else
                                Menunggu
                            @endif
                        </span>
                    </span>
                </li>
            @endforeach
        </ol>
    </section>
@endif

@if (! $linimasa['batal'] && $item->status_item === App\Enums\OrderItemStatus::MENUNGGU_PERSETUJUAN)
    {{-- Selama menunggu persetujuan, kartu persetujuan (KEP-125) memuat
         detail, desain, dan daftar pemain sekaligus. --}}
    @include('publik.lacak.persetujuan', ['item' => $item, 'order' => $order])
@elseif (! $linimasa['batal'])
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
                    <div class="grid grid-cols-[6.75rem_minmax(0,1fr)] gap-3 py-2.5 sm:grid-cols-[8.5rem_minmax(0,1fr)]">
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
                {{-- Di layar di bawah 360px empat kolom ini tidak muat; tabelnya digeser
                     di dalam pembungkusnya, bukan menggeser halaman. --}}
                <div class="mt-4 overflow-x-auto" x-show="! ubah">
                <table class="tabel text-sm">
                    <thead>
                        <tr>
                            {{-- Nomor urut disembunyikan di HP: nama dan ukuran lebih perlu tempat. --}}
                            <th class="hidden w-12 sm:table-cell">No.</th>
                            <th class="whitespace-nowrap">Nama pemain</th>
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
                                <td class="tabular hidden text-muted sm:table-cell">{{ $loop->iteration }}</td>
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
                </div>

                <p class="mt-3 flex flex-wrap items-center gap-2 rounded-[10px] bg-navy-50 px-4 py-2.5 text-sm" x-show="! ubah">
                    <x-ikon nama="bantuan" ukuran="size-4" class="text-navy-700" />
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

@endif

@unless ($linimasa['batal'])
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
