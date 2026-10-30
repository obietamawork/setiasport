@php
    /** @var \App\Models\OrderItem $item */
    use App\Enums\DesignAssetType;
    use App\Enums\OrderItemStatus;

    $mockup = $item->designAssets->where('tipe', DesignAssetType::MOCKUP)->sortByDesc('versi');
    $perluSetuju = $item->status_item === OrderItemStatus::MENUNGGU_PERSETUJUAN;

    $waDesain = tautan_wa('Halo '.setting('toko_nama', 'Setia Sport').', saya mau mengirim file desain '
        .'untuk pesanan '.$order->kode.' ('.$item->nama_produk.').');
@endphp

<div class="border-t border-line px-5 py-4">
    <h3 class="font-semibold">Desain</h3>

    @if ($item->sudahDisetujui())
        <div class="mt-2 border-l-2 border-navy bg-navy-50 p-3 text-sm leading-relaxed">
            Disetujui oleh <strong>{{ $item->approved_by_name }}</strong> pada
            {{ $item->approved_at->translatedFormat('j F Y, H:i') }}
            @if ($item->approvedDesign)
                mockup v{{ $item->approvedDesign->versi }}
            @endif
        </div>
    @endif

    @if ($mockup->isNotEmpty())
        <div class="mt-3 space-y-2">
            @foreach ($mockup as $m)
                <div class="flex flex-wrap items-center gap-3 border border-line p-3 text-sm">
                    <span class="font-medium">Mockup v{{ $m->versi }}</span>
                    @if ($m->path)
                        <a href="{{ asset('storage/'.$m->path) }}" target="_blank" rel="noopener" class="tautan">Lihat gambar</a>
                    @endif
                    @if ($m->external_url)
                        <a href="{{ $m->external_url }}" target="_blank" rel="noopener" class="tautan">File siap cetak</a>
                    @endif
                    <span class="ml-auto text-xs text-muted tabular">{{ $m->created_at->translatedFormat('j M, H:i') }}</span>
                    @if ($m->catatan)
                        <p class="w-full text-xs text-muted">{{ $m->catatan }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Gerbang persetujuan (docs/02). Tautan halaman ini memang beredar di grup
         tim, jadi tombolnya minta nama diketik dan pernyataan dicentang: dan
         siapa yang mengetiknya dicatat bersama versi mockup yang disetujui. --}}
    @if ($perluSetuju)
        <div class="mt-4 border border-merah bg-merah-50 p-4">
            <p class="font-semibold">Mockup menunggu persetujuan Anda</p>
            <p class="kolom-baca mt-1 text-sm leading-relaxed">
                Periksa nama, nomor, warna, dan penempatan logo. Waktu pengerjaan
                {{ $item->product->lead_time_hari }} hari kerja baru mulai dihitung
                setelah Anda menyetujui dan setelah itu desain tidak dapat diubah.
            </p>

            <form method="POST" action="{{ route('lacak.item.setujui', [$order->access_token, $item]) }}"
                  class="mt-4 space-y-3 border-t border-line pt-4">
                @csrf
                <div>
                    <label for="setuju-nama-{{ $item->id }}" class="label-form">Ketik nama Anda</label>
                    <input type="text" id="setuju-nama-{{ $item->id }}" name="nama" class="kotak-input max-w-xs"
                           required minlength="3" autocomplete="name">
                </div>
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="paham" value="1" class="mt-0.5 size-4 accent-navy" required>
                    <span>
                        Saya menyetujui desain ini dan memahami bahwa setelah disetujui,
                        desain serta daftar pemain tidak dapat diubah.
                    </span>
                </label>
                <button type="submit" class="tombol tombol-utama">Setujui desain</button>
            </form>

            <details class="mt-4 border-t border-line pt-4">
                <summary class="cursor-pointer text-sm font-medium">Ada yang perlu diubah?</summary>
                @php $sisaRevisi = app(\App\Services\AlurDesain::class)->sisaRevisi($item); @endphp

                {{-- Setelah jatahnya habis, formulirnya tidak ditampilkan sama sekali
                     (KEP-038). Tombol yang pasti ditolak hanya membuang waktu
                     pelanggan dan membuatnya merasa dipermainkan. --}}
                @if ($sisaRevisi > 0)
                    <form method="POST" action="{{ route('lacak.item.revisi', [$order->access_token, $item]) }}"
                          class="mt-3 space-y-3">
                        @csrf
                        <div>
                            <label for="revisi-{{ $item->id }}" class="label-form">Bagian mana yang perlu diubah?</label>
                            <textarea id="revisi-{{ $item->id }}" name="catatan" rows="3" class="kotak-input"
                                      required minlength="5"
                                      placeholder="Warna kerah terlalu gelap, logo sponsor kurang ke kanan"></textarea>
                        </div>
                        <button type="submit" class="tombol tombol-garis text-sm">Minta revisi</button>
                        <p class="text-xs text-muted">
                            Sisa revisi: {{ $sisaRevisi }} dari {{ setting('revisi_gratis_maks', 3) }}.
                        </p>
                    </form>
                @else
                    <p class="mt-3 text-sm leading-relaxed text-muted">
                        Jatah revisi {{ setting('revisi_gratis_maks', 3) }} kali sudah terpakai.
                        Kalau masih ada yang perlu diubah, hubungi kami lewat
                        {{ kanal_kontak() }} supaya bisa dibicarakan langsung.
                    </p>
                @endif
            </details>
        </div>
    @endif

    {{-- File desain pelanggan hanya lewat WhatsApp (KEP-095), tidak diunggah di
         sini. Halaman ini juga memuat tagihan, dan kotak unggah di bawah
         instruksi transfer terbaca seperti bagian dari pembayaran. Yang tersisa
         hanya kabar ke mana filenya dikirim, sampai mockup pertama terbit. --}}
    @if ($item->desain_sumber === 'sendiri' && $mockup->isEmpty() && ! $item->sudahDisetujui())
        <div class="mt-3 border-l-2 border-navy bg-navy-50 p-3 text-sm leading-relaxed">
            <p class="font-semibold">File desain Anda dikirim lewat WhatsApp</p>
            <p class="mt-1">
                Admin kami menghubungi
                {{ $order->customer->no_hp ? 'nomor '.$order->customer->no_hp : 'Anda' }}
                untuk menerima filenya. Semua format diterima: CDR, AI, PSD, PDF, PNG, JPG,
                atau tautan Google Drive. Sudah siap? Kirim sekarang; kode pesanannya sudah
                tertulis di pesan.
            </p>
            @if ($waDesain)
                <a href="{{ $waDesain }}" target="_blank" rel="noopener" class="tombol tombol-garis tombol-sm mt-3">
                    <x-ikon nama="whatsapp" ukuran="size-4" />
                    Kirim desain lewat WhatsApp
                </a>
            @endif
        </div>
    @endif
</div>
