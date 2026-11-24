{{--
    Ringkasan total (KEP-124), menggantikan kartu "Uang" di samping. Subtotal
    dihitung balik dari total supaya selalu cocok dengan tagihan, apa pun
    yang ikut di dalamnya.
--}}
@php
    $penyesuaian = (int) $pesanan->adjustments->sum('jumlah');
    $subtotal = $pesanan->total - $penyesuaian - (int) $pesanan->biaya_layanan - (int) $pesanan->ongkir + (int) $pesanan->diskon;
@endphp

<section class="kartu-bayar">
    <h2 class="judul-kartu"><x-ikon nama="uang" class="text-navy-400" /> Ringkasan total</h2>

    <dl class="mt-4 space-y-2.5 text-sm">
        <div class="flex justify-between gap-3">
            <dt>Subtotal pesanan</dt>
            <dd class="tabular font-medium">{{ rupiah($subtotal) }}</dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt>Penyesuaian biaya</dt>
            <dd class="tabular font-medium {{ $penyesuaian !== 0 ? 'text-ok' : '' }}">
                {{ $penyesuaian < 0 ? '−' : ($penyesuaian > 0 ? '+' : '') }}{{ rupiah(abs($penyesuaian)) }}
            </dd>
        </div>
        @if ($pesanan->diskon > 0)
            <div class="flex justify-between gap-3">
                <dt>Diskon</dt>
                <dd class="tabular font-medium text-ok">−{{ rupiah($pesanan->diskon) }}</dd>
            </div>
        @endif
        @if ($pesanan->biaya_layanan > 0)
            <div class="flex justify-between gap-3">
                <dt>Biaya layanan</dt>
                <dd class="tabular font-medium">{{ rupiah($pesanan->biaya_layanan) }}</dd>
            </div>
        @endif
        @if ($pesanan->dikirim())
            <div class="flex justify-between gap-3">
                <dt>Ongkos kirim</dt>
                <dd class="tabular font-medium {{ $pesanan->ongkir > 0 ? '' : 'text-merah' }}">
                    {{ $pesanan->ongkir > 0 ? rupiah($pesanan->ongkir) : 'belum diisi' }}
                </dd>
            </div>
        @endif

        <div class="flex items-center justify-between gap-3 rounded-[10px] bg-merah-50 px-3 py-3">
            <dt class="text-base font-bold text-merah">Total tagihan</dt>
            <dd class="tabular text-xl font-extrabold text-merah">{{ rupiah($pesanan->total) }}</dd>
        </div>

        @if ($pesanan->dikembalikan > 0)
            <div class="flex justify-between gap-3 pt-1">
                <dt class="text-muted">Dibayar</dt>
                <dd class="tabular font-medium text-ok">{{ rupiah($pesanan->sudah_dibayar + $pesanan->dikembalikan) }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-muted">Dikembalikan</dt>
                <dd class="tabular font-medium">−{{ rupiah($pesanan->dikembalikan) }}</dd>
            </div>
        @else
            <div class="flex justify-between gap-3 pt-1">
                <dt class="text-muted">Sudah dibayar</dt>
                <dd class="tabular font-medium text-ok">{{ rupiah($pesanan->sudah_dibayar) }}</dd>
            </div>
        @endif
        <div class="flex justify-between gap-3">
            <dt class="font-semibold">Sisa</dt>
            <dd class="tabular font-semibold {{ $pesanan->sisa_tagihan > 0 ? 'text-merah' : 'text-ok' }}">
                {{ $pesanan->sisa_tagihan > 0 ? rupiah($pesanan->sisa_tagihan) : 'Lunas' }}
            </dd>
        </div>

        {{-- Sisa berhenti di nol, jadi kelebihan bayar setelah pesanan
             dikurangi (KEP-044) tidak pernah muncul sendiri. --}}
        @if ($pesanan->kelebihan_bayar > 0)
            <div class="flex justify-between gap-3 border-t border-line pt-2">
                <dt class="font-semibold">Kelebihan bayar</dt>
                <dd class="tabular font-semibold text-merah">{{ rupiah($pesanan->kelebihan_bayar) }}</dd>
            </div>
        @endif
    </dl>

    {{-- Uang kelebihan dikembalikan di luar sistem; yang dicatat di sini adalah
         bahwa ia sudah keluar, oleh siapa, dan lewat apa (KEP-158). --}}
    @if ($pesanan->kelebihan_bayar > 0 && $izin->punyaIzin('pembayaran.catat'))
        <form method="POST" action="{{ route('panel.pesanan.pengembalian.simpan', $pesanan) }}"
              class="mt-4 space-y-3 rounded-[10px] border border-line p-3"
              data-konfirmasi="Catat pengembalian ke pelanggan?"
              data-konfirmasi-pesan="Pastikan uangnya sudah benar-benar diserahkan atau ditransfer ke pelanggan."
              data-konfirmasi-tombol="Catat">
            @csrf
            <p class="text-sm font-semibold">Catat pengembalian</p>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label for="kembali-jumlah" class="label-form">Nominal</label>
                    <input type="number" id="kembali-jumlah" name="jumlah" min="1" max="{{ $pesanan->kelebihan_bayar }}"
                           value="{{ old('jumlah', $pesanan->kelebihan_bayar) }}" required class="kotak-input tabular">
                </div>
                <div>
                    <label for="kembali-metode" class="label-form">Lewat</label>
                    <select id="kembali-metode" name="metode" class="kotak-input">
                        <option value="TUNAI" @selected(old('metode') === 'TUNAI')>Tunai</option>
                        <option value="TRANSFER" @selected(old('metode') === 'TRANSFER')>Transfer</option>
                    </select>
                </div>
            </div>
            <div>
                <label for="kembali-catatan" class="label-form">Catatan (opsional)</label>
                <input type="text" id="kembali-catatan" name="catatan" maxlength="200" value="{{ old('catatan') }}"
                       class="kotak-input" placeholder="Contoh: ditransfer ke BCA a.n. pelanggan">
            </div>
            @error('jumlah')
                <p class="text-sm text-merah">{{ $message }}</p>
            @enderror
            <button type="submit" class="tombol tombol-garis w-full">Catat sudah dikembalikan</button>
        </form>
    @endif

    @if ($pesanan->kode_ambil)
        <div class="mt-4 border-t border-line pt-3">
            <p class="text-sm text-muted">Kode ambil</p>
            <p class="numeral mt-1 text-2xl">{{ $pesanan->kode_ambil }}</p>
        </div>
    @endif
</section>
