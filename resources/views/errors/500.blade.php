@extends('errors.layout')
@section('kode', '500')
@section('judul', 'Ada yang bermasalah di sisi kami')
@section('pesan', 'Ini bukan kesalahan Anda. Coba beberapa saat lagi.
    Kalau pesanan Anda sedang berjalan, datanya aman dan tidak hilang.')

@section('aksi')
    <a href="/" class="tombol tombol-garis">Ke beranda</a>
@endsection
