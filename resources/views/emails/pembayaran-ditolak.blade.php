@extends('emails.layout')

{{-- Bukti pembayaran ditolak admin (KEP-097). Rekening sengaja tidak ditulis
     (KEP-029): halaman pesanan di domain toko satu-satunya sumber rekening yang sah. --}}
@php
    $ditolak = $order->payments
        ->where('status', \App\Enums\PaymentStatus::GAGAL)
        ->sortByDesc('id')
        ->first();
@endphp

@section('isi')
<p style="margin:0 0 12px;">Halo {{ $order->customer->nama }},</p>

<p style="margin:0 0 12px;">
  Bukti pembayaran untuk pesanan <strong>{{ $order->kode }}</strong> belum bisa kami terima.
</p>

@if ($ditolak?->alasan_tolak)
  <p style="margin:0 0 12px;padding:12px 14px;background:#fdecec;border-left:3px solid #c62828;">
    {{ $ditolak->alasan_tolak }}
  </p>
@endif

<p style="margin:0 0 12px;">
  Pesanan Anda tetap kami simpan. Silakan periksa lagi transfernya, lalu kirim ulang
  buktinya di halaman pesanan.
  @if ($order->kadaluarsa_pada)
    Batas pembayarannya <strong>{{ $order->kadaluarsa_pada->translatedFormat('l, j F Y H:i') }}</strong>.
  @endif
</p>

<p style="margin:0 0 20px;">
  Kalau Anda yakin transfernya sudah benar, hubungi kami lewat {{ kanal_kontak() }}
  dan sebutkan kode pesanan {{ $order->kode }}.
</p>

<a href="{{ $tautan }}"
   style="display:inline-block;background:#16255b;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:bold;border-radius:3px;">
  Kirim ulang bukti pembayaran
</a>
@endsection
