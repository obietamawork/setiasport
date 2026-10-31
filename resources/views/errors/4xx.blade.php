@extends('errors.layout')
{{-- Penolakan dari penjaga alur yang dibuka lewat tautan, bukan tombol (KEP-098).
     Tombol dikembalikan ke halamannya sendiri lewat bootstrap/app.php; halaman
     ini untuk sisanya, dan kalimat penjelasannya tetap ditampilkan. --}}
@section('kode', $exception->getStatusCode())
@section('judul', 'Belum bisa dilakukan')
@section('pesan', $exception->getMessage() ?: 'Permintaan ini tidak bisa diproses dalam keadaan sekarang.')

@section('aksi')
    <a href="{{ url()->previous() }}" class="tombol tombol-garis">Kembali</a>
@endsection
