{{-- Isian satu alamat di buku alamat (KEP-157), untuk tambah maupun ubah. --}}
@php($kunci = $alamat?->id ?? 'baru')
<form method="POST" action="{{ $aksi }}" class="space-y-4"
      x-data="isianAlamat({
          urlArea: @js(route('pengiriman.area')),
          areaKode: @js($alamat?->area_kode ?? ''),
          areaNama: @js($alamat?->area_nama ?? ''),
          kodePos: @js($alamat?->kode_pos ?? ''),
      })">
    @csrf
    @if ($metode !== 'POST')
        @method($metode)
    @endif

    <div>
        <label for="label-{{ $kunci }}" class="label-form">Nama alamat</label>
        <input type="text" id="label-{{ $kunci }}" name="label" value="{{ $alamat?->label }}"
               class="kotak-input" maxlength="40" required placeholder="Contoh: Rumah, Kantor, Sekolah">
    </div>

    <div>
        <label for="alamat-{{ $kunci }}" class="label-form">Alamat lengkap</label>
        <textarea id="alamat-{{ $kunci }}" name="alamat" rows="3" maxlength="500" required
                  class="kotak-input" placeholder="Nama jalan, nomor rumah, RT/RW, desa">{{ $alamat?->alamat }}</textarea>
    </div>

    <div>
        <label for="area-{{ $kunci }}" class="label-form">Kecamatan, kota, atau kode pos</label>
        <input type="hidden" name="area_kode" :value="areaKode">
        <input type="hidden" name="area_nama" :value="areaNama">
        <input type="hidden" name="kode_pos" :value="kodePos">

        <p x-show="areaKode" x-cloak class="text-sm">
            <strong x-text="areaNama"></strong>
            <button type="button" class="ml-1 tautan" @click="hapusArea()">ganti</button>
        </p>
        <div x-show="! areaKode">
            <input type="text" id="area-{{ $kunci }}" class="kotak-input" autocomplete="off"
                   placeholder="Contoh: Laweyan Solo, atau kode pos 57141"
                   x-model="kata" @input.debounce.400ms="cariArea()">
            <p x-show="mencari" x-cloak class="bantuan-form">Mencari...</p>
            <p x-show="belumKetemu && ! mencari" x-cloak class="mt-1 text-sm text-merah">
                Alamat belum ketemu. Coba ketik nama kecamatan dan kotanya saja, atau kode posnya.
            </p>
            <ul x-show="daftarArea.length" x-cloak class="mt-2 divide-y divide-line border border-line bg-white text-sm">
                <template x-for="a in daftarArea" :key="a.kode">
                    <li>
                        <button type="button" class="w-full px-3 py-2 text-left hover:bg-navy-50" @click="pilih(a)" x-text="a.nama"></button>
                    </li>
                </template>
            </ul>
            <p class="bantuan-form">Dipakai untuk menghitung ongkos kirim otomatis.</p>
        </div>
    </div>

    <div>
        <label for="lokasi-{{ $kunci }}" class="label-form">Tautan lokasi Google Maps (opsional)</label>
        <input type="url" id="lokasi-{{ $kunci }}" name="tautan_lokasi" value="{{ $alamat?->tautan_lokasi }}"
               class="kotak-input" maxlength="500" placeholder="https://maps.app.goo.gl/...">
    </div>

    <button type="submit" class="tombol tombol-utama">{{ $tombol }}</button>
</form>
