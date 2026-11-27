{{--
    Data pelanggan (KEP-124). Formulir yang jarang dipakai, yaitu betulkan
    kontak (KEP-098) dan penanda reseller (KEP-046), dibuka lewat menu titik
    tiga. Kabari lewat WhatsApp (KEP-004) ikut di sini karena langkah
    berikut "Kabari pelanggan" mengarah ke kartu ini.
--}}
@php
    $pelanggan = $pesanan->customer;
    $tautanPeta = $pesanan->tautanLokasiAman()
        ? $pesanan->tautan_lokasi
        : ($pesanan->alamat_kirim ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($pesanan->alamat_kirim) : null);
    $bukaKontak = $errors->has('nama') || $errors->has('no_hp') || $errors->has('email');
@endphp

<section id="pelanggan" class="kartu-bayar scroll-mt-32"
         x-data="{ ubah: @js($bukaKontak ? 'kontak' : null), tersalin: false }">
    <div class="flex items-start justify-between gap-3">
        <h2 class="judul-kartu"><x-ikon nama="orang" class="text-navy-400" /> Data pelanggan</h2>

        @if ($izin->punyaIzin('order.kelola'))
            <details class="relative" x-data @click.outside="$el.removeAttribute('open')">
                <summary class="tombol-ikon" title="Tindakan lain">
                    <x-ikon nama="titik-tiga" ukuran="size-4" />
                    <span class="sr-only">Tindakan lain untuk pelanggan</span>
                </summary>
                <div class="menu-tarik menu-aksi-isi right-0 w-56">
                    <button type="button" @click="ubah = 'kontak'; $el.closest('details').open = false">Betulkan kontak</button>
                    <button type="button" @click="ubah = 'reseller'; $el.closest('details').open = false">
                        {{ $pelanggan->reseller ? 'Ubah penanda reseller' : 'Tandai sebagai reseller' }}
                    </button>
                </div>
            </details>
        @endif
    </div>

    <dl class="mt-4 space-y-3 text-sm">
        <div class="flex items-start gap-3">
            <dt><x-ikon nama="orang" ukuran="size-5" class="text-navy-400" /><span class="sr-only">Nama</span></dt>
            <dd class="flex flex-wrap items-center gap-2 font-semibold">
                {{ $pelanggan->nama }}
                @if ($pelanggan->reseller)
                    <span class="chip chip-kuning">Reseller</span>
                @endif
            </dd>
        </div>
        <div class="flex items-start gap-3">
            <dt><x-ikon nama="whatsapp" ukuran="size-5" class="text-ok" /><span class="sr-only">Nomor WhatsApp</span></dt>
            <dd class="tabular">
                @if (nomor_wa($pelanggan->no_hp))
                    <a href="https://wa.me/{{ nomor_wa($pelanggan->no_hp) }}" target="_blank" rel="noopener"
                       class="area-sentuh font-medium text-navy-700 hover:underline">{{ $pelanggan->no_hp }}</a>
                @else
                    {{ $pelanggan->no_hp ?: '-' }}
                @endif
            </dd>
        </div>
        @if ($pelanggan->email)
            <div class="flex items-start gap-3">
                <dt><x-ikon nama="chat" ukuran="size-5" class="text-navy-400" /><span class="sr-only">Email</span></dt>
                <dd class="break-all">{{ $pelanggan->email }}</dd>
            </div>
        @endif
        @if ($pesanan->alamat_kirim)
            <div class="flex items-start gap-3">
                <dt><x-ikon nama="kirim" ukuran="size-5" class="text-navy-400" /><span class="sr-only">Alamat kirim</span></dt>
                <dd class="leading-relaxed">{{ $pesanan->alamat_kirim }}</dd>
            </div>
        @endif
    </dl>

    @if ($tautanPeta)
        <a href="{{ $tautanPeta }}" target="_blank" rel="noopener noreferrer" class="tombol tombol-garis tombol-sm mt-4">
            <x-ikon nama="buka-luar" ukuran="size-4" /> Lihat di peta
        </a>
    @endif

    @if ($pelanggan->reseller && $pelanggan->catatan_reseller)
        <p class="mt-3 text-sm text-muted">{{ $pelanggan->catatan_reseller }}</p>
    @endif

    {{-- Salah ketik email atau nomor WhatsApp saat memesan (KEP-098).
         Tanpa ini semua email berikutnya ikut terpental. --}}
    @if ($izin->punyaIzin('order.kelola'))
        <form method="POST" action="{{ route('panel.pesanan.pelanggan.kontak', $pesanan) }}"
              class="mt-4 space-y-3 border-t border-line pt-4" x-show="ubah === 'kontak'" x-cloak>
            @csrf @method('PUT')
            <p class="font-semibold">Betulkan kontak</p>
            <div>
                <label for="kontak-nama" class="label-form">Nama</label>
                <input type="text" id="kontak-nama" name="nama" maxlength="150" required class="kotak-input"
                       value="{{ old('nama', $pelanggan->nama) }}">
            </div>
            <div>
                <label for="kontak-hp" class="label-form">Nomor WhatsApp</label>
                <input type="tel" id="kontak-hp" name="no_hp" maxlength="20" required class="kotak-input tabular"
                       value="{{ old('no_hp', $pelanggan->no_hp) }}">
            </div>
            <div>
                <label for="kontak-email" class="label-form">Email</label>
                <input type="email" id="kontak-email" name="email" maxlength="150" class="kotak-input"
                       value="{{ old('email', $pelanggan->email) }}"
                       @if ($pelanggan->akunAktif()) readonly @endif>
                @if ($pelanggan->akunAktif())
                    <p class="mt-1 text-xs text-muted">Pelanggan ini punya akun; emailnya dipakai untuk masuk.</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="tombol tombol-navy tombol-sm">Simpan kontak</button>
                <button type="button" class="tombol tombol-garis tombol-sm" @click="ubah = null">Batal</button>
            </div>
            <p class="text-xs leading-relaxed text-muted">Berlaku untuk semua pesanan pelanggan ini.</p>
        </form>

        {{-- Penanda reseller (KEP-046). Harganya tidak berubah otomatis:
             potongan diberikan admin per pesanan lewat penyesuaian biaya. --}}
        <form method="POST" action="{{ route('panel.pesanan.reseller', $pesanan) }}"
              class="mt-4 space-y-3 border-t border-line pt-4" x-show="ubah === 'reseller'" x-cloak>
            @csrf
            <p class="font-semibold">{{ $pelanggan->reseller ? 'Ubah penanda reseller' : 'Tandai sebagai reseller' }}</p>
            <input type="hidden" name="reseller" value="{{ $pelanggan->reseller ? 0 : 1 }}">
            @unless ($pelanggan->reseller)
                <div>
                    <label for="catatan_reseller" class="label-form">Kesepakatan potongan (opsional)</label>
                    <input type="text" id="catatan_reseller" name="catatan_reseller" maxlength="200"
                           class="kotak-input" placeholder="Contoh: potongan Rp5.000 per pcs di atas 50 pcs">
                </div>
            @endunless
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="tombol tombol-garis tombol-sm">
                    {{ $pelanggan->reseller ? 'Lepas penanda' : 'Tandai reseller' }}
                </button>
                <button type="button" class="tombol tombol-garis tombol-sm" @click="ubah = null">Batal</button>
            </div>
            <p class="text-xs leading-relaxed text-muted">
                Penanda ini pengingat untuk admin. Potongannya tetap ditambahkan
                per pesanan lewat penyesuaian biaya.
            </p>
        </form>
    @endif

    {{-- Tanpa WhatsApp API (KEP-004), sistem menyiapkan teksnya dan admin
         yang menekan kirim. --}}
    <div class="mt-5 rounded-[12px] bg-paper p-4">
        <p class="text-sm font-semibold">Kabari lewat WhatsApp</p>
        <p class="mt-1 text-xs leading-relaxed text-muted">
            {{-- Pembeli di toko sering hanya punya nomor WhatsApp (KEP-054). --}}
            @if ($pelanggan->email)
                Email otomatis sudah dikirim. Pakai ini kalau pelanggan belum merespons.
            @else
                Pelanggan ini tidak punya email, jadi tidak ada kabar otomatis. Kabari lewat WhatsApp.
            @endif
        </p>
        <p class="mt-3 max-h-24 overflow-y-auto rounded-[8px] bg-field p-3 text-xs leading-relaxed wrap-break-word"
           tabindex="0" role="region" aria-label="Isi pesan WhatsApp"
           x-ref="pesan">{{ $pesanWa['teks'] }}</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" class="tombol tombol-garis tombol-sm"
                    @click="navigator.clipboard.writeText($refs.pesan.textContent.trim()); tersalin = true; setTimeout(() => tersalin = false, 2000)">
                <x-ikon nama="salin" ukuran="size-4" />
                <span x-text="tersalin ? 'Tersalin' : 'Salin teks'">Salin teks</span>
            </button>
            <a href="{{ $pesanWa['url'] }}" target="_blank" rel="noopener" class="tombol tombol-navy tombol-sm">
                <x-ikon nama="whatsapp" ukuran="size-4" /> Buka WhatsApp
            </a>
        </div>
    </div>
</section>
