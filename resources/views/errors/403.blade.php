@extends('errors.layout')
@section('kode', '403')
@section('judul', 'Halaman ini bukan untuk Anda')
@section('pesan', 'Akun yang sedang masuk tidak punya akses ke halaman ini.
    Kalau menurut Anda seharusnya punya, minta pemilik toko menambahkan peran yang sesuai.')

@section('aksi')
    <a href="/" class="tombol tombol-garis">Ke beranda</a>
@endsection
