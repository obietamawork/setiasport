@extends('layouts.publik')
@section('judul', 'Data pemesan')
@section('subjudul', 'Data pemesan')

@section('isi')
<div class="mx-auto max-w-4xl px-5 py-10">
    <h1 class="text-3xl font-extrabold">Data pemesan</h1>
    <p class="kolom-baca mt-3 text-muted">
        Kami tidak meminta alamat: pesanan diambil sendiri di toko. Kalau Anda jauh dan
        ingin barang stok dikirim, kabari kami lewat {{ kanal_kontak() }} setelah memesan.
        Yang kami butuhkan hanya cara menghubungi Anda saat pesanan siap.
    </p>

    <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
        <form method="POST" action="{{ route('checkout.proses') }}" class="space-y-4 border border-line bg-field p-6">
            @csrf

            <div>
                <label for="nama" class="label-form">Nama pemesan</label>
                <input type="text" id="nama" name="nama" value="{{ old('nama', $pelanggan?->nama) }}"
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
                <input type="tel" id="no_hp" name="no_hp" value="{{ old('no_hp', $pelanggan?->no_hp) }}"
                       class="kotak-input tabular" required autocomplete="tel">
                <p class="mt-1 text-xs text-muted">Dipakai kalau kami perlu menghubungi Anda langsung.</p>
            </div>

            <div>
                <label for="catatan" class="label-form">Catatan untuk toko</label>
                <textarea id="catatan" name="catatan" rows="3" class="kotak-input">{{ old('catatan') }}</textarea>
            </div>

            @if ($bolehDp)
                <fieldset class="border-t border-line pt-4">
                    <legend class="label-form">Cara pembayaran</legend>
                    <div class="space-y-2">
                        <label class="flex items-start gap-2 text-sm">
                            <input type="radio" name="tipe_pembayaran" value="DP" class="mt-0.5 size-4 accent-navy"
                                   @checked(old('tipe_pembayaran', 'DP') === 'DP')>
                            <span>
                                <strong>Uang muka {{ $persenDp }}%</strong>
                                <span class="tabular">{{ rupiah((int) ceil($total * $persenDp / 100)) }}</span> sekarang,
                                sisanya dilunasi setelah barang selesai dikerjakan, lewat transfer atau di kasir.
                            </span>
                        </label>
                        <label class="flex items-start gap-2 text-sm">
                            <input type="radio" name="tipe_pembayaran" value="LUNAS" class="mt-0.5 size-4 accent-navy"
                                   @checked(old('tipe_pembayaran') === 'LUNAS')>
                            <span>
                                <strong>Bayar lunas</strong>
                                <span class="tabular">{{ rupiah($total) }}</span> sekarang.
                            </span>
                        </label>
                    </div>
                </fieldset>
            @else
                <input type="hidden" name="tipe_pembayaran" value="LUNAS">
            @endif

            @unless ($pelanggan)
                {{-- Akun sepenuhnya opsional. Ditawarkan di sini karena inilah
                     saat pelanggan paling mungkin mau, bukan karena diwajibkan. --}}
                <div class="border-t border-line pt-4" x-data="{ buat: {{ old('buat_akun') ? 'true' : 'false' }} }">
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" name="buat_akun" value="1" x-model="buat"
                               class="mt-0.5 size-4 accent-navy">
                        <span>
                            <strong>Buat akun sekalian</strong>
                            <span class="block text-muted">
                                Supaya semua pesanan Anda bisa dilihat dalam satu halaman
                                tanpa mencari email. Kami kirim tautan konfirmasi terpisah;
                                pesanan ini tetap berjalan normal sambil menunggu.
                            </span>
                        </span>
                    </label>

                    <div x-show="buat" x-cloak class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="password" class="label-form">Kata sandi</label>
                            <input type="password" id="password" name="password"
                                   class="kotak-input" autocomplete="new-password">
                            <p class="bantuan-form">Minimal 8 karakter.</p>
                        </div>
                        <div>
                            <label for="password_confirmation" class="label-form">Ulangi kata sandi</label>
                            <input type="password" id="password_confirmation" name="password_confirmation"
                                   class="kotak-input" autocomplete="new-password">
                        </div>
                    </div>
                </div>
            @endunless

            <label class="flex items-start gap-2 border-t border-line pt-4 text-sm">
                <input type="checkbox" name="setuju" value="1" class="mt-0.5 size-4 accent-navy" @checked(old('setuju'))>
                <span>
                    Saya paham pesanan ini <strong>diambil sendiri di toko</strong> dan pembayaran
                    perlu diselesaikan dalam {{ setting('batas_bayar_jam', 24) }} jam sebelum
                    pesanan otomatis dibatalkan.
                </span>
            </label>

            <button type="submit" class="tombol tombol-utama w-full">Buat pesanan</button>

            {{-- Bantuan diletakkan tepat di samping tombol terakhir, karena di
                 situlah keraguan muncul: ukuran, bahan, atau cara bayar. Tanpa
                 jalan bertanya, pelanggan yang ragu menutup halaman, dan
                 pesanannya hilang tanpa jejak. WhatsApp di sini BANTUAN, bukan
                 jalur pemesanan (KEP-040); pesanannya tetap dibuat di sini. --}}
            @if ($bantuanWa = tautan_wa('Halo '.setting('toko_nama', 'Setia Sport').', saya sedang mengisi pesanan di web dan mau bertanya.'))
                <div class="flex flex-wrap items-center justify-center gap-2 text-sm text-muted">
                    <span>Ada yang ingin ditanyakan dulu?</span>
                    <a href="{{ $bantuanWa }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 font-medium text-ink underline decoration-line-strong underline-offset-4 hover:text-navy">
                        <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2zm0 2a8 8 0 1 1-4.2 14.8l-.3-.2-2.5.7.7-2.4-.2-.3A8 8 0 0 1 12 4zm-3.3 4c-.2 0-.5.1-.7.4-.2.3-.8.8-.8 1.9s.8 2.2.9 2.4c.1.2 1.6 2.6 4 3.5 1.9.7 2.3.6 2.7.6.4 0 1.4-.6 1.6-1.1.2-.6.2-1 .1-1.1l-.6-.3-1.4-.7c-.2-.1-.4-.1-.5.1l-.7.9c-.1.2-.3.2-.5.1a6.5 6.5 0 0 1-1.9-1.2 7.3 7.3 0 0 1-1.3-1.7c-.1-.2 0-.4.1-.5l.4-.5.3-.5v-.5l-.7-1.6c-.2-.4-.4-.4-.5-.4h-.5z" />
                        </svg>
                        Tanya lewat WhatsApp
                    </a>
                </div>
            @endif
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
                    {{-- Biaya layanan disebut terbuka di sini (KEP-048). Angka yang
                         baru muncul di halaman tagihan adalah cara tercepat membuat
                         pelanggan merasa ditambah-tambahi diam-diam. --}}
                    @if ($biayaLayanan > 0)
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="text-muted">Subtotal</span>
                            <span class="tabular">{{ rupiah($total) }}</span>
                        </div>
                        <div class="mt-1 flex items-baseline justify-between gap-3 text-sm">
                            <span class="text-muted">Biaya layanan</span>
                            <span class="tabular">{{ rupiah($biayaLayanan) }}</span>
                        </div>
                    @endif

                    <div class="mt-2 flex items-baseline justify-between gap-3">
                        <span class="font-semibold">Total</span>
                        <span class="numeral text-2xl text-merah">{{ rupiah($total + $biayaLayanan) }}</span>
                    </div>
                    <p class="mt-2 text-xs text-muted">Diambil di toko, tanpa ongkos kirim.</p>
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection
