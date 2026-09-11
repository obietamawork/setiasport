@extends('emails.layout')

@section('isi')
<p style="margin:0 0 12px;">Halo {{ $order->customer->nama }},</p>

<p style="margin:0 0 16px;">
  Pesanan <strong>{{ $order->kode }}</strong> sudah kami serahkan ke jasa kirim.
</p>

<div style="background:#e7efe9;padding:16px;margin:0 0 16px;">
  <p style="margin:0 0 4px;font-size:12px;color:#5c6b64;">{{ $order->jasa_kirim }}</p>
  <p style="margin:0;font-size:24px;font-weight:bold;letter-spacing:1px;">{{ $order->no_resi }}</p>
</div>

@if (setting('panduan_resi'))
  <p style="margin:0 0 16px;">{{ setting('panduan_resi') }}</p>
@endif

@if ($order->alamat_kirim)
  <p style="margin:0 0 16px;">
    Dikirim ke:<br>
    {{ $order->alamat_kirim }}
  </p>
@endif

@include('emails.komponen-rincian')

<p style="margin:0 0 20px;">
  Kalau setelah beberapa hari resinya masih belum bergerak, kabari kami lewat
  {{ kanal_kontak() }} dan akan kami tanyakan ke kurirnya.
</p>

<a href="{{ $tautan }}"
   style="display:inline-block;background:#16255b;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:bold;border-radius:3px;">
  Lihat detail pesanan
</a>
@endsection
