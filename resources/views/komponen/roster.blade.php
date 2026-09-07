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

            <div class="overflow-x-auto">
                <table class="w-full min-w-100 border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-line text-left">
                            <th class="w-8 py-1.5 font-semibold">#</th>
                            <th class="py-1.5 font-semibold">Nama punggung</th>
                            <th class="w-20 py-1.5 font-semibold">Nomor</th>
                            <th class="w-28 py-1.5 font-semibold">Ukuran</th>
                            <th class="w-8"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(b, i) in baris" :key="i">
                            <tr>
                                <td class="py-1 text-muted tabular" x-text="i + 1"></td>
                                <td class="py-1 pr-2">
                                    <input type="text" :name="`roster[${i}][nama_punggung]`" x-model="b.nama_punggung"
                                           class="kotak-input" maxlength="60" placeholder="RIZKY">
                                </td>
                                <td class="py-1 pr-2">
                                    <input type="text" inputmode="numeric" :name="`roster[${i}][nomor]`"
                                           x-model="b.nomor" class="kotak-input tabular text-center"
                                           maxlength="3" placeholder="10"
                                           :class="nomorGanda(i) ? 'border-merah' : ''">
                                </td>
                                <td class="py-1 pr-2">
                                    <select :name="`roster[${i}][ukuran]`" x-model="b.ukuran" class="kotak-input">
                                        <option value="">Pilih</option>
                                        @foreach ($ukuranPilihan as $u)
                                            <option value="{{ $u }}">{{ $u }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-1 text-right">
                                    <button type="button" @click="baris.splice(i, 1)"
                                            class="tautan text-xs text-muted" aria-label="Hapus baris">Hapus</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
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
    <script>
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
