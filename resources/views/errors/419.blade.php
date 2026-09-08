@extends('errors.layout')
@section('kode', '419')
@section('judul', 'Halaman terlalu lama dibuka')
@section('pesan', 'Demi keamanan, formulir yang dibiarkan terbuka terlalu lama tidak lagi
    diterima. Muat ulang halamannya, lalu kirim sekali lagi. Isian Anda sebelumnya tidak tersimpan.')

@section('aksi')
    <a href="javascript:history.back()" class="tombol tombol-utama">Kembali dan ulangi</a>
    <a href="/" class="tombol tombol-garis">Ke beranda</a>
@endsection
