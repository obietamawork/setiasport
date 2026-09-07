<table role="presentation" style="width:100%;border-collapse:collapse;font-size:13px;margin:16px 0;">
  @foreach ($order->items as $item)
    <tr>
      <td style="padding:6px 0;border-bottom:1px solid #dce4de;">
        {{ $item->nama_produk }}
        <span style="color:#5c6b64;">&times; {{ $item->qty }}</span>
      </td>
      <td style="padding:6px 0;border-bottom:1px solid #dce4de;text-align:right;white-space:nowrap;">
        {{ rupiah($item->subtotal) }}
      </td>
    </tr>
  @endforeach

  @foreach ($order->adjustments as $p)
    <tr>
      <td style="padding:6px 0;border-bottom:1px solid #dce4de;">{{ $p->deskripsi }}</td>
      <td style="padding:6px 0;border-bottom:1px solid #dce4de;text-align:right;white-space:nowrap;">
        {{ $p->jumlah < 0 ? '−' : '' }}{{ rupiah(abs($p->jumlah)) }}
      </td>
    </tr>
  @endforeach

  <tr>
    <td style="padding:10px 0;font-weight:bold;">Total</td>
    <td style="padding:10px 0;text-align:right;font-weight:bold;">{{ rupiah($order->total) }}</td>
  </tr>

  @if ($order->sudah_dibayar > 0)
    <tr>
      <td style="padding:2px 0;color:#5c6b64;">Sudah dibayar</td>
      <td style="padding:2px 0;text-align:right;color:#5c6b64;">{{ rupiah($order->sudah_dibayar) }}</td>
    </tr>
    <tr>
      <td style="padding:2px 0;font-weight:bold;">Sisa tagihan</td>
      <td style="padding:2px 0;text-align:right;font-weight:bold;color:#ff5a1f;">
        {{ rupiah($order->sisa_tagihan) }}
      </td>
    </tr>
  @endif
</table>
