{{-- Gaya ditulis inline: klien email mengabaikan sebagian besar CSS eksternal. --}}
<div style="background:#f5f7f4;padding:24px 0;font-family:Arial,Helvetica,sans-serif;color:#0e1c17;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #dce4de;">

    <div style="background:#0b2b20;padding:16px 24px;">
      <span style="color:#ffffff;font-size:16px;font-weight:bold;">{{ setting('toko_nama', 'Setia Sport') }}</span>
    </div>

    <div style="padding:24px;line-height:1.6;font-size:14px;">
      @yield('isi')
    </div>

    <div style="border-top:1px solid #dce4de;padding:16px 24px;font-size:12px;color:#5c6b64;line-height:1.6;">
      <p style="margin:0 0 6px;">{{ setting('toko_alamat') }}</p>
      <p style="margin:0 0 6px;">Jam buka: {{ setting('toko_jam_buka') }}</p>
      <p style="margin:0;">
        Semua pesanan diambil sendiri di toko. Kami belum melayani pengiriman.
      </p>
    </div>
  </div>
</div>
