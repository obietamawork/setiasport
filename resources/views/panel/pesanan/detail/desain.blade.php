{{--
    File desain (KEP-124): mockup terbaru tiap barang kustom dengan nama
    berkas, ukuran, dan statusnya; versi lama dilipat. Kirim mockup versi
    baru (KEP-098) tetap di sini selama pelanggan belum menyetujui.
--}}
@php
    use App\Enums\DesignAssetType;
    use App\Enums\OrderItemStatus;
    use Illuminate\Support\Facades\Storage;

    $kustom = $pesanan->items->filter(fn ($i) => $i->kustom() && $i->status_item !== OrderItemStatus::DIBATALKAN);

    $ukuranBerkas = function (?string $path): ?string {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $byte = Storage::disk('public')->size($path);

        return $byte >= 1_048_576
            ? number_format($byte / 1_048_576, 1, ',', '.').' MB'
            : max(1, (int) round($byte / 1024)).' KB';
    };
@endphp

<section class="kartu-bayar">
    <h2 class="judul-kartu"><x-ikon nama="gambar" class="text-navy-400" /> File desain</h2>

    <div class="mt-4 space-y-5">
        @foreach ($kustom as $item)
            @php
                $mockup = $item->designAssets->where('tipe', DesignAssetType::MOCKUP)->sortByDesc('versi')->values();
                $terbaru = $mockup->first();
                $lama = $mockup->slice(1);
                $menunggu = $item->status_item === OrderItemStatus::MENUNGGU_PERSETUJUAN;
            @endphp

            <div @class(['border-t border-line pt-5' => ! $loop->first])>
                @if ($kustom->count() > 1)
                    <p class="mb-2 text-sm font-semibold">{{ $item->nama_produk }}</p>
                @endif

                @if ($terbaru)
                    @php
                        $berkas = $terbaru->path ? basename($terbaru->path) : null;
                        $ukuran = $ukuranBerkas($terbaru->path);
                        $disetujui = $item->approvedDesign && $item->approvedDesign->is($terbaru);
                    @endphp
                    <div class="flex items-center gap-4">
                        @if ($terbaru->path)
                            <img src="{{ asset('storage/'.$terbaru->path) }}" alt="Mockup v{{ $terbaru->versi }}" width="88" height="88"
                                 loading="lazy" class="size-22 shrink-0 rounded-[10px] border border-line bg-paper object-cover">
                        @else
                            <span class="flex size-22 shrink-0 items-center justify-center rounded-[10px] border border-dashed border-line-strong text-navy-300">
                                <x-ikon nama="gambar" ukuran="size-7" />
                            </span>
                        @endif

                        <div class="min-w-0 flex-1 text-sm">
                            <p class="font-semibold break-all">{{ $berkas ?? 'Tautan file siap cetak' }}</p>
                            <p class="text-xs text-muted">
                                Mockup v{{ $terbaru->versi }}
                                @if ($ukuran) &middot; {{ $ukuran }} @endif
                                @if ($berkas) &middot; {{ strtoupper(pathinfo($berkas, PATHINFO_EXTENSION)) }} @endif
                                &middot; {{ $terbaru->created_at->translatedFormat('j M, H.i') }}
                            </p>
                            <p class="mt-1">
                                @if ($disetujui)
                                    <span class="chip chip-ok"><x-ikon nama="centang" ukuran="size-3.5" /> Disetujui pelanggan</span>
                                @elseif ($menunggu)
                                    <span class="chip chip-kuning"><x-ikon nama="jam" ukuran="size-3.5" /> Menunggu persetujuan</span>
                                @else
                                    <span class="chip chip-ok"><x-ikon nama="centang" ukuran="size-3.5" /> Sudah diunggah</span>
                                @endif
                            </p>
                        </div>
                    </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($terbaru->path)
                                <a href="{{ asset('storage/'.$terbaru->path) }}" target="_blank" rel="noopener" class="tombol tombol-garis tombol-sm">
                                    <x-ikon nama="mata" ukuran="size-4" /> Lihat file
                                </a>
                                <a href="{{ asset('storage/'.$terbaru->path) }}" download class="tombol-ikon" title="Unduh">
                                    <x-ikon nama="unduh" ukuran="size-4" />
                                    <span class="sr-only">Unduh mockup v{{ $terbaru->versi }}</span>
                                </a>
                            @endif
                            @if ($terbaru->external_url)
                                <a href="{{ $terbaru->external_url }}" target="_blank" rel="noopener" class="tombol tombol-garis tombol-sm">
                                    <x-ikon nama="buka-luar" ukuran="size-4" /> File siap cetak
                                </a>
                            @endif
                        </div>

                    @if ($lama->isNotEmpty())
                        <details class="mt-3 text-sm">
                            <summary class="cursor-pointer text-muted">Versi sebelumnya ({{ $lama->count() }})</summary>
                            <ul class="mt-2 space-y-1.5">
                                @foreach ($lama as $m)
                                    <li class="flex flex-wrap items-center gap-3">
                                        <span class="font-medium">Mockup v{{ $m->versi }}</span>
                                        @if ($m->path)
                                            <a href="{{ asset('storage/'.$m->path) }}" target="_blank" rel="noopener" class="tautan">Lihat</a>
                                        @endif
                                        @if ($m->external_url)
                                            <a href="{{ $m->external_url }}" target="_blank" rel="noopener" class="tautan">File siap cetak</a>
                                        @endif
                                        <span class="tabular text-xs text-muted">{{ $m->created_at->translatedFormat('j M, H.i') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                @else
                    <p class="rounded-[10px] bg-paper px-4 py-3 text-sm text-muted">
                        Belum ada mockup. Desainer mengunggahnya dari Antrian desain.
                    </p>
                @endif

                {{-- Tautan file desain dari pelanggan (KEP-139), terbaru di atas. --}}
                @php
                    $dariPelanggan = $item->designAssets
                        ->where('tipe', DesignAssetType::UPLOAD_PELANGGAN)
                        ->whereNotNull('external_url')
                        ->sortByDesc('versi');
                @endphp
                @if ($dariPelanggan->isNotEmpty())
                    <div class="mt-3 rounded-[10px] bg-paper px-4 py-3 text-sm">
                        <p class="font-semibold">File dari pelanggan</p>
                        <ul class="mt-1 space-y-1">
                            @foreach ($dariPelanggan as $f)
                                <li class="flex flex-wrap items-baseline gap-x-2">
                                    <a href="{{ $f->external_url }}" target="_blank" rel="noopener noreferrer"
                                       class="tautan break-all">{{ $f->external_url }}</a>
                                    <span class="tabular text-xs text-muted">{{ $f->created_at->translatedFormat('j M, H.i') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Mockup versi baru selama pelanggan belum menyetujui (KEP-098). --}}
                @if ($menunggu && $izin->punyaIzin('produksi.desain'))
                    <details class="mt-3 border-t border-line pt-3">
                        <summary class="cursor-pointer text-sm text-muted">Kirim mockup versi baru</summary>
                        <form method="POST" action="{{ route('panel.mockup.simpan', $item) }}"
                              enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="w-56">
                                <label for="preview-{{ $item->id }}" class="label-form">Preview mockup</label>
                                <input type="file" id="preview-{{ $item->id }}" name="preview"
                                       accept=".jpg,.jpeg,.png,.webp" class="kotak-input">
                            </div>
                            <div class="min-w-56 flex-1">
                                <label for="tautan-{{ $item->id }}" class="label-form">Tautan file siap cetak</label>
                                <input type="url" id="tautan-{{ $item->id }}" name="tautan" class="kotak-input"
                                       placeholder="https://drive.google.com/...">
                            </div>
                            <button type="submit" class="tombol tombol-navy tombol-sm">Kirim versi baru</button>
                            <p class="w-full text-xs leading-relaxed text-muted">
                                Versi lama diganti, pelanggan menerima email untuk memeriksanya lagi.
                                Jatah revisi pelanggan tidak berkurang.
                            </p>
                        </form>
                    </details>
                @endif
            </div>
        @endforeach
    </div>
</section>
