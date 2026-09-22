{{-- Gaya ditulis inline: klien email mengabaikan sebagian besar CSS eksternal. --}}
<div style="background:#f5f7f4;padding:24px 0;font-family:Arial,Helvetica,sans-serif;color:#0e1c17;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #dce4de;">

    <div style="background:#16255b;padding:16px 24px;">
      {{-- Logo versi terang untuk latar navy. Klien email yang memblokir gambar
           menampilkan teks alt, jadi nama toko tetap terbaca. --}}
      <img src="{{ asset('logo-terang.png') }}" alt="{{ setting('toko_nama', 'Setia Sport') }} Wonogiri"
           width="127" height="32" style="display:block;height:32px;width:auto;border:0;">
    </div>

    <div style="padding:24px;line-height:1.6;font-size:14px;">
      @yield('isi')
    </div>

    <div style="border-top:1px solid #dce4de;padding:16px 24px;font-size:12px;color:#5c6b64;line-height:1.6;">
      <p style="margin:0 0 6px;">{{ setting('toko_alamat') }}</p>
      <p style="margin:0 0 6px;">Jam buka:<br>{{ baris_html(setting('toko_jam_buka')) }}</p>
      <p style="margin:0;">
        @if (isset($order) && $order->dikirim())
          Pesanan ini dikirim ke alamat Anda.
        @else
          Pesanan diambil sendiri di toko.
        @endif
        Ada pertanyaan atau komplain? Hubungi kami lewat {{ kanal_kontak() }}.
      </p>
    </div>
  </div>
</div>
