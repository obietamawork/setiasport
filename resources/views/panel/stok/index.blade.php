@extends('layouts.panel')
@section('judul', 'Stok')

@section('isi')
{{--
    Layar opname. Dibuka sambil berdiri di depan rak, dari HP, sambil menghitung
    barang dengan tangan satu lagi. Karena itu semuanya bertombol besar, dan
    angka yang diketik adalah angka yang BENAR-BENAR ADA DI RAK, bukan angka
    yang sudah dikurangi pesanan.
--}}
<x-kop judul="Stok"
       catatan="Isi dengan jumlah yang benar-benar ada di rak. Yang sudah dipesan pelanggan dikurangi otomatis oleh sistem.">
    <form method="GET" class="relative">
        <input type="hidden" name="saring" value="{{ request('saring') }}">
        <label for="cari-stok" class="sr-only">Cari produk</label>
        <x-ikon nama="cari" ukuran="size-4"
                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-navy-300" />
        <input type="search" id="cari-stok" name="q" value="{{ request('q') }}"
               placeholder="Produk atau SKU&hellip;" class="kotak-input w-44 pl-9 sm:w-56">
    </form>
</x-kop>

<div class="segmen mt-1">
    @foreach (['' => 'Semua varian', 'habis' => 'Hanya yang habis'] as $nilai => $label)
        <a href="{{ route('panel.stok.index', array_filter(['saring' => $nilai, 'q' => request('q')])) }}"
           class="segmen-item {{ request('saring') === $nilai ? 'segmen-item-aktif' : '' }}">
            @if ($nilai === 'habis')
                <x-ikon nama="peringatan" ukuran="size-4" />
            @endif
            {{ $label }}
        </a>
    @endforeach
</div>

@if ($varian->isEmpty())
    {{-- "Habis" yang kosong berarti kabar BAIK (tidak ada yang kehabisan),
         bukan "belum ada varian apa pun" -- dua keadaan yang beda jauh, jadi
         pesannya tidak boleh sama. --}}
    <div class="kartu mt-6 p-8 text-center">
        <x-ikon nama="{{ request('saring') === 'habis' ? 'centang' : 'stok' }}" ukuran="size-9"
                class="mx-auto {{ request('saring') === 'habis' ? 'text-ok' : 'text-navy-300' }}" />
        <p class="mt-3 text-lg font-bold">
            @if (request('saring') === 'habis')
                Tidak ada yang kehabisan stok
            @elseif (request('q'))
                Tidak ada produk atau SKU yang cocok
            @else
                Tidak ada varian yang cocok
            @endif
        </p>
        <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted">
            @if (request('saring') === 'habis')
                Semua varian masih punya stok yang bisa dijual.
            @elseif (request('q'))
                Coba kata lain, atau hapus pencarian untuk melihat semua varian.
            @else
                Varian ditambahkan dari halaman ubah produk, di bagian Varian &amp; stok.
            @endif
        </p>
    </div>
@else
    {{-- Seluruh daftar satu formulir: opname dikerjakan sekali jalan dari rak
         pertama sampai rak terakhir, bukan disimpan per baris. --}}
    {{-- Tiap baris menjaga angkanya sendiri dan mengabarkan perubahannya ke
         formulir. Menghitung ulang dengan membaca .value milik input tidak bisa
         dipakai: nilai mentah input bukan sesuatu yang bisa diamati Alpine,
         sehingga penandanya tidak pernah menyala. --}}
    <form method="POST" action="{{ route('panel.stok.update') }}" class="mt-5"
          x-data="{
              diubah: {},
              get berubah() { return Object.values(this.diubah).filter(Boolean).length },
          }"
          @stok-berubah="diubah[$event.detail.id] = $event.detail.berubah">
        @csrf @method('PUT')

        <div class="space-y-3">
            @foreach ($varian as $grup)
                @php $produk = $grup->first()->product; @endphp

                <section class="kartu overflow-hidden">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 border-b border-line px-4 py-2.5">
                        <a href="{{ route('panel.produk.edit', $produk) }}" class="font-semibold hover:text-navy">
                            {{ $produk->nama }}
                        </a>
                        <span class="text-xs text-muted tabular">{{ $produk->sku }}</span>
                        <span class="ml-auto text-xs text-muted tabular">{{ $grup->count() }} varian</span>
                    </div>

                    <div class="divide-y divide-line">
                        @foreach ($grup as $v)
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
                                <div class="min-w-36 flex-1">
                                    <p class="font-medium">{{ $v->label ?: 'Varian tunggal' }}</p>
                                    <p class="tabular text-xs text-muted">{{ $v->sku }}</p>
                                </div>

                                {{-- Dua angka yang beda arti dipisah terang-terangan:
                                     yang dihitung tangan, dan yang boleh dijual. Dulu
                                     keduanya berdempetan dan tertukar terus. --}}
                                <p class="tabular min-w-32 text-sm">
                                    @if ($v->stok_dipesan > 0)
                                        <span class="text-muted">{{ $v->stok_dipesan }} sudah dipesan,</span>
                                        <span class="block {{ $v->stok_tersedia < 1 ? 'font-semibold text-merah' : 'text-muted' }}">
                                            sisa {{ $v->stok_tersedia }} bisa dijual
                                        </span>
                                    @elseif ($v->stok_tersedia < 1)
                                        <span class="chip chip-merah">
                                            <x-ikon nama="peringatan" ukuran="size-3.5" />
                                            Habis
                                        </span>
                                    @else
                                        <span class="text-muted">{{ $v->stok_tersedia }} bisa dijual</span>
                                    @endif
                                </p>

                                {{-- Tombol kurang dan tambah: menghitung sambil memegang
                                     barang jauh lebih mudah dengan menekan daripada
                                     mengetik ulang angkanya di papan ketik HP. Ukurannya
                                     dipatok size-9 (36x36) di semua perangkat. aspect-square
                                     saja tidak cukup: di layar tanpa sentuh tingginya tidak
                                     pasti, sehingga lebarnya menyusut ke lebar huruf (14px). --}}
                                @php $nAwal = (int) old('stok.'.$v->id, $v->stok); @endphp
                                <div class="flex items-center gap-1"
                                     x-data="{ n: {{ $nAwal }}, awal: {{ (int) $v->stok }} }"
                                     x-init="$watch('n', () => $dispatch('stok-berubah', {
                                         id: {{ $v->id }}, berubah: n !== awal,
                                     })); if (n !== awal) $dispatch('stok-berubah', { id: {{ $v->id }}, berubah: true })">
                                    <button type="button" class="tombol tombol-garis tombol-sm size-9 px-0"
                                            @click="n = Math.max(0, n - 1)">
                                        <span class="sr-only">Kurangi stok {{ $v->label ?: $produk->nama }}</span>
                                        <span aria-hidden="true">&minus;</span>
                                    </button>

                                    <label class="sr-only" for="stok-{{ $v->id }}">
                                        Jumlah di rak, {{ $produk->nama }} {{ $v->label }}
                                    </label>
                                    {{-- value tetap ditulis walau ada x-model: kalau
                                         JavaScript gagal dimuat, kotaknya harus tetap
                                         berisi angka stok yang sekarang, bukan kosong.
                                         Dibaca dari old() dulu: tanpa ini, angka yang
                                         sudah diketik hilang diam-diam begitu "alasan"
                                         ditolak validasi karena sebab LAIN selain kosong
                                         (misalnya kepanjangan), dan opname harus diulang
                                         dari rak pertama. --}}
                                    <input type="number" id="stok-{{ $v->id }}" name="stok[{{ $v->id }}]"
                                           value="{{ $nAwal }}" x-model.number="n"
                                           :class="n !== awal ? 'border-navy bg-navy-50 font-semibold' : ''"
                                           class="kotak-input tabular w-20 text-center" min="0" inputmode="numeric">

                                    <button type="button" class="tombol tombol-garis tombol-sm size-9 px-0"
                                            @click="n = n + 1">
                                        <span class="sr-only">Tambah stok {{ $v->label ?: $produk->nama }}</span>
                                        <span aria-hidden="true">+</span>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        {{-- Stok yang berubah tanpa jejak adalah sumber perselisihan internal,
             jadi alasan wajib diisi sebelum penyimpanan diterima. --}}
        <div class="bilah-aksi mt-6 items-end">
            <div class="min-w-56 flex-1">
                <label for="alasan" class="label-form">
                    Alasan penyesuaian
                    <span class="font-normal text-muted" x-show="berubah > 0" x-cloak>
                        &middot; <span x-text="berubah"></span> varian diubah
                    </span>
                </label>
                <input type="text" id="alasan" name="alasan" class="kotak-input" required
                       value="{{ old('alasan') }}"
                       placeholder="Opname 7 September / barang rusak / retur supplier">
            </div>
            <button type="submit" class="tombol tombol-navy">
                <x-ikon nama="centang" ukuran="size-4" />
                Simpan stok
            </button>
        </div>

        <p class="kolom-baca mt-3 text-sm leading-relaxed text-muted">
            Setiap perubahan dicatat beserta alasan dan nama Anda. Itu disengaja: stok yang
            berubah tanpa jejak adalah awal dari saling menyalahkan.
        </p>
    </form>
@endif
@endsection
