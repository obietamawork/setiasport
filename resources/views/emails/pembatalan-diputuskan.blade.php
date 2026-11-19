@extends('emails.layout')

{{-- Keputusan pengajuan pembatalan (KEP-128). Kartu pengajuannya di halaman
     pesanan berjanji "diberitahukan melalui email". Rekening tidak ditulis
     (KEP-029); pengembalian dikirim toko, bukan diminta dari pelanggan. --}}
@php
    $pembatalan = $order->pembatalans()->latest('diputuskan_pada')->latest('id')->first();
    $disetujui = $pembatalan?->status === \App\Enums\StatusPembatalan::DISETUJUI;
@endphp

@section('isi')
<p style="margin:0 0 12px;">Halo {{ $order->customer->nama }},</p>

@if ($disetujui)
  <p style="margin:0 0 12px;">
    Pengajuan pembatalan pesanan <strong>{{ $order->kode }}</strong> <strong>disetujui</strong>.
    Pesanan ini dibatalkan.
  </p>

  <p style="margin:0 0 12px;padding:12px 14px;background:#eef3ff;border-left:3px solid #16255b;">
    @if ($pembatalan->refund_jumlah > 0)
      Pengembalian sebesar <strong>{{ rupiah($pembatalan->refund_jumlah) }}</strong>
      dikirim lewat {{ $pembatalan->refund_metode === 'TUNAI' ? 'tunai di toko' : 'transfer' }}.
      Kalau dalam 3 hari kerja belum masuk, hubungi kami.
    @else
      Tidak ada pengembalian uang untuk pembatalan ini, sesuai ketentuan pembatalan pada tahap pesanan Anda.
    @endif
  </p>
@else
  <p style="margin:0 0 12px;">
    Pengajuan pembatalan pesanan <strong>{{ $order->kode }}</strong> belum bisa kami setujui.
    Pesanan Anda tetap berjalan seperti biasa.
  </p>
@endif

@if ($pembatalan?->catatan_admin)
  <p style="margin:0 0 12px;padding:12px 14px;background:#f5f6fa;border-left:3px solid #9aa3b8;">
    {{ $pembatalan->catatan_admin }}
  </p>
@endif

<p style="margin:0 0 20px;">
  Kalau masih ada yang ingin dibicarakan, hubungi kami lewat {{ kanal_kontak() }}
  dan sebutkan kode pesanan {{ $order->kode }}.
</p>

<a href="{{ $tautan }}"
   style="display:inline-block;background:#16255b;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:bold;border-radius:3px;">
  Lihat halaman pesanan
</a>
@endsection
