@extends('layouts.publik')
@section('judul', 'Roll printing '.$roll->kode)
@section('subjudul', $roll->kode)
@section('subcatatan', $roll->items->count().' pesanan dalam satu roll')

@section('isi')
<div class="mx-auto max-w-lg px-5 py-6">

    @if (session('sukses'))
        <p class="notis notis-ok mb-5">{{ session('sukses') }}</p>
    @endif

    <h1 class="text-2xl font-extrabold">{{ $roll->kode }}</h1>
    <p class="mt-1 text-sm text-muted">
        Dibuat {{ $roll->created_at->translatedFormat('j M Y, H:i') }}
        @if ($roll->catatan)
            &middot; {{ $roll->catatan }}
        @endif
    </p>

    <div class="mt-6 border border-line bg-field p-5">
        @if ($roll->selesai())
            <p class="text-sm text-muted">Roll ini</p>
            <p class="mt-1 text-2xl font-extrabold">Sudah selesai printing</p>
            <p class="mt-3 text-sm leading-relaxed text-muted">
                Ditandai {{ $roll->selesai_pada->translatedFormat('j M Y, H:i') }}
                oleh {{ $roll->pekerja?->nama ?? 'admin' }}.
            </p>
        @else
            <p class="text-sm text-muted">Tahap sekarang</p>
            <p class="mt-1 text-2xl font-extrabold">Printing Jersey</p>

            <form method="POST" action="{{ route('roll.selesai', $roll->token) }}" class="mt-5 space-y-3">
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
                    Tandai seluruh roll selesai
                </button>

                <p class="text-xs leading-relaxed text-muted">
                    Seluruh pesanan di roll ini naik ke tahap berikutnya sekaligus.
                    Salah tandai? Hubungi admin konveksi.
                </p>
            </form>
        @endif
    </div>

    <h2 class="mt-8 font-bold">Isi roll</h2>
    <ul class="mt-2 divide-y divide-line border border-line bg-field text-sm">
        @foreach ($roll->items as $item)
            <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                <span>
                    <span class="tabular font-medium">{{ $item->order->kode }}</span>
                    <span class="block text-muted">{{ $item->nama_produk }}</span>
                </span>
                <span class="text-right">
                    <span class="tabular">{{ $item->qty }} pcs</span>
                    <span class="block text-xs text-muted">{{ $item->status_item->labelStaf() }}</span>
                </span>
            </li>
        @endforeach
    </ul>
</div>
@endsection
