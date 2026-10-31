@extends('emails.layout')

{{-- Mockup menunggu persetujuan pelanggan (KEP-098). Gambarnya sengaja tidak
     disematkan: yang disetujui harus versi yang tampil di halaman pesanan,
     lengkap dengan tombol setuju dan revisinya, bukan salinan di kotak masuk. --}}
@php
    $menunggu = $order->items->where('status_item', \App\Enums\OrderItemStatus::MENUNGGU_PERSETUJUAN);
    $sisaRevisi = $menunggu->map(fn ($i) => app(\App\Services\AlurDesain::class)->sisaRevisi($i))->min();
@endphp

@section('isi')
<p style="margin:0 0 12px;">Halo {{ $order->customer->nama }},</p>

<p style="margin:0 0 12px;">
  Desain untuk pesanan <strong>{{ $order->kode }}</strong> sudah jadi dan menunggu persetujuan Anda:
</p>

<ul style="margin:0 0 16px;padding-left:20px;">
  @foreach ($menunggu as $item)
    <li style="margin:0 0 4px;">{{ $item->nama_produk }} ({{ $item->qty }} pcs)</li>
  @endforeach
</ul>

<p style="margin:0 0 12px;">
  Periksa warna, logo, serta nama dan nomor punggung satu per satu. Kalau sudah sesuai,
  tekan <strong>Setujui desain</strong> di halaman pesanan. Produksi baru dimulai setelah
  Anda menyetujuinya, dan perkiraan tanggal jadi dihitung dari hari itu.
</p>

<p style="margin:0 0 20px;">
  @if ($sisaRevisi > 0)
    Masih ada yang perlu diubah? Tulis permintaannya lewat tombol revisi di halaman yang
    sama. Sisa revisi: {{ $sisaRevisi }} kali.
  @else
    Jatah revisi sudah terpakai semua. Kalau masih ada yang perlu diubah, hubungi kami lewat
    {{ kanal_kontak() }} dan sebutkan kode pesanan {{ $order->kode }}.
  @endif
</p>

<a href="{{ $tautan }}"
   style="display:inline-block;background:#16255b;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:bold;border-radius:3px;">
  Periksa desain
</a>
@endsection
