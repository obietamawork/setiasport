@extends('emails.layout')

@section('isi')
<p style="margin:0 0 12px;">Halo {{ $pelanggan->nama }},</p>

<p style="margin:0 0 12px;">
  Ada permintaan pembuatan akun {{ setting('toko_nama', 'Setia Sport') }} memakai alamat
  email ini. Klik tombol di bawah untuk menyelesaikannya.
</p>

<p style="margin:0 0 20px;">
  Setelah dikonfirmasi, seluruh pesanan yang pernah Anda buat dengan email ini akan
  tampil dalam satu halaman.
</p>

<a href="{{ $tautan }}"
   style="display:inline-block;background:#d62b1f;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:bold;border-radius:7px;">
  Konfirmasi akun
</a>

<p style="margin:20px 0 0;font-size:12px;color:#5d687f;line-height:1.6;">
  Tautan ini berlaku 24 jam. <strong>Kalau Anda tidak merasa mendaftar, abaikan saja
  email ini</strong> dan tidak ada yang berubah pada pesanan maupun data Anda.
</p>

<p style="margin:12px 0 0;font-size:12px;color:#5d687f;line-height:1.6;">
  Anda tetap bisa membuka pesanan lewat tautan yang kami kirim di setiap email
  pesanan, tanpa perlu akun.
</p>
@endsection
