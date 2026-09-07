@extends('emails.layout')

@section('isi')
<p style="margin:0 0 12px;">Halo {{ $order->customer->nama }},</p>

<p style="margin:0 0 16px;">
  Pesanan <strong>{{ $order->kode }}</strong> sudah selesai dan bisa diambil di toko.
</p>

<div style="background:#e7efe9;padding:16px;margin:0 0 16px;">
  <p style="margin:0 0 4px;font-size:12px;color:#5c6b64;">Kode ambil</p>
  <p style="margin:0;font-size:26px;font-weight:bold;letter-spacing:1px;">{{ $order->kode_ambil }}</p>
</div>

@if ($order->sisa_tagihan > 0)
  <div style="border-left:3px solid #ff5a1f;background:#ffede4;padding:12px 16px;margin:0 0 16px;">
    <p style="margin:0;">
      Mohon bawa <strong>{{ rupiah($order->sisa_tagihan) }}</strong> untuk pelunasan.
      Bisa tunai maupun non-tunai di kasir.
    </p>
  </div>
@endif

@include('emails.komponen-rincian')

<p style="margin:0 0 12px;">
  Alamat: {{ setting('toko_alamat') }}<br>
  Jam buka: {{ setting('toko_jam_buka') }}
</p>

<p style="margin:0 0 20px;">
  Kalau diwakilkan, cukup tunjukkan kode ambil di atas kepada petugas kami.
</p>

<a href="{{ $tautan }}"
   style="display:inline-block;background:#14523a;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:bold;border-radius:3px;">
  Lihat detail pesanan
</a>
@endsection
