@extends('emails.layout')

{{-- Tagihan berubah setelah pesanan dibuat (KEP-158): penyesuaian biaya,
     ongkir yang diisi admin, atau isi pesanan yang diubah. Pelanggan tahu
     angka barunya sebelum membayar atau datang ke toko. --}}
@section('isi')
@php
    $menunggu = $order->payments->firstWhere('status', \App\Enums\PaymentStatus::MENUNGGU);
@endphp
<p style="margin:0 0 12px;">Halo {{ $order->customer->nama }},</p>

<p style="margin:0 0 16px;">
  Tagihan pesanan <strong>{{ $order->kode }}</strong> kami perbarui. Berikut rincian terbarunya.
</p>

@include('emails.komponen-rincian')

<div style="border-left:3px solid #16255b;background:#f2f5fa;padding:16px;margin:0 0 16px;">
  @if ($order->kelebihan_bayar > 0)
    <p style="margin:0;">
      Ada <strong>kelebihan bayar {{ rupiah($order->kelebihan_bayar) }}</strong>. Kami akan
      mengembalikannya dan menghubungi Anda untuk caranya.
    </p>
  @elseif ($order->sisa_tagihan < 1)
    <p style="margin:0;">Tagihan pesanan ini tetap lunas. Tidak ada yang perlu Anda bayar.</p>
  @elseif ($menunggu)
    <p style="margin:0;">
      Tagihan yang perlu dibayar sekarang menjadi <strong>{{ rupiah($menunggu->jumlah) }}</strong>.
    </p>
  @elseif ($order->bolehBayarPelunasan())
    <p style="margin:0;">
      Sisa <strong>{{ rupiah($order->sisa_tagihan) }}</strong> bisa dilunasi sekarang lewat halaman
      pesanan.@unless ($order->dikirim()) Bisa juga dibayar di kasir saat mengambil.@endunless
    </p>
  @else
    <p style="margin:0;">
      Sisa <strong>{{ rupiah($order->sisa_tagihan) }}</strong> dibayar saat pelunasan, setelah pesanan
      siap. Kami kabari lagi saat waktunya tiba.
    </p>
  @endif
</div>

{{-- Rekening sengaja tidak ditulis di email (KEP-029). --}}
<p style="margin:0 0 20px;font-size:13px;color:#5d687f;">
  Ada yang kurang jelas? Hubungi kami lewat {{ kanal_kontak() }}. Rekening dan QRIS resmi kami hanya
  ada di halaman pesanan Anda; kami tidak pernah meminta transfer lewat WhatsApp.
</p>

<a href="{{ $tautan }}"
   style="display:inline-block;background:#16255b;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:bold;border-radius:8px;">
  Buka halaman pesanan
</a>
@endsection
