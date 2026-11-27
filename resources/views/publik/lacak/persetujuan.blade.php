{{--
    Persetujuan desain seperti gambar klien (KEP-125): mockup besar, detail
    pesanan, lipatan nama dan nomor punggung yang masih bisa diubah, dua
    pilihan tindakan, dan satu tombol Kirim keputusan.

    Nama dan pernyataan tetap diminta saat menyetujui (docs/02): tautan
    halaman ini beredar di grup tim, dan siapa yang menyetujui versi mana
    adalah yang menyelesaikan sengketa berbulan-bulan kemudian.

    @var \App\Models\OrderItem $item
    @var \App\Models\Order $order
--}}
@php
    use App\Enums\DesignAssetType;

    $mockup = $item->designAssets->where('tipe', DesignAssetType::MOCKUP)->sortByDesc('versi')->first();
    $maks = (int) setting('revisi_gratis_maks', 3);
    $sisaRevisi = app(\App\Services\AlurDesain::class)->sisaRevisi($item);
    $revisiKe = min($maks, $maks - $sisaRevisi + 1);
    $jumlahPemain = $item->rosters->sum('jumlah');
    $pilihanAwal = old('keputusan', $errors->has('catatan') ? 'revisi' : ($errors->has('nama') || $errors->has('paham') ? 'setuju' : null));

    $detail = array_filter([
        ['Produk', $item->product?->nama ?? $item->nama_produk],
        ['Jumlah', $item->qty.' pcs'],
        ...$item->options->map(fn ($o) => [$o->option_nama, $o->option_value])->all(),
        ['Catatan', $item->catatan ?: '-'],
    ]);
@endphp

<section class="kartu-bayar space-y-5" id="persetujuan-{{ $item->id }}"
         x-data="{ pilihan: @js($pilihanAwal), catatan: @js(old('catatan', '')) }">
    <div class="flex items-start gap-4 rounded-[14px] bg-navy-50 p-4">
        <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-navy-100 text-navy-700">
            <x-ikon nama="gambar" ukuran="size-6" />
        </span>
        <div>
            <h3 class="text-xl font-extrabold">Persetujuan desain</h3>
            <p class="mt-1 text-sm leading-relaxed">
                Silakan periksa kembali desain, data pemain, dan detail pesanan.
                Pilih <strong>Setujui desain</strong> jika sudah sesuai, atau <strong>Ajukan revisi</strong>
                jika masih perlu diperbaiki.
            </p>
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
        <div class="text-center">
            <div class="overflow-hidden rounded-[14px] bg-paper">
                @if ($mockup?->path)
                    <img src="{{ asset('storage/'.$mockup->path) }}" alt="Mockup desain versi {{ $mockup->versi }}"
                         class="mx-auto max-h-96 w-full object-contain">
                @else
                    <div class="flex h-56 items-center justify-center text-navy-300">
                        <x-ikon nama="kaos" ukuran="size-12" />
                    </div>
                @endif
            </div>
            <div class="mt-3 flex flex-wrap justify-center gap-2">
                @if ($mockup?->path)
                    <a href="{{ asset('storage/'.$mockup->path) }}" target="_blank" rel="noopener" class="tombol tombol-garis tombol-sm">
                        <x-ikon nama="cari" ukuran="size-4" /> Lihat ukuran penuh
                    </a>
                @endif
                @if ($mockup?->external_url)
                    <a href="{{ $mockup->external_url }}" target="_blank" rel="noopener" class="tombol tombol-garis tombol-sm">
                        <x-ikon nama="buka-luar" ukuran="size-4" /> Buka file desain
                    </a>
                @endif
            </div>
            @if ($mockup)
                <p class="mt-2 text-xs text-muted">Mockup versi {{ $mockup->versi }}, dikirim {{ $mockup->created_at->translatedFormat('j M Y, H.i') }}</p>
            @endif
        </div>

        <div class="rounded-[14px] border border-line">
            <h4 class="flex items-center gap-2 border-b border-line px-4 py-3 font-bold">
                <x-ikon nama="bantuan" class="text-navy-400" /> Detail pesanan
            </h4>
            <dl class="divide-y divide-line px-4 text-sm">
                @foreach ($detail as [$label, $nilai])
                    <div class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-3 py-2.5">
                        <dt class="text-muted">{{ $label }}</dt>
                        <dd class="font-medium">{{ $nilai }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    {{-- Nama dan nomor punggung masih bisa diubah sampai desain disetujui.
         Dilipat supaya keputusan di bawahnya tetap terlihat. --}}
    <details class="lipatan-pemain rounded-[14px] border border-line" @if ($errors->has('roster') || $errors->has('roster.*')) open @endif>
        <summary class="flex cursor-pointer items-center gap-3 px-4 py-3.5">
            <x-ikon nama="pekerja" class="text-navy-400" />
            <span class="flex-1 font-bold">Nama &amp; nomor punggung <span class="font-normal text-muted">({{ $jumlahPemain }} pemain)</span></span>
            <x-ikon nama="turun" ukuran="size-5" class="lipatan-pemain-panah text-muted" />
        </summary>
        <div class="border-t border-line p-4">
            @include('komponen.roster', ['item' => $item, 'order' => $order, 'dalamLipatan' => true])
        </div>
    </details>

    <form method="POST" action="{{ route('lacak.item.setujui', [$order->access_token, $item]) }}"
          data-aksi-setuju="{{ route('lacak.item.setujui', [$order->access_token, $item]) }}"
          @if ($sisaRevisi > 0) data-aksi-revisi="{{ route('lacak.item.revisi', [$order->access_token, $item]) }}" @endif
          :action="pilihan === 'revisi' ? $el.dataset.aksiRevisi : $el.dataset.aksiSetuju"
          class="space-y-4">
        @csrf
        {{-- Versi yang sedang dilihat (KEP-098): kalau toko mengirim versi baru
             sebelum keputusan terkirim, keputusannya ditolak. --}}
        <input type="hidden" name="mockup_versi" value="{{ $mockup?->versi }}">
        <input type="hidden" name="keputusan" :value="pilihan">

        <h4 class="flex items-center gap-2 text-lg font-bold">
            <x-ikon nama="daftar" class="text-navy-400" /> Pilih tindakan
        </h4>

        <div class="grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Pilih tindakan">
            <button type="button" class="pilih-tindakan" :class="pilihan === 'setuju' && 'pilih-tindakan-setuju'"
                    role="radio" :aria-checked="pilihan === 'setuju'" @click="pilihan = 'setuju'">
                <span class="pilih-tindakan-ikon bg-ok text-white"><x-ikon nama="centang" ukuran="size-6" /></span>
                <span class="block text-lg font-extrabold">Setujui desain</span>
                <span class="block text-sm text-muted">Desain sudah sesuai dan dapat dilanjutkan ke tahap produksi.</span>
            </button>

            @if ($sisaRevisi > 0)
                <button type="button" class="pilih-tindakan" :class="pilihan === 'revisi' && 'pilih-tindakan-revisi'"
                        role="radio" :aria-checked="pilihan === 'revisi'" @click="pilihan = 'revisi'">
                    <span class="pilih-tindakan-ikon bg-navy-50 text-navy-700"><x-ikon nama="muat-ulang" ukuran="size-6" /></span>
                    <span class="block text-lg font-extrabold">Ajukan revisi</span>
                    <span class="block text-sm text-muted">Desain masih perlu diperbaiki. Tuliskan bagian yang perlu direvisi.</span>
                </button>
            @else
                {{-- Setelah jatahnya habis tidak ada tombol yang pasti ditolak (KEP-038). --}}
                <div class="pilih-tindakan cursor-default opacity-70" aria-disabled="true">
                    <span class="pilih-tindakan-ikon bg-navy-50 text-muted"><x-ikon nama="muat-ulang" ukuran="size-6" /></span>
                    <span class="block text-lg font-extrabold text-muted">Ajukan revisi</span>
                    <span class="block text-sm leading-relaxed text-muted">
                        Jatah revisi {{ $maks }} kali sudah terpakai. Kalau masih ada yang perlu diubah,
                        hubungi kami lewat {{ kanal_kontak() }} supaya bisa dibicarakan langsung.
                    </span>
                </div>
            @endif
        </div>

        <div x-show="pilihan === 'setuju'" x-cloak class="space-y-3 rounded-[14px] border border-ok bg-ok-50 p-4">
            <div>
                <label for="setuju-nama-{{ $item->id }}" class="label-form">Ketik nama Anda</label>
                <input type="text" id="setuju-nama-{{ $item->id }}" name="nama" class="kotak-input max-w-xs" value="{{ old('nama') }}"
                       minlength="3" autocomplete="name" :required="pilihan === 'setuju'" :disabled="pilihan !== 'setuju'">
            </div>
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="paham" value="1" class="mt-0.5 size-4 accent-navy"
                       :required="pilihan === 'setuju'" :disabled="pilihan !== 'setuju'">
                <span>
                    Saya menyetujui desain ini (mockup v{{ $mockup?->versi }}) dan memahami bahwa setelah disetujui,
                    desain serta daftar pemain tidak dapat diubah. Waktu pengerjaan
                    {{ $item->product?->lead_time_hari }} hari kerja mulai dihitung sejak saat ini.
                </span>
            </label>
        </div>

        @if ($sisaRevisi > 0)
            <div x-show="pilihan === 'revisi'" x-cloak>
                <label for="revisi-{{ $item->id }}" class="flex items-center gap-2 font-bold">
                    <x-ikon nama="daftar" class="text-navy-400" />
                    Catatan revisi <span class="font-normal text-muted">(jika memilih Ajukan revisi)</span>
                </label>
                <textarea id="revisi-{{ $item->id }}" name="catatan" rows="4" maxlength="250" class="kotak-input mt-2"
                          x-model="catatan" minlength="5" :required="pilihan === 'revisi'" :disabled="pilihan !== 'revisi'"
                          placeholder="Contoh: ganti warna kerah, ubah nomor, atau sesuaikan logo."></textarea>
                <p class="mt-1 text-right text-sm text-muted tabular"><span x-text="catatan.length">0</span> / 250</p>
            </div>

            <p class="flex flex-wrap items-center gap-x-6 gap-y-1 rounded-[12px] bg-paper px-4 py-3 text-sm">
                <span class="flex items-center gap-2">
                    <x-ikon nama="bantuan" ukuran="size-4" class="text-navy-400" />
                    Batas revisi: <strong>maksimal {{ $maks }} kali.</strong>
                </span>
                <span>Revisi ke: <strong>{{ $revisiKe }} dari {{ $maks }}</strong></span>
            </p>
        @endif

        <button type="submit" class="tombol tombol-navy w-full py-3 text-base" :disabled="! pilihan">
            <x-ikon nama="lanjut" ukuran="size-5" /> Kirim keputusan
        </button>
    </form>
</section>
