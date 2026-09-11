@extends('emails.layout')

@section('isi')
<p style="margin:0 0 12px;">Halo {{ $order->customer->nama }},</p>

<p style="margin:0 0 12px;">
  Pembayaran untuk pesanan <strong>{{ $order->kode }}</strong> sudah kami terima dan
  pesanan Anda mulai dikerjakan.
</p>

@include('emails.komponen-rincian')

@if ($order->sisa_tagihan > 0)
  <p style="margin:0 0 12px;">
    Sisa <strong>{{ rupiah($order->sisa_tagihan) }}</strong> dibayar saat Anda mengambil
    barang di toko. Bisa tunai maupun non-tunai.
  </p>
@endif

<p style="margin:0 0 20px;">
  Kami akan mengirim email lagi begitu pesanan siap diambil.
</p>

<a href="{{ $tautan }}"
   style="display:inline-block;background:#16255b;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:bold;border-radius:3px;">
  Lihat progres pesanan
</a>
@endsection
