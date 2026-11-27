{{--
    Pop-up Perbarui progres (KEP-124). Semua tahap barang terlihat, tetapi
    yang bisa dipilih hanya satu tahap maju (bukan gerbang) atau satu tahap
    mundur dengan alasan. Pilihannya dari ProgresStaf, yang membaca aturan
    yang sama dengan penjagaan server, jadi pilihan aktif selalu diterima.
--}}
<div x-data="perbaruiProgres(@js($barangProgres))" @buka-progres.window="buka()">
    <dialog x-ref="dialog" class="dialog w-[min(32rem,calc(100vw-2rem))]" aria-labelledby="judul-progres">
        <form method="POST" :action="aksi === 'mundur' ? barang.mundur : barang.maju" class="p-5 sm:p-6">
            @csrf
            <input type="hidden" name="dari" :value="barang.dari">

            <div class="flex items-start justify-between gap-3">
                <h2 id="judul-progres" class="flex items-center gap-2 text-lg font-bold">
                    <x-ikon nama="pensil" class="text-navy-400" /> Perbarui progres pesanan
                </h2>
                <button type="button" class="tombol-ikon border-transparent" @click="$refs.dialog.close()" aria-label="Tutup">
                    <x-ikon nama="silang" ukuran="size-4" />
                </button>
            </div>

            <div class="mt-4 flex items-center gap-3 rounded-[12px] bg-paper p-3">
                <x-ikon nama="kaos" ukuran="size-6" class="text-navy-400" />
                <div class="min-w-0">
                    <p class="tabular font-bold">{{ $pesanan->kode }}</p>
                    <p class="truncate text-xs text-muted" x-text="barang.nama"></p>
                </div>
            </div>

            @if ($barangProgres->count() > 1)
                <div class="mt-4">
                    <label for="progres-barang" class="label-form">Pilih barang</label>
                    <select id="progres-barang" class="kotak-input" x-model.number="idBarang" @change="tujuan = null">
                        @foreach ($barangProgres as $b)
                            <option value="{{ $b['id'] }}">{{ $b['nama'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <fieldset class="mt-4">
                <legend class="label-form">Status progres</legend>
                <div class="max-h-72 divide-y divide-line overflow-y-auto rounded-[12px] border border-line">
                    <template x-for="p in barang.pilihan" :key="p.status">
                        <label class="flex items-start gap-3 px-3 py-2.5 text-sm"
                               :class="{
                                   'bg-navy-50': tujuan === p.status,
                                   'cursor-pointer hover:bg-navy-50': p.aksi,
                                   'text-muted': ! p.aksi && p.keadaan !== 'kini',
                               }">
                            <input type="radio" class="mt-0.5 size-4 accent-navy" :value="p.status" x-model="tujuan"
                                   :disabled="! p.aksi">
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span :class="p.keadaan === 'kini' && 'font-bold'" x-text="p.label"></span>
                                    <span class="chip chip-navy" x-show="p.keadaan === 'kini'">Sekarang</span>
                                    <span class="chip chip-ok" x-show="p.aksi === 'maju'">Berikutnya</span>
                                    <span class="chip chip-kuning" x-show="p.aksi === 'mundur'">Kembalikan</span>
                                    <span class="text-xs text-ok" x-show="p.keadaan === 'lewat'">sudah lewat</span>
                                </span>
                                <span class="mt-0.5 block text-xs text-muted" x-show="p.alasan" x-text="p.alasan"></span>
                            </span>
                        </label>
                    </template>
                </div>
            </fieldset>

            <div class="mt-4">
                <label for="progres-catatan" class="label-form">
                    <span x-text="aksi === 'mundur' ? 'Alasan dikembalikan (wajib)' : 'Catatan (opsional)'">Catatan (opsional)</span>
                </label>
                <textarea id="progres-catatan" rows="3" maxlength="200" class="kotak-input"
                          :name="aksi === 'mundur' ? 'alasan' : 'catatan'" x-model="catatan" :required="aksi === 'mundur'"
                          :placeholder="aksi === 'mundur' ? 'Misal: sablon luntur, cetak ulang' : 'Contoh: kain sudah dicek, lanjut press'"></textarea>
                <p class="mt-1 flex justify-between gap-3 text-xs text-muted">
                    <span>Hanya dibaca staf. Pelanggan melihat tahapnya saja.</span>
                    <span class="tabular" x-text="catatan.length + '/200'"></span>
                </p>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" class="tombol tombol-garis" @click="$refs.dialog.close()">Batal</button>
                <button type="submit" class="tombol tombol-navy" :disabled="! aksi">
                    <x-ikon nama="centang" ukuran="size-4" /> Simpan perubahan
                </button>
            </div>
        </form>
    </dialog>
</div>

@push('skrip')
<script nonce="{{ Vite::cspNonce() }}">
    document.addEventListener('alpine:init', () => {
        Alpine.data('perbaruiProgres', (daftar) => ({
            daftar,
            idBarang: daftar[0]?.id ?? null,
            tujuan: null,
            catatan: '',
            get barang() {
                return this.daftar.find((b) => b.id === this.idBarang) ?? this.daftar[0] ?? { pilihan: [] };
            },
            get aksi() {
                return this.barang.pilihan.find((p) => p.status === this.tujuan)?.aksi ?? null;
            },
            buka() {
                this.tujuan = null;
                this.catatan = '';
                this.$refs.dialog.showModal();
            },
        }));
    });
</script>
@endpush
