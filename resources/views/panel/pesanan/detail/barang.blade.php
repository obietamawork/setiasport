{{--
    Detail satu barang (KEP-124): gambar, spesifikasi, dan data pemain.
    Data pemain dibaca dalam dua kolom seperti gambar karyawan; tombol Ubah
    data pemain membuka tabel isian yang menyimpan lewat penyunting isi
    pesanan (KEP-044), termasuk model kerah atau oblong per pemain (KEP-123).

    @var \App\Models\OrderItem $item
--}}
@php
    use App\Enums\KategoriUkuran;
    use App\Enums\OrderItemStatus;

    $batal = $item->status_item === OrderItemStatus::DIBATALKAN;
    $gambar = $item->approvedDesign?->path
        ? asset('storage/'.$item->approvedDesign->path)
        : $item->product?->images->first()?->thumb_url;
    $bolehUbahIsi = ! $batal && $izin->punyaIzin('order.kelola') && ! $pesanan->status->final();
    $adaPemain = $item->rosters->isNotEmpty();
    $tampilPemain = $item->kustom() && ($adaPemain || $item->product?->roster_wajib);
    $kolomPemain = $item->rosters->values()->chunk((int) max(1, ceil($item->rosters->count() / 2)));
    $galatPemain = collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'baris'));
@endphp

<section id="barang-{{ $item->id }}" class="kartu-bayar scroll-mt-32" x-data="{ ubah: @js($galatPemain) }">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h2 class="judul-kartu">
            <x-ikon :nama="$item->kustom() ? 'kaos' : 'produk'" class="text-navy-400" />
            {{ $item->kustom() ? 'Detail jersey' : 'Detail barang' }}
        </h2>
        <span class="chip {{ $batal ? 'chip-netral' : 'chip-biru' }}">{{ $item->status_item->labelStaf() }}</span>
    </div>

    <div class="mt-4 grid gap-5 md:grid-cols-2">
        <div class="flex items-start gap-4">
            @if ($gambar)
                <img src="{{ $gambar }}" alt="" width="96" height="96" loading="lazy"
                     class="size-24 shrink-0 rounded-[12px] border border-line object-cover">
            @else
                <span class="flex size-24 shrink-0 items-center justify-center rounded-[12px] border border-dashed border-line-strong text-navy-300">
                    <x-ikon :nama="$item->kustom() ? 'kaos' : 'produk'" ukuran="size-8" />
                </span>
            @endif
            <div class="min-w-0 text-sm">
                <p class="text-base font-bold">{{ $item->nama_produk }}</p>
                <p class="mt-1 text-muted">Jumlah: <span class="tabular font-medium text-ink">{{ $item->qty }} pcs</span></p>
                <p class="text-muted">Harga: <span class="tabular font-medium text-ink">{{ rupiah($item->harga_satuan) }} / pcs</span></p>
                <p class="text-muted">Subtotal: <span class="tabular font-semibold text-ink">{{ rupiah($item->subtotal) }}</span></p>
            </div>
        </div>

        @if ($item->options->isNotEmpty())
            <div>
                <p class="text-sm font-bold">Spesifikasi</p>
                <dl class="mt-2 space-y-1 text-sm">
                    @foreach ($item->options as $opsi)
                        <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-2">
                            <dt class="text-muted">{{ $opsi->option_nama }}</dt>
                            <dd class="font-medium">
                                {{ $opsi->option_value }}
                                @if ($opsi->tambahan_harga != 0)
                                    <span class="tabular text-xs font-normal text-muted">
                                        ({{ $opsi->tambahan_harga < 0 ? '−' : '+' }}{{ rupiah_ringkas(abs($opsi->tambahan_harga)) }}{{ $opsi->basis === \App\Enums\PriceBasis::PER_ITEM ? ' sekali' : '' }})
                                    </span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif
    </div>

    {{-- File desain pelanggan hanya lewat WhatsApp (KEP-095), jadi tidak ada
         yang masuk ke sistem sampai mockup diunggah. Admin yang memintanya. --}}
    @if ($item->desain_sumber === 'sendiri' && in_array($item->status_item,
        [OrderItemStatus::MENUNGGU_PEMBAYARAN, OrderItemStatus::ANTRIAN_DESAIN, OrderItemStatus::PROSES_DESAIN], true))
        @php
            $waPelanggan = nomor_wa($pesanan->customer?->no_hp);
            $mintaDesain = 'Halo '.$pesanan->customer?->nama.', kami dari '.setting('toko_nama', 'Setia Sport')
                .'. Untuk pesanan '.$pesanan->kode.' ('.$item->nama_produk.'), silakan kirim file desainnya '
                .'di sini. Semua format kami terima: CDR, AI, PSD, PDF, PNG, JPG, atau tautan Google Drive.';
        @endphp
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-[10px] bg-kuning-50 px-3 py-2 text-sm">
            <span>
                <strong>Desain dikirim pelanggan sendiri, lewat WhatsApp.</strong>
                Pastikan filenya sudah diterima sebelum mulai mendesain.
            </span>
            @if ($waPelanggan)
                <a href="https://wa.me/{{ $waPelanggan }}?text={{ rawurlencode($mintaDesain) }}"
                   target="_blank" rel="noopener" class="tombol tombol-garis tombol-sm">
                    <x-ikon nama="whatsapp" ukuran="size-4" />
                    Minta file desain
                </a>
            @endif
        </div>
    @endif

    {{-- Tautan kerja (KEP-047), sama persis dengan QR di lembar kerja. --}}
    @if (! $batal && $item->productionJobs->isNotEmpty())
        <div class="mt-3 flex flex-wrap items-center gap-3 text-sm" x-data="{ tersalin: false }">
            <button type="button" class="tautan"
                    @click="navigator.clipboard.writeText(@js(route('kerja', $item->tokenKerja())))
                            .then(() => { tersalin = true; setTimeout(() => tersalin = false, 2500) })">
                <span x-show="!tersalin">Salin tautan kerja</span>
                <span x-show="tersalin" x-cloak>Tautan tersalin</span>
            </button>
            <span class="text-xs text-muted">Pekerja butuh PIN untuk menandai selesai.</span>
        </div>
    @endif

    @if ($batal)
        <p class="mt-4 text-sm text-muted">Item ini dibatalkan.</p>
    @elseif ($tampilPemain)
        <div class="mt-5 border-t border-line pt-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="flex items-center gap-2 font-bold">
                    <x-ikon nama="pekerja" class="text-navy-400" />
                    Data pemain ({{ $item->rosters->sum('jumlah') }})
                </h3>
                @if ($bolehUbahIsi && $item->hargaPerUkuran())
                    <button type="button" class="tombol tombol-garis tombol-sm" x-show="! ubah" @click="ubah = true">
                        <x-ikon nama="pensil" ukuran="size-4" /> Ubah data pemain
                    </button>
                @endif
            </div>

            @if ($adaPemain)
                <p class="tabular mt-3 rounded-[8px] bg-navy-50 px-3 py-2 text-sm font-semibold" x-show="! ubah">
                    {{ $item->ringkasanUkuran() }}
                    <span class="font-normal text-muted">({{ $item->jumlahBernama() }} dari {{ $item->qty }} nama terisi)</span>
                </p>

                <div class="mt-3 grid gap-3 lg:grid-cols-2" x-show="! ubah">
                    @foreach ($kolomPemain as $kolom)
                        <table @class(['tabel tabel-rapat text-sm', 'lg:col-span-2' => $kolomPemain->count() === 1])>
                            <thead>
                                <tr>
                                    <th class="w-10">No</th>
                                    <th>Nama</th>
                                    <th class="text-center">Nomor</th>
                                    <th>Ukuran</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($kolom as $urut => $r)
                                    <tr>
                                        <td class="tabular text-muted">{{ $urut + 1 }}</td>
                                        <td class="font-medium">{{ $r->nama_punggung ?: '-' }}</td>
                                        <td class="tabular text-center font-semibold">{{ $r->nomor ?: '-' }}</td>
                                        <td>{{ $r->labelUkuran() }}{{ $r->jumlah > 1 ? ' × '.$r->jumlah : '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endforeach
                </div>
            @else
                <p class="mt-3 rounded-[8px] bg-merah-50 px-3 py-2 text-sm">
                    Daftar nama dan nomor punggung belum diisi pelanggan.
                </p>
            @endif

            @if ($bolehUbahIsi && $item->hargaPerUkuran())
                @php
                    // Pilihannya mengikuti panduan ukuran produk, sama seperti saat
                    // memesan (KEP-099).
                    $pakaiKategori = $item->product->pakaiKategoriUkuran();
                    $pilihanUkuran = $pakaiKategori
                        ? collect(KategoriUkuran::cases())->map(fn ($k) => [
                            'value' => $k->value,
                            'ukuran' => $item->product->ukuranTersedia($k),
                        ])->values()
                        : collect([['value' => '', 'ukuran' => $item->product->ukuranTersedia(null)]]);

                    // Model kerah atau oblong per pemain (KEP-123): nilai yang
                    // masih dijual, ditambah yang sudah terpakai di pesanan ini.
                    $opsiPemain = $item->product->options->firstWhere('per_pemain', true);
                    $pilihanModel = $opsiPemain
                        ? $opsiPemain->values
                            ->filter(fn ($v) => $v->aktif || $item->rosters->contains('option_value_id', $v->id))
                            ->map(fn ($v) => ['id' => $v->id, 'label' => $v->label])->values()
                        : collect();
                @endphp
                <form method="POST" action="{{ route('panel.pesanan.item.jumlah', [$pesanan, $item]) }}"
                      class="mt-3" x-show="ubah" x-cloak
                      x-data="{
                          baris: {{ Js::from($item->rosters->map(fn ($r) => [
                              'kategori' => $pakaiKategori ? ($r->kategori?->value ?? '') : '',
                              'ukuran' => $r->ukuran,
                              'jumlah' => $r->jumlah,
                              'nama_punggung' => $r->nama_punggung ?? '',
                              'nomor' => $r->nomor ?? '',
                              'model' => $r->option_value_id ?? '',
                          ])->values()) }},
                          pilihan: {{ Js::from($pilihanUkuran) }},
                          ukuranUntuk(b) {
                              const ada = this.pilihan.find(p => p.value === b.kategori)?.ukuran ?? [];
                              return b.ukuran && !ada.includes(b.ukuran) ? [b.ukuran, ...ada] : ada;
                          },
                          betulkan(b) {
                              const ada = this.ukuranUntuk({ kategori: b.kategori });
                              if (!ada.includes(b.ukuran)) b.ukuran = ada[0] ?? '';
                          },
                          tambah() {
                              const akhir = this.baris.at(-1);
                              const k = akhir ? this.pilihan.find(p => p.value === akhir.kategori) ?? this.pilihan.at(-1) : this.pilihan.at(-1);
                              this.baris.push({ kategori: k.value, ukuran: k.ukuran.includes('L') ? 'L' : (k.ukuran[0] ?? ''),
                                  jumlah: 1, nama_punggung: '', nomor: '', model: akhir?.model ?? '' });
                          },
                      }">
                    @csrf

                    <div class="overflow-x-auto">
                        <table class="tabel tabel-rapat min-w-[40rem] text-sm">
                            <thead>
                                <tr>
                                    <th class="w-10">No</th>
                                    <th>Nama</th>
                                    <th class="w-20">Nomor</th>
                                    @if ($pakaiKategori)
                                        <th class="w-28">Kategori</th>
                                    @endif
                                    <th class="w-24">Ukuran</th>
                                    @if ($opsiPemain)
                                        <th class="w-36">{{ $opsiPemain->nama }}</th>
                                    @endif
                                    <th class="w-36">Jumlah</th>
                                    <th class="w-12"><span class="sr-only">Hapus</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(b, i) in baris" :key="i">
                                    <tr>
                                        <td class="tabular text-muted" x-text="i + 1"></td>
                                        <td>
                                            <input type="text" x-model="b.nama_punggung" :name="`baris[${i}][nama_punggung]`"
                                                   maxlength="60" class="kotak-input" placeholder="Tanpa nama" :aria-label="`Nama pemain ${i + 1}`">
                                            @unless ($opsiPemain)
                                                <input type="hidden" :name="`baris[${i}][model]`" :value="b.model">
                                            @endunless
                                        </td>
                                        <td>
                                            <input type="text" x-model="b.nomor" :name="`baris[${i}][nomor]`"
                                                   maxlength="5" class="kotak-input tabular" :aria-label="`Nomor pemain ${i + 1}`">
                                        </td>
                                        @if ($pakaiKategori)
                                            <td>
                                                <select x-model="b.kategori" :name="`baris[${i}][kategori]`" @change="betulkan(b)"
                                                        class="kotak-input" :aria-label="`Kategori pemain ${i + 1}`">
                                                    @foreach (KategoriUkuran::cases() as $kat)
                                                        <option value="{{ $kat->value }}">{{ $kat->label() }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        @endif
                                        <td>
                                            <select x-model="b.ukuran" :name="`baris[${i}][ukuran]`" class="kotak-input" required
                                                    :aria-label="`Ukuran pemain ${i + 1}`">
                                                <template x-for="u in ukuranUntuk(b)" :key="u">
                                                    <option :value="u" x-text="u" :selected="u === b.ukuran"></option>
                                                </template>
                                            </select>
                                        </td>
                                        @if ($opsiPemain)
                                            <td>
                                                <select x-model="b.model" :name="`baris[${i}][model]`" class="kotak-input"
                                                        :aria-label="`{{ $opsiPemain->nama }} pemain ${i + 1}`">
                                                    @unless ($opsiPemain->wajib)
                                                        <option value="">Tanpa pilihan</option>
                                                    @endunless
                                                    @foreach ($pilihanModel as $m)
                                                        <option value="{{ $m['id'] }}">{{ $m['label'] }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        @endif
                                        <td>
                                            <x-jumlah x-model.number="b.jumlah" ::name="`baris[${i}][jumlah]`" :min="0" max="5000" required />
                                        </td>
                                        <td class="text-right">
                                            <button type="button" class="tombol-ikon tombol-ikon-bahaya" @click="baris.splice(i, 1)"
                                                    :aria-label="`Hapus baris ${i + 1}`" title="Hapus baris">
                                                <x-ikon nama="hapus" ukuran="size-4" />
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button type="button" @click="tambah()" class="tombol tombol-garis tombol-sm">
                            <x-ikon nama="tambah" ukuran="size-4" /> Tambah data pemain
                        </button>
                        <button type="submit" class="tombol tombol-navy tombol-sm">
                            <x-ikon nama="centang" ukuran="size-4" /> Simpan perubahan
                        </button>
                        <button type="button" class="tombol tombol-halus tombol-sm" @click="ubah = false">Batal</button>
                    </div>

                    <p class="mt-2 text-xs leading-relaxed text-muted">
                        Ukuran yang sudah ada memakai harga yang disepakati saat memesan; ukuran atau model baru memakai
                        harga katalog hari ini. Jumlah 0 menghapus baris. Tagihan dan lembar kerja ikut berubah.
                    </p>
                </form>
            @endif
        </div>
    @elseif ($bolehUbahIsi)
        {{-- Barang stok toko: reservasi di gudang ikut menyesuaikan (KEP-044). --}}
        <div class="mt-4 border-t border-line pt-4">
            <button type="button" class="tombol tombol-garis tombol-sm" x-show="! ubah" @click="ubah = true">
                <x-ikon nama="pensil" ukuran="size-4" /> Ubah jumlah
            </button>
            <form method="POST" action="{{ route('panel.pesanan.item.jumlah', [$pesanan, $item]) }}"
                  class="flex flex-wrap items-end gap-3" x-show="ubah" x-cloak>
                @csrf
                <div>
                    <label for="qty-item-{{ $item->id }}" class="label-form">Jumlah (pcs)</label>
                    <x-jumlah id="qty-item-{{ $item->id }}" name="qty" :value="$item->qty" max="5000" required />
                </div>
                <button type="submit" class="tombol tombol-navy tombol-sm">Simpan jumlah</button>
                <button type="button" class="tombol tombol-halus tombol-sm" @click="ubah = false">Batal</button>
                <p class="w-full text-xs leading-relaxed text-muted">Reservasi stok di gudang ikut menyesuaikan.</p>
            </form>
        </div>
    @endif
</section>
