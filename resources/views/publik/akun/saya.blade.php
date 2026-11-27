@extends('layouts.publik')
@section('judul', 'Akun saya')

@section('isi')
@php($batas = \App\Models\AlamatPelanggan::BATAS)
<div class="mx-auto max-w-3xl px-5 py-10">
    @include('publik.akun.kepala', ['aktif' => 'akun'])

    {{-- Profil --}}
    <section class="kartu mt-8 p-6">
        <h2 class="text-lg font-bold">Profil</h2>
        <form method="POST" action="{{ route('akun.profil') }}" class="mt-4 space-y-4">
            @csrf @method('PUT')
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="nama" class="label-form">Nama</label>
                    <input type="text" id="nama" name="nama" value="{{ old('nama', $pelanggan->nama) }}"
                           class="kotak-input" maxlength="100" required autocomplete="name">
                </div>
                <div>
                    <label for="no_hp" class="label-form">Nomor HP (WhatsApp)</label>
                    <input type="tel" id="no_hp" name="no_hp" value="{{ old('no_hp', $pelanggan->no_hp) }}"
                           class="kotak-input" maxlength="20" required autocomplete="tel">
                </div>
            </div>
            <div>
                <span class="label-form">Email</span>
                <p class="kotak-input bg-paper text-muted">{{ $pelanggan->email }}</p>
                {{-- Email mengikat pesanan ke akun (KEP-020), jadi tidak diubah sendiri. --}}
                <p class="bantuan-form">Email terhubung dengan seluruh pesanan Anda. Untuk menggantinya, hubungi CS kami.</p>
            </div>
            <button type="submit" class="tombol tombol-navy">Simpan profil</button>
        </form>
    </section>

    {{-- Buku alamat --}}
    <section class="kartu mt-6 p-6" id="alamat">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="text-lg font-bold">Buku alamat</h2>
            <span class="text-sm text-muted">{{ $daftarAlamat->count() }} dari {{ $batas }} alamat</span>
        </div>
        <p class="mt-1 text-sm text-muted">Alamat utama langsung terisi saat checkout, beserta ongkos kirimnya.</p>

        @error('alamat')
            <p class="mt-3 text-sm text-merah">{{ $message }}</p>
        @enderror

        <div class="mt-4 space-y-3">
            @forelse ($daftarAlamat as $a)
                <div class="rounded-[10px] border border-line p-4" x-data="{ ubah: false }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold">
                                {{ $a->label }}
                                @if ($a->utama)
                                    <span class="chip chip-netral">Utama</span>
                                @endif
                            </p>
                            <p class="mt-1 text-sm leading-relaxed">{{ $a->alamat }}</p>
                            @if ($a->area_nama)
                                <p class="mt-0.5 text-xs text-muted">{{ $a->area_nama }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-sm">
                            @unless ($a->utama)
                                <form method="POST" action="{{ route('akun.alamat.utama', $a->id) }}">
                                    @csrf
                                    <button type="submit" class="tautan">Jadikan utama</button>
                                </form>
                            @endunless
                            <button type="button" class="tautan" @click="ubah = !ubah">Ubah</button>
                            <form method="POST" action="{{ route('akun.alamat.hapus', $a->id) }}"
                                  data-konfirmasi="Hapus alamat {{ $a->label }}?" data-konfirmasi-tombol="Hapus">
                                @csrf @method('DELETE')
                                <button type="submit" class="tautan text-muted">Hapus</button>
                            </form>
                        </div>
                    </div>
                    <div x-show="ubah" x-cloak class="mt-4 border-t border-line pt-4">
                        @include('publik.akun.isian-alamat', ['alamat' => $a, 'aksi' => route('akun.alamat.ubah', $a->id), 'metode' => 'PUT', 'tombol' => 'Simpan perubahan'])
                    </div>
                </div>
            @empty
                <p class="text-sm text-muted">Belum ada alamat tersimpan.</p>
            @endforelse
        </div>

        @if ($daftarAlamat->count() < $batas)
            <div class="mt-5 border-t border-line pt-5" x-data="{ tambah: {{ $daftarAlamat->isEmpty() ? 'true' : 'false' }} }">
                <button type="button" class="tombol tombol-garis" x-show="! tambah" @click="tambah = true">Tambah alamat</button>
                <div x-show="tambah" x-cloak>
                    <h3 class="font-semibold">Alamat baru</h3>
                    <div class="mt-3">
                        @include('publik.akun.isian-alamat', ['alamat' => null, 'aksi' => route('akun.alamat.simpan'), 'metode' => 'POST', 'tombol' => 'Simpan alamat'])
                    </div>
                </div>
            </div>
        @endif
    </section>

    {{-- Kata sandi --}}
    <section class="kartu mt-6 p-6">
        <h2 class="text-lg font-bold">Ganti kata sandi</h2>
        <form method="POST" action="{{ route('akun.sandi') }}" class="mt-4 space-y-4">
            @csrf @method('PUT')
            <div>
                <label for="sandi_lama" class="label-form">Kata sandi saat ini</label>
                <input type="password" id="sandi_lama" name="sandi_lama" class="kotak-input" required autocomplete="current-password">
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="password" class="label-form">Kata sandi baru</label>
                    <input type="password" id="password" name="password" class="kotak-input" required minlength="8" autocomplete="new-password">
                </div>
                <div>
                    <label for="password_confirmation" class="label-form">Ulangi kata sandi baru</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="kotak-input" required minlength="8" autocomplete="new-password">
                </div>
            </div>
            <button type="submit" class="tombol tombol-garis">Ganti kata sandi</button>
        </form>
        <p class="mt-4 text-sm text-muted">
            Lupa kata sandi saat ini? <a href="{{ route('lupa-sandi') }}" class="tautan">Kirim tautan atur ulang</a>
        </p>
    </section>

    @include('publik.akun.keluar')
</div>
@endsection

@push('skrip')
<script nonce="{{ Vite::cspNonce() }}">
    /** Pencarian area tujuan untuk buku alamat; sama dengan checkout (KEP-155). */
    function isianAlamat(cfg) {
        return {
            ...cfg,
            kata: '',
            daftarArea: [],
            mencari: false,
            belumKetemu: false,

            async cariArea() {
                const kata = this.kata.trim();
                this.belumKetemu = false;
                if (kata.length < 3) { this.daftarArea = []; return; }
                this.mencari = true;
                try {
                    const r = await fetch(this.urlArea + '?q=' + encodeURIComponent(kata));
                    this.daftarArea = (await r.json()).area ?? [];
                } catch (e) {
                    this.daftarArea = [];
                }
                this.mencari = false;
                if (kata !== this.kata.trim()) return;
                this.belumKetemu = this.daftarArea.length === 0;
                if (/^\d{5}$/.test(kata) && this.daftarArea.length === 1) this.pilih(this.daftarArea[0]);
            },

            pilih(a) {
                this.areaKode = a.kode;
                this.areaNama = a.nama;
                this.kodePos = a.kode_pos ?? '';
                this.daftarArea = [];
                this.kata = '';
            },

            hapusArea() {
                this.areaKode = '';
                this.areaNama = '';
                this.kodePos = '';
            },
        };
    }
</script>
@endpush
