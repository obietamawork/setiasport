@extends('emails.layout')

@section('isi')
<p style="margin:0 0 12px;">Halo {{ $order->customer->nama }},</p>

<p style="margin:0 0 16px;">
  Pesanan <strong>{{ $order->kode }}</strong> sudah selesai dikerjakan.
  Tinggal satu langkah sebelum bisa diambil: pelunasan.
</p>

<div style="border-left:3px solid #ff5a1f;background:#ffede4;padding:16px;margin:0 0 16px;">
  <p style="margin:0 0 4px;font-size:12px;color:#5c6b64;">Sisa yang perlu dilunasi</p>
  <p style="margin:0;font-size:26px;font-weight:bold;">{{ rupiah($order->sisa_tagihan) }}</p>
</div>

{{-- Nomor rekening sengaja TIDAK ditulis di email (KEP-029). Email bisa
     dipalsukan orang yang menyamar sebagai toko; halaman pesanan di domain
     kami tidak. Melatih pelanggan membuka halaman itu adalah perlindungan
     termurah terhadap penipuan atas nama toko. --}}
<p style="margin:0 0 16px;">
  Rekening dan QRIS resmi kami ada di halaman pesanan Anda, bersama tempat untuk
  mengunggah bukti transfernya. Bisa juga dibayar langsung di kasir saat datang.
</p>

<p style="margin:0 0 20px;font-size:13px;color:#5c6b64;">
  Kami tidak pernah meminta transfer lewat WhatsApp atau ke rekening selain yang
  tertera di halaman pesanan. Kalau ada yang memintanya atas nama kami, abaikan
  dan kabari kami lewat chat CS di website.
</p>

<a href="{{ $tautan }}"
   style="display:inline-block;background:#16255b;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:bold;border-radius:3px;">
  Buka halaman pesanan
</a>
@endsection
