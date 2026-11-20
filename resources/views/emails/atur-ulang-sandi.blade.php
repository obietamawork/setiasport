@extends('emails.layout')

@section('isi')
<p style="margin:0 0 12px;">Halo {{ $pelanggan->nama }},</p>

<p style="margin:0 0 12px;">
  Kami menerima permintaan untuk mengatur ulang kata sandi akun {{ setting('toko_nama', 'Setia Sport') }}
  yang terhubung dengan alamat email ini. Klik tombol di bawah untuk membuat kata sandi baru.
</p>

<a href="{{ $tautan }}"
   style="display:inline-block;background:#d62b1f;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:bold;border-radius:7px;">
  Buat kata sandi baru
</a>

<p style="margin:20px 0 0;font-size:12px;color:#5d687f;line-height:1.6;">
  Tautan ini berlaku {{ \App\Models\Customer::MENIT_ATUR_SANDI }} menit dan hanya dapat digunakan satu kali.
  <strong>Jika Anda tidak meminta pengaturan ulang kata sandi, abaikan email ini.</strong>
  Kata sandi Anda tidak akan berubah.
</p>

<p style="margin:12px 0 0;font-size:12px;color:#5d687f;line-height:1.6;">
  Demi keamanan, tim {{ setting('toko_nama', 'Setia Sport') }} tidak pernah meminta kata sandi Anda
  melalui chat, WhatsApp, maupun telepon.
</p>
@endsection
