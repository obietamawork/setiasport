@extends('emails.layout')

@section('isi')
<p style="margin:0 0 12px;">Halo {{ $order->customer->nama }},</p>

<p style="margin:0 0 12px;">
  Pembayaran untuk pesanan <strong>{{ $order->kode }}</strong> sudah kami terima dan
  pesanan Anda mulai dikerjakan.
</p>

@include('emails.komponen-rincian')

{{-- Pesanan yang dikirim tidak pernah lewat toko (KEP-097): sisanya dilunasi
     lewat transfer sebelum paket berangkat, bukan di kasir. --}}
@if ($order->sisa_tagihan > 0)
  <p style="margin:0 0 12px;">
    @if ($order->dikirim())
      Sisa <strong>{{ rupiah($order->sisa_tagihan) }}</strong> dilunasi setelah barang selesai
      dikerjakan, sebelum paketnya kami kirim. Kami kabari lewat email saat waktunya.
    @else
      Sisa <strong>{{ rupiah($order->sisa_tagihan) }}</strong> dibayar saat Anda mengambil
      barang di toko. Bisa tunai maupun non-tunai.
    @endif
  </p>
@endif

<p style="margin:0 0 20px;">
  {{ $order->dikirim()
      ? 'Kami akan mengirim email lagi begitu paketnya dikirim, lengkap dengan nomor resinya.'
      : 'Kami akan mengirim email lagi begitu pesanan siap diambil.' }}
</p>

<a href="{{ $tautan }}"
   style="display:inline-block;background:#16255b;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:bold;border-radius:3px;">
  Lihat progres pesanan
</a>
@endsection
