@props(['fase'])

{{--
    Deret tahap menurun untuk HP (KEP-182). Di layar yang lebih lebar dipakai
    versi mendatarnya, .tahap-datar: mulai 640px di halaman lacak, mulai 768px
    di detail pesanan panel yang tahapnya lebih banyak. Di bawah batas itu deret
    mendatar harus digeser dan tahap terakhirnya tersembunyi. Titik hijau untuk
    tahap yang sudah lewat, navy bercincin kuning untuk tahap sekarang, abu
    untuk yang belum.

    Kolom kanan memuat waktu tahap (halaman lacak) atau keterangan status
    rincinya (detail pesanan panel), mana yang ada.
--}}
<ol {{ $attributes }}>
    @foreach ($fase as $f)
        <li class="flex gap-3 {{ $loop->last ? '' : 'pb-3' }}">
            <div class="flex flex-col items-center">
                <span class="mt-1 size-2.5 shrink-0 rounded-full
                    {{ $f['keadaan'] === 'kini' ? 'bg-navy ring-4 ring-kuning' : ($f['keadaan'] === 'lewat' ? 'bg-ok' : 'bg-line-strong') }}"></span>
                @unless ($loop->last)
                    <span class="w-px flex-1 {{ $f['keadaan'] === 'lewat' ? 'bg-ok' : 'bg-line' }}"></span>
                @endunless
            </div>
            <div class="-mt-0.5 flex min-w-0 flex-1 flex-wrap items-baseline justify-between gap-x-3">
                <p class="text-sm {{ $f['keadaan'] === 'kini' ? 'font-bold' : ($f['keadaan'] === 'lewat' ? '' : 'text-muted') }}">{{ $f['label'] }}</p>
                @if (! empty($f['waktu']))
                    <p class="text-xs text-muted tabular">{{ $f['waktu']->translatedFormat('j M, H.i') }}</p>
                @elseif (! empty($f['keterangan']))
                    <p class="text-xs text-muted">{{ $f['keterangan'] }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
