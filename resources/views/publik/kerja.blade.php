@extends('layouts.kerja')
{{-- Satu pesanan bisa punya beberapa barang, masing-masing dengan QR-nya
     sendiri: tanpa tahap dan nama barang, semua tabnya berjudul sama persis. --}}
@php $tahapJudul = $item->status_item->tahapProduksi(); @endphp
@section('judul', ($tahapJudul ? $tahapJudul->label().': ' : '').$item->nama_produk.' · '.$item->order->kode)
@section('kop', $item->order->kode)

@section('isi')
@php
    use App\Enums\OrderItemStatus;

    $tahap = $item->status_item->tahapProduksi();
    $alur = collect(OrderItemStatus::alur($item->fulfillment_type))
        ->filter(fn (OrderItemStatus $s) => $s->tahapProduksi() !== null)
        ->values();
    $posisi = $alur->search($item->status_item);
@endphp

    <p class="tabular text-sm text-muted">{{ $item->order->kode }}</p>
    <h1 class="mt-1 text-2xl font-extrabold">{{ $item->nama_produk }}</h1>
    <p class="mt-1 text-lg font-semibold">{{ $item->qty }} pcs</p>

    @if ($item->options->isNotEmpty())
        <p class="mt-2 text-sm text-muted">{{ $item->options->pluck('option_value')->implode(' · ') }}</p>
    @endif

    {{-- Tahap yang sedang dipegang ditulis besar: pekerja membuka halaman ini
         sambil berdiri di meja, bukan sambil duduk membaca layar. --}}
    <div class="mt-6 border border-line bg-field p-5">
        @if ($tahap)
            <p class="text-sm text-muted">Tahap sekarang</p>
            <p class="mt-1 text-2xl font-extrabold">{{ $tahap->label() }}</p>

            <form method="POST" action="{{ route('kerja.selesai', $item->kerja_token) }}" class="mt-5 space-y-3">
                @csrf
                <div>
                    <label for="pin" class="label-form">PIN Anda</label>
                    <input type="password" id="pin" name="pin" inputmode="numeric" autocomplete="off"
                           class="kotak-input tabular text-center text-2xl tracking-[0.4em]"
                           minlength="4" maxlength="8" required autofocus>
                    @error('pin')
                        <p class="mt-1 text-sm text-merah">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="tombol tombol-utama w-full" data-teks-proses="Menyimpan...">
                    Tandai {{ $tahap->label() }} selesai
                </button>

                <p class="text-xs leading-relaxed text-muted">
                    Salah tandai? Hubungi admin konveksi; hanya admin yang bisa memperbaikinya.
                </p>
            </form>
        @else
            <p class="text-sm text-muted">Tahap sekarang</p>
            <p class="mt-1 text-2xl font-extrabold">{{ $item->status_item->labelStaf() }}</p>
            <p class="mt-3 text-sm leading-relaxed text-muted">
                Barang ini sedang tidak berada di meja produksi, jadi belum ada yang perlu ditandai.
            </p>
        @endif
    </div>

    {{-- Urutan tahap ikut ditampilkan supaya pekerja tahu barang ini datang dari
         meja mana dan akan ke meja mana berikutnya. --}}
    <ol class="mt-6 divide-y divide-line border border-line bg-field text-sm">
        @foreach ($alur as $i => $status)
            @php
                $job = $item->productionJobs->firstWhere('tahap', $status->tahapProduksi());
                $lewat = $posisi !== false && $i < $posisi;
            @endphp
            <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                <span class="{{ $posisi === $i ? 'font-semibold' : ($lewat ? 'text-muted' : '') }}">
                    {{ $status->tahapProduksi()->label() }}
                </span>
                <span class="text-right text-xs text-muted">
                    @if ($job?->selesai_pada)
                        {{ $job->pekerja?->nama ?? 'admin' }} ·
                        {{ $job->selesai_pada->translatedFormat('j M, H:i') }}
                    @elseif ($posisi === $i)
                        sedang dikerjakan
                    @endif
                </span>
            </li>
        @endforeach
    </ol>

    @if ($item->rosters->isNotEmpty())
        <h2 class="mt-8 font-bold">Daftar ukuran</h2>
        <ul class="mt-2 divide-y divide-line border border-line bg-field text-sm">
            @foreach ($item->rosters as $r)
                <li class="flex items-center justify-between gap-3 px-4 py-2">
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
    @endif

    @if ($item->catatan)
        <div class="mt-6 border-l-2 border-kuning bg-kuning-50 p-4 text-sm leading-relaxed">
            {{ $item->catatan }}
        </div>
    @endif
@endsection
