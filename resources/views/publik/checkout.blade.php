@extends('layouts.publik')
@section('judul', 'Data pemesan')
@section('subjudul', 'Data pemesan')

@section('isi')
<div class="mx-auto max-w-4xl px-5 py-10">
    <h1 class="text-3xl font-extrabold">Data pemesan</h1>
    <p class="kolom-baca mt-3 text-muted">
        Pesanan boleh diambil sendiri di toko atau dikirim ke alamat Anda. Yang kami
        butuhkan hanya cara menghubungi Anda saat pesanan siap.
    </p>

    <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]"
         x-data="pilihPengiriman({
             otomatis: {{ $ongkirOtomatis ? 'true' : 'false' }},
             metode: '{{ old('metode_ambil', 'AMBIL_TOKO') }}',
             area: '{{ old('area_tujuan') }}',
             tarif: '{{ old('tarif') }}',
             urlArea: '{{ route('pengiriman.area') }}',
             urlTarif: '{{ route('pengiriman.tarif') }}',
         })">
        {{-- Ringkasan tampil lebih dulu di HP (order-1) supaya pelanggan tahu
             persis angka yang disepakati sebelum mengisi formulir, bukan
             menemukannya terkubur di bawah tombol "Buat pesanan" setelah
             menggulir panjang. Di layar lebar urutan baliknya tidak masalah
             karena ringkasan sudah terlihat di kolom kanan sepanjang waktu. --}}
        <form method="POST" action="{{ route('checkout.proses') }}"
              class="order-2 space-y-4 border border-line bg-field p-6 lg:order-1">
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

            {{-- Pilihan penyerahan (KEP-058). Ditaruh sebelum cara pembayaran
                 karena ongkirnya ikut mengubah angka yang dibayar. --}}
            <fieldset class="border-t border-line pt-4">
                <legend class="label-form">Barangnya mau diapakan?</legend>
                <div class="space-y-2">
                    <label class="flex items-start gap-2 text-sm">
                        <input type="radio" name="metode_ambil" value="AMBIL_TOKO" x-model="metode"
                               class="mt-0.5 size-4 accent-navy">
                        <span>
                            <strong>Saya ambil sendiri di toko</strong>
                            <span class="block text-muted">{{ setting('toko_alamat', 'Alamat toko') }}. Tanpa ongkos kirim.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <input type="radio" name="metode_ambil" value="KIRIM" x-model="metode"
                               class="mt-0.5 size-4 accent-navy">
                        <span>
                            <strong>Kirim ke alamat saya</strong>
                            <span class="block text-muted">
                                Paket berangkat setelah pesanan lunas dan barangnya selesai.
                            </span>
                        </span>
                    </label>
                </div>

                <div x-show="metode === 'KIRIM'" x-cloak class="mt-4 space-y-4 border-l-2 border-line-strong pl-4">
                    <div>
                        <label for="alamat_kirim" class="label-form">Alamat lengkap</label>
                        <textarea id="alamat_kirim" name="alamat_kirim" rows="3" maxlength="500"
                                  class="kotak-input leading-relaxed"
                                  placeholder="Nama penerima, jalan, RT/RW, kelurahan, kecamatan, kota, kode pos">{{ old('alamat_kirim') }}</textarea>
                        <p class="bantuan-form">Tulis selengkap mungkin; kurir membaca alamat ini apa adanya.</p>
                    </div>

                    <div>
                        <label for="tautan_lokasi" class="label-form">Tautan lokasi Google Maps (opsional)</label>
                        <input type="url" id="tautan_lokasi" name="tautan_lokasi" class="kotak-input"
                               placeholder="https://maps.app.goo.gl/..." value="{{ old('tautan_lokasi') }}">
                        <p class="bantuan-form">
                            Supaya kurir langsung menuju titik rumah Anda, bukan menebak dari tulisan
                            alamat saja. Buka Google Maps &rarr; cari lokasi Anda &rarr; <strong>Bagikan</strong>
                            &rarr; <strong>Salin tautan</strong>, lalu tempel di sini.
                        </p>
                    </div>

                    @if ($ongkirOtomatis)
                        <div>
                            <label for="cari_area" class="label-form">Kota atau kecamatan tujuan</label>
                            <input type="text" id="cari_area" class="kotak-input" autocomplete="off"
                                   placeholder="Ketik nama kecamatan, misalnya: Wonogiri"
                                   x-model="kata" @input.debounce.400ms="cariArea()">
                            <input type="hidden" name="area_tujuan" x-model="area">

                            <ul x-show="daftarArea.length" x-cloak class="mt-2 divide-y divide-line border border-line bg-white text-sm">
                                <template x-for="a in daftarArea" :key="a.kode">
                                    <li>
                                        <button type="button" class="w-full px-3 py-2 text-left hover:bg-navy-50"
                                                @click="pilihArea(a)" x-text="a.nama"></button>
                                    </li>
                                </template>
                            </ul>

                            <p x-show="area" x-cloak class="mt-2 text-sm">
                                Tujuan: <strong x-text="namaArea"></strong>
                                <button type="button" class="ml-1 underline decoration-line-strong underline-offset-4"
                                        @click="gantiArea()">ganti</button>
                            </p>
                        </div>

                        <div x-show="area" x-cloak>
                            <span class="label-form">Pilih pengiriman</span>

                            <p x-show="memuat" class="text-sm text-muted">Menghitung ongkos kirim&hellip;</p>

                            <div x-show="! memuat &amp;&amp; daftarTarif.length" class="space-y-2">
                                <template x-for="t in daftarTarif" :key="t.kode">
                                    <label class="flex items-start justify-between gap-3 border border-line bg-white px-3 py-2 text-sm">
                                        <span class="flex items-start gap-2">
                                            <input type="radio" name="tarif" :value="t.kode" x-model="tarif"
                                                   class="mt-0.5 size-4 accent-navy">
                                            <span x-text="t.label"></span>
                                        </span>
                                        <span class="tabular whitespace-nowrap"
                                              x-text="t.harga ? rupiah(t.harga) : 'Gratis'"></span>
                                    </label>
                                </template>
                            </div>

                            {{-- Penyedia ongkirnya sedang tidak menjawab. Pesanannya tetap
                                 boleh dibuat: ongkirnya dihitung admin, seperti sebelum
                                 hitungan otomatis ada. --}}
                            <p x-show="! memuat &amp;&amp; ! daftarTarif.length" x-cloak
                               class="border-l-2 border-kuning bg-kuning-50 p-3 text-sm leading-relaxed">
                                Ongkos kirim ke sana belum bisa dihitung otomatis sekarang. Pesanan tetap
                                bisa dibuat, dan kami kabari ongkirnya lewat email sebelum Anda membayar.
                            </p>
                        </div>
                    @else
                        <p class="border-l-2 border-kuning bg-kuning-50 p-3 text-sm leading-relaxed">
                            Ongkos kirimnya kami hitung dulu setelah paket ditimbang, lalu kami kabari
                            lewat email. Angkanya masuk ke tagihan pesanan ini, jadi tidak ada biaya
                            yang muncul di luar halaman ini.
                        </p>
                    @endif

                    @if ($gratisOngkirMulai > 0)
                        <p class="text-sm text-ok">
                            Belanja di atas {{ rupiah($gratisOngkirMulai) }} bebas ongkos kirim.
                        </p>
                    @endif
                </div>
            </fieldset>

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
                                <span class="tabular">{{ rupiah($dpMinimum) }}</span> sekarang,
                                @if ($campuran)
                                    sisanya dilunasi setelah seluruh pesanan siap, termasuk barang stok di dalamnya, karena diserahkan bersamaan dalam satu kali pengambilan, lewat transfer atau di kasir.
                                @else
                                    sisanya dilunasi setelah barang selesai dikerjakan, lewat transfer atau di kasir.
                                @endif
                                <span x-show="metode === 'KIRIM'" x-cloak class="block text-muted">
                                    Ongkos kirim ikut dilunasi di akhir, bukan sekarang.
                                </span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 text-sm">
                            <input type="radio" name="tipe_pembayaran" value="LUNAS" class="mt-0.5 size-4 accent-navy"
                                   @checked(old('tipe_pembayaran') === 'LUNAS')>
                            <span>
                                <strong>Bayar lunas</strong>
                                <span class="tabular" x-text="rupiah({{ $total + $biayaLayanan }} + ongkir)">{{ rupiah($total + $biayaLayanan) }}</span> sekarang.
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
                    Saya paham pembayaran perlu diselesaikan dalam
                    {{ setting('batas_bayar_jam', 24) }} jam sebelum pesanan otomatis dibatalkan,
                    <span x-show="metode === 'KIRIM'" x-cloak>dan paket baru berangkat setelah pesanan lunas.</span><span
                          x-show="metode !== 'KIRIM'">dan barangnya diambil sendiri di toko.</span>
                </span>
            </label>

            {{-- Di luar <label> centang di atas, supaya mengetuk tautannya tidak ikut
                 mencentang. Dibuka di tab baru: isian checkout tidak hilang. --}}
            <p class="text-xs leading-relaxed text-muted">
                Dengan membuat pesanan, Anda menyetujui
                <a href="{{ route('syarat-ketentuan') }}" target="_blank" rel="noopener" class="tautan">Syarat &amp; Ketentuan</a>
                dan
                <a href="{{ route('kebijakan-privasi') }}" target="_blank" rel="noopener" class="tautan">Kebijakan Privasi</a>
                kami.
            </p>

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

        <aside class="order-1 lg:order-2 lg:sticky lg:top-6 lg:self-start">
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
                            <span class="tabular whitespace-nowrap">
                                {{ rupiah(($b['rincian']?->subtotal ?? 0) - ($b['rincian']->potongan ?? 0)) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
                <div class="border-t border-line px-5 py-4">
                    {{-- Biaya layanan dan ongkir disebut terbuka di sini (KEP-048,
                         KEP-058). Angka yang baru muncul di halaman tagihan adalah
                         cara tercepat membuat pelanggan merasa ditambah-tambahi
                         diam-diam. --}}
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="text-muted">Subtotal</span>
                        <span class="tabular">{{ rupiah($total) }}</span>
                    </div>
                    @if ($biayaLayanan > 0)
                        <div class="mt-1 flex items-baseline justify-between gap-3 text-sm">
                            <span class="text-muted">Biaya layanan</span>
                            <span class="tabular">{{ rupiah($biayaLayanan) }}</span>
                        </div>
                    @endif
                    <div x-show="metode === 'KIRIM'" x-cloak
                         class="mt-1 flex items-baseline justify-between gap-3 text-sm">
                        <span class="text-muted">Ongkos kirim</span>
                        <span class="tabular" x-text="ongkir ? rupiah(ongkir) : 'menyusul'"></span>
                    </div>

                    <div class="mt-2 flex items-baseline justify-between gap-3">
                        <span class="font-semibold">Total</span>
                        <span class="numeral text-2xl text-merah"
                              x-text="rupiah({{ $total + $biayaLayanan }} + ongkir)">{{ rupiah($total + $biayaLayanan) }}</span>
                    </div>
                    <p class="mt-2 text-xs text-muted" x-show="metode !== 'KIRIM'">
                        Diambil di toko, tanpa ongkos kirim.
                    </p>
                    <p class="mt-2 text-xs leading-relaxed text-muted" x-show="metode === 'KIRIM' && ! ongkir" x-cloak>
                        Ongkos kirimnya ditambahkan ke tagihan ini setelah kami hitung, bukan ditagih terpisah.
                    </p>
                </div>
            </div>
        </aside>
    </div>
</div>

@push('skrip')
<script>
    /*
     * Pilihan pengiriman di checkout (KEP-058).
     *
     * Beratnya sengaja TIDAK dikirim dari sini: server menjumlahkannya sendiri
     * dari isi keranjang. Begitu juga harga ongkirnya, yang di sini cuma untuk
     * dilihat dan dihitung ulang saat pesanan disimpan (KEP-009).
     */
    function pilihPengiriman(cfg) {
        return {
            ...cfg,
            kata: '',
            namaArea: '',
            daftarArea: [],
            daftarTarif: [],
            memuat: false,

            get ongkir() {
                return this.daftarTarif.find(t => t.kode === this.tarif)?.harga ?? 0;
            },

            rupiah(n) {
                return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n || 0));
            },

            async cariArea() {
                if (this.kata.trim().length < 3) {
                    this.daftarArea = [];
                    return;
                }

                try {
                    const r = await fetch(this.urlArea + '?q=' + encodeURIComponent(this.kata));
                    this.daftarArea = (await r.json()).area ?? [];
                } catch (e) {
                    // Jaringan pelanggan putus. Dibiarkan kosong: formulirnya
                    // tetap bisa dikirim, ongkirnya menyusul dari admin.
                    this.daftarArea = [];
                }
            },

            pilihArea(a) {
                this.area = a.kode;
                this.namaArea = a.nama;
                this.daftarArea = [];
                this.kata = '';
                this.ambilTarif();
            },

            gantiArea() {
                this.area = '';
                this.namaArea = '';
                this.tarif = '';
                this.daftarTarif = [];
            },

            async ambilTarif() {
                if (! this.area) return;

                this.memuat = true;
                this.tarif = '';

                try {
                    const r = await fetch(this.urlTarif + '?area=' + encodeURIComponent(this.area));
                    this.daftarTarif = (await r.json()).tarif ?? [];
                } catch (e) {
                    this.daftarTarif = [];
                } finally {
                    this.memuat = false;
                }
            },

            init() {
                // Formulir yang dikembalikan karena ada isian salah tidak boleh
                // kehilangan pilihan kurir yang sudah susah payah dipilih.
                if (this.area) this.ambilTarif();
            },
        };
    }
</script>
@endpush
@endsection
