@extends('layouts.panel')
@section('judul', 'Antrian '.$stage->label())

@section('isi')
@php
    use App\Enums\ProductionStatus;
    use App\Enums\ProductionStage;
@endphp

<x-kop :judul="'Antrian '.$stage->label()"
       :kembali="$tabDivisi->count() > 1 ? route('panel.antrian.index') : null"
       kembali-label="Semua divisi"
       :catatan="'Daftar pesanan pada divisi '.Str::lower($stage->label()).'.'" />

{{-- Tab semua divisi seperti gambar klien (KEP-129): yang aktif berwarna
     navy, masing-masing dengan jumlah pekerjaannya. Orang yang memegang dua
     meja bolak-balik di antara keduanya sepanjang hari. --}}
@if ($tabDivisi->count() > 1)
    <nav class="mb-4 flex flex-wrap gap-2" aria-label="Divisi">
        @foreach ($tabDivisi as $tab)
            @php $aktif = $tab['tahap'] === $stage; @endphp
            <a href="{{ route('panel.antrian.tahap', strtolower($tab['tahap']->value)) }}"
               @class(['tombol tombol-sm', 'tombol-navy' => $aktif, 'tombol-garis' => ! $aktif])
               @if ($aktif) aria-current="page" @endif>
                <x-ikon :nama="$tab['tahap']->ikon()" ukuran="size-4" />
                <span>{{ $tab['tahap']->label() }} ({{ $tab['jumlah'] }})</span>
            </a>
        @endforeach
    </nav>
@endif

{{-- Petunjuk dan kotak cari dalam satu bilah, seperti gambar klien. --}}
<div class="mb-2 flex flex-wrap items-center gap-3 rounded-[12px] border border-biru-100 bg-biru-50 px-4 py-3">
    <x-ikon nama="bantuan" class="shrink-0 text-biru-700" />
    <div class="min-w-48 flex-1 text-sm">
        <p class="font-semibold">Antrian {{ Str::lower($stage->label()) }}</p>
        <p class="text-muted">
            {{ $pekerjaan->count() }} pekerjaan{{ $cari !== '' ? ' cocok dengan "'.$cari.'"' : ' menunggu di meja ini' }}.
            Pilih pesanan di bawah, lalu mulai kerjakan atau tandai selesai.
        </p>
    </div>
    <form method="GET" role="search" class="relative w-full sm:w-72">
        <label for="cari-antrian" class="sr-only">Cari no. pesanan, nama pelanggan, atau produk</label>
        <x-ikon nama="cari" ukuran="size-4" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-navy-400" />
        <input type="search" id="cari-antrian" name="q" value="{{ $cari }}" maxlength="60"
               placeholder="Cari no. pesanan / nama..." class="kotak-input bg-field pl-9">
    </form>
</div>

<div x-data="{ terpilih: [] }">

@if ($pekerjaan->isEmpty())
    <div class="kartu mt-6 p-8 text-center">
        <x-ikon :nama="$stage->ikon()" ukuran="size-9" class="mx-auto text-navy-300" />
        @if ($cari !== '')
            <p class="mt-3 text-lg font-bold">Tidak ada yang cocok</p>
            <p class="mt-2 text-sm text-muted">
                Tidak ada pekerjaan {{ Str::lower($stage->label()) }} untuk "{{ $cari }}".
                <a href="{{ route('panel.antrian.tahap', strtolower($stage->value)) }}" class="tautan">Tampilkan semua</a>
            </p>
        @else
            <p class="mt-3 text-lg font-bold">Antrian kosong</p>
            <p class="mt-2 text-sm text-muted">
                Tidak ada pekerjaan {{ Str::lower($stage->label()) }} yang menunggu. Cek lagi nanti.
            </p>
        @endif
    </div>
@else
    {{-- Printing berjalan per ROLL kertas, bukan per pesanan (KEP-051): satu
         roll memuat beberapa pesanan, dan pesanan kecil diselipkan untuk
         menggenapi sisa kertas. Centang isinya, lalu satu QR mewakili roll itu. --}}
    @php $rollBisa = $stage === \App\Enums\ProductionStage::PRINT; @endphp

    <form method="POST" action="{{ route('panel.roll.simpan') }}" id="roll-printing"
          @if (! $rollBisa) class="hidden" @endif>
        @csrf

        @if ($rollBisa)
            <div class="kartu mt-6 flex flex-wrap items-center gap-3 p-4">
                <div class="min-w-56 flex-1">
                    <p class="font-semibold">Buat roll printing</p>
                    <p class="mt-0.5 text-sm text-muted">
                        Centang pesanan yang masuk satu roll kertas, lalu cetak QR-nya sekali.
                    </p>
                </div>

                <input type="text" name="catatan" maxlength="200" class="kotak-input w-48"
                       placeholder="Catatan roll (opsional)">

                <button type="submit" class="tombol tombol-navy tombol-sm"
                        :disabled="terpilih.length === 0">
                    Buat roll (<span x-text="terpilih.length"></span>)
                </button>
            </div>
        @endif
    </form>

    <div class="mt-3 space-y-3">
        @foreach ($pekerjaan as $item)
            @php
                $job = $item->productionJobs->firstWhere('tahap', $stage);
                $dikerjakan = $job?->status === ProductionStatus::DIKERJAKAN;
            @endphp

            <div class="kartu overflow-hidden {{ $dikerjakan ? 'border-l-2 border-l-merah' : '' }}">
                <div class="flex flex-wrap items-start gap-3 p-4">
                    @if ($rollBisa)
                        <label class="flex items-center pt-1"
                               x-data="{ id: {{ $item->id }} }">
                            <input type="checkbox" name="item[]" value="{{ $item->id }}"
                                   form="roll-printing" class="size-4 accent-navy"
                                   @change="$event.target.checked
                                       ? terpilih.push(id)
                                       : terpilih.splice(terpilih.indexOf(id), 1)">
                            <span class="sr-only">Masukkan {{ $item->nama_produk }} ke roll</span>
                        </label>
                    @endif

                    @php
                        $gambar = $item->approvedDesign?->path
                            ? asset('storage/'.$item->approvedDesign->path)
                            : $item->product?->images->first()?->thumb_url;
                        $rentang = $item->rentangUkuran();
                    @endphp
                    @if ($gambar)
                        <img src="{{ $gambar }}" alt="" width="72" height="72" loading="lazy"
                             class="size-18 shrink-0 rounded-[10px] border border-line bg-paper object-cover">
                    @else
                        <span class="flex size-18 shrink-0 items-center justify-center rounded-[10px] border border-dashed border-line-strong text-navy-300">
                            <x-ikon nama="kaos" ukuran="size-7" />
                        </span>
                    @endif

                    <div class="min-w-50 flex-1">
                        <p class="font-bold">{{ $item->nama_produk }}</p>
                        <p class="mt-0.5 text-sm text-muted tabular">
                            {{ $item->order->kode }} &middot; {{ $item->qty }} pcs &middot;
                            {{ $item->order->customer->nama }}
                        </p>
                        @if ($rentang)
                            <p class="text-sm text-muted" title="{{ $item->ringkasanUkuran() }}">{{ $rentang }}</p>
                        @endif

                        {{-- KEP-095: filenya ada di WhatsApp toko, bukan di sistem. --}}
                        @if ($stage === ProductionStage::DESAIN && $item->desain_sumber === 'sendiri')
                            {{-- Tautan Drive dari pelanggan (KEP-139), kalau ada; selain itu lewat WhatsApp. --}}
                            @php $tautanPelanggan = $item->designAssets->where('tipe', \App\Enums\DesignAssetType::UPLOAD_PELANGGAN)->whereNotNull('external_url')->sortByDesc('versi')->first(); @endphp
                            @if ($tautanPelanggan)
                                <p class="mt-1 text-sm font-medium">
                                    Desain dari pelanggan:
                                    <a href="{{ $tautanPelanggan->external_url }}" target="_blank" rel="noopener noreferrer" class="tautan break-all font-normal">buka tautan</a>
                                </p>
                            @else
                                <p class="mt-1 text-sm font-medium">Desain dari pelanggan, lewat WhatsApp</p>
                            @endif
                        @endif

                        @if ($item->order->target_siap_pada)
                            <p class="text-sm {{ $item->order->target_siap_pada->isPast() ? 'font-semibold text-merah' : 'text-muted' }}">
                                Target: {{ $item->order->target_siap_pada->translatedFormat('j M Y') }}
                            </p>
                        @endif

                        @if ($dikerjakan && $job->dimulai_pada)
                            <p class="mt-1 text-xs text-muted">
                                Dimulai {{ $job->dimulai_pada->diffForHumans() }}
                                @if ($job->petugas) oleh {{ $job->petugas->name }} @endif
                            </p>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('panel.pesanan.worksheet', $item->order) }}" target="_blank"
                           rel="noopener" class="tombol tombol-garis text-sm">
                            <x-ikon nama="cetak" ukuran="size-4" />
                            Lembar kerja
                        </a>

                        @unless ($dikerjakan)
                            <form method="POST" action="{{ route('panel.antrian.mulai', $item) }}">
                                @csrf
                                <input type="hidden" name="dari" value="{{ $item->status_item->value }}">
                                <button type="submit" class="tombol tombol-garis text-sm">
                                    <x-ikon nama="jam" ukuran="size-4" />
                                    Mulai kerjakan
                                </button>
                            </form>
                        @endunless

                        @if ($stage !== ProductionStage::DESAIN)
                            <form method="POST" action="{{ route('panel.antrian.selesai', $item) }}">
                                @csrf
                                <input type="hidden" name="dari" value="{{ $item->status_item->value }}">
                                <button type="submit" class="tombol tombol-navy text-sm">
                                    <x-ikon nama="centang" ukuran="size-4" />
                                    Selesai
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                @if ($stage === ProductionStage::DESAIN)
                    <form method="POST" action="{{ route('panel.mockup.simpan', $item) }}"
                          enctype="multipart/form-data"
                          class="flex flex-wrap items-end gap-3 border-t border-line bg-paper p-4">
                        @csrf
                        <div class="w-56"><label for="mockup-{{ $item->id }}" class="label-form">Preview mockup</label>
                            <input type="file" id="mockup-{{ $item->id }}" name="preview" accept=".jpg,.jpeg,.png,.webp" class="kotak-input"></div>
                        <div class="min-w-56 flex-1"><label for="tautan-{{ $item->id }}" class="label-form">Tautan file siap cetak</label>
                            <input type="url" id="tautan-{{ $item->id }}" name="tautan" class="kotak-input"
                                   placeholder="https://drive.google.com/..."></div>
                        <button type="submit" class="tombol tombol-utama">Kirim untuk disetujui</button>
                        <p class="w-full text-xs text-muted">
                            Mengirim mockup memindahkan item ke Menunggu Persetujuan Pelanggan.
                            Jangan pakai tombol Selesai untuk itu.
                        </p>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
@endif
</div>
@endsection
