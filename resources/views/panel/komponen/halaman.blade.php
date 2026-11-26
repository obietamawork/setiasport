{{--
    Penomoran halaman daftar panel (KEP-113): "Menampilkan 1 sampai 20 dari 57
    pesanan" di kiri, Sebelumnya, nomor, dan Selanjutnya di kanan. Angka
    totalnya disebut supaya staf tahu daftar yang ia saring tidak terpotong.

    @var \Illuminate\Pagination\LengthAwarePaginator $daftar
    @var string $benda  "pesanan", "produk", ...
--}}
@if ($daftar->total() > 0)
    <nav class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3 text-sm sm:px-5"
         aria-label="Halaman {{ $benda }}">
        <p class="tabular text-muted">
            Menampilkan {{ $daftar->firstItem() }} sampai {{ $daftar->lastItem() }} dari {{ $daftar->total() }} {{ $benda }}
        </p>

        <div class="flex flex-wrap items-center gap-3">
        @isset($tampil)
            {{-- Jumlah baris per halaman (KEP-119). Penyaring lain ikut terbawa. --}}
            <form method="GET" class="flex items-center gap-2 text-sm" data-saring-otomatis>
                @foreach (request()->except(['tampil', 'page']) as $nama => $nilai)
                    @if (is_string($nilai))
                        <input type="hidden" name="{{ $nama }}" value="{{ $nilai }}">
                    @endif
                @endforeach
                <label for="tampil-{{ $benda }}" class="text-muted">Tampilkan</label>
                <select id="tampil-{{ $benda }}" name="tampil" class="kotak-input w-auto py-1.5">
                    @foreach ($tampil as $n)
                        <option value="{{ $n }}" @selected($daftar->perPage() === $n)>{{ $n }}</option>
                    @endforeach
                </select>
            </form>
        @endisset

        @if ($daftar->hasPages())
            <div class="flex items-center gap-1.5">
                @if ($daftar->onFirstPage())
                    <span class="tombol tombol-garis tombol-sm cursor-default text-navy-300 hover:bg-field" aria-disabled="true">
                        <x-ikon nama="kembali" ukuran="size-4" /> <span class="max-[22.5rem]:sr-only">Sebelumnya</span>
                    </span>
                @else
                    <a href="{{ $daftar->previousPageUrl() }}" rel="prev" class="tombol tombol-garis tombol-sm">
                        <x-ikon nama="kembali" ukuran="size-4" /> <span class="max-[22.5rem]:sr-only">Sebelumnya</span>
                    </a>
                @endif

                @foreach ($daftar->getUrlRange(max(1, $daftar->currentPage() - 2), min($daftar->lastPage(), $daftar->currentPage() + 2)) as $nomor => $url)
                    @if ($nomor === $daftar->currentPage())
                        <span class="tombol tombol-navy tombol-sm tabular" aria-current="page">{{ $nomor }}</span>
                    @else
                        <a href="{{ $url }}" class="tombol tombol-garis tombol-sm tabular hidden sm:inline-flex">{{ $nomor }}</a>
                    @endif
                @endforeach

                @if ($daftar->hasMorePages())
                    <a href="{{ $daftar->nextPageUrl() }}" rel="next" class="tombol tombol-garis tombol-sm">
                        <span class="max-[22.5rem]:sr-only">Selanjutnya</span> <x-ikon nama="lanjut" ukuran="size-4" />
                    </a>
                @else
                    <span class="tombol tombol-garis tombol-sm cursor-default text-navy-300 hover:bg-field" aria-disabled="true">
                        <span class="max-[22.5rem]:sr-only">Selanjutnya</span> <x-ikon nama="lanjut" ukuran="size-4" />
                    </span>
                @endif
            </div>
        @endif
        </div>
    </nav>
@endif
