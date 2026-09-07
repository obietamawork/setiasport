@extends('layouts.panel')
@section('judul', 'Antrian '.$stage->label())

@section('isi')
@php
    use App\Enums\ProductionStatus;
    use App\Enums\ProductionStage;
@endphp

<div class="flex flex-wrap items-baseline justify-between gap-3">
    <h1 class="text-2xl font-extrabold">Antrian {{ $stage->label() }}</h1>
    <span class="tabular text-muted">{{ $pekerjaan->count() }} pekerjaan</span>
</div>

@if ($tahapLain->isNotEmpty())
    <div class="mt-3 flex flex-wrap gap-1 text-sm">
        @foreach ($tahapLain as $t)
            <a href="{{ route('panel.antrian.tahap', strtolower($t->value)) }}"
               class="rounded-sm bg-field px-3 py-1.5 hover:bg-navy-50">{{ $t->label() }}</a>
        @endforeach
    </div>
@endif

@if ($pekerjaan->isEmpty())
    <div class="mt-6 border border-line bg-field p-8 text-center">
        <p class="text-lg font-bold">Antrian kosong</p>
        <p class="mt-2 text-sm text-muted">
            Tidak ada pekerjaan {{ Str::lower($stage->label()) }} yang menunggu. Cek lagi nanti.
        </p>
    </div>
@else
    {{-- Kartu, bukan tabel: operator produksi membuka layar ini dari HP di
         dekat mesin, bukan dari PC (docs/04). --}}
    <div class="mt-6 space-y-3">
        @foreach ($pekerjaan as $item)
            @php
                $job = $item->productionJobs->firstWhere('tahap', $stage);
                $dikerjakan = $job?->status === ProductionStatus::DIKERJAKAN;
            @endphp

            <div class="border border-line bg-field {{ $dikerjakan ? 'border-l-2 border-l-merah' : '' }}">
                <div class="flex flex-wrap items-start gap-3 p-4">
                    <div class="min-w-50 flex-1">
                        <p class="font-bold">{{ $item->nama_produk }}</p>
                        <p class="mt-0.5 text-sm text-muted tabular">
                            {{ $item->qty }} pcs &middot; {{ $item->order->kode }} &middot;
                            {{ $item->order->customer->nama }}
                        </p>

                        @if ($item->rosters->isNotEmpty())
                            <p class="mt-1 text-sm tabular">{{ $item->ringkasanUkuran() }}</p>
                        @endif

                        @if ($item->order->target_siap_pada)
                            <p class="mt-1 text-sm {{ $item->order->target_siap_pada->isPast() ? 'text-merah' : 'text-muted' }}">
                                Target siap {{ $item->order->target_siap_pada->translatedFormat('j M Y') }}
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
                           rel="noopener" class="tombol tombol-garis text-sm">Worksheet</a>

                        @unless ($dikerjakan)
                            <form method="POST" action="{{ route('panel.antrian.mulai', $item) }}">
                                @csrf
                                <button type="submit" class="tombol tombol-garis text-sm">Mulai kerjakan</button>
                            </form>
                        @endunless

                        @if ($stage !== ProductionStage::DESAIN)
                            <form method="POST" action="{{ route('panel.antrian.selesai', $item) }}">
                                @csrf
                                <button type="submit" class="tombol tombol-navy text-sm">Selesai</button>
                            </form>
                        @endif
                    </div>
                </div>

                @if ($stage === ProductionStage::DESAIN)
                    <form method="POST" action="{{ route('panel.mockup.simpan', $item) }}"
                          enctype="multipart/form-data"
                          class="flex flex-wrap items-end gap-3 border-t border-line bg-paper p-4">
                        @csrf
                        <div class="w-56"><label class="label-form">Preview mockup</label>
                            <input type="file" name="preview" accept=".jpg,.jpeg,.png,.pdf" class="kotak-input"></div>
                        <div class="min-w-56 flex-1"><label class="label-form">Tautan file siap cetak</label>
                            <input type="url" name="tautan" class="kotak-input"
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
@endsection
