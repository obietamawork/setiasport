@extends('layouts.panel')
@section('judul', 'Daftar pekerjaan')

@section('isi')
<x-kop judul="Daftar pekerjaan" :kembali="route('panel.antrian.index')" kembali-label="Antrian kerja"
       catatan="Semua pekerjaan yang sedang berjalan di divisi Anda, dari target selesai yang paling dekat. Yang belum punya target ada di paling bawah." />

@if ($pekerjaan->isEmpty())
    <div class="kartu mt-2 p-8 text-center">
        <x-ikon nama="antrian" ukuran="size-9" class="mx-auto text-navy-300" />
        <p class="mt-3 font-bold">Tidak ada pekerjaan yang berjalan</p>
        <p class="mx-auto mt-2 max-w-sm text-sm text-muted">Pekerjaan baru muncul sendiri di sini begitu pesanan masuk ke divisi Anda.</p>
    </div>
@else
    <section class="kartu overflow-hidden">
        @include('panel.antrian.tabel-pekerjaan', ['pekerjaan' => $pekerjaan])
    </section>

    <div class="mt-6">{{ $pekerjaan->links() }}</div>
@endif
@endsection
