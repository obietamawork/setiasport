@extends('layouts.panel')
@section('judul', 'Pesanan '.$pesanan->kode)

@section('isi')
@php
    use App\Enums\MetodeAmbil;
    use App\Enums\OrderItemStatus;
    use App\Enums\OrderStatus;
    use App\Enums\PaymentMethod;
    use App\Enums\PaymentStatus;
    $izin = auth('web')->user();
@endphp

{{-- Judulnya kode pesanan, bukan status. Kode itulah yang dicari, disebut di
     telepon, dan dicocokkan dengan lembar kerja; statusnya sudah terbaca
     sebagai chip berwarna di sebelahnya. --}}
<x-kop :judul="$pesanan->kode" :kembali="route('panel.pesanan.index')" kembali-label="Daftar pesanan">
    <x-slot:bawahJudul>
        <div class="flex flex-wrap items-center gap-2 text-sm text-muted">
            <span class="chip {{ $pesanan->status->warna() }}">
                <x-ikon :nama="$pesanan->status->ikon()" ukuran="size-3.5" />
                {{ $pesanan->status->label() }}
            </span>
            <span>
                Dibuat {{ $pesanan->created_at->translatedFormat('j M Y, H:i') }}
                @if ($pesanan->target_siap_pada)
                    &middot; target siap {{ $pesanan->target_siap_pada->translatedFormat('j M Y') }}
                @endif
            </span>
        </div>
    </x-slot:bawahJudul>

    <a href="{{ route('panel.pesanan.worksheet', $pesanan) }}" target="_blank" rel="noopener"
       class="tombol tombol-garis tombol-sm">
        <x-ikon nama="cetak" ukuran="size-4" />
        Worksheet
    </a>
    <a href="{{ route('panel.pesanan.invoice', $pesanan) }}" target="_blank" rel="noopener"
       class="tombol tombol-garis tombol-sm">
        <x-ikon nama="uang" ukuran="size-4" />
        Invoice
    </a>
    <a href="{{ route('lacak.token', $pesanan->access_token) }}" target="_blank" rel="noopener"
       class="tombol tombol-halus tombol-sm">
        <x-ikon nama="buka-luar" ukuran="size-4" />
        <span class="hidden sm:inline">Lihat seperti pelanggan</span>
    </a>
</x-kop>

{{-- Satu kalimat yang menjawab "sekarang saya harus apa". Tanpa ini, giliran
     siapa sekarang hanya bisa disimpulkan setelah membaca sembilan kartu. --}}
<div class="langkah-berikut mt-1 mb-6
            {{ $langkah['nada'] === 'mendesak' ? 'langkah-berikut-mendesak' : '' }}
            {{ $langkah['nada'] === 'selesai' ? 'langkah-berikut-selesai' : '' }}">
    <div class="min-w-0 flex-1">
        <p class="font-bold">{{ $langkah['judul'] }}</p>
        <p class="kolom-baca mt-0.5 text-sm leading-relaxed text-muted">{{ $langkah['isi'] }}</p>
    </div>

    @if ($langkah['tuju'])
        <a href="{{ $langkah['tuju'] }}"
           class="tombol tombol-sm {{ $langkah['nada'] === 'mendesak' ? 'tombol-utama' : 'tombol-garis' }}">
            {{ $langkah['label'] }}
            <x-ikon nama="lanjut" ukuran="size-4" />
        </a>
    @endif
</div>

{{-- Tumpukan biasa di layar sempit, grid baru mulai lg. Trek grid "auto"
     melar mengikuti max-content isinya dan mendorong halaman ke samping. --}}
<div class="flex flex-col gap-6 lg:grid lg:grid-cols-[minmax(0,1fr)_296px] lg:items-start">
    <div class="min-w-0 space-y-6">

        {{-- Pengajuan pembatalan (KEP-041) ditaruh paling atas: selama belum
             diputuskan, produksi tetap berjalan dan kain tetap terpakai. --}}
        @if ($pembatalan)
            <section id="pembatalan" class="kartu scroll-mt-32 overflow-hidden border-kuning">
                <h2 class="kartu-kop bg-kuning-50">Pengajuan pembatalan</h2>
                <div class="space-y-4 p-5">
                    <div>
                        <p class="text-sm text-muted">
                            Diajukan pelanggan {{ $pembatalan->created_at->translatedFormat('j M Y, H:i') }}
                            &middot; {{ $pembatalan->tahap->label() }}
                        </p>
                        <p class="mt-2 leading-relaxed">"{{ $pembatalan->alasan }}"</p>
                    </div>

                    <dl class="grid gap-2 border-y border-line py-3 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-muted">Sudah dibayar</dt>
                            <dd class="tabular font-medium">{{ rupiah($pembatalan->sudah_dibayar) }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Biaya setup tercatat</dt>
                            <dd class="tabular font-medium">{{ rupiah($biayaSetup) }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Usulan pengembalian</dt>
                            <dd class="tabular font-medium">{{ rupiah($pembatalan->refund_usulan) }}</dd>
                        </div>
                    </dl>

                    <p class="text-sm leading-relaxed text-muted">{{ $pembatalan->tahap->ketentuan() }}</p>

                    @if ($izin->punyaIzin('order.batal'))
                        <form method="POST" action="{{ route('panel.pesanan.pembatalan.setujui', [$pesanan, $pembatalan]) }}"
                              class="space-y-3 border-t border-line pt-4"
                              onsubmit="return confirm('Setujui pembatalan? Pesanan dibatalkan dan reservasi stoknya dilepas.')">
                            @csrf
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="refund_jumlah" class="label-form">Pengembalian ke pelanggan</label>
                                    <input type="number" id="refund_jumlah" name="refund_jumlah"
                                           value="{{ old('refund_jumlah', $pembatalan->refund_usulan) }}"
                                           min="0" max="{{ $pembatalan->sudah_dibayar }}"
                                           class="kotak-input tabular" required>
                                    <p class="mt-1 text-xs text-muted">
                                        Isi 0 kalau tidak ada pengembalian. Maksimal {{ rupiah($pembatalan->sudah_dibayar) }}.
                                    </p>
                                </div>
                                <div>
                                    <label for="refund_metode" class="label-form">Dikembalikan lewat</label>
                                    <select id="refund_metode" name="refund_metode" class="kotak-input">
                                        <option value="TRANSFER">Transfer</option>
                                        <option value="TUNAI">Tunai di toko</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label for="catatan_admin" class="label-form">Catatan untuk pelanggan (opsional)</label>
                                <input type="text" id="catatan_admin" name="catatan_admin" maxlength="1000"
                                       class="kotak-input" value="{{ old('catatan_admin') }}">
                            </div>
                            <button type="submit" class="tombol tombol-navy">Setujui pembatalan</button>
                        </form>

                        <form method="POST" action="{{ route('panel.pesanan.pembatalan.tolak', [$pesanan, $pembatalan]) }}"
                              class="space-y-3 border-t border-line pt-4">
                            @csrf
                            <div>
                                <label for="alasan_tolak" class="label-form">Alasan menolak</label>
                                <input type="text" id="alasan_tolak" name="catatan_admin" maxlength="1000"
                                       class="kotak-input" required minlength="10"
                                       placeholder="Dibaca pelanggan di halaman pesanannya">
                            </div>
                            <button type="submit" class="tombol tombol-garis">Tolak pengajuan</button>
                        </form>
                    @endif
                </div>
            </section>
        @endif

        <section id="barang" class="kartu scroll-mt-32 overflow-hidden">
            <h2 class="kartu-kop">Barang</h2>
            <div class="divide-y divide-line">
                @foreach ($pesanan->items as $item)
                    @php
                        $alur = OrderItemStatus::alur($item->fulfillment_type);
                        $posisi = array_search($item->status_item, $alur, true);
                        $lanjut = $item->status_item->berikutnya($item->fulfillment_type);
                        $menungguPelanggan = $item->status_item === OrderItemStatus::MENUNGGU_PERSETUJUAN;
                        $menungguLunas = $item->status_item === OrderItemStatus::MENUNGGU_PELUNASAN;
                        $batal = $item->status_item === OrderItemStatus::DIBATALKAN;

                        // "Tandai Menunggu Pelunasan" tidak menjelaskan apa-apa ke
                        // operator. Yang ia lakukan sebenarnya menutup produksi.
                        $labelLanjut = $lanjut === OrderItemStatus::MENUNGGU_PELUNASAN
                            ? 'produksi selesai'
                            : $lanjut?->label();
                    @endphp

                    <div class="p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold">{{ $item->nama_produk }}</p>
                                <p class="tabular mt-0.5 text-sm text-muted">
                                    {{ $item->qty }} pcs &times; {{ rupiah($item->harga_satuan) }}
                                </p>
                            </div>
                            <p class="tabular font-semibold whitespace-nowrap">{{ rupiah($item->subtotal) }}</p>
                        </div>

                        @if ($item->options->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach ($item->options as $opsi)
                                    <span class="chip {{ $opsi->basis === \App\Enums\PriceBasis::PER_ITEM ? 'chip-kuning' : 'chip-netral' }}">
                                        {{ $opsi->option_value }}
                                        <span class="tabular font-normal">
                                            {{ $opsi->tambahan_harga < 0 ? '−' : '+' }}{{ rupiah_ringkas(abs($opsi->tambahan_harga)) }}
                                        </span>
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        {{-- Rincian ukuran ditonjolkan: inilah satu-satunya angka di
                             kartu ini yang dipakai orang produksi saat memotong kain. --}}
                        @if ($item->rosters->isNotEmpty())
                            <p class="tabular mt-3 rounded-[7px] bg-navy-50 px-3 py-2 text-sm font-semibold">
                                {{ $item->ringkasanUkuran() }}
                                <span class="font-normal text-muted">
                                    ({{ $item->rosters->count() }} dari {{ $item->qty }} nama terisi)
                                </span>
                            </p>
                        @elseif ($item->product->roster_wajib)
                            <p class="mt-3 rounded-[7px] bg-merah-50 px-3 py-2 text-sm">
                                Daftar nama dan nomor punggung belum diisi pelanggan.
                            </p>
                        @endif

                        {{-- Tautan kerja (KEP-047) disalin dari sini, lalu dikirim ke
                             pekerja atau grup bagiannya lewat WhatsApp. Sama persis
                             dengan yang ada di QR lembar kerja, untuk pekerja yang
                             lembarannya tertinggal di meja lain. --}}
                        @if (! $batal && $item->productionJobs->isNotEmpty())
                            <div class="mt-3 flex flex-wrap items-center gap-3 text-sm"
                                 x-data="{ tersalin: false }">
                                <button type="button" class="tautan"
                                        @click="navigator.clipboard.writeText(@js(route('kerja', $item->tokenKerja())))
                                                .then(() => { tersalin = true; setTimeout(() => tersalin = false, 2500) })">
                                    <span x-show="!tersalin">Salin tautan kerja</span>
                                    <span x-show="tersalin" x-cloak>Tautan tersalin</span>
                                </button>
                                <span class="text-xs text-muted">Pekerja butuh PIN untuk menandai selesai.</span>
                            </div>
                        @endif

                        {{-- Tambah atau kurangi isi pesanan (KEP-044). Dua pemain
                             menyusul adalah kejadian sehari-hari, dan selama ini
                             jalan keluarnya cuma penyesuaian biaya, sementara
                             daftar pemain dan lembar kerja tetap menyebut jumlah
                             lama. Konveksi lalu menjahit sesuai lembar kerja. --}}
                        @if (! $batal && $izin->punyaIzin('order.kelola') && ! $pesanan->status->final())
                            <details class="mt-3 border-t border-line pt-3">
                                <summary class="cursor-pointer text-sm text-muted">Ubah jumlah pesanan</summary>

                                @if ($item->hargaPerUkuran())
                                    <form method="POST" action="{{ route('panel.pesanan.item.jumlah', [$pesanan, $item]) }}"
                                          class="mt-3 space-y-3"
                                          x-data="{ baris: {{ Js::from($item->rosters->map(fn ($r) => [
                                              'kategori' => $r->kategori?->value ?? '',
                                              'ukuran' => $r->ukuran,
                                              'jumlah' => $r->jumlah,
                                              'nama_punggung' => $r->nama_punggung ?? '',
                                              'nomor' => $r->nomor ?? '',
                                          ])->values()) }} }">
                                        @csrf

                                        <template x-for="(b, i) in baris" :key="i">
                                            <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_4.5rem_8rem_5rem_4.5rem_auto] sm:items-end">
                                                <label class="block">
                                                    <span class="label-form">Nama</span>
                                                    <input type="text" x-model="b.nama_punggung" :name="`baris[${i}][nama_punggung]`"
                                                           maxlength="60" class="kotak-input" placeholder="Tanpa nama">
                                                </label>
                                                <label class="block">
                                                    <span class="label-form">No</span>
                                                    <input type="text" x-model="b.nomor" :name="`baris[${i}][nomor]`"
                                                           maxlength="5" class="kotak-input tabular">
                                                </label>
                                                <label class="block">
                                                    <span class="label-form">Kategori</span>
                                                    <select x-model="b.kategori" :name="`baris[${i}][kategori]`" class="kotak-input">
                                                        @foreach (\App\Enums\KategoriUkuran::cases() as $kat)
                                                            <option value="{{ $kat->value }}">{{ $kat->label() }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                                <label class="block">
                                                    <span class="label-form">Ukuran</span>
                                                    <input type="text" x-model="b.ukuran" :name="`baris[${i}][ukuran]`"
                                                           maxlength="10" class="kotak-input" required>
                                                </label>
                                                <label class="block">
                                                    <span class="label-form">Jumlah</span>
                                                    <input type="number" x-model.number="b.jumlah" :name="`baris[${i}][jumlah]`"
                                                           min="0" max="5000" class="kotak-input tabular" required>
                                                </label>
                                                <button type="button" @click="baris.splice(i, 1)"
                                                        class="tautan pb-2 text-xs text-merah">Hapus</button>
                                            </div>
                                        </template>

                                        <div class="flex flex-wrap items-center gap-3">
                                            <button type="button"
                                                    @click="baris.push({ kategori: 'DEWASA', ukuran: 'L', jumlah: 1, nama_punggung: '', nomor: '' })"
                                                    class="tombol tombol-garis tombol-sm">+ Tambah baris</button>
                                            <button type="submit" class="tombol tombol-navy tombol-sm">Simpan jumlah</button>
                                        </div>

                                        <p class="text-xs leading-relaxed text-muted">
                                            Ukuran yang sudah ada memakai harga yang disepakati saat memesan.
                                            Ukuran baru memakai harga katalog hari ini. Isi jumlah 0 untuk menghapus baris.
                                        </p>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('panel.pesanan.item.jumlah', [$pesanan, $item]) }}"
                                          class="mt-3 flex flex-wrap items-end gap-3">
                                        @csrf
                                        <label class="block">
                                            <span class="label-form">Jumlah (pcs)</span>
                                            <input type="number" name="qty" value="{{ $item->qty }}" min="1" max="5000"
                                                   class="kotak-input tabular w-28" required>
                                        </label>
                                        <button type="submit" class="tombol tombol-navy tombol-sm">Simpan jumlah</button>
                                        <p class="w-full text-xs leading-relaxed text-muted">
                                            Untuk barang stok, reservasi di gudang ikut menyesuaikan.
                                        </p>
                                    </form>
                                @endif
                            </details>
                        @endif

                        @unless ($batal)
                            <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-line pt-4">
                                {{-- Tahapan berlabel, bukan deretan titik. Titik tanpa nama hanya
                                     terbaca oleh orang yang sudah hafal urutannya, dan yang paling
                                     sering bertanya "sekarang sampai mana" justru yang belum hafal. --}}
                                <div class="tangga w-full" aria-label="Tahap: {{ $item->status_item->labelStaf() }}">
                                    @foreach ($alur as $i => $tahap)
                                        <span class="tangga-langkah
                                            {{ $posisi !== false && $i < $posisi ? 'tangga-lewat' : '' }}
                                            {{ $posisi === $i ? 'tangga-kini' : '' }}">
                                            @if ($posisi !== false && $i < $posisi)
                                                <x-ikon nama="centang" ukuran="size-3.5" />
                                            @endif
                                            {{ $tahap->labelStaf() }}
                                        </span>
                                    @endforeach
                                </div>

                                @if ($menungguPelanggan)
                                    <span class="ml-auto text-sm text-muted">
                                        Hanya pelanggan yang bisa menyetujui desain
                                    </span>
                                @elseif ($menungguLunas)
                                    {{-- Gerbang tanpa tombol (KEP-028): item naik sendiri begitu
                                         uangnya tercatat, tidak ada jalan pintas dari panel. --}}
                                    <span class="ml-auto text-sm text-muted">
                                        Naik ke Siap begitu sisa {{ rupiah($pesanan->sisa_tagihan) }} tercatat
                                    </span>
                                @elseif ($lanjut && $lanjut !== OrderItemStatus::SELESAI && $izin->punyaIzin('order.kelola'))
                                    <form method="POST" action="{{ route('panel.pesanan.item.maju', [$pesanan, $item]) }}"
                                          class="ml-auto">
                                        @csrf
                                        <button type="submit" class="tombol tombol-garis tombol-sm">
                                            Tandai {{ $labelLanjut }}
                                        </button>
                                    </form>
                                @elseif ($lanjut === OrderItemStatus::SELESAI)
                                    <span class="ml-auto text-sm text-muted">Selesai lewat serah terima</span>
                                @endif
                            </div>
                        @else
                            <p class="mt-3 text-sm text-muted">Item ini dibatalkan.</p>
                        @endunless
                    </div>
                @endforeach
            </div>
        </section>

        <section id="pembayaran" class="kartu scroll-mt-32 overflow-hidden">
            <h2 class="kartu-kop">Pembayaran</h2>

            <ul class="divide-y divide-line text-sm">
                @foreach ($pesanan->payments as $bayar)
                    <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                        <div class="min-w-40 flex-1">
                            <p class="tabular font-semibold">{{ rupiah($bayar->jumlah) }}</p>
                            <p class="mt-0.5 text-xs text-muted">
                                @php
                                    $jejak = array_filter([
                                        $bayar->jenis->label(),
                                        $bayar->metode->label(),
                                        $bayar->pencatat ? 'dicatat '.$bayar->pencatat->name : null,
                                        $bayar->verifikator ? 'diverifikasi '.$bayar->verifikator->name : null,
                                    ]);
                                @endphp
                                {{ implode(', ', $jejak) }}
                            </p>
                        </div>

                        <span class="chip {{ $bayar->status === PaymentStatus::LUNAS ? 'chip-ok' : 'chip-kuning' }}">
                            {{ $bayar->status->label() }}
                        </span>

                        @if ($bayar->bukti_path)
                            {{-- Lewat rute berizin, bukan alamat berkas: bukti memuat nama
                                 dan nomor rekening pelanggan (KEP-029). --}}
                            <a href="{{ route('panel.pesanan.pembayaran.bukti', [$pesanan, $bayar]) }}" target="_blank" rel="noopener"
                               class="tautan">Lihat bukti</a>
                        @endif

                        @if ($bayar->status === PaymentStatus::MENUNGGU && $izin->punyaIzin('pembayaran.verifikasi'))
                            {{-- Tidak ada gerbang yang mengabari kami (KEP-023): admin
                                 mencocokkan sendiri mutasi rekening dengan bukti yang
                                 masuk lewat WhatsApp, lalu mencatat hasilnya di sini. --}}
                            <details class="lipatan w-full">
                                <summary>Verifikasi pembayaran ini</summary>
                                <div class="grid gap-4 bg-navy-50 p-4 sm:grid-cols-2">
                                    <form method="POST" enctype="multipart/form-data" class="contents"
                                          action="{{ route('panel.pesanan.pembayaran.verifikasi', [$pesanan, $bayar]) }}">
                                        @csrf
                                        <div>
                                            <label for="metode-{{ $bayar->id }}" class="label-form">Uang masuk lewat</label>
                                            <select id="metode-{{ $bayar->id }}" name="metode" class="kotak-input">
                                                <option value="TRANSFER" @selected($bayar->metode === PaymentMethod::TRANSFER)>Transfer bank</option>
                                                <option value="QRIS" @selected($bayar->metode === PaymentMethod::QRIS)>QRIS</option>
                                                <option value="TUNAI" @selected($bayar->metode === PaymentMethod::TUNAI)>Tunai</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="bukti-{{ $bayar->id }}" class="label-form">
                                                {{ $bayar->bukti_path ? 'Ganti bukti' : 'Lampirkan bukti' }}
                                            </label>
                                            <input type="file" id="bukti-{{ $bayar->id }}" name="bukti"
                                                   accept="image/*" class="kotak-input">
                                            <p class="mt-1 text-xs text-muted">
                                                {{ $bayar->bukti_path
                                                    ? 'Pelanggan sudah mengunggah bukti. Kosongkan untuk memakainya.'
                                                    : (cs_aktif() ? 'Untuk bukti yang diterima lewat chat CS atau di toko.' : 'Untuk bukti yang diterima di toko.').' Boleh dikosongkan.' }}
                                            </p>
                                        </div>

                                        {{-- Bukti bisa diedit dalam semenit; mutasi tidak (KEP-029). --}}
                                        <label class="flex items-start gap-2 border-l-2 border-kuning bg-kuning-50 p-3 text-sm leading-relaxed sm:col-span-2">
                                            <input type="checkbox" name="cek_mutasi" value="1" required
                                                   class="mt-1 size-4 shrink-0 accent-navy">
                                            <span>
                                                Saya sudah melihat <strong>{{ rupiah($bayar->jumlah) }}</strong> benar-benar masuk
                                                di mutasi rekening atau riwayat QRIS toko, atau menerimanya tunai.
                                                Bukti transfer saja tidak cukup.
                                            </span>
                                        </label>

                                        <div class="sm:col-span-2">
                                            <button type="submit" class="tombol tombol-navy">
                                                Uang sudah masuk, verifikasi
                                            </button>
                                            <p class="mt-2 text-xs leading-relaxed text-muted">
                                                Setelah ini pesanan berlanjut ke tahap berikutnya dan pelanggan dikabari
                                                lewat email. Nama Anda tercatat sebagai yang memverifikasi.
                                            </p>
                                        </div>
                                    </form>

                                    <form method="POST" class="sm:col-span-2 flex flex-wrap items-end gap-3 border-t border-line pt-4"
                                          action="{{ route('panel.pesanan.pembayaran.tolak', [$pesanan, $bayar]) }}">
                                        @csrf
                                        <div class="min-w-56 flex-1">
                                            <label for="alasan-{{ $bayar->id }}" class="label-form">Atau tandai gagal, alasannya</label>
                                            <input type="text" id="alasan-{{ $bayar->id }}" name="alasan"
                                                   class="kotak-input" maxlength="150"
                                                   placeholder="Misal: nominal kurang, bukti tidak cocok">
                                        </div>
                                        <button type="submit" class="tombol tombol-garis">Tandai gagal</button>
                                    </form>
                                </div>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>

            {{-- Formulir yang jarang dipakai dilipat. Kotak isian yang menganggur
                 membuat layar terlihat penuh padahal tidak ada yang dikerjakan. --}}
            @if ($pesanan->sisa_tagihan > 0 && $izin->punyaIzin('pembayaran.catat') && ! $pesanan->status->final())
                <details class="lipatan border-t border-line">
                    <summary>Catat pembayaran diterima di toko</summary>
                    <form method="POST" action="{{ route('panel.pesanan.pembayaran.catat', $pesanan) }}"
                          class="grid gap-4 bg-navy-50 p-4 sm:grid-cols-3">
                        @csrf
                        <div>
                            <label for="jumlah" class="label-form">Nominal</label>
                            <input type="number" id="jumlah" name="jumlah" value="{{ $pesanan->sisa_tagihan }}"
                                   class="kotak-input tabular" min="1" required>
                        </div>
                        <div>
                            <label for="metode" class="label-form">Metode</label>
                            <select id="metode" name="metode" class="kotak-input">
                                <option value="TUNAI">Tunai</option>
                                <option value="QRIS">QRIS</option>
                                <option value="TRANSFER">Transfer bank</option>
                            </select>
                        </div>
                        <div>
                            <label for="jenis" class="label-form">Jenis</label>
                            <select id="jenis" name="jenis" class="kotak-input">
                                <option value="PELUNASAN">Pelunasan</option>
                                <option value="DP">Uang muka</option>
                                <option value="FULL">Lunas</option>
                            </select>
                        </div>
                        <div class="sm:col-span-3">
                            <button type="submit" class="tombol tombol-navy">Catat pembayaran</button>
                        </div>
                    </form>
                </details>
            @endif
        </section>

        {{-- Pengambilan vs pengiriman (KEP-026). Disetel di sini, bukan di layar
             kasir: kesepakatan mengirim terjadi lewat WhatsApp jauh sebelum
             barangnya siap, bukan di depan meja kasir. --}}
        <section id="pengambilan" class="kartu scroll-mt-32 overflow-hidden">
            <h2 class="kartu-kop">Pengambilan</h2>

            <div class="space-y-4 p-5">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="chip {{ $pesanan->dikirim() ? 'chip-kuning' : 'chip-netral' }}">
                        {{ $pesanan->metode_ambil->label() }}
                    </span>
                    @if ($pesanan->dikirim())
                        <span class="text-sm {{ $pesanan->ongkir > 0 ? 'text-muted' : 'text-merah' }}">
                            @if ($pesanan->ongkir > 0)
                                Ongkir <span class="tabular">{{ rupiah($pesanan->ongkir) }}</span> sudah masuk tagihan.
                            @else
                                Ongkirnya belum diisi, jadi tagihannya masih kurang.
                            @endif
                        </span>
                    @endif
                </div>

                @if ($pesanan->dikirim_pada)
                    <dl class="grid gap-3 border-t border-line pt-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-muted">Jasa kirim</dt>
                            <dd class="font-semibold">{{ $pesanan->jasa_kirim }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Nomor resi</dt>
                            <dd class="numeral text-lg">{{ $pesanan->no_resi }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Diserahkan ke kurir</dt>
                            <dd class="tabular">{{ $pesanan->dikirim_pada->translatedFormat('j M Y, H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Alamat</dt>
                            <dd class="leading-relaxed">{{ $pesanan->alamat_kirim }}</dd>
                        </div>
                    </dl>

                    {{-- Apa kata kurir (KEP-061). Ditampilkan sebagai ajakan menandai,
                         bukan sebagai tindakan: kurir bilang "diterima" pun, yang
                         menutup pesanan tetap orang toko, karena diterima satpam
                         sekolah dan diterima pemesannya adalah dua hal berbeda. --}}
                    @if ($pesanan->lacak_status)
                        <div class="border-t border-line pt-4">
                            <p class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="chip {{ $pesanan->paketSampai() ? 'chip-ok' : 'chip-navy' }}">
                                    <x-ikon nama="kirim" ukuran="size-3.5" />
                                    Kata kurir: {{ $pesanan->lacak_status }}
                                </span>
                                <span class="text-muted">
                                    {{ $pesanan->lacak_catatan }}
                                    @if ($pesanan->lacak_pada)
                                        &middot; diperbarui {{ $pesanan->lacak_pada->diffForHumans() }}
                                    @endif
                                </span>
                            </p>

                            @if ($pesanan->paketSampai() && ! $pesanan->diterima_pada)
                                <p class="mt-2 text-sm leading-relaxed text-muted">
                                    Kurir menyatakan paketnya sudah sampai. Pastikan dulu ke pelanggan,
                                    lalu tandai di bawah supaya pesanan ini keluar dari daftar aktif.
                                </p>
                            @endif
                        </div>
                    @endif

                    @if ($pesanan->status === OrderStatus::DIKIRIM && $izin->punyaIzin('serah_terima.proses'))
                        <form method="POST" action="{{ route('panel.pesanan.pengiriman.diterima', $pesanan) }}"
                              class="border-t border-line pt-4">
                            @csrf
                            <button type="submit" class="tombol tombol-navy">Paket sudah diterima pelanggan</button>
                            <p class="mt-2 text-xs leading-relaxed text-muted">
                                Selama belum ditekan, pesanan ini tetap muncul di daftar aktif. Itu disengaja:
                                paket yang nyangkut di kurir tidak boleh hilang dari pandangan.
                            </p>
                        </form>
                    @elseif ($pesanan->diterima_pada)
                        <p class="border-t border-line pt-4 text-sm text-ok">
                            Diterima {{ $pesanan->diterima_pada->translatedFormat('j M Y, H:i') }}.
                        </p>
                    @endif
                @elseif ($izin->punyaIzin('serah_terima.proses') && ! $pesanan->status->final())
                    @if ($pesanan->dikirim())
                        <div class="border-t border-line pt-4">
                            <p class="text-sm leading-relaxed">
                                Dikirim ke:<br>
                                <span class="font-medium">{{ $pesanan->alamat_kirim }}</span>
                            </p>

                            @if ($pesanan->bolehDikirim())
                                <form method="POST" action="{{ route('panel.pesanan.pengiriman.kirim', $pesanan) }}"
                                      class="mt-4 grid gap-4 bg-navy-50 p-4 sm:grid-cols-2">
                                    @csrf
                                    <div>
                                        <label for="jasa_kirim" class="label-form">Jasa kirim</label>
                                        <select id="jasa_kirim" name="jasa_kirim" class="kotak-input">
                                            @foreach (daftar_setting('jasa_kirim_daftar') as $jasa)
                                                <option value="{{ $jasa }}">{{ $jasa }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="no_resi" class="label-form">Nomor resi</label>
                                        <input type="text" id="no_resi" name="no_resi" class="kotak-input tabular"
                                               maxlength="60" required autocomplete="off">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <button type="submit" class="tombol tombol-navy">Catat resi, tandai dikirim</button>
                                        <p class="mt-2 text-xs leading-relaxed text-muted">
                                            Tekan setelah paket benar-benar diserahkan ke kurir. Pelanggan langsung
                                            menerima email berisi resi ini.
                                        </p>
                                    </div>
                                </form>
                            @else
                                <p class="mt-3 border-l-2 border-kuning bg-kuning-50 p-3 text-sm leading-relaxed">
                                    Belum bisa dikirim. {{ $pesanan->alasanBelumBisaDikirim() }}
                                </p>
                            @endif
                        </div>
                    @endif

                    <details class="lipatan border-t border-line" @if ($errors->has('alamat_kirim') || $errors->has('metode_ambil')) open @endif>
                        <summary>{{ $pesanan->dikirim() ? 'Ubah alamat atau kembalikan ke ambil di toko' : 'Pesanan ini mau dikirim' }}</summary>
                        <form method="POST" action="{{ route('panel.pesanan.pengiriman.atur', $pesanan) }}"
                              class="space-y-4 bg-navy-50 p-4">
                            @csrf
                            <div>
                                <label for="metode_ambil" class="label-form">Cara pengambilan</label>
                                <select id="metode_ambil" name="metode_ambil" class="kotak-input">
                                    @foreach (MetodeAmbil::cases() as $m)
                                        <option value="{{ $m->value }}" @selected(old('metode_ambil', $pesanan->metode_ambil->value) === $m->value)>
                                            {{ $m->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="alamat_kirim" class="label-form">Alamat lengkap</label>
                                <textarea id="alamat_kirim" name="alamat_kirim" rows="3" class="kotak-input leading-relaxed"
                                          maxlength="500" placeholder="Nama penerima, alamat, kecamatan, kota, kode pos, nomor HP">{{ old('alamat_kirim', $pesanan->alamat_kirim) }}</textarea>
                                <p class="mt-1 text-xs leading-relaxed text-muted">
                                    Salin apa adanya dari WhatsApp pelanggan. Hanya dipakai kalau pesanan dikirim.
                                </p>
                            </div>
                            <div>
                                <label for="ongkir" class="label-form">Ongkos kirim (Rp)</label>
                                <input type="number" id="ongkir" name="ongkir" min="0" step="1000"
                                       value="{{ old('ongkir', $pesanan->ongkir) }}" class="kotak-input tabular">
                                <p class="mt-1 text-xs leading-relaxed text-muted">
                                    Masuk tagihan sebagai barisnya sendiri, bukan penyesuaian biaya. Kalau pelanggan
                                    sudah memilih kurir sendiri saat memesan, kolom ini sudah terisi.
                                </p>
                            </div>
                            <div>
                                <button type="submit" class="tombol tombol-navy">Simpan</button>
                            </div>
                        </form>
                    </details>
                @endif
            </div>
        </section>

        <section id="penyesuaian" class="kartu scroll-mt-32 overflow-hidden">
            <h2 class="kartu-kop">Penyesuaian biaya</h2>

            @if ($pesanan->adjustments->isEmpty())
                <p class="px-5 py-4 text-sm text-muted">
                    Belum ada. Pakai ini untuk perubahan setelah pesanan dibuat, misalnya tambah
                    pemain atau potongan harga. Pelanggan melihatnya di halaman pesanannya
                    sebelum datang ke toko.
                </p>
            @else
                <ul class="divide-y divide-line text-sm">
                    @foreach ($pesanan->adjustments as $p)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                            <span class="min-w-40 flex-1">
                                {{ $p->deskripsi }}
                                <span class="mt-0.5 block text-xs text-muted">
                                    {{ $p->pembuat?->name ?? 'Sistem' }},
                                    {{ $p->created_at->translatedFormat('j M Y, H:i') }}
                                </span>
                            </span>
                            <span class="tabular font-semibold {{ $p->jumlah < 0 ? 'text-ok' : '' }}">
                                {{ $p->jumlah < 0 ? '−' : '+' }}{{ rupiah(abs($p->jumlah)) }}
                            </span>
                            @if ($izin->punyaIzin('order.penyesuaian') && ! $pesanan->status->final())
                                <form method="POST" action="{{ route('panel.pesanan.penyesuaian.hapus', [$pesanan, $p]) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="tautan text-muted">Hapus</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($izin->punyaIzin('order.penyesuaian') && ! $pesanan->status->final())
                <details class="lipatan border-t border-line">
                    <summary>Tambah penyesuaian biaya</summary>
                    <form method="POST" action="{{ route('panel.pesanan.penyesuaian.simpan', $pesanan) }}"
                          class="grid gap-4 bg-navy-50 p-4 sm:grid-cols-[minmax(0,1fr)_10rem]">
                        @csrf
                        <div>
                            <label for="deskripsi" class="label-form">Deskripsi untuk pelanggan</label>
                            <input type="text" id="deskripsi" name="deskripsi" class="kotak-input" required
                                   placeholder="Tambah 2 pemain">
                        </div>
                        <div>
                            <label for="nominal" class="label-form">Nominal</label>
                            <input type="number" id="nominal" name="jumlah" class="kotak-input tabular" required
                                   placeholder="240000">
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="tombol tombol-navy">Tambah penyesuaian</button>
                            <span class="bantuan-form inline-block">
                                Pakai angka negatif untuk potongan harga, misalnya -70000.
                            </span>
                        </div>
                    </form>
                </details>
            @endif
        </section>

        {{-- Riwayat adalah rujukan, bukan pekerjaan. Dilipat supaya tidak
             sebobot Barang dan Pembayaran. --}}
        <details class="kartu lipatan overflow-hidden">
            <summary>Riwayat perubahan ({{ $pesanan->statusLogs->count() }})</summary>
            <ul class="divide-y divide-line border-t border-line text-sm">
                @foreach ($pesanan->statusLogs as $log)
                    <li class="flex flex-wrap justify-between gap-3 px-5 py-2.5">
                        <span>
                            {{ $log->orderItem?->nama_produk ?? 'Pesanan' }}:
                            {{ OrderItemStatus::tryFrom($log->ke)?->label() ?? OrderStatus::tryFrom($log->ke)?->label() ?? $log->ke }}
                            @if ($log->catatan)
                                <span class="block text-xs text-muted">{{ $log->catatan }}</span>
                            @endif
                        </span>
                        <span class="text-right text-muted">
                            <span class="tabular">{{ $log->created_at?->translatedFormat('j M, H:i') }}</span>
                            <span class="block text-xs">{{ $log->pelaku?->name ?? 'Pelanggan' }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </details>
    </div>

    <aside class="space-y-4">
        {{-- Uang didahulukan: itu yang paling sering dilihat kasir. --}}
        <div class="kartu p-5">
            <h2 class="font-semibold">Uang</h2>
            <dl class="mt-3 space-y-1.5 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-muted">Total</dt>
                    <dd class="tabular font-semibold">{{ rupiah($pesanan->total) }}</dd>
                </div>
                @if ($pesanan->biaya_layanan > 0)
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted">Biaya layanan</dt>
                        <dd class="tabular">{{ rupiah($pesanan->biaya_layanan) }}</dd>
                    </div>
                @endif
                @if ($pesanan->dikirim())
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted">Ongkos kirim</dt>
                        <dd class="tabular {{ $pesanan->ongkir > 0 ? '' : 'text-merah' }}">
                            {{ $pesanan->ongkir > 0 ? rupiah($pesanan->ongkir) : 'belum diisi' }}
                        </dd>
                    </div>
                @endif
                <div class="flex justify-between gap-3">
                    <dt class="text-muted">Sudah dibayar</dt>
                    <dd class="tabular">{{ rupiah($pesanan->sudah_dibayar) }}</dd>
                </div>
                <div class="flex justify-between gap-3 border-t border-line pt-1.5">
                    <dt class="font-semibold">Sisa</dt>
                    <dd class="tabular font-semibold {{ $pesanan->sisa_tagihan > 0 ? 'text-merah' : 'text-ok' }}">
                        {{ $pesanan->sisa_tagihan > 0 ? rupiah($pesanan->sisa_tagihan) : 'Lunas' }}
                    </dd>
                </div>

                {{-- Kelebihan bayar tidak pernah muncul sendiri di "sisa", karena
                     sisa berhenti di nol. Setelah pesanan dikurangi (KEP-044),
                     uang lebih ini yang harus dikembalikan ke pelanggan. --}}
                @if ($pesanan->sudah_dibayar > $pesanan->total)
                    <div class="flex justify-between gap-3 border-t border-line pt-1.5">
                        <dt class="font-semibold">Kelebihan bayar</dt>
                        <dd class="tabular font-semibold text-merah">
                            {{ rupiah($pesanan->sudah_dibayar - $pesanan->total) }}
                        </dd>
                    </div>
                @endif
            </dl>

            @if ($pesanan->kode_ambil)
                <div class="mt-4 border-t border-line pt-3">
                    <p class="text-sm text-muted">Kode ambil</p>
                    <p class="numeral mt-1 text-2xl">{{ $pesanan->kode_ambil }}</p>
                </div>
            @endif
        </div>

        <div id="pelanggan" class="kartu scroll-mt-32 p-5">
            <h2 class="font-semibold">Pelanggan</h2>
            <p class="mt-2 flex flex-wrap items-center gap-2 text-sm font-medium">
                {{ $pesanan->customer->nama }}
                @if ($pesanan->customer->reseller)
                    <span class="chip chip-kuning">Reseller</span>
                @endif
            </p>
            <p class="text-sm break-all text-muted">{{ $pesanan->customer->email }}</p>
            <p class="tabular text-sm text-muted">{{ $pesanan->customer->no_hp }}</p>

            @if ($pesanan->customer->reseller && $pesanan->customer->catatan_reseller)
                <p class="mt-2 text-sm text-muted">{{ $pesanan->customer->catatan_reseller }}</p>
            @endif

            @if ($pesanan->catatan)
                <p class="mt-3 border-l-2 border-kuning bg-kuning-50 py-2 pr-2 pl-3 text-sm">
                    {{ $pesanan->catatan }}
                </p>
            @endif

            {{-- Penanda reseller (KEP-046). Harganya tidak berubah otomatis:
                 potongan diberikan admin per pesanan lewat penyesuaian biaya,
                 karena kesepakatannya memang dibicarakan satu per satu. --}}
            @if ($izin->punyaIzin('order.kelola'))
                <details class="mt-4 border-t border-line pt-3">
                    <summary class="cursor-pointer text-sm text-muted">
                        {{ $pesanan->customer->reseller ? 'Ubah penanda reseller' : 'Tandai sebagai reseller' }}
                    </summary>
                    <form method="POST" action="{{ route('panel.pesanan.reseller', $pesanan) }}" class="mt-3 space-y-3">
                        @csrf
                        <input type="hidden" name="reseller" value="{{ $pesanan->customer->reseller ? 0 : 1 }}">

                        @unless ($pesanan->customer->reseller)
                            <div>
                                <label for="catatan_reseller" class="label-form">Kesepakatan potongan (opsional)</label>
                                <input type="text" id="catatan_reseller" name="catatan_reseller" maxlength="200"
                                       class="kotak-input" placeholder="Contoh: potongan Rp5.000 per pcs di atas 50 pcs">
                            </div>
                        @endunless

                        <button type="submit" class="tombol tombol-garis tombol-sm">
                            {{ $pesanan->customer->reseller ? 'Lepas penanda' : 'Tandai reseller' }}
                        </button>
                        <p class="text-xs leading-relaxed text-muted">
                            Penanda ini pengingat untuk admin. Potongannya tetap ditambahkan
                            per pesanan lewat penyesuaian biaya.
                        </p>
                    </form>
                </details>
            @endif
        </div>

        {{-- Tanpa WhatsApp API (KEP-004), sistem menyiapkan teksnya dan admin
             yang menekan kirim. --}}
        <div class="kartu p-5" x-data="{ tersalin: false }">
            <h2 class="font-semibold">Kabari lewat WhatsApp</h2>
            <p class="mt-1 text-xs leading-relaxed text-muted">
                Email otomatis sudah dikirim. Pakai ini kalau pelanggan belum merespons.
            </p>

            <p class="mt-3 max-h-24 overflow-y-auto rounded-[7px] bg-navy-50 p-3 text-xs leading-relaxed"
               x-ref="pesan">{{ $pesanWa['teks'] }}</p>

            <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" class="tombol tombol-garis tombol-sm"
                        @click="navigator.clipboard.writeText($refs.pesan.textContent.trim()); tersalin = true; setTimeout(() => tersalin = false, 2000)">
                    <span x-text="tersalin ? 'Tersalin' : 'Salin teks'">Salin teks</span>
                </button>
                <a href="{{ $pesanWa['url'] }}" target="_blank" rel="noopener"
                   class="tombol tombol-navy tombol-sm">Buka WhatsApp</a>
            </div>
        </div>

        @if ($pesanan->emailLogs->isNotEmpty())
            <div class="kartu p-5">
                <h2 class="font-semibold">Email terkirim</h2>
                <ul class="mt-3 space-y-1.5 text-xs">
                    @foreach ($pesanan->emailLogs as $log)
                        <li class="flex justify-between gap-2">
                            <span>{{ $log->template }}</span>
                            <span class="{{ $log->status->perluTindakLanjut() ? 'text-merah' : 'text-muted' }}">
                                {{ $log->status->label() }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </aside>
</div>

{{-- Tindakan berisiko dipisah dan diredam. Membatalkan pesanan tidak boleh
     sebobot kartu informasi di sebelahnya. --}}
@if ($izin->punyaIzin('order.batal') && ! $pesanan->status->final())
    <div class="zona-bahaya" x-data="{ buka: false }">
        <button type="button" @click="buka = !buka" class="tautan text-sm text-muted">
            Batalkan pesanan ini
        </button>

        <form method="POST" action="{{ route('panel.pesanan.batal', $pesanan) }}"
              x-show="buka" x-cloak class="notis notis-galat mt-3 max-w-xl"
              onsubmit="return confirm('Batalkan pesanan {{ $pesanan->kode }}? Reservasi stoknya dilepas.')">
            @csrf
            <p class="font-semibold">Pesanan akan dibatalkan dan reservasi stoknya dilepas.</p>
            <label for="alasan" class="label-form mt-3">Alasan, akan masuk riwayat pesanan</label>
            <input type="text" id="alasan" name="alasan" class="kotak-input" required
                   placeholder="Dibatalkan pelanggan lewat telepon">
            <button type="submit" class="tombol tombol-utama tombol-sm mt-3">Ya, batalkan</button>
        </form>

        {{-- Menghanguskan hanya muncul setelah batasnya benar-benar lewat
             (KEP-043). Uang pelanggan tidak kembali, jadi tombol ini tidak
             boleh tersedia "untuk berjaga-jaga". --}}
        @if ($pesanan->bolehDihanguskan())
            <form method="POST" action="{{ route('panel.pesanan.hangus', $pesanan) }}" class="mt-5"
                  onsubmit="return confirm('Tandai pesanan {{ $pesanan->kode }} hangus? Uang yang sudah dibayar tidak dikembalikan.')">
                @csrf
                <p class="text-sm leading-relaxed text-muted">
                    Barang sudah siap sejak {{ $pesanan->siap_pada->translatedFormat('j F Y') }} dan belum diambil,
                    melewati batas {{ setting('hangus_hari', 30) }} hari. Pastikan pelanggan sudah dihubungi
                    sebelum menghanguskan.
                </p>
                <button type="submit" class="tombol tombol-garis tombol-sm mt-2 text-merah">
                    Tandai hangus
                </button>
            </form>
        @endif
    </div>
@endif
@endsection
