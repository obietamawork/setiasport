@php
    /** @var \App\Models\OrderItem $item */
    $terkunci = $item->rosterTerkunci();
    $baris = $item->rosters->map(fn ($r) => [
        'nama_punggung' => $r->nama_punggung,
        'nomor' => $r->nomor,
        'ukuran' => $r->ukuran,
    ])->values();

    $ukuranPilihan = ['S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
@endphp

<div class="border-t border-line px-5 py-4">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h3 class="font-semibold">Nama &amp; nomor punggung</h3>
        <span class="text-sm text-muted tabular">
            {{ $item->rosters->count() }} dari {{ $item->qty }} terisi
        </span>
    </div>

    @if ($terkunci)
        <p class="mt-2 text-sm text-muted">
            Terkunci karena desain sudah disetujui. Hubungi toko kalau ada yang perlu diubah.
        </p>

        @if ($item->rosters->isNotEmpty())
            <p class="mt-3 text-sm tabular">{{ $item->ringkasanUkuran() }}</p>
            <ol class="mt-3 divide-y divide-line border border-line text-sm">
                @foreach ($item->rosters as $r)
                    <li class="flex gap-3 px-3 py-1.5">
                        <span class="w-6 text-muted tabular">{{ $r->urutan }}</span>
                        <span class="flex-1">{{ $r->nama_punggung ?: '-' }}</span>
                        <span class="w-10 tabular">{{ $r->nomor ?: '-' }}</span>
                        <span class="w-12 text-muted">{{ $r->ukuran }}</span>
                    </li>
                @endforeach
            </ol>
        @endif
    @else
        <p class="kolom-baca mt-2 text-sm leading-relaxed text-muted">
            Isi sebisanya sekarang, sisanya menyusul: daftar ini bisa Anda ubah sampai
            desain disetujui. Setelah itu terkunci, karena namanya sudah masuk mesin cetak.
        </p>

        <form method="POST" action="{{ route('lacak.item.roster', [$order->access_token, $item]) }}"
              x-data="rosterEditor(@js($baris), {{ $item->qty }})" class="mt-4">
            @csrf

            {{-- Baris yang melipat, bukan tabel. Tabel empat kolom memaksa
                 geser horizontal di layar sempit, padahal daftar ini justru
                 paling sering diisi dari HP sambil menyalin dari grup tim. --}}
            <div class="mt-4 divide-y divide-line border-y border-line">
                <template x-for="(b, i) in baris" :key="i">
                    <div class="flex flex-wrap items-center gap-2 py-2">
                        <span class="tabular w-5 shrink-0 text-sm text-muted" x-text="i + 1"></span>

                        <input type="text" :name="`roster[${i}][nama_punggung]`" x-model="b.nama_punggung"
                               class="kotak-input min-w-40 flex-1" maxlength="60"
                               placeholder="Nama punggung" :aria-label="`Nama punggung pemain ${i + 1}`">

                        <input type="text" inputmode="numeric" :name="`roster[${i}][nomor]`"
                               x-model="b.nomor" class="kotak-input tabular w-16 text-center"
                               maxlength="3" placeholder="No" :aria-label="`Nomor punggung pemain ${i + 1}`"
                               :class="nomorGanda(i) ? 'border-merah' : ''">

                        <select :name="`roster[${i}][ukuran]`" x-model="b.ukuran" class="kotak-input w-24"
                                :aria-label="`Ukuran pemain ${i + 1}`">
                            <option value="">Ukuran</option>
                            @foreach ($ukuranPilihan as $u)
                                <option value="{{ $u }}">{{ $u }}</option>
                            @endforeach
                        </select>

                        <button type="button" @click="baris.splice(i, 1)"
                                class="rounded-[7px] p-2 text-muted transition-colors hover:bg-merah-50 hover:text-merah"
                                :aria-label="`Hapus baris ${i + 1}`">
                            <svg class="size-4" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                                <path d="M4 4l8 8M12 4l-8 8" />
                            </svg>
                        </button>
                    </div>
                </template>
            </div>

            <p x-show="adaNomorGanda" x-cloak class="mt-2 text-sm text-merah">
                Ada nomor punggung yang dipakai dua kali. Perbaiki dulu sebelum menyimpan.
            </p>

            <div class="mt-3 flex flex-wrap items-center gap-3">
                <button type="button" @click="tambah()" :disabled="baris.length >= qty"
                        class="tombol tombol-garis text-sm">Tambah baris</button>
                <button type="button" @click="isiSampaiPenuh()" x-show="baris.length < qty" x-cloak
                        class="tautan text-sm">Isi sampai <span x-text="qty"></span> baris</button>
                <span class="text-sm text-muted tabular" x-text="ringkasan"></span>
                <button type="submit" :disabled="adaNomorGanda"
                        class="tombol tombol-navy ml-auto text-sm">Simpan daftar</button>
            </div>
        </form>
    @endif
</div>

@once
    @push('skrip')
    <script nonce="{{ Vite::cspNonce() }}">
        function rosterEditor(awal, qty) {
            return {
                qty,
                baris: awal.length ? awal : [{ nama_punggung: '', nomor: '', ukuran: '' }],

                tambah() {
                    if (this.baris.length < this.qty) {
                        this.baris.push({ nama_punggung: '', nomor: '', ukuran: '' });
                    }
                },

                isiSampaiPenuh() {
                    while (this.baris.length < this.qty) this.tambah();
                },

                // Dua pemain bernomor sama baru ketahuan setelah jersey tercetak,
                // dan jersey bernama orang tidak bisa dijual ulang.
                nomorGanda(i) {
                    const n = (this.baris[i].nomor || '').trim();
                    if (!n) return false;
                    return this.baris.some((b, j) => j !== i && (b.nomor || '').trim() === n);
                },

                get adaNomorGanda() {
                    return this.baris.some((_, i) => this.nomorGanda(i));
                },

                get ringkasan() {
                    const hitung = {};
                    this.baris.forEach(b => {
                        if (b.ukuran) hitung[b.ukuran] = (hitung[b.ukuran] || 0) + 1;
                    });
                    const urut = ['S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
                    return Object.keys(hitung)
                        .sort((a, b) => urut.indexOf(a) - urut.indexOf(b))
                        .map(k => `${k}:${hitung[k]}`).join(', ');
                },
            };
        }
    </script>
    @endpush
@endonce
