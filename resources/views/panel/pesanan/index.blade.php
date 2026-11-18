@extends('layouts.panel')
@php
    use App\Enums\OrderStatus;

    $saringan = [
        '' => 'Semua status',
        'tindakan' => 'Perlu tindakan',
        'aktif' => 'Masih berjalan',
        OrderStatus::MENUNGGU_PEMBAYARAN->value => 'Menunggu pembayaran',
        OrderStatus::DIPROSES->value => 'Diproses',
        OrderStatus::MENUNGGU_PELUNASAN->value => 'Menunggu pelunasan',
        OrderStatus::SIAP_DIAMBIL->value => 'Siap diambil',
        OrderStatus::DIKIRIM->value => 'Dalam pengiriman',
        OrderStatus::SELESAI->value => 'Selesai',
        OrderStatus::DIBATALKAN->value => 'Dibatalkan',
        OrderStatus::KADALUARSA->value => 'Kedaluwarsa',
    ];

    // Tab daftar yang disaring tidak boleh kembar dengan daftar lengkapnya
    // (KEP-101): staf membuka "menunggu pembayaran" berdampingan dengan semua.
    $penyaring = array_filter([
        ['kustom' => 'Kustom', 'stok' => 'Stok toko'][$jalur] ?? null,
        $status !== '' ? ($saringan[$status] ?? null) : null,
        request()->filled('q') ? 'cari "'.Str::limit(request()->string('q')->squish(), 30).'"' : null,
    ]);

    $adaSaringan = $status !== '' || request()->filled('q') || $dari || $sampai;
    $bolehKelola = auth('web')->user()->punyaIzin('order.kelola');
    $bolehSerah = auth('web')->user()->punyaIzin('serah_terima.proses');
    // Hapus riwayat (KEP-119): hanya Pemilik, hanya pesanan mati tanpa uang.
    $bolehHapus = auth('web')->user()->punyaIzin('order.hapus');
    $bisaDihapus = $bolehHapus ? $pesanan->filter(fn ($o) => $o->bolehDihapus())->pluck('id')->values() : collect();

    // Tautan yang mempertahankan penyaring lain saat satu penyaring diganti.
    $tautan = fn (array $ubah) => route('panel.pesanan.index', array_filter(array_merge([
        'jalur' => $jalur, 'status' => $status, 'q' => request('q'),
        'dari' => $dari?->toDateString(), 'sampai' => $sampai?->toDateString(),
    ], $ubah), fn ($v) => $v !== null && $v !== ''));

    /*
     * Ringkasan isi satu pesanan untuk kolom Jenis: barang kustom disebut
     * namanya dan jumlah potongnya, barang stok cukup jumlah barangnya.
     */
    $jenis = function (\App\Models\Order $o): array {
        $kustom = $o->items->filter(fn ($i) => $i->kustom());

        if ($kustom->isNotEmpty()) {
            return ['kaos', $kustom->first()->nama_produk, $kustom->sum('qty').' pcs'];
        }

        return ['stok', 'Produk toko', $o->items->sum('qty').' item'];
    };
@endphp
@section('judul', 'Pesanan'.($penyaring ? ': '.implode(', ', $penyaring) : ''))

@section('isi')

<x-kop judul="Pesanan" ikon="keranjang" warna="biru"
       catatan="Kelola semua pesanan dari website, kustom/konveksi, dan stok toko. Terbaru di atas.">
    @if ($bolehKelola)
        <a href="{{ route('panel.pesanan.baru') }}" class="tombol tombol-navy">
            <x-ikon nama="tambah" ukuran="size-4" />
            <span class="sr-only sm:not-sr-only">Pesanan baru</span>
        </a>
    @endif
</x-kop>

{{-- Kartu ringkasan per status, mengikuti tab jalur yang sedang dibuka.
     Mengekliknya sama dengan memilih status itu di penyaring. --}}
<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
    @foreach ([
        ['', 'Semua pesanan', 'biru', 'keranjang', 'semua'],
        [OrderStatus::MENUNGGU_PEMBAYARAN->value, 'Menunggu pembayaran', 'kuning', 'jam-pasir', OrderStatus::MENUNGGU_PEMBAYARAN->value],
        [OrderStatus::DIPROSES->value, 'Diproses', 'jingga', 'pengaturan', OrderStatus::DIPROSES->value],
        [OrderStatus::SELESAI->value, 'Selesai', 'hijau', 'centang', OrderStatus::SELESAI->value],
        [OrderStatus::KADALUARSA->value, 'Kedaluwarsa', 'merah', 'silang', OrderStatus::KADALUARSA->value],
    ] as [$nilai, $label, $warna, $ikon, $kunci])
        <x-kartu-angka :warna="$warna" :ikon="$ikon" :label="$label" :angka="$ringkasan[$kunci]"
                       :href="$tautan(['status' => $nilai])" :aktif="$status === $nilai"
                       class="{{ $loop->first ? 'col-span-2 sm:col-span-1' : '' }}" />
    @endforeach
</div>

{{-- Kustom dan stok toko dipisah karena alurnya berbeda (KEP-027). Ini tab,
     bukan sekadar filter, supaya perbedaannya terlihat sebelum dipilih. --}}
<nav class="segmen mt-6" aria-label="Jenis pesanan">
    @foreach ([['', 'Semua', 'Semua', 'keranjang'], ['kustom', 'Kustom / konveksi', 'Kustom', 'kaos'], ['stok', 'Stok toko', 'Stok toko', 'stok']] as [$nilai, $label, $labelPendek, $ikon])
        @php $aktifJalur = $jalur === $nilai; @endphp
        <a href="{{ $tautan(['jalur' => $nilai]) }}" @if ($aktifJalur) aria-current="page" @endif
           class="segmen-item segmen-pil {{ $aktifJalur ? 'segmen-item-aktif' : '' }}">
            <x-ikon :nama="$ikon" ukuran="size-4" />
            <span class="sm:hidden">{{ $labelPendek }}</span><span class="hidden sm:inline">{{ $label }}</span>
            <span class="tabular">({{ $jumlahJalur[$nilai] ?? 0 }})</span>
        </a>
    @endforeach
</nav>

<div class="kartu mt-4" x-data="{ dipilih: [], semua: @js($bisaDihapus) }">
    {{-- Penyaring: status, rentang tanggal, dan cari dalam satu baris. Pilihan
         status dan tanggal langsung menyaring; kotak cari menunggu Enter. --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line p-4 sm:p-5" data-saring-otomatis>
        <input type="hidden" name="jalur" value="{{ $jalur }}">

        <div class="w-full sm:w-auto">
            <label for="saring-status" class="label-form">Status</label>
            <select id="saring-status" name="status" class="kotak-input sm:w-52">
                @foreach ($saringan as $nilai => $label)
                    <option value="{{ $nilai }}" @selected($status === $nilai)>
                        {{ $label }}{{ isset($jumlahStatus[$nilai]) && $jumlahStatus[$nilai] > 0 ? ' ('.$jumlahStatus[$nilai].')' : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        <fieldset class="flex w-full min-w-0 items-end gap-2 sm:w-auto">
            <legend class="label-form">Rentang tanggal</legend>
            <label for="saring-dari" class="sr-only">Dari tanggal</label>
            <input type="date" id="saring-dari" name="dari" value="{{ $dari?->toDateString() }}" class="kotak-input min-w-0 flex-1 sm:w-40">
            <span class="pb-2.5 text-muted" aria-hidden="true">&ndash;</span>
            <label for="saring-sampai" class="sr-only">Sampai tanggal</label>
            <input type="date" id="saring-sampai" name="sampai" value="{{ $sampai?->toDateString() }}" class="kotak-input min-w-0 flex-1 sm:w-40">
        </fieldset>

        <div class="relative min-w-0 flex-1 basis-56">
            <label for="cari-pesanan" class="sr-only">Cari pesanan</label>
            <x-ikon nama="cari" ukuran="size-4"
                    class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-navy-300" />
            <input type="search" id="cari-pesanan" name="q" value="{{ request('q') }}"
                   placeholder="Kode pesanan, nama, atau HP&hellip;" class="kotak-input pl-9">
        </div>

        <div class="flex gap-2">
            <button type="submit" class="tombol tombol-navy tombol-sm">Saring</button>
            @if ($adaSaringan)
                <a href="{{ route('panel.pesanan.index', array_filter(['jalur' => $jalur])) }}" class="tombol tombol-garis tombol-sm">
                    <x-ikon nama="muat-ulang" ukuran="size-4" /> Reset
                </a>
            @endif
        </div>
    </form>

    {{-- Hapus riwayat massal (KEP-119). Kotak centang hanya ada di pesanan
         yang memang boleh dihapus; server tetap memeriksanya satu per satu. --}}
    @if ($bisaDihapus->isNotEmpty())
        <form id="form-hapus-massal" method="POST" action="{{ route('panel.pesanan.hapus-massal') }}"
              class="flex flex-wrap items-center gap-3 border-b border-line px-4 py-3 sm:px-5"
              data-konfirmasi="Hapus riwayat pesanan?"
              data-konfirmasi-pesan="Pesanan yang dipilih akan dihapus permanen beserta isinya, dan tidak bisa dikembalikan."
              data-konfirmasi-tombol="Hapus" data-konfirmasi-bahaya>
            @csrf
            <label class="flex items-center gap-2 text-sm font-medium">
                <input type="checkbox" class="kotak-centang"
                       :checked="dipilih.length > 0 && dipilih.length === semua.length"
                       @change="dipilih = $event.target.checked ? [...semua] : []">
                Pilih semua yang bisa dihapus
            </label>
            <span class="text-sm text-muted"><span class="tabular" x-text="dipilih.length">0</span> pesanan dipilih</span>
            <button type="submit" class="tombol tombol-sm border-merah-100 bg-merah-50 text-merah-tua hover:bg-merah-100"
                    :disabled="dipilih.length === 0">
                <x-ikon nama="hapus" ukuran="size-4" /> Hapus riwayat
            </button>
            <p class="flex items-center gap-1.5 text-xs text-muted sm:ml-auto">
                <x-ikon nama="bantuan" ukuran="size-4" />
                Hanya pesanan kedaluwarsa atau dibatalkan yang belum pernah dibayar.
            </p>
        </form>
    @endif

    @if ($status === 'tindakan')
        <p class="border-b border-line bg-kuning-50 px-5 py-3 text-sm leading-relaxed">
            Yang menunggu Anda, bukan yang menunggu pelanggan: bukti transfer yang belum
            diperiksa, pengajuan pembatalan yang belum diputuskan, dan barang yang sudah siap
            tetapi belum diserahkan.
        </p>
    @endif

    @if ($pesanan->isEmpty())
        <div class="p-8 text-center">
            <x-ikon nama="keranjang" ukuran="size-9" class="mx-auto text-navy-300" />
            <p class="mt-3 text-lg font-bold">Tidak ada pesanan di sini</p>
            <p class="mx-auto mt-2 max-w-sm text-sm text-muted">
                {{ $adaSaringan ? 'Coba kata kunci atau tanggal lain, atau tekan Reset.' : 'Pesanan yang masuk lewat web akan muncul di daftar ini.' }}
            </p>
        </div>
    @else
        {{-- Kartu di layar sempit, tabel di layar lebar. Admin memeriksa pesanan
             dari HP, dan tabel delapan kolom yang digeser membuat itu menyiksa. --}}
        <ul class="divide-y divide-line lg:hidden">
            @foreach ($pesanan as $o)
                @php [$ikonJenis, $namaJenis, $jumlahJenis] = $jenis($o); @endphp
                <li class="relative">
                    <a href="{{ route('panel.pesanan.show', $o) }}" class="block p-4 hover:bg-navy-50">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="tabular font-semibold text-navy-700">{{ $o->kode }}</span>
                            <span class="chip {{ $o->status->warna() }}">
                                <x-ikon :nama="$o->status->ikon()" ukuran="size-3.5" />
                                {{ $o->status->label() }}
                            </span>
                        </div>
                        <p class="mt-2 font-semibold">{{ $o->customer->nama }}</p>
                        <p class="mt-0.5 flex items-center gap-1.5 text-sm text-muted">
                            <x-ikon nama="{{ $ikonJenis }}" ukuran="size-4" />
                            {{ $namaJenis }} <span class="tabular">({{ $jumlahJenis }})</span>
                        </p>
                        @if ($o->punyaKustom() || $o->dikirim())
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @include('panel.pesanan.penanda-jalur', ['o' => $o])
                            </div>
                        @endif
                        <div class="mt-3 flex flex-wrap items-baseline justify-between gap-2 border-t border-line pt-3">
                            <span class="tabular text-sm text-muted">{{ $o->created_at->translatedFormat('j M Y, H:i') }}</span>
                            <span class="text-right">
                                <span class="tabular font-semibold">{{ rupiah($o->total) }}</span>
                                <span class="tabular block text-sm {{ $o->sisa_tagihan > 0 ? 'text-merah' : 'text-ok' }}">
                                    sisa {{ rupiah($o->sisa_tagihan) }}
                                </span>
                            </span>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        {{-- Tanpa pembungkus yang bisa digulir: di bawah 64rem daftarnya
             berganti jadi kartu (KEP-101). --}}
        <table class="tabel tabel-rapat hidden lg:table">
            <thead>
                <tr>
                    @if ($bisaDihapus->isNotEmpty())
                        <th class="w-10"><span class="sr-only">Pilih</span></th>
                    @endif
                    <th>Kode pesanan</th>
                    <th>Pelanggan</th>
                    <th>Jenis</th>
                    <th>Status</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Sisa pembayaran</th>
                    <th>Tanggal</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pesanan as $o)
                    @php
                        [$ikonJenis, $namaJenis, $jumlahJenis] = $jenis($o);
                        $wa = 'https://wa.me/'.nomor_wa($o->customer->no_hp);
                    @endphp
                    {{-- Seluruh baris jadi sasaran klik lewat data-tautan (app.js);
                         tautan, tombol, dan menu aksi di dalamnya tetap bekerja sendiri. --}}
                    <tr class="hover:bg-navy-50" data-tautan="{{ route('panel.pesanan.show', $o) }}"
                        @if ($bisaDihapus->contains($o->id)) :class="dipilih.includes({{ $o->id }}) && 'bg-merah-50'" @endif>
                        @if ($bisaDihapus->isNotEmpty())
                            <td>
                                @if ($bisaDihapus->contains($o->id))
                                    <label class="sr-only" for="hapus-{{ $o->id }}">Pilih {{ $o->kode }} untuk dihapus</label>
                                    <input type="checkbox" id="hapus-{{ $o->id }}" name="pesanan[]" value="{{ $o->id }}"
                                           form="form-hapus-massal" x-model.number="dipilih" class="kotak-centang">
                                @endif
                            </td>
                        @endif
                        <td>
                            <a href="{{ route('panel.pesanan.show', $o) }}" class="tabular font-bold whitespace-nowrap text-navy-700">{{ $o->kode }}</a>
                            @if ($o->kode_ambil)
                                <div class="tabular text-xs text-muted">Ambil: {{ $o->kode_ambil }}</div>
                            @endif
                            @if ($o->punyaKustom() || $o->dikirim())
                                <div class="mt-1 flex flex-wrap gap-1.5">
                                    @include('panel.pesanan.penanda-jalur', ['o' => $o])
                                </div>
                            @endif
                        </td>
                        <td>
                            <span class="font-semibold">{{ $o->customer->nama }}</span>
                            <a href="{{ $wa }}" target="_blank" rel="noopener"
                               class="tabular mt-0.5 flex items-center gap-1 text-xs text-muted hover:text-ok"
                               title="Kirim WhatsApp ke {{ $o->customer->nama }}">
                                <x-ikon nama="whatsapp" ukuran="size-3.5" class="text-ok" />
                                {{ $o->customer->no_hp }}
                            </a>
                        </td>
                        <td class="min-w-32">
                            <span class="flex items-start gap-2">
                                <x-ikon :nama="$ikonJenis" ukuran="size-4" class="mt-0.5 text-navy-400" />
                                <span>{{ $namaJenis }}<span class="tabular block text-xs text-muted">({{ $jumlahJenis }})</span></span>
                            </span>
                        </td>
                        <td>
                            <span class="chip {{ $o->status->warna() }}">
                                <x-ikon :nama="$o->status->ikon()" ukuran="size-3.5" />
                                {{ $o->status->label() }}
                            </span>
                        </td>
                        <td class="tabular text-right whitespace-nowrap">{{ rupiah($o->total) }}</td>
                        <td class="tabular text-right font-semibold whitespace-nowrap {{ $o->sisa_tagihan > 0 ? 'text-merah' : 'text-ok' }}">
                            {{ rupiah($o->sisa_tagihan) }}
                        </td>
                        <td class="tabular whitespace-nowrap text-muted">
                            {{ $o->created_at->translatedFormat('j M Y') }}
                            <span class="block text-xs">{{ $o->created_at->format('H:i') }}</span>
                        </td>
                        <td>
                            <div class="flex items-center justify-center gap-1">
                                <a href="{{ route('panel.pesanan.show', $o) }}" class="tombol-ikon" title="Lihat pesanan {{ $o->kode }}">
                                    <x-ikon nama="mata" ukuran="size-4" />
                                    <span class="sr-only">Lihat pesanan {{ $o->kode }}</span>
                                </a>
                                @if ($bolehHapus && $o->bolehDihapus())
                                    <form method="POST" action="{{ route('panel.pesanan.hapus', $o) }}"
                                          data-konfirmasi="Hapus pesanan {{ $o->kode }}?"
                                          data-konfirmasi-pesan="Pesanan ini akan dihapus permanen beserta isinya, dan tidak bisa dikembalikan."
                                          data-konfirmasi-tombol="Hapus" data-konfirmasi-bahaya>
                                        @csrf @method('DELETE')
                                        <button type="submit" class="tombol-ikon tombol-ikon-bahaya" title="Hapus pesanan {{ $o->kode }}">
                                            <x-ikon nama="hapus" ukuran="size-4" />
                                            <span class="sr-only">Hapus pesanan {{ $o->kode }}</span>
                                        </button>
                                    </form>
                                @endif
                                <details class="relative" x-data @click.outside="$el.removeAttribute('open')">
                                    <summary class="tombol-ikon" title="Aksi lain">
                                        <x-ikon nama="titik-tiga" ukuran="size-4" />
                                        <span class="sr-only">Aksi lain untuk {{ $o->kode }}</span>
                                    </summary>
                                    <div class="menu-tarik menu-aksi-isi right-0 w-56">
                                        <a href="{{ route('panel.pesanan.show', $o) }}"><x-ikon nama="mata" ukuran="size-4" class="text-navy-400" /> Lihat detail</a>
                                        <a href="{{ route('panel.pesanan.invoice', $o) }}" target="_blank"><x-ikon nama="cetak" ukuran="size-4" class="text-navy-400" /> Cetak invoice</a>
                                        <a href="{{ $wa }}" target="_blank" rel="noopener"><x-ikon nama="whatsapp" ukuran="size-4" class="text-ok" /> Kirim WhatsApp</a>
                                        @if ($o->punyaKustom())
                                            <a href="{{ route('panel.pesanan.worksheet', $o) }}" target="_blank"><x-ikon nama="qr" ukuran="size-4" class="text-navy-400" /> Lembar kerja</a>
                                        @endif
                                        @if ($bolehSerah && $o->status === OrderStatus::SIAP_DIAMBIL && ! $o->dikirim())
                                            <a href="{{ route('panel.serah-terima', ['kode' => $o->kode_ambil ?? $o->kode]) }}"><x-ikon nama="serah-terima" ukuran="size-4" class="text-navy-400" /> Serah terima</a>
                                        @endif
                                    </div>
                                </details>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @include('panel.komponen.halaman', ['daftar' => $pesanan, 'benda' => 'pesanan', 'tampil' => [10, 20, 50]])
    @endif
</div>
@endsection
