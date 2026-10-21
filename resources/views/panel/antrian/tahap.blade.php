@extends('layouts.panel')
@section('judul', 'Antrian '.$stage->label())

@section('isi')
@php
    use App\Enums\ProductionStatus;
    use App\Enums\ProductionStage;
@endphp

<x-kop :judul="'Antrian '.$stage->label()"
       :kembali="$tahapLain->isNotEmpty() ? route('panel.antrian.index') : null"
       kembali-label="Semua divisi">
    <x-slot:bawahJudul>
        <p class="text-sm text-muted">
            {{ $pekerjaan->count() }} pekerjaan menunggu di meja ini.
        </p>
    </x-slot:bawahJudul>
</x-kop>

{{-- Pindah divisi tanpa kembali ke daftar: orang yang memegang dua meja
     bolak-balik di antara keduanya sepanjang hari. --}}
@if ($tahapLain->isNotEmpty())
    <div class="segmen -mt-2 mb-4">
        @foreach ($tahapLain as $t)
            <a href="{{ route('panel.antrian.tahap', strtolower($t->value)) }}" class="segmen-item">
                <x-ikon :nama="$t->ikon()" ukuran="size-4" />
                {{ $t->label() }}
            </a>
        @endforeach
    </div>
@endif

<div x-data="{ terpilih: [] }">

@if ($pekerjaan->isEmpty())
    <div class="kartu mt-6 p-8 text-center">
        <x-ikon :nama="$stage->ikon()" ukuran="size-9" class="mx-auto text-navy-300" />
        <p class="mt-3 text-lg font-bold">Antrian kosong</p>
        <p class="mt-2 text-sm text-muted">
            Tidak ada pekerjaan {{ Str::lower($stage->label()) }} yang menunggu. Cek lagi nanti.
        </p>
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
                           rel="noopener" class="tombol tombol-garis text-sm">
                            <x-ikon nama="cetak" ukuran="size-4" />
                            Lembar kerja
                        </a>

                        @unless ($dikerjakan)
                            <form method="POST" action="{{ route('panel.antrian.mulai', $item) }}">
                                @csrf
                                <button type="submit" class="tombol tombol-garis text-sm">
                                    <x-ikon nama="jam" ukuran="size-4" />
                                    Mulai kerjakan
                                </button>
                            </form>
                        @endunless

                        @if ($stage !== ProductionStage::DESAIN)
                            <form method="POST" action="{{ route('panel.antrian.selesai', $item) }}">
                                @csrf
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
                        <div class="w-56"><label class="label-form">Preview mockup</label>
                            <input type="file" name="preview" accept=".jpg,.jpeg,.png,.webp" class="kotak-input"></div>
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
</div>
@endsection
