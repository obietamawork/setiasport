@php
    /** @var \App\Models\OrderItem $item */
    use App\Enums\DesignAssetType;
    use App\Enums\OrderItemStatus;

    $mockup = $item->designAssets->where('tipe', DesignAssetType::MOCKUP)->sortByDesc('versi');
    $kiriman = $item->designAssets->where('tipe', DesignAssetType::UPLOAD_PELANGGAN)->sortByDesc('versi');
    $perluSetuju = $item->status_item === OrderItemStatus::MENUNGGU_PERSETUJUAN;
@endphp

<div class="border-t border-line px-5 py-4">
    <h3 class="font-semibold">Desain</h3>

    @if ($item->sudahDisetujui())
        <div class="mt-2 border-l-2 border-court bg-court-soft p-3 text-sm leading-relaxed">
            Disetujui oleh <strong>{{ $item->approved_by_name }}</strong> pada
            {{ $item->approved_at->translatedFormat('j F Y, H:i') }}
            @if ($item->approvedDesign)
                &mdash; mockup v{{ $item->approvedDesign->versi }}
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
         tim, jadi tombolnya minta nama diketik dan pernyataan dicentang — dan
         siapa yang mengetiknya dicatat bersama versi mockup yang disetujui. --}}
    @if ($perluSetuju)
        <div class="mt-4 border border-signal bg-signal-soft p-4">
            <p class="font-semibold">Mockup menunggu persetujuan Anda</p>
            <p class="kolom-baca mt-1 text-sm leading-relaxed">
                Periksa nama, nomor, warna, dan penempatan logo. Waktu pengerjaan
                {{ $item->product->lead_time_hari }} hari kerja baru mulai dihitung
                setelah Anda menyetujui &mdash; dan setelah itu desain tidak dapat diubah.
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
                    <input type="checkbox" name="paham" value="1" class="mt-0.5 size-4 accent-court" required>
                    <span>
                        Saya menyetujui desain ini dan memahami bahwa setelah disetujui,
                        desain serta daftar pemain tidak dapat diubah.
                    </span>
                </label>
                <button type="submit" class="tombol tombol-utama">Setujui desain</button>
            </form>

            <details class="mt-4 border-t border-line pt-4">
                <summary class="cursor-pointer text-sm font-medium">Ada yang perlu diubah?</summary>
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
                        Sisa revisi gratis: {{ app(\App\Services\AlurDesain::class)->sisaRevisiGratis($item) }}
                        dari {{ setting('revisi_gratis_maks', 3) }}.
                    </p>
                </form>
            </details>
        </div>
    @endif

    @unless ($item->sudahDisetujui())
        <div class="mt-4">
            @if ($kiriman->isNotEmpty())
                <p class="text-sm font-medium">Desain yang Anda kirim</p>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($kiriman as $k)
                        <li class="flex flex-wrap items-center gap-3">
                            <span class="text-muted">v{{ $k->versi }}</span>
                            @if ($k->path)
                                <a href="{{ asset('storage/'.$k->path) }}" target="_blank" rel="noopener" class="tautan">Lihat gambar</a>
                            @endif
                            @if ($k->external_url)
                                <a href="{{ $k->external_url }}" target="_blank" rel="noopener" class="tautan">Tautan file</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-medium">Kirim desain sendiri</summary>
                <form method="POST" action="{{ route('lacak.item.desain', [$order->access_token, $item]) }}"
                      enctype="multipart/form-data" class="mt-3 space-y-3">
                    @csrf
                    <div>
                        <label for="berkas-{{ $item->id }}" class="label-form">Gambar preview</label>
                        <input type="file" id="berkas-{{ $item->id }}" name="berkas"
                               accept=".jpg,.jpeg,.png,.pdf" class="kotak-input">
                        <p class="mt-1 text-xs leading-relaxed text-muted">
                            JPG, PNG, atau PDF, maksimal {{ setting('upload_maks_mb', 10) }} MB.
                            File mentah seperti AI, PSD, atau CDR terlalu besar untuk sistem &mdash;
                            bagikan lewat tautan di bawah.
                        </p>
                    </div>
                    <div>
                        <label for="tautan-{{ $item->id }}" class="label-form">Tautan Google Drive (opsional)</label>
                        <input type="url" id="tautan-{{ $item->id }}" name="tautan" class="kotak-input"
                               placeholder="https://drive.google.com/...">
                    </div>
                    <div>
                        <label for="catdes-{{ $item->id }}" class="label-form">Catatan untuk desainer</label>
                        <textarea id="catdes-{{ $item->id }}" name="catatan" rows="2" class="kotak-input"></textarea>
                    </div>
                    <button type="submit" class="tombol tombol-garis text-sm">Kirim desain</button>
                </form>
            </details>
        </div>
    @endunless
</div>
