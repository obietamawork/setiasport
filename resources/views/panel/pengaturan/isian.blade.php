{{-- Satu isian pengaturan. Semua isian menunjuk ke formulir yang sama lewat
     atribut form, jadi boleh diletakkan di tab mana pun, dan kartu QRIS (yang
     punya formulir unggahnya sendiri) bisa berada di tengah-tengahnya. --}}
@php
    // Satuan di akhir label, misalnya "(hari)", pindah ke samping isian.
    preg_match('/^(.*?)\s*\(([^)]+)\)\s*$/', $s->label, $m);
    $label = $m[1] ?? $s->label;
    $satuan = $m[2] ?? null;
    $nama = 'setting['.$s->key.']';
    $id = 's-'.$s->key;
    $nilai = old('setting.'.$s->key, $s->value);
@endphp

{{-- biteship_area_nama tidak punya baris sendiri: nilainya disetor lewat
     input tersembunyi di dalam widget pencarian biteship_area_asal di bawah. --}}
@unless ($s->key === 'biteship_area_nama')
<div>
    @if ($s->tipe === 'bool')
        <input type="hidden" name="{{ $nama }}" value="0" form="form-pengaturan">
        <label for="{{ $id }}" class="flex cursor-pointer items-start gap-3">
            <input type="checkbox" id="{{ $id }}" name="{{ $nama }}" value="1" form="form-pengaturan"
                   class="peer sr-only" @checked((bool) $nilai)
                   @if ($s->key === 'cs_aktif') x-model="csAktif" @endif>
            <span class="relative mt-0.5 h-6 w-11 shrink-0 rounded-full bg-line-strong transition-colors
                         after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition-transform
                         peer-checked:bg-navy peer-checked:after:translate-x-5
                         peer-focus-visible:ring-2 peer-focus-visible:ring-navy-300"></span>
            <span>
                <span class="font-medium">{{ $label }}</span>
                @if ($s->deskripsi)
                    <span class="mt-0.5 block text-xs leading-relaxed text-muted">{{ $s->deskripsi }}</span>
                @endif
            </span>
        </label>
    @else
        <label for="{{ $id }}" class="label-form">
            {{ $label }}
            @if ($satuan && $s->tipe !== 'int')
                <span class="font-normal text-muted">({{ $satuan }})</span>
            @endif
        </label>

        @if ($s->tipe === 'markdown')
            <textarea id="{{ $id }}" name="{{ $nama }}" form="form-pengaturan" rows="18"
                      class="kotak-input font-mono text-[13px] leading-relaxed"
                      aria-describedby="{{ $id }}-format">{{ $nilai }}</textarea>
            <p id="{{ $id }}-format" class="mt-1 text-xs leading-relaxed text-muted">
                Format: baris kosong memisahkan paragraf, <code>## Judul</code> untuk subjudul,
                <code>- butir</code> untuk daftar, <code>**tebal**</code> untuk huruf tebal.
                Penanda seperti <code>{batas_bayar_jam}</code>, <code>{dp_minimum}</code>, <code>{whatsapp}</code>,
                dan <code>{toko}</code> otomatis diganti angka dan data dari pengaturan saat ditampilkan.
            </p>
        @elseif ($s->tipe === 'teks')
            <textarea id="{{ $id }}" name="{{ $nama }}" form="form-pengaturan"
                      rows="{{ $s->key === 'tentang_lengkap' ? 8 : (str_contains($s->key, 'jam_buka') ? 3 : 4) }}"
                      class="kotak-input leading-relaxed">{{ $nilai }}</textarea>
        @elseif ($s->tipe === 'int')
            <div class="flex items-center gap-2">
                <input type="number" id="{{ $id }}" name="{{ $nama }}" form="form-pengaturan" value="{{ $nilai }}"
                       min="0" class="kotak-input tabular max-w-32">
                @if ($satuan)
                    <span class="text-sm text-muted">{{ $satuan }}</span>
                @endif
            </div>
        @elseif ($s->key === 'biteship_area_asal')
            {{-- Kode area Biteship tidak bisa ditebak atau diketik dengan aman
                 (KEP-067): satu karakter salah membuat hitung ongkir diam-diam
                 tidak pernah menjawab. Dicari lewat API, bukan diketik manual. --}}
            <div x-data="{
                    cari: '',
                    hasil: [],
                    mencari: false,
                    kodeArea: @js($nilai),
                    namaArea: @js(old('setting.biteship_area_nama', $setting['biteship_area_nama']->value ?? '')),
                    async cariArea() {
                        if (this.cari.trim().length < 3) { this.hasil = []; return; }
                        this.mencari = true;
                        try {
                            const r = await fetch(@js(route('panel.pengaturan.biteship-area')) + '?q=' + encodeURIComponent(this.cari));
                            this.hasil = r.ok ? await r.json() : [];
                        } catch (e) {
                            this.hasil = [];
                        } finally {
                            this.mencari = false;
                        }
                    },
                    pilih(a) {
                        this.kodeArea = a.kode;
                        this.namaArea = a.nama;
                        this.hasil = [];
                        this.cari = '';
                        // Alpine mengubah properti, bukan atribut DOM, jadi
                        // penanda 'ada perubahan belum disimpan' di luar tidak
                        // ikut menyala kecuali event input disebarkan manual.
                        // getElementById dipakai, bukan $refs: nama ref yang
                        // sama dengan nama properti data ternyata membuat
                        // $refs kosong di sini.
                        this.$nextTick(() => document.getElementById({{ Js::from($id) }}).dispatchEvent(new Event('input', { bubbles: true })));
                    },
                 }">
                <input type="hidden" id="{{ $id }}" name="{{ $nama }}" form="form-pengaturan" :value="kodeArea">
                <input type="hidden" name="setting[biteship_area_nama]" form="form-pengaturan" :value="namaArea">

                <p class="text-sm" x-show="namaArea" x-cloak>
                    Area asal saat ini: <strong x-text="namaArea"></strong>
                </p>
                <p class="text-sm text-muted" x-show="!namaArea" x-cloak>
                    Belum diatur. Hitung ongkir otomatis mati sampai ini diisi.
                </p>

                <div class="relative mt-2">
                    <input type="text" x-model="cari" @input.debounce.400ms="cariArea()" autocomplete="off"
                           aria-label="Cari area asal pengiriman"
                           placeholder="Ketik nama kota/kecamatan toko, mis. Wonogiri"
                           class="kotak-input">
                    <p class="mt-1 text-xs text-muted" x-show="mencari" x-cloak>Mencari&hellip;</p>
                    <ul x-show="hasil.length > 0" x-cloak
                        class="absolute z-10 mt-1 max-h-56 w-full overflow-auto border border-line bg-field shadow-lg">
                        <template x-for="a in hasil" :key="a.kode">
                            <li>
                                <button type="button" @click="pilih(a)"
                                        class="block w-full px-3 py-2 text-left text-sm hover:bg-navy-50"
                                        x-text="a.nama"></button>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        @else
            <input type="text" id="{{ $id }}" name="{{ $nama }}" form="form-pengaturan" value="{{ $nilai }}"
                   class="kotak-input {{ str_contains($s->key, 'rekening') ? 'tabular' : '' }}">
        @endif

        @if ($s->deskripsi)
            <p class="mt-1 text-xs leading-relaxed text-muted">{{ $s->deskripsi }}</p>
        @endif
    @endif

    @error('setting.'.$s->key)
        <p class="mt-1 text-xs font-medium text-merah">{{ $message }}</p>
    @enderror
</div>
@endunless
