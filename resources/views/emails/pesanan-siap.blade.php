@extends('emails.layout')

@section('isi')
<p style="margin:0 0 12px;">Halo {{ $order->customer->nama }},</p>

@if ($order->dikirim())
  {{-- Pesanan yang akan dikirim tidak punya kode ambil yang berguna, dan
       menyuruh pelanggan datang ke toko justru membingungkan (KEP-026). --}}
  <p style="margin:0 0 16px;">
    Pesanan <strong>{{ $order->kode }}</strong> sudah selesai dan sedang kami siapkan
    untuk dikirim. Nomor resinya menyusul lewat email begitu paket diserahkan ke kurir.
  </p>

  @if ($order->sisa_tagihan > 0)
    <div style="border-left:3px solid #ff5a1f;background:#ffede4;padding:12px 16px;margin:0 0 16px;">
      <p style="margin:0;">
        Masih ada sisa <strong>{{ rupiah($order->sisa_tagihan) }}</strong>. Paket berangkat
        setelah pelunasannya kami terima.
      </p>
    </div>
  @endif
@else
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
@endif

@include('emails.komponen-rincian')

@unless ($order->dikirim())
  <p style="margin:0 0 12px;">
    Alamat: {{ setting('toko_alamat') }}<br>
    Jam buka:<br>{{ baris_html(setting('toko_jam_buka')) }}
  </p>

  <p style="margin:0 0 20px;">
    Kalau diwakilkan, cukup tunjukkan kode ambil di atas kepada petugas kami.
  </p>
@endunless

<a href="{{ $tautan }}"
   style="display:inline-block;background:#16255b;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:bold;border-radius:3px;">
  Lihat detail pesanan
</a>
@endsection
