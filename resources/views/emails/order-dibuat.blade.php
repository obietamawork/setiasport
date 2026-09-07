@extends('emails.layout')

@section('isi')
<p style="margin:0 0 12px;">Halo {{ $order->customer->nama }},</p>

<p style="margin:0 0 12px;">
  Pesanan <strong>{{ $order->kode }}</strong> sudah kami terima. Pesanan ini belum
  kami kerjakan sampai pembayaran masuk.
</p>

@include('emails.komponen-rincian')

@php
    // Jangan pernah mengandalkan keberadaan tagihan di sini: email yang gagal
    // dirender adalah email yang tidak pernah terkirim, dan pelanggan tidak
    // akan pernah menerima tautan pesanannya.
    $tagihan = $order->payments->firstWhere('status', \App\Enums\PaymentStatus::MENUNGGU)
        ?? $order->payments->first();
@endphp

<p style="margin:0 0 12px;">
  Mohon transfer <strong>{{ rupiah($tagihan?->jumlah ?? $order->total) }}</strong>
  @if ($order->kadaluarsa_pada)
      sebelum <strong>{{ $order->kadaluarsa_pada->translatedFormat('l, j F Y H:i') }}</strong>.
      Lewat batas itu pesanan otomatis dibatalkan dan barangnya kembali dijual.
  @else
      untuk melanjutkan pesanan ini.
  @endif
</p>

<p style="margin:0 0 20px;">
  Unggah bukti transfer lewat halaman pesanan Anda — kami akan memeriksanya pada jam kerja.
</p>

<a href="{{ $tautan }}"
   style="display:inline-block;background:#ff5a1f;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:bold;border-radius:3px;">
  Buka halaman pesanan
</a>

<p style="margin:20px 0 0;font-size:12px;color:#5c6b64;">
  Simpan email ini. Tautan di atas adalah satu-satunya cara membuka pesanan Anda tanpa
  membuat akun — boleh dibagikan ke anggota tim yang ingin ikut memantau.
</p>
@endsection
