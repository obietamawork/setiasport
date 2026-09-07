@php
    /** @var \App\Models\Product $produk */
    $dataOpsi = $produk->options->map(fn ($o) => [
        'id' => $o->id,
        'nama' => $o->nama,
        'wajib' => $o->wajib,
        'values' => $o->values->map(fn ($v) => [
            'id' => $v->id,
            'label' => $v->label,
            'harga' => $v->tambahan_harga,
            'basis' => $v->basis->value,
        ])->values(),
    ])->values();

    $dataTier = $produk->priceTiers->map(fn ($t) => [
        'min' => $t->min_qty,
        'max' => $t->max_qty,
        'harga' => $t->harga_satuan,
    ])->values();
@endphp

<form method="POST" action="{{ route('keranjang.kustom', $produk) }}"
      x-data="konfigurator(@js($dataTier), @js($dataOpsi), {{ $produk->moq }})"
      class="space-y-5">
    @csrf

    <div>
        <label for="qty" class="label-form">Jumlah pesanan</label>
        <div class="flex items-center gap-3">
            <input type="number" id="qty" name="qty" x-model.number="qty" min="{{ $produk->moq }}"
                   class="kotak-input w-28 tabular text-right" required>
            <span class="text-sm text-muted">pcs &middot; minimum {{ $produk->moq }}</span>
        </div>
        <p x-show="qty < moq" x-cloak class="mt-1 text-sm text-signal">
            Minimum pemesanan <span x-text="moq"></span> pcs.
        </p>
    </div>

    @foreach ($produk->options as $opsi)
        <div>
            <label for="opsi-{{ $opsi->id }}" class="label-form">
                {{ $opsi->nama }}
                @unless ($opsi->wajib)
                    <span class="font-normal text-muted">(opsional)</span>
                @endunless
            </label>
            <select id="opsi-{{ $opsi->id }}" name="opsi[{{ $opsi->id }}]"
                    x-model.number="pilihan[{{ $opsi->id }}]" class="kotak-input">
                @unless ($opsi->wajib)
                    <option value="">Tidak dipakai</option>
                @endunless
                @foreach ($opsi->values as $v)
                    <option value="{{ $v->id }}">
                        {{ $v->label }}@if ($v->tambahan_harga !== 0) — {{ $v->tambahan_harga > 0 ? '+' : '−' }}{{ rupiah(abs($v->tambahan_harga)) }}{{ $v->basis === \App\Enums\PriceBasis::PER_ITEM ? ' sekali' : ' / pcs' }}@endif
                    </option>
                @endforeach
            </select>
        </div>
    @endforeach

    {{-- Rincian ditampilkan terbuka, bukan hanya total: pelanggan yang paham
         dari mana angkanya tidak perlu bertanya ke admin (KEP-010). --}}
    <div class="border border-line bg-paper p-4">
        <dl class="space-y-1.5 text-sm">
            <div class="flex justify-between gap-3">
                <dt class="text-muted">Harga dasar (<span x-text="qty"></span> pcs)</dt>
                <dd class="tabular" x-text="rp(tier)"></dd>
            </div>

            <template x-for="k in komponenPerPcs" :key="k.id">
                <div class="flex justify-between gap-3">
                    <dt class="text-muted" x-text="k.label"></dt>
                    <dd class="tabular" x-text="(k.harga < 0 ? '−' : '+') + rp(Math.abs(k.harga))"></dd>
                </div>
            </template>

            <div class="flex justify-between gap-3 border-t border-line pt-1.5">
                <dt class="font-semibold">Harga per pcs</dt>
                <dd class="tabular font-semibold" x-text="rp(hargaSatuan)"></dd>
            </div>

            <div class="flex justify-between gap-3">
                <dt class="text-muted">&times; <span x-text="qty"></span> pcs</dt>
                <dd class="tabular" x-text="rp(hargaSatuan * qty)"></dd>
            </div>

            <template x-for="k in komponenPerItem" :key="k.id">
                <div class="flex justify-between gap-3">
                    <dt class="text-muted">
                        <span x-text="k.label"></span>
                        <span class="text-signal">sekali</span>
                    </dt>
                    <dd class="tabular" x-text="(k.harga < 0 ? '−' : '+') + rp(Math.abs(k.harga))"></dd>
                </div>
            </template>
        </dl>

        <div class="mt-3 flex items-baseline justify-between gap-3 border-t border-line pt-3">
            <span class="font-semibold">Total</span>
            <span class="numeral text-3xl text-signal" x-text="rp(total)"></span>
        </div>

        <p class="mt-2 text-sm text-muted">
            Uang muka minimum {{ setting('dp_minimum_persen', 50) }}%:
            <span class="tabular" x-text="rp(dp)"></span>
        </p>
    </div>

    <button type="submit" class="tombol tombol-utama w-full" :disabled="qty < moq">
        Masukkan ke keranjang
    </button>

    <p class="text-xs leading-relaxed text-muted">
        Nama dan nomor punggung diisi setelah pesanan dibuat — Anda punya waktu
        mengumpulkannya dari tim sampai desain disetujui.
    </p>
</form>

@once
    @push('skrip')
    <script>
        function konfigurator(tiers, opsi, moq) {
            return {
                tiers, opsi, moq,
                qty: moq,
                pilihan: Object.fromEntries(
                    opsi.map(o => [o.id, o.wajib ? (o.values[0]?.id ?? '') : ''])
                ),

                rp(n) {
                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n || 0));
                },

                get terpilih() {
                    return this.opsi.flatMap(o => {
                        const id = this.pilihan[o.id];
                        const v = o.values.find(v => v.id === Number(id));
                        return v ? [{ ...v, opsiNama: o.nama }] : [];
                    });
                },

                get komponenPerPcs() {
                    return this.terpilih.filter(v => v.basis === 'PER_PCS' && v.harga !== 0)
                        .map(v => ({ id: v.id, label: v.opsiNama + ': ' + v.label, harga: v.harga }));
                },

                get komponenPerItem() {
                    return this.terpilih.filter(v => v.basis === 'PER_ITEM' && v.harga !== 0)
                        .map(v => ({ id: v.id, label: v.label, harga: v.harga }));
                },

                get tier() {
                    const t = this.tiers.find(t => this.qty >= t.min && (t.max === null || this.qty <= t.max));
                    return t ? t.harga : (this.tiers[0]?.harga ?? 0);
                },

                get hargaSatuan() {
                    return this.tier + this.komponenPerPcs.reduce((s, k) => s + k.harga, 0);
                },

                // Rumus yang sama persis dengan PriceCalculator di server: biaya
                // PER_ITEM ditambahkan SETELAH perkalian, bukan sebelum (KEP-009).
                get total() {
                    return this.hargaSatuan * this.qty
                        + this.komponenPerItem.reduce((s, k) => s + k.harga, 0);
                },

                get dp() {
                    return Math.ceil(this.total * {{ (int) setting('dp_minimum_persen', 50) }} / 100);
                },
            };
        }
    </script>
    @endpush
@endonce
