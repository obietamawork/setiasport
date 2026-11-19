{{--
    Catatan pelanggan (KEP-124): catatan pesanan dan catatan desain tiap
    barang, lalu jejak email (KEP-098) yang dulu di kolom samping.
--}}
@php
    $catatanBarang = $pesanan->items->filter(fn ($i) => filled($i->catatan));
@endphp

<section class="kartu-bayar">
    <h2 class="judul-kartu"><x-ikon nama="chat" class="text-navy-400" /> Catatan pelanggan</h2>

    <div class="mt-4 space-y-2 text-sm">
        @if ($pesanan->catatan)
            <p class="rounded-[10px] bg-kuning-50 px-4 py-3 leading-relaxed italic">"{{ $pesanan->catatan }}"</p>
        @endif
        @foreach ($catatanBarang as $item)
            <p class="rounded-[10px] bg-kuning-50 px-4 py-3 leading-relaxed">
                <span class="font-semibold not-italic">{{ $item->nama_produk }}:</span>
                <span class="italic">"{{ $item->catatan }}"</span>
            </p>
        @endforeach
        @if (! $pesanan->catatan && $catatanBarang->isEmpty())
            <p class="text-muted">Tidak ada catatan dari pelanggan.</p>
        @endif
    </div>

    @if ($pesanan->emailLogs->isNotEmpty())
        <div class="mt-5 border-t border-line pt-4">
            <p class="text-sm font-semibold">Email terkirim</p>
            <ul class="mt-2 space-y-1.5 text-xs">
                @foreach ($pesanan->emailLogs as $log)
                    <li>
                        <span class="flex justify-between gap-2">
                            <span>{{ $log->template }}</span>
                            <span class="{{ $log->masihPerluDitangani() ? 'text-merah' : 'text-muted' }}">
                                {{ $log->status->label() }}{{ $log->ditindaklanjuti_pada ? ', sudah ditangani' : '' }}
                            </span>
                        </span>

                        {{-- Email yang tidak sampai (KEP-098): kirim ulang setelah
                             alamatnya dibetulkan, atau tandai sudah dikabari lewat
                             WhatsApp supaya hilang dari dashboard. --}}
                        @if ($log->masihPerluDitangani() && $izin->punyaIzin('order.kelola'))
                            <div class="mt-1 flex flex-wrap gap-3">
                                <form method="POST" action="{{ route('panel.pesanan.email.ulang', [$pesanan, $log]) }}">
                                    @csrf
                                    <button type="submit" class="tautan">Kirim ulang</button>
                                </form>
                                <form method="POST" action="{{ route('panel.pesanan.email.selesai', [$pesanan, $log]) }}">
                                    @csrf
                                    <button type="submit" class="tautan text-muted">Sudah dikabari lewat WhatsApp</button>
                                </form>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
