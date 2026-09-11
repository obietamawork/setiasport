@extends('emails.layout')

@section('isi')
<p style="margin:0 0 12px;">Halo {{ $order->customer->nama }},</p>

<p style="margin:0 0 16px;">
  Pesanan <strong>{{ $order->kode }}</strong> sudah siap sejak
  {{ $order->siap_pada?->translatedFormat('j F Y') }} dan masih menunggu diambil.
</p>

<div style="background:#e7efe9;padding:16px;margin:0 0 16px;">
  <p style="margin:0 0 4px;font-size:12px;color:#5c6b64;">Kode ambil</p>
  <p style="margin:0;font-size:26px;font-weight:bold;letter-spacing:1px;">{{ $order->kode_ambil }}</p>
</div>

@if ($order->sisa_tagihan > 0)
  <p style="margin:0 0 12px;">
    Sisa yang perlu dibawa: <strong>{{ rupiah($order->sisa_tagihan) }}</strong>.
  </p>
@endif

<p style="margin:0 0 20px;">
  Alamat: {{ setting('toko_alamat') }}<br>
  Jam buka:<br>{{ baris_html(setting('toko_jam_buka')) }}
</p>

<a href="{{ $tautan }}"
   style="display:inline-block;background:#16255b;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:bold;border-radius:3px;">
  Lihat detail pesanan
</a>
@endsection
