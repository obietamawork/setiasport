@extends('layouts.publik')
@section('judul', 'Data pemesan')

@section('isi')
<div class="mx-auto max-w-4xl px-5 py-10">
    <h1 class="t-wide text-3xl font-extrabold">Data pemesan</h1>
    <p class="kolom-baca mt-3 text-muted">
        Kami tidak meminta alamat pengiriman — pesanan diambil sendiri di toko.
        Yang kami butuhkan hanya cara menghubungi Anda saat pesanan siap.
    </p>

    <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
        <form method="POST" action="{{ route('checkout.proses') }}" class="space-y-4 border border-line bg-field p-6">
            @csrf

            <div>
                <label for="nama" class="label-form">Nama pemesan</label>
                <input type="text" id="nama" name="nama" value="{{ old('nama') }}"
                       class="kotak-input" required autofocus autocomplete="name">
            </div>

            <div>
                <label for="email" class="label-form">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}"
                       class="kotak-input" required autocomplete="email">
                <p class="mt-1 text-xs leading-relaxed text-muted">
                    Ke sinilah tautan pesanan dikirim. Tautan itu satu-satunya cara membuka
                    pesanan Anda tanpa membuat akun, dan boleh dibagikan ke anggota tim.
                </p>
            </div>

            <div>
                <label for="no_hp" class="label-form">Nomor HP (WhatsApp)</label>
                <input type="tel" id="no_hp" name="no_hp" value="{{ old('no_hp') }}"
                       class="kotak-input tabular" required autocomplete="tel">
                <p class="mt-1 text-xs text-muted">Dipakai kalau kami perlu menghubungi Anda langsung.</p>
            </div>

            <div>
                <label for="catatan" class="label-form">Catatan untuk toko</label>
                <textarea id="catatan" name="catatan" rows="3" class="kotak-input">{{ old('catatan') }}</textarea>
            </div>

            <label class="flex items-start gap-2 border-t border-line pt-4 text-sm">
                <input type="checkbox" name="setuju" value="1" class="mt-0.5 size-4 accent-court" @checked(old('setuju'))>
                <span>
                    Saya paham pesanan ini <strong>diambil sendiri di toko</strong> dan pembayaran
                    perlu diselesaikan dalam {{ setting('batas_bayar_jam', 24) }} jam sebelum
                    pesanan otomatis dibatalkan.
                </span>
            </label>

            <button type="submit" class="tombol tombol-utama w-full">Buat pesanan</button>
        </form>

        <aside class="lg:sticky lg:top-6 lg:self-start">
            <div class="border border-line bg-field">
                <h2 class="border-b border-line px-5 py-3 font-semibold">Ringkasan</h2>
                <ul class="divide-y divide-line text-sm">
                    @foreach ($isi as $b)
                        <li class="flex justify-between gap-3 px-5 py-3">
                            <span>
                                {{ $b['produk']->nama }}
                                @if ($b['varian']?->label)
                                    <span class="text-muted">{{ $b['varian']->label }}</span>
                                @endif
                                <span class="text-muted tabular">&times; {{ $b['qty'] }}</span>
                            </span>
                            <span class="tabular whitespace-nowrap">{{ rupiah($b['rincian']?->subtotal ?? 0) }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="border-t border-line px-5 py-4">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="font-semibold">Total</span>
                        <span class="numeral text-2xl text-signal">{{ rupiah($total) }}</span>
                    </div>
                    <p class="mt-2 text-xs text-muted">Tanpa ongkos kirim.</p>
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection
