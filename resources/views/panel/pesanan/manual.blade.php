@extends('layouts.panel')
@section('judul', 'Pesanan baru')

@section('isi')
@php
    // Data produk dikirim utuh supaya isian menyesuaikan sendiri saat produk
    // diganti: yang kustom memunculkan daftar ukuran, yang stok memunculkan
    // pilihan varian.
    $dataProduk = $produk->map(fn ($p) => [
        'id' => $p->id,
        'nama' => $p->nama,
        'moq' => $p->moq,
        'perUkuran' => $p->prices->isNotEmpty(),
        'pakaiKategori' => $p->pakaiKategoriUkuran(),
        'stok' => $p->punyaJalurReadyStock(),
        'kategori' => $p->pakaiKategoriUkuran()
            ? collect($kategori)->map(fn ($k) => [
                'value' => $k->value,
                'label' => $k->label(),
                'ukuran' => $p->ukuranTersedia($k),
            ])->values()
            : [['value' => '', 'label' => 'Semua ukuran', 'ukuran' => $p->ukuranTersedia(null)]],
        'opsi' => $p->options->map(fn ($o) => [
            'id' => $o->id,
            'nama' => $o->nama,
            'values' => $o->values->map(fn ($v) => [
                'id' => $v->id,
                'label' => $v->label,
                'harga' => $v->tambahan_harga,
            ])->values(),
        ])->values(),
        'varian' => $p->variants->map(fn ($v) => [
            'id' => $v->id,
            'label' => $v->label,
            'stok' => $v->stok_tersedia,
        ])->values(),
    ])->values();
@endphp

<x-kop judul="Pesanan baru" :kembali="route('panel.pesanan.index')" kembali-label="Daftar pesanan"
       catatan="Untuk pesanan yang datang langsung ke toko atau lewat WhatsApp. Pesanannya mendapat nomor, halaman lacak, lembar kerja, dan QR produksi seperti pesanan dari web, jadi pelanggan bisa memantau sendiri." />

<form method="POST" action="{{ route('panel.pesanan.baru.simpan') }}" class="mt-6 max-w-4xl space-y-6"
      x-data="pesananManual(@js($dataProduk), {{ $persenDp }})">
    @csrf

    <section class="kartu p-5">
        <h2 class="font-semibold">Pelanggan</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="nama" class="label-form">Nama pemesan</label>
                <input type="text" id="nama" name="nama" value="{{ old('nama') }}"
                       class="kotak-input" maxlength="150" required>
            </div>
            <div>
                <label for="no_hp" class="label-form">Nomor WhatsApp</label>
                <input type="text" id="no_hp" name="no_hp" value="{{ old('no_hp') }}"
                       class="kotak-input tabular" maxlength="20" required>
            </div>
        </div>

        <div class="mt-4">
            <label for="email" class="label-form">Email (opsional)</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   class="kotak-input" maxlength="150">
            <p class="mt-1 text-xs leading-relaxed text-muted">
                Kosongkan kalau pelanggan tidak punya email. Tanpa email, pemberitahuan
                otomatis tidak dikirim dan kabar disampaikan lewat WhatsApp.
            </p>
        </div>
    </section>

    <section class="kartu p-5">
        <h2 class="font-semibold">Barang</h2>

        <div class="mt-4">
            <label for="product_id" class="label-form">Produk</label>
            <select id="product_id" name="product_id" x-model.number="produkId" class="kotak-input">
                <option value="">Pilih produk</option>
                <template x-for="p in produk" :key="p.id">
                    <option :value="p.id" x-text="p.nama"></option>
                </template>
            </select>
        </div>

        {{-- Barang stok: cukup varian dan jumlah. --}}
        <template x-if="pilih && pilih.stok && pilih.varian.length > 0">
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="variant_id" class="label-form">Varian</label>
                    <select id="variant_id" name="variant_id" class="kotak-input">
                        <template x-for="v in pilih.varian" :key="v.id">
                            <option :value="v.id" x-text="v.label + ' (' + v.stok + ' tersedia)'"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label for="qty" class="label-form">Jumlah</label>
                    <input type="number" id="qty" name="qty" x-model.number="qty"
                           class="kotak-input tabular" min="1" max="5000">
                </div>
            </div>
        </template>

        {{-- Barang kustom: opsi dan daftar ukuran. --}}
        <template x-if="pilih && !pilih.stok">
            <div class="mt-4 space-y-4">
                <template x-for="o in pilih.opsi" :key="o.id">
                    <div>
                        <label class="label-form" x-text="o.nama"></label>
                        <select :name="`opsi[${o.id}]`" class="kotak-input">
                            <template x-for="v in o.values" :key="v.id">
                                <option :value="v.id"
                                        x-text="v.label + (v.harga ? ' (+' + rp(v.harga) + ')' : '')"></option>
                            </template>
                        </select>
                    </div>
                </template>

                <template x-if="pilih.perUkuran">
                    <div>
                        <p class="label-form">Daftar ukuran</p>

                        <template x-for="(b, i) in baris" :key="i">
                            <div class="mb-2 grid gap-2 sm:grid-cols-[minmax(0,1fr)_5rem_9rem_6rem_5rem_auto] sm:items-end">
                                <label class="block">
                                    <span class="label-form">Nama</span>
                                    <input type="text" x-model="b.nama_punggung" :name="`baris[${i}][nama_punggung]`"
                                           class="kotak-input" maxlength="60" placeholder="Tanpa nama">
                                </label>
                                <label class="block">
                                    <span class="label-form">No</span>
                                    <input type="text" x-model="b.nomor" :name="`baris[${i}][nomor]`"
                                           class="kotak-input tabular" maxlength="5">
                                </label>
                                <label class="block" x-show="pilih.pakaiKategori">
                                    <span class="label-form">Kategori</span>
                                    <select x-model="b.kategori" :name="`baris[${i}][kategori]`"
                                            @change="betulkanUkuran(b)" class="kotak-input">
                                        <template x-for="k in pilih.kategori" :key="k.value">
                                            <option :value="k.value" x-text="k.label"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="label-form">Ukuran</span>
                                    <select x-model="b.ukuran" :name="`baris[${i}][ukuran]`" class="kotak-input">
                                        <template x-for="u in ukuranUntuk(b.kategori)" :key="u">
                                            <option :value="u" x-text="u"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="block">
                                    <span class="label-form">Jumlah</span>
                                    <input type="number" x-model.number="b.jumlah" :name="`baris[${i}][jumlah]`"
                                           class="kotak-input tabular" min="1" max="5000">
                                </label>
                                <button type="button" @click="baris.splice(i, 1)"
                                        class="tautan pb-2 text-xs text-merah">Hapus</button>
                            </div>
                        </template>

                        <div class="mt-2 flex flex-wrap items-center gap-3">
                            <button type="button" @click="tambahBaris()" class="tombol tombol-garis tombol-sm">
                                + Tambah baris
                            </button>
                            <p class="text-sm" :class="qtyUkuran < pilih.moq ? 'text-merah' : 'text-muted'">
                                <span x-text="qtyUkuran"></span> pcs &middot; minimal <span x-text="pilih.moq"></span>
                            </p>
                        </div>
                    </div>
                </template>

                <template x-if="!pilih.perUkuran">
                    <div>
                        <label for="qty-kustom" class="label-form">Jumlah (pcs)</label>
                        <input type="number" id="qty-kustom" name="qty" x-model.number="qty"
                               class="kotak-input tabular w-32" :min="pilih.moq" max="5000">
                    </div>
                </template>
            </div>
        </template>
    </section>

    <section class="kartu p-5">
        <h2 class="font-semibold">Pembayaran &amp; penyerahan</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="tipe_pembayaran" class="label-form">Cara bayar</label>
                <select id="tipe_pembayaran" name="tipe_pembayaran" class="kotak-input">
                    <option value="DP">Uang muka {{ $persenDp }}%</option>
                    <option value="LUNAS">Lunas di muka</option>
                </select>
            </div>
            <div>
                <label for="metode_ambil" class="label-form">Penyerahan</label>
                <select id="metode_ambil" name="metode_ambil" x-model="metodeAmbil" class="kotak-input">
                    <option value="AMBIL">Diambil di toko</option>
                    <option value="KIRIM">Dikirim</option>
                </select>
            </div>
        </div>

        <div class="mt-4" x-show="metodeAmbil === 'KIRIM'" x-cloak>
            <label for="alamat_kirim" class="label-form">Alamat kirim</label>
            <textarea id="alamat_kirim" name="alamat_kirim" rows="3" class="kotak-input">{{ old('alamat_kirim') }}</textarea>
        </div>

        {{-- Uang yang sudah diterima di kasir dicatat sekalian, supaya pesanan
             langsung masuk antrian tanpa perlu dibuka lagi. --}}
        <div class="mt-4 grid gap-4 border-t border-line pt-4 sm:grid-cols-2">
            <div>
                <label for="dibayar" class="label-form">Sudah dibayar sekarang (Rp)</label>
                <input type="number" id="dibayar" name="dibayar" value="{{ old('dibayar') }}"
                       class="kotak-input tabular" min="0" max="999999999">
                <p class="mt-1 text-xs text-muted">Kosongkan kalau pelanggan belum membayar.</p>
            </div>
            <div>
                <label for="metode_bayar" class="label-form">Diterima lewat</label>
                <select id="metode_bayar" name="metode_bayar" class="kotak-input">
                    <option value="TUNAI">Tunai</option>
                    <option value="TRANSFER">Transfer</option>
                </select>
            </div>
        </div>

        <div class="mt-4">
            <label for="catatan" class="label-form">Catatan pesanan (opsional)</label>
            <textarea id="catatan" name="catatan" rows="2" class="kotak-input"
                      placeholder="Misalnya: pesanan reseller Alim Sport, desain menyusul lewat WhatsApp">{{ old('catatan') }}</textarea>
        </div>
    </section>

    <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="tombol tombol-navy" data-teks-proses="Menyimpan...">
            Buat pesanan
        </button>
        <a href="{{ route('panel.pesanan.index') }}" class="tautan text-sm text-muted">Batal</a>
    </div>
</form>

@push('skrip')
<script>
    function pesananManual(produk, persenDp) {
        return {
            produk, persenDp,
            produkId: '',
            qty: 1,
            baris: [],
            metodeAmbil: 'AMBIL',

            rp(n) {
                return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n || 0));
            },

            get pilih() {
                return this.produk.find(p => p.id === this.produkId) ?? null;
            },

            ukuranUntuk(nilai) {
                return this.pilih?.kategori.find(k => k.value === nilai)?.ukuran ?? [];
            },

            betulkanUkuran(b) {
                const tersedia = this.ukuranUntuk(b.kategori);
                if (!tersedia.includes(b.ukuran)) b.ukuran = tersedia[0] ?? '';
            },

            tambahBaris() {
                const k = this.pilih?.kategori.at(-1);
                if (!k) return;

                this.baris.push({
                    nama_punggung: '', nomor: '',
                    kategori: k.value, ukuran: k.ukuran[1] ?? k.ukuran[0], jumlah: 1,
                });
            },

            get qtyUkuran() {
                return this.baris.reduce((s, b) => s + (parseInt(b.jumlah, 10) || 0), 0);
            },

            init() {
                // Produk diganti berarti daftar ukuran lama tidak berlaku lagi.
                this.$watch('produkId', () => {
                    this.baris = [];
                    this.qty = this.pilih?.moq ?? 1;
                    if (this.pilih?.perUkuran) this.tambahBaris();
                });
            },
        };
    }
</script>
@endpush
@endsection
