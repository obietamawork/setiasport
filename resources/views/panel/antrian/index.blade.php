@extends('layouts.panel')
@section('judul', 'Antrian kerja')

@section('isi')
<div class="kop-halaman">
    <div>
        <h1 class="kop-judul">Antrian kerja</h1>
        <p class="kop-catatan">Divisi yang menjadi tanggung jawab Anda.</p>
    </div>
</div>

@if (empty($tahap))
    <div class="kartu mt-6 p-8 text-center">
        <p class="text-lg font-bold">Anda belum memegang antrian mana pun</p>
        <p class="mt-2 text-sm text-muted">
            Minta pemilik toko menambahkan peran produksi ke akun Anda.
        </p>
    </div>
@else
    <div class="mt-6 grid gap-3 sm:grid-cols-2">
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
