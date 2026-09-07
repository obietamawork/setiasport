@extends('layouts.panel')
@section('judul', 'Antrian kerja')

@section('isi')
<h1 class="text-2xl font-extrabold">Antrian kerja</h1>
<p class="mt-1 text-sm text-muted">Divisi yang menjadi tanggung jawab Anda.</p>

@if (empty($tahap))
    <div class="mt-6 border border-line bg-field p-8 text-center">
        <p class="text-lg font-bold">Anda belum memegang antrian mana pun</p>
        <p class="mt-2 text-sm text-muted">
            Minta pemilik toko menambahkan peran produksi ke akun Anda.
        </p>
    </div>
@else
    <div class="mt-6 grid gap-px border border-line bg-line sm:grid-cols-2">
        @foreach ($tahap as $t)
            <a href="{{ route('panel.antrian.tahap', strtolower($t->value)) }}"
               class="flex items-baseline justify-between gap-4 bg-field p-6 hover:bg-navy-50">
                <span class="text-lg font-bold">{{ $t->label() }}</span>
                <span class="numeral text-3xl {{ ($jumlah[$t->value] ?? 0) > 0 ? 'text-merah' : 'text-line-strong' }}">
                    {{ $jumlah[$t->value] ?? 0 }}
                </span>
            </a>
        @endforeach
    </div>
@endif
@endsection
