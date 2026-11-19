{{-- Bagian dari detail pesanan panel (KEP-124). Isinya dipindah dari halaman lama apa adanya. --}}
@php
    use App\Enums\MetodeAmbil;
    use App\Enums\OrderStatus;
@endphp
{{-- Pengambilan vs pengiriman (KEP-026). Disetel di sini, bukan di layar
     kasir: kesepakatan mengirim terjadi lewat WhatsApp jauh sebelum
     barangnya siap, bukan di depan meja kasir. --}}
<section id="pengambilan" class="kartu-bayar scroll-mt-32">
    <h2 class="judul-kartu"><x-ikon nama="kirim" class="text-navy-400" /> Pengambilan</h2>

    <div class="mt-4 space-y-4">
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
                    <dd class="leading-relaxed">
                        {{ $pesanan->alamat_kirim }}
                        @if ($pesanan->tautanLokasiAman())
                            <a href="{{ $pesanan->tautan_lokasi }}" target="_blank" rel="noopener noreferrer"
                               class="tautan ml-1 whitespace-nowrap">Buka di peta</a>
                        @endif
                    </dd>
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
                        @if ($pesanan->tautanLokasiAman())
                            <a href="{{ $pesanan->tautan_lokasi }}" target="_blank" rel="noopener noreferrer"
                               class="tautan ml-1 whitespace-nowrap">Buka di peta</a>
                        @endif
                    </p>

                    @if ($pesanan->bolehDikirim())
                        <form method="POST" action="{{ route('panel.pesanan.pengiriman.kirim', $pesanan) }}"
                              class="mt-4 grid gap-4 bg-navy-50 p-4 sm:grid-cols-2">
                            @csrf
                            <div>
                                <label for="jasa_kirim" class="label-form">Jasa kirim</label>
                                <select id="jasa_kirim" name="jasa_kirim" class="kotak-input">
                                    {{-- Kurir yang dipilih pelanggan saat checkout dipilih lebih
                                         dulu (KEP-098), supaya resi JNE tidak tercatat sebagai J&T. --}}
                                    @php
                                        // "J&T EZ" dicocokkan lewat nama kurirnya saja: "jt" di
                                        // depan "jtexpress", "jne" di depan "jne".
                                        $bersihkan = fn ($t) => preg_replace('/[^a-z]/', '', mb_strtolower((string) $t));
                                        $kurirPelanggan = $bersihkan(strtok((string) $pesanan->layanan_kirim, ' '));
                                        $jasaPelanggan = $kurirPelanggan === '' ? null : collect(daftar_setting('jasa_kirim_daftar'))
                                            ->first(fn ($j) => str_starts_with($bersihkan($j), $kurirPelanggan));
                                    @endphp
                                    @foreach (daftar_setting('jasa_kirim_daftar') as $jasa)
                                        <option value="{{ $jasa }}" @selected($jasa === $jasaPelanggan)>{{ $jasa }}</option>
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
                        <label for="tautan_lokasi" class="label-form">Tautan lokasi Google Maps (opsional)</label>
                        <input type="url" id="tautan_lokasi" name="tautan_lokasi" class="kotak-input"
                               placeholder="https://maps.app.goo.gl/..."
                               value="{{ old('tautan_lokasi', $pesanan->tautan_lokasi) }}">
                        <p class="mt-1 text-xs leading-relaxed text-muted">
                            Kalau pelanggan membagikan titik lokasi lewat WhatsApp, tempel tautannya di sini.
                        </p>
                    </div>
                    <div>
                        <label for="ongkir" class="label-form">Ongkos kirim (Rp)</label>
                        {{-- Tanpa step="1000" (KEP-099): tarif kurir sering Rp12.500,
                             dan browser menolaknya sebelum formulir terkirim. --}}
                        <input type="number" id="ongkir" name="ongkir" min="0"
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
