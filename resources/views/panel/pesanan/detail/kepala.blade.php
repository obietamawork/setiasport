{{--
    Kartu kepala detail pesanan (KEP-124): jenis, kode, tanggal, total, dan
    deret tahap tiap barang seperti gambar karyawan. Tombol Perbarui progres
    membuka pop-up yang hanya menawarkan langkah yang sah.
--}}
@php
    use App\Support\ProgresStaf;

    $adaKustom = $pesanan->items->contains(fn ($i) => $i->kustom());
    $adaStok = $pesanan->items->contains(fn ($i) => ! $i->kustom());
    $jenis = match (true) {
        $adaKustom && $adaStok => 'Kustom + stok toko',
        $adaKustom => 'Kustom',
        default => 'Stok toko',
    };
    $banyakBarang = $pesanan->items->count() > 1;
@endphp

<section class="kartu-bayar" x-data="{ tersalin: false }">
    <div class="flex flex-wrap items-start gap-4">
        <span class="flex size-14 shrink-0 items-center justify-center rounded-[14px] bg-navy-50 text-navy-700">
            <x-ikon :nama="$adaKustom ? 'kaos' : 'produk'" ukuran="size-7" />
        </span>

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <span class="chip {{ $adaKustom ? 'chip-pink' : 'chip-ok' }}">{{ $jenis }}</span>
                <span class="chip {{ $pesanan->status->warna() }}">
                    <x-ikon :nama="$pesanan->status->ikon()" ukuran="size-3.5" />
                    {{ $pesanan->status->label() }}
                </span>
            </div>
            <div class="mt-1.5 flex items-center gap-2">
                <p class="tabular text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $pesanan->kode }}</p>
                <button type="button" class="tombol-ikon border-transparent" title="Salin kode pesanan"
                        @click="navigator.clipboard.writeText(@js($pesanan->kode)).then(() => { tersalin = true; setTimeout(() => tersalin = false, 2000) })">
                    <x-ikon nama="salin" ukuran="size-4" />
                    <span class="sr-only">Salin kode pesanan</span>
                </button>
                <span x-show="tersalin" x-cloak class="text-xs text-ok">Tersalin</span>
            </div>
            <p class="mt-1 flex flex-wrap items-center gap-1.5 text-sm text-muted">
                <x-ikon nama="kalender" ukuran="size-4" />
                Dibuat: <span class="tabular">{{ $pesanan->created_at->translatedFormat('j M Y, H.i') }}</span>
            </p>
        </div>

        <div class="flex w-full flex-wrap items-end justify-between gap-3 sm:w-auto sm:flex-col sm:items-end">
            @if ($bolehKelola && $barangProgres->isNotEmpty())
                <button type="button" class="tombol tombol-garis order-2 sm:order-1" @click="$dispatch('buka-progres')">
                    <x-ikon nama="pensil" ukuran="size-4" />
                    Perbarui progres
                </button>
            @endif
            <div class="order-1 sm:order-2 sm:text-right">
                <p class="text-sm text-muted">Total pesanan</p>
                <p class="tabular text-2xl font-extrabold text-ink">{{ rupiah($pesanan->total) }}</p>
            </div>
        </div>
    </div>

    {{-- Satu deret tahap per barang. Pesanan campuran memberi nama pada tiap
         deret; barang kustom dan barang stok punya jalur yang berbeda. --}}
    @foreach ($pesanan->items as $item)
        @php $fase = ProgresStaf::fase($item); @endphp
        <div class="mt-6 border-t border-line pt-5">
            @if ($banyakBarang)
                <p class="mb-3 text-sm font-semibold">{{ $item->nama_produk }}</p>
            @endif

            @if ($fase === [])
                <p class="text-sm text-muted">Barang ini dibatalkan.</p>
            @else
                <div class="-mx-1 overflow-x-auto px-1 pb-1">
                    <ol class="tahap-datar min-w-[34rem]" style="grid-template-columns: repeat({{ count($fase) }}, minmax(0, 1fr))"
                        aria-label="Tahap {{ $item->nama_produk }}: {{ $item->status_item->labelStaf() }}">
                        @foreach ($fase as $f)
                            <li class="tahap-datar-{{ $f['keadaan'] }}">
                                <span class="tahap-datar-bulat">
                                    <x-ikon :nama="$f['keadaan'] === 'lewat' ? 'centang' : $f['ikon']" ukuran="size-5" />
                                </span>
                                <span class="mt-2 block text-sm font-semibold leading-tight">{{ $f['label'] }}</span>
                                @if ($f['keterangan'])
                                    <span class="mt-0.5 block text-xs text-muted">{{ $f['keterangan'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </div>
    @endforeach
</section>
