@extends('errors.layout')
@section('kode', '503')
@section('judul', 'Sedang ada perawatan')
@section('pesan', 'Situs sedang diperbarui sebentar dan akan segera kembali normal.
    Pesanan yang sudah masuk tidak terpengaruh.')

@section('aksi')
    <a href="/" class="tombol tombol-garis">Coba lagi</a>
@endsection
