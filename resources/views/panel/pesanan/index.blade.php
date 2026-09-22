@extends('layouts.panel')
@section('judul', 'Pesanan')

@section('isi')
@php use App\Enums\OrderStatus; @endphp

<x-kop judul="Pesanan"
       catatan="Pesanan dari web maupun yang diketik admin untuk pembeli di toko, terbaru di atas.">
    {{-- Pencarian ikut menempel di kepala halaman: ia dipakai jauh lebih sering
         daripada penyaring status, dan di HP tetap terjangkau jempol. --}}
    <form method="GET" class="relative">
        <input type="hidden" name="jalur" value="{{ $jalur }}">
        <input type="hidden" name="status" value="{{ $status }}">
        <label for="cari-pesanan" class="sr-only">Cari pesanan</label>
        <x-ikon nama="cari" ukuran="size-4"
                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-navy-300" />
        <input type="search" id="cari-pesanan" name="q" value="{{ request('q') }}"
               placeholder="Kode, nama, HP&hellip;" class="kotak-input w-44 pl-9 sm:w-64">
    </form>

    @if (auth('web')->user()->punyaIzin('order.kelola'))
        <a href="{{ route('panel.pesanan.baru') }}" class="tombol tombol-navy tombol-sm">
            <x-ikon nama="tambah" ukuran="size-4" />
            <span class="hidden sm:inline">Pesanan baru</span>
        </a>
    @endif
</x-kop>

{{-- Kustom dan stok toko dipisah karena alurnya berbeda (KEP-027). Ini tab,
     bukan sekadar filter, supaya perbedaannya terlihat sebelum dipilih. --}}
<div class="mt-2 flex gap-1 border-b border-line" role="tablist">
    @foreach ([['', 'Semua'], ['kustom', 'Kustom / konveksi'], ['stok', 'Stok toko']] as [$nilai, $label])
        @php $aktifJalur = $jalur === $nilai; @endphp
        <a href="{{ route('panel.pesanan.index', array_filter(['jalur' => $nilai, 'status' => $status, 'q' => request('q')])) }}"
           role="tab" aria-selected="{{ $aktifJalur ? 'true' : 'false' }}"
           class="-mb-px px-4 py-2.5 text-sm font-semibold transition-colors
                  {{ $aktifJalur ? 'garis-aktif text-navy' : 'text-muted hover:text-ink' }}">
            {{ $label }}
            @if (($jumlahJalur[$nilai] ?? 0) > 0)
                <span class="tabular font-normal text-muted">{{ $jumlahJalur[$nilai] }}</span>
            @endif
        </a>
    @endforeach
</div>

@php
    $saringan = [
        '' => 'Semua',
        'aktif' => 'Masih berjalan',
        OrderStatus::MENUNGGU_PEMBAYARAN->value => 'Menunggu pembayaran',
        OrderStatus::DIPROSES->value => 'Diproses',
        OrderStatus::MENUNGGU_PELUNASAN->value => 'Menunggu pelunasan',
        OrderStatus::SIAP_DIAMBIL->value => 'Siap diambil',
        OrderStatus::DIKIRIM->value => 'Dalam pengiriman',
    ];
@endphp

{{-- Penyaring status menggeser ke samping di layar sempit, tidak melipat jadi
     empat baris yang mendorong datanya keluar layar. --}}
<div class="segmen mt-4">
    @foreach ($saringan as $nilai => $label)
        {{-- Status yang kosong di tab ini disembunyikan, kecuali yang sedang dibuka. --}}
        @continue($nilai !== '' && $status !== $nilai && ($jumlahStatus[$nilai] ?? 0) === 0)

        <a href="{{ route('panel.pesanan.index', array_filter(['jalur' => $jalur, 'status' => $nilai, 'q' => request('q')])) }}"
           class="segmen-item {{ $status === $nilai ? 'segmen-item-aktif' : '' }}">
            {{ $label }}
            @if (isset($jumlahStatus[$nilai]) && $jumlahStatus[$nilai] > 0)
                <span class="segmen-jumlah">{{ $jumlahStatus[$nilai] }}</span>
            @endif
        </a>
    @endforeach
</div>

@if (request('q'))
    <p class="mt-3 flex flex-wrap items-center gap-2 text-sm text-muted">
        Hasil pencarian <strong class="text-ink">{{ request('q') }}</strong>
        <a href="{{ route('panel.pesanan.index', array_filter(['jalur' => $jalur, 'status' => $status])) }}"
           class="segmen-item">
            <x-ikon nama="silang" ukuran="size-3.5" />
            Hapus pencarian
        </a>
    </p>
@endif

@if ($pesanan->isEmpty())
    <div class="kartu mt-6 p-8 text-center">
        <x-ikon nama="pesanan" ukuran="size-9" class="mx-auto text-navy-300" />
        <p class="mt-3 text-lg font-bold">Tidak ada pesanan di sini</p>
        <p class="mx-auto mt-2 max-w-sm text-sm text-muted">
            {{ request('q') ? 'Coba kata kunci lain, atau hapus filter status.' : 'Pesanan yang masuk lewat web akan muncul di daftar ini.' }}
        </p>
    </div>
@else
    {{-- Kartu di layar sempit, tabel di layar lebar. Admin memeriksa pesanan
         dari HP, dan tabel enam kolom yang digeser membuat itu menyiksa. --}}
    <div class="mt-5 space-y-2.5 md:hidden">
        @foreach ($pesanan as $o)
            <a href="{{ route('panel.pesanan.show', $o) }}" class="kartu kartu-tautan block p-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="tabular text-sm font-semibold">{{ $o->kode }}</span>
                    <span class="chip {{ $o->status->warna() }}">
                        <x-ikon :nama="$o->status->ikon()" ukuran="size-3.5" />
                        {{ $o->status->label() }}
                    </span>
                </div>

                <p class="mt-2 font-medium">{{ $o->customer->nama }}</p>
                <p class="tabular text-sm text-muted">{{ $o->customer->no_hp }}</p>

                @if ($o->punyaKustom() || $o->dikirim())
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @include('panel.pesanan.penanda-jalur', ['o' => $o])
                    </div>
                @endif

                <div class="mt-3 flex flex-wrap items-baseline justify-between gap-2 border-t border-line pt-3">
                    <span class="tabular text-sm text-muted">
                        {{ $o->created_at->translatedFormat('j M, H:i') }}
                        @if ($o->kode_ambil)
                            &middot; ambil {{ $o->kode_ambil }}
                        @endif
                    </span>
                    <span class="text-right">
                        <span class="tabular font-semibold">{{ rupiah($o->total) }}</span>
                        @if ($o->sisa_tagihan > 0)
                            <span class="tabular block text-sm text-merah">
                                sisa {{ rupiah($o->sisa_tagihan) }}
                            </span>
                        @endif
                    </span>
                </div>
            </a>
        @endforeach
    </div>

    {{-- Tanpa pembungkus yang bisa digulir: lihat .tabel-lekat. Di bawah 48rem
         daftarnya memang sudah berganti jadi kartu, jadi tidak ada yang perlu
         digeser ke samping. --}}
    <div class="kartu mt-5 hidden md:block">
        <table class="tabel tabel-lekat">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Pelanggan</th>
                    <th>Status</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Sisa</th>
                    <th>Masuk</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pesanan as $o)
                    {{-- Seluruh baris jadi sasaran klik lewat tautan rentang penuh,
                         bukan hanya kodenya: memburu tautan selebar enam karakter
                         adalah pekerjaan tambahan yang dilakukan ratusan kali sehari. --}}
                    <tr class="group relative hover:bg-navy-50">
                        <td>
                            <a href="{{ route('panel.pesanan.show', $o) }}"
                               class="tabular font-semibold text-navy-700 before:absolute before:inset-0 before:content-['']">
                                {{ $o->kode }}
                            </a>
                            @if ($o->kode_ambil)
                                <div class="text-xs text-muted tabular">Ambil: {{ $o->kode_ambil }}</div>
                            @endif
                            @if ($o->punyaKustom() || $o->dikirim())
                                <div class="mt-1 flex flex-wrap gap-1.5">
                                    @include('panel.pesanan.penanda-jalur', ['o' => $o])
                                </div>
                            @endif
                        </td>
                        <td>
                            {{ $o->customer->nama }}
                            <div class="text-xs text-muted">{{ $o->customer->no_hp }}</div>
                        </td>
                        <td>
                            <span class="chip {{ $o->status->warna() }}">
                                <x-ikon :nama="$o->status->ikon()" ukuran="size-3.5" />
                                {{ $o->status->label() }}
                            </span>
                        </td>
                        <td class="text-right tabular">{{ rupiah($o->total) }}</td>
                        <td class="text-right tabular {{ $o->sisa_tagihan > 0 ? 'font-semibold text-merah' : 'text-muted' }}">
                            {{ $o->sisa_tagihan > 0 ? rupiah($o->sisa_tagihan) : 'Lunas' }}
                        </td>
                        <td class="text-muted tabular whitespace-nowrap">
                            {{ $o->created_at->translatedFormat('j M, H:i') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $pesanan->links() }}</div>
@endif
@endsection
