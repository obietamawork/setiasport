@extends('layouts.publik')
@section('judul', 'Lacak pesanan')

@section('isi')
<div class="mx-auto max-w-lg px-5 py-14">
    <h1 class="t-wide text-3xl font-extrabold">Lacak pesanan</h1>
    <p class="mt-3 leading-relaxed text-muted">
        Cara tercepat adalah membuka tautan di email konfirmasi Anda. Kalau emailnya
        tidak ketemu, masukkan kode pesanan dan email yang dipakai saat memesan.
    </p>

    <form method="POST" action="{{ route('lacak.cari') }}" class="mt-8 space-y-4 border border-line bg-field p-6">
        @csrf

        @if ($errors->any())
            <p class="border-l-2 border-signal bg-signal-soft px-3 py-2 text-sm">{{ $errors->first() }}</p>
        @endif

        <div>
            <label for="kode" class="label-form">Kode pesanan</label>
            <input type="text" id="kode" name="kode" value="{{ old('kode') }}"
                   class="kotak-input tabular" placeholder="SS-2609-0042" required autofocus>
        </div>

        <div>
            <label for="email" class="label-form">Email saat memesan</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" class="kotak-input" required>
        </div>

        <button type="submit" class="tombol tombol-lapangan w-full">Buka pesanan</button>
    </form>
</div>
@endsection
