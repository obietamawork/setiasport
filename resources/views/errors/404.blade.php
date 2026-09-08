@extends('errors.layout')
@section('kode', '404')
@section('judul', 'Halaman tidak ditemukan')
@section('pesan', 'Alamatnya mungkin salah ketik, atau halaman ini sudah dipindahkan.
    Produk yang Anda cari bisa jadi masih ada di katalog.')

@section('aksi')
    <a href="/katalog" class="tombol tombol-utama">Buka katalog</a>
    <a href="/" class="tombol tombol-garis">Ke beranda</a>
@endsection
