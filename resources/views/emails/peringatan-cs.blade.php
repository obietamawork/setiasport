@extends('emails.layout')

@section('isi')
<p style="margin:0 0 16px;">
  {{ $percakapan->count() === 1 ? 'Ada pelanggan' : 'Ada '.$percakapan->count().' pelanggan' }}
  yang menunggu jawaban di chat CS website.
</p>

<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 20px;font-size:14px;">
  @foreach ($percakapan as $p)
    <tr>
      <td style="padding:10px 0;border-top:1px solid #e3e7f0;">
        <strong>{{ $p->nama }}</strong><br>
        <span style="color:#5d687f;">{{ $p->topik->label() }}</span>
      </td>
      <td style="padding:10px 0;border-top:1px solid #e3e7f0;text-align:right;color:#d62b1f;white-space:nowrap;">
        menunggu {{ $p->menunggu_sejak->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}
      </td>
    </tr>
  @endforeach
</table>

<p style="margin:0 0 20px;font-size:13px;color:#5d687f;">
  Chat yang tidak dijawab lebih buruk daripada tidak ada chat. Kalau memang di luar jam
  layanan, cukup balas singkat bahwa pertanyaannya akan dijawab besok pagi.
</p>

<a href="{{ route('panel.cs.index') }}"
   style="display:inline-block;background:#16255b;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:bold;border-radius:8px;">
  Buka chat CS
</a>
@endsection
