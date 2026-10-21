@extends('errors.layout')
@section('kode', '429')
@section('judul', 'Terlalu banyak percobaan')
@section('pesan', 'Permintaan dari perangkat ini terlalu sering dalam waktu singkat. Tunggu sebentar,
    lalu coba lagi. Kalau tadi Anda menekan tombol berkali-kali, kemungkinan yang pertama sudah tercatat.')

@section('aksi')
    <a href="javascript:history.back()" class="tombol tombol-utama">Kembali</a>
    <a href="/" class="tombol tombol-garis">Ke beranda</a>
@endsection
