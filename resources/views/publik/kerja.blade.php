@extends('layouts.kerja')
{{-- Satu pesanan bisa punya beberapa barang, masing-masing dengan QR-nya
     sendiri: tanpa tahap dan nama barang, semua tabnya berjudul sama persis. --}}
@php $tahapJudul = $item->status_item->mejaKerja(); @endphp
@section('judul', ($tahapJudul ? $tahapJudul->label().': ' : '').$item->nama_produk.' · '.$item->order->kode)
@section('kop', $item->order->kode)

@section('isi')
@php
    use App\Enums\OrderItemStatus;

    // Barang yang masih antri di meja printing ditandai dari meja itu (KEP-100).
    $tahap = $item->status_item->mejaKerja();
    $alur = collect(OrderItemStatus::alur($item->fulfillment_type))
        ->filter(fn (OrderItemStatus $s) => $s->tahapProduksi() !== null)
        ->values();
    $posisi = $alur->search(fn (OrderItemStatus $s) => $s->tahapProduksi() === $tahap);
@endphp

    {{-- Tampilan dari gambar klien (KEP-160). --}}
    <p class="tabular text-sm text-muted">{{ $item->order->kode }}</p>
    <h1 class="mt-1 text-2xl font-extrabold leading-tight">{{ $item->nama_produk }}</h1>
    <p class="mt-1 text-lg font-bold">{{ $item->qty }} pcs</p>

    @if ($item->options->isNotEmpty())
        <p class="mt-1 text-sm text-muted">{{ $item->options->pluck('option_value')->implode(' · ') }}</p>
    @endif

    {{-- Tahap yang sedang dipegang ditulis besar: pekerja membuka halaman ini
         sambil berdiri di meja, bukan sambil duduk membaca layar. --}}
    <section class="kartu mt-6 p-4 sm:p-5">
        <h2 class="text-lg font-extrabold">Tahap Pekerjaan Saat Ini</h2>

        @if ($tahap)
            <div class="mt-3 flex items-center gap-4 rounded-[12px] bg-navy-50 p-4">
                <span class="flex size-20 shrink-0 items-center justify-center rounded-[12px] bg-white text-navy shadow-sm">
                    <x-ikon-tahap :tahap="$tahap" />
                </span>
                <span class="h-14 w-px shrink-0 bg-line-strong" aria-hidden="true"></span>
                <div class="min-w-0">
                    <p class="text-2xl font-extrabold leading-tight">{{ $tahap->label() }}</p>
                    <span class="chip chip-kuning mt-2">Sedang Dikerjakan</span>
                </div>
            </div>

            <form method="POST" action="{{ route('kerja.selesai', $item->kerja_token) }}" class="mt-5 space-y-3"
                  x-data="{ lihat: false }">
                @csrf
                <input type="hidden" name="dari" value="{{ $item->status_item->value }}">
                <div>
                    <label for="pin" class="mb-1.5 block font-bold">PIN Divisi {{ $tahap->label() }}</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-muted" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/><path d="M12 15v2"/>
                        </svg>
                        <input type="password" :type="lihat ? 'text' : 'password'" id="pin" name="pin" inputmode="numeric"
                               autocomplete="off" placeholder="Masukkan PIN divisi"
                               class="kotak-input tabular py-3 pr-12 pl-11 text-lg"
                               minlength="4" maxlength="8" required autofocus>
                        <button type="button" @click="lihat = !lihat" :aria-pressed="lihat.toString()"
                                class="absolute inset-y-0 right-0 flex items-center px-3.5 text-muted hover:text-ink">
                            <x-ikon nama="mata" ukuran="size-6" x-show="!lihat" />
                            <x-ikon nama="mata-coret" ukuran="size-6" x-show="lihat" x-cloak />
                            <span class="sr-only">Tampilkan PIN</span>
                        </button>
                    </div>
                    @error('pin')
                        <p class="mt-1 text-sm text-merah">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="tombol tombol-navy w-full gap-2 py-3.5 text-base" data-teks-proses="Menyimpan...">
                    <x-ikon nama="centang" ukuran="size-5" />
                    Tandai {{ $tahap->label() }} Selesai
                </button>

                <p class="flex items-start gap-2 text-sm leading-relaxed text-muted">
                    <svg class="mt-0.5 size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                         stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
                    <span>Hanya divisi yang berwenang dapat memperbarui progres. Salah tandai? Hubungi admin konveksi.</span>
                </p>
            </form>
        @elseif ($item->status_item === OrderItemStatus::DIBATALKAN)
            {{-- Lembar kerjanya masih menempel di kain. Yang memindainya harus
                 tahu untuk berhenti, bukan sekadar "tidak ada yang ditandai". --}}
            <div class="mt-3 rounded-[12px] bg-merah-50 p-4">
                <p class="text-2xl font-extrabold text-merah">Pesanan dibatalkan</p>
                <p class="mt-2 text-sm leading-relaxed">
                    Hentikan pengerjaannya. Serahkan kain dan lembar kerja ini ke admin konveksi.
                </p>
            </div>
        @else
            <div class="mt-3 rounded-[12px] bg-navy-50 p-4">
                <p class="text-2xl font-extrabold">{{ $item->status_item->labelStaf() }}</p>
                <p class="mt-2 text-sm leading-relaxed text-muted">
                    Barang ini sedang tidak berada di meja produksi, jadi belum ada yang perlu ditandai.
                </p>
            </div>
        @endif
    </section>

    {{-- Urutan tahap ikut ditampilkan supaya pekerja tahu barang ini datang dari
         meja mana dan akan ke meja mana berikutnya. --}}
    <section class="kartu mt-5 p-4 sm:p-5">
        <h2 class="text-lg font-extrabold">Progres Produksi</h2>
        <ol class="mt-3 divide-y divide-line">
            @foreach ($alur as $i => $status)
                @php
                    $job = $item->productionJobs->firstWhere('tahap', $status->tahapProduksi());
                    $sekarang = $posisi === $i;
                    $selesai = ! $sekarang && ($job?->selesai_pada !== null || ($posisi !== false && $i < $posisi));
                @endphp
                <li @class(['flex items-center gap-3 px-2 py-2.5', 'rounded-[10px] border-0 bg-navy-50' => $sekarang])>
                    @if ($selesai)
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-ok text-white">
                            <x-ikon nama="centang" ukuran="size-4" />
                        </span>
                    @elseif ($sekarang)
                        <span class="flex size-7 shrink-0 items-center justify-center text-navy">
                            <x-ikon nama="jam" ukuran="size-7" />
                        </span>
                    @else
                        <span class="size-7 shrink-0 rounded-full border-2 border-line-strong" aria-hidden="true"></span>
                    @endif

                    <span @class(['min-w-0 flex-1', 'font-bold' => $sekarang, 'font-medium' => ! $sekarang])>
                        {{ $status->tahapProduksi()->label() }}
                    </span>

                    <span class="shrink-0 text-right text-sm text-muted">
                        @if ($sekarang)
                            <span class="chip chip-biru">Sedang Dikerjakan</span>
                        @elseif ($selesai)
                            Selesai{{ $job?->selesai_pada ? ' · '.$job->selesai_pada->translatedFormat('j M, H:i') : '' }}
                        @else
                            Menunggu
                        @endif
                    </span>
                </li>
            @endforeach
        </ol>
    </section>

    @if ($item->rosters->isNotEmpty())
        <details class="kartu group mt-5">
            <summary class="flex cursor-pointer list-none items-center gap-3 p-4 sm:p-5 [&::-webkit-details-marker]:hidden">
                <x-ikon nama="kaos" ukuran="size-6" />
                <span class="flex-1 text-lg font-extrabold">Daftar Ukuran ({{ $item->rosters->sum('jumlah') }} pcs)</span>
                <x-ikon nama="turun" ukuran="size-5" class="transition-transform group-open:rotate-180" />
            </summary>
            <ul class="divide-y divide-line border-t border-line text-sm">
                @foreach ($item->rosters as $r)
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5 sm:px-5">
                        <span>
                            {{ $r->nama_punggung ?: 'Tanpa nama' }}
                            @if ($r->nomor)
                                <span class="text-muted">#{{ $r->nomor }}</span>
                            @endif
                        </span>
                        <span class="tabular">{{ $r->labelUkuran() }} · {{ $r->jumlah }} pcs</span>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif

    @if ($item->catatan)
        <div class="mt-5 flex gap-3 rounded-[12px] bg-kuning-50 p-4">
            <x-ikon nama="daftar" ukuran="size-6" class="mt-0.5" />
            <div class="min-w-0">
                <p class="font-extrabold">Catatan Pesanan</p>
                <p class="mt-1 text-sm leading-relaxed">{{ $item->catatan }}</p>
            </div>
        </div>
    @endif
@endsection
