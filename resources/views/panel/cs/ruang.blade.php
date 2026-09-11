@extends('layouts.cs')

@section('isi')
<div x-data="ruangCs(@js($awal))" class="flex h-full flex-col">

    {{-- Bilah atas. Tipis: yang penting di sini hanya dua saklar yang menentukan
         apakah pelanggan akan dilayani, yaitu "Menerima chat" dan suara. --}}
    <header class="flex h-12 shrink-0 items-center gap-3 bg-navy-900 px-3 text-white sm:px-4">
        <a href="{{ route('panel.dashboard') }}" class="flex items-center gap-3" title="Kembali ke panel">
            <x-logo :terang="true" />
        </a>
        <span class="hidden h-5 w-px bg-white/20 sm:block"></span>
        <span class="hidden text-sm font-semibold sm:block">Ruang CS</span>

        <div class="ml-auto flex items-center gap-1 text-sm">
            {{-- Pelanggan melihat status ini di jendela chat mereka (KEP-031). --}}
            <button type="button" x-show="{{ $awal['aktif'] ? 'true' : 'false' }}" @click="gantiMenerima()" role="switch" :aria-checked="menerima"
                    class="flex items-center gap-2 rounded-full px-3 py-1.5 font-medium transition-colors"
                    :class="menerima ? 'bg-white/10 hover:bg-white/15' : 'text-white/60 hover:bg-white/10'">
                <span class="size-2 rounded-full" :class="menerima ? 'bg-emerald-400' : 'bg-white/40'"></span>
                <span x-text="menerima ? 'Menerima chat' : 'Tidak menerima chat'"></span>
            </button>

            <button type="button" @click="gantiSuara()" :aria-pressed="suara"
                    class="rounded-[7px] p-2 hover:bg-white/10" :title="suara ? 'Matikan suara pesan baru' : 'Nyalakan suara pesan baru'">
                <svg x-show="suara" class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 8h2.5L10 5v10l-3.5-3H4z"/><path d="M13 7.5a3.5 3.5 0 0 1 0 5M15 5.5a6.5 6.5 0 0 1 0 9"/>
                </svg>
                <svg x-show="!suara" x-cloak class="size-5 text-white/60" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 8h2.5L10 5v10l-3.5-3H4z"/><path d="m13.5 8 4 4m0-4-4 4"/>
                </svg>
                <span class="sr-only">Suara pesan baru</span>
            </button>

            <a href="{{ route('panel.cs.template.index') }}" class="hidden rounded-[7px] px-2.5 py-1.5 text-white/80 hover:bg-white/10 hover:text-white md:block">
                Kelola template
            </a>
            <a href="{{ route('panel.dashboard') }}" class="hidden rounded-[7px] px-2.5 py-1.5 text-white/80 hover:bg-white/10 hover:text-white sm:block">
                Panel
            </a>
        </div>
    </header>

    @unless ($awal['aktif'])
        {{-- CS dimatikan di Pengaturan (KEP-033). Riwayatnya tetap catatan resmi
             komplain, jadi tetap bisa dibaca, tapi tidak bisa dibalas. --}}
        <p class="shrink-0 border-b border-kuning/40 bg-kuning-50 px-4 py-2 text-sm">
            Chat CS sedang dimatikan, jadi ini arsip: riwayat bisa dibaca tapi tidak bisa dibalas.
            Nyalakan lagi di <a href="{{ route('panel.pengaturan.index') }}" class="tautan">Pengaturan</a>.
        </p>
    @endunless

    {{-- grid-cols-1 di dasar WAJIB: tanpa kolom eksplisit, satu-satunya kolom
         di HP melebar mengikuti isinya dan teks yang dipotong ikut meluber. --}}
    <div class="grid min-h-0 flex-1 grid-cols-1 md:grid-cols-[19rem_minmax(0,1fr)] xl:grid-cols-[20rem_minmax(0,1fr)_18rem]">

        {{-- ================= Daftar percakapan ================= --}}
        <aside class="flex min-h-0 flex-col border-r border-line bg-field" :class="terpilih ? 'hidden md:flex' : 'flex'">
            <div class="border-b border-line p-3">
                <label for="cari-cs" class="sr-only">Cari percakapan</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                        <circle cx="9" cy="9" r="5.5"/><path d="m13.5 13.5 3 3"/>
                    </svg>
                    <input id="cari-cs" type="search" x-model="cari" placeholder="Nama, kontak, atau kode pesanan"
                           class="kotak-input py-2 pl-9 text-sm">
                </div>

                {{-- Empat saringan dalam satu bilah bersekat, bukan deretan pil yang
                     meluber: lebar kolom ini tetap, jadi pembagiannya juga tetap. --}}
                <div class="mt-3 grid grid-cols-[1.4fr_1fr_1fr_0.9fr] rounded-[8px] bg-paper p-0.5 text-[13px]" role="tablist">
                    <template x-for="[nilai, label] in [['menunggu', 'Menunggu'], ['terbuka', 'Berjalan'], ['selesai', 'Ditutup'], ['semua', 'Semua']]" :key="nilai">
                        <button type="button" role="tab" @click="gantiSaring(nilai)" :aria-selected="saring === nilai"
                                class="flex items-center justify-center gap-1 rounded-[6px] px-1 py-1.5 font-medium transition-colors"
                                :class="saring === nilai ? 'bg-field text-ink shadow-[0_0_0_1px_var(--color-line)]' : 'text-muted hover:text-ink'">
                            <span x-text="label"></span>
                            <span x-show="nilai === 'menunggu' && jumlah.menunggu > 0"
                                  class="tabular inline-flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-merah px-1 text-[11px] text-white"
                                  x-text="jumlah.menunggu"></span>
                        </button>
                    </template>
                </div>
            </div>

            <ul class="min-h-0 flex-1 overflow-y-auto" :class="memuatDaftar && 'opacity-60'">
                <template x-for="p in daftar" :key="p.id">
                    <li>
                        {{-- Garis tepi kiri adalah satu-satunya hal yang berteriak di
                             layar ini: kuning selama pelanggan menunggu dalam batas
                             wajar, merah begitu melewatinya. --}}
                        <button type="button" @click="pilih(p.id)"
                                class="relative flex w-full gap-3 border-b border-line py-3 pr-3 pl-4 text-left transition-colors"
                                :class="terpilih?.id === p.id ? 'bg-navy-50' : 'hover:bg-paper'">
                            <span class="absolute inset-y-0 left-0 w-[3px]"
                                  :class="{ 'bg-kuning': keadaanTunggu(p) === 'menunggu', 'bg-merah': keadaanTunggu(p) === 'terlambat' }"></span>

                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                  :class="p.topik_kode === 'KOMPLAIN' ? 'bg-merah-50 text-merah-tua' : 'bg-navy-100 text-navy-800'"
                                  x-text="inisial(p.nama)"></span>

                            <span class="min-w-0 flex-1">
                                <span class="flex items-baseline gap-2">
                                    <span class="min-w-0 flex-1 truncate font-semibold" :class="p.belum_dibaca > 0 && 'text-navy'" x-text="p.nama"></span>
                                    <span class="tabular shrink-0 text-xs text-muted" x-text="waktuDaftar(p.pesan_terakhir_pada)"></span>
                                </span>
                                <span class="mt-0.5 flex items-center gap-2">
                                    <span class="min-w-0 flex-1 truncate text-sm"
                                          :class="p.belum_dibaca > 0 ? 'font-medium text-ink' : 'text-muted'">
                                        <span x-show="p.cuplikan_dari === 'STAF'" class="text-muted">Anda: </span><span x-text="p.cuplikan"></span>
                                    </span>
                                    <span x-show="p.belum_dibaca > 0"
                                          class="tabular inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-merah px-1 text-xs text-white"
                                          x-text="p.belum_dibaca"></span>
                                </span>
                                <span class="mt-1 flex items-center gap-2 text-xs">
                                    <span class="text-muted" x-text="p.topik"></span>
                                    <span x-show="keadaanTunggu(p) !== 'tenang'" class="font-semibold"
                                          :class="keadaanTunggu(p) === 'terlambat' ? 'text-merah' : 'text-warn'"
                                          x-text="'menunggu ' + lamaMenunggu(p.menunggu_sejak)"></span>
                                    <span x-show="p.status === 'SELESAI'" class="text-muted">ditutup</span>
                                </span>
                            </span>
                        </button>
                    </li>
                </template>

                <li x-show="!daftar.length && !memuatDaftar" class="px-6 py-12 text-center text-sm text-muted">
                    <span x-show="cari">Tidak ada percakapan yang cocok dengan pencarian ini.</span>
                    <span x-show="!cari && saring === 'menunggu'">Tidak ada yang menunggu balasan.</span>
                    <span x-show="!cari && saring !== 'menunggu'">Belum ada percakapan di sini.</span>
                </li>
            </ul>
        </aside>

        {{-- ================= Percakapan ================= --}}
        <main class="flex min-h-0 flex-col bg-paper" :class="terpilih ? 'flex' : 'hidden md:flex'">

            <template x-if="!terpilih">
                <div class="m-auto max-w-sm px-6 text-center">
                    <p class="text-lg font-bold" x-text="jumlah.menunggu ? jumlah.menunggu + ' pelanggan menunggu balasan' : 'Semua pelanggan sudah dibalas'"></p>
                    <p class="mt-2 text-sm leading-relaxed text-muted">
                        Pilih percakapan di kiri untuk mulai membalas. Yang paling lama menunggu ada di urutan teratas.
                    </p>
                </div>
            </template>

            <template x-if="terpilih">
                <div class="flex min-h-0 flex-1 flex-col">
                    <div class="flex shrink-0 items-center gap-3 border-b border-line bg-field px-3 py-2.5 sm:px-5">
                        <button type="button" @click="lepas()" class="-ml-1 rounded-[7px] p-1.5 text-muted hover:bg-navy-50 md:hidden" aria-label="Kembali ke daftar">
                            <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 5-5 5 5 5"/></svg>
                        </button>

                        <div class="min-w-0 flex-1">
                            <p class="truncate font-bold" x-text="terpilih.nama"></p>
                            <p class="truncate text-xs text-muted">
                                <span x-text="terpilih.topik"></span>
                                <template x-if="terpilih.order">
                                    <span>, pesanan <span class="tabular" x-text="terpilih.order.kode"></span></span>
                                </template>
                            </p>
                        </div>

                        <button type="button" @click="infoTerbuka = !infoTerbuka" class="tombol tombol-halus tombol-sm shrink-0 xl:hidden" :aria-expanded="infoTerbuka">
                            Info<span class="hidden sm:inline">&nbsp;pelanggan</span>
                        </button>

                        <button type="button" x-show="terpilih.status === 'TERBUKA'" @click="tutup()" class="tombol tombol-garis tombol-sm shrink-0">
                            Tutup<span class="hidden sm:inline">&nbsp;percakapan</span>
                        </button>
                        <span x-show="terpilih.status === 'SELESAI'" class="chip chip-netral">Ditutup</span>
                    </div>

                    <div x-ref="daftarPesan" class="min-h-0 flex-1 overflow-y-auto px-3 py-4 sm:px-6" :class="memuatPercakapan && 'opacity-60'">
                        <ol class="mx-auto max-w-3xl">
                            <template x-for="(p, i) in pesan" :key="p.id">
                                <li>
                                    <p x-show="pemisah(i)" class="my-4 text-center">
                                        <span class="rounded-full bg-field px-3 py-1 text-xs font-medium text-muted shadow-[0_0_0_1px_var(--color-line)]" x-text="pemisah(i)"></span>
                                    </p>

                                    <p x-show="p.pengirim === 'SISTEM'" class="my-3 text-center text-xs text-muted" x-text="p.isi"></p>

                                    <div x-show="p.pengirim !== 'SISTEM'" class="flex"
                                         :class="[p.pengirim === 'STAF' ? 'justify-end' : 'justify-start', awalRentetan(i) ? 'mt-3' : 'mt-1']">
                                        <div class="max-w-[78%]">
                                            <p x-show="awalRentetan(i)" class="mb-1 text-xs font-semibold"
                                               :class="p.pengirim === 'STAF' ? 'text-right text-navy' : 'text-muted'" x-text="p.nama"></p>
                                            <div class="overflow-hidden text-[15px] leading-relaxed"
                                                 :class="p.pengirim === 'STAF'
                                                    ? 'rounded-[14px] rounded-tr-[4px] bg-navy text-white'
                                                    : 'rounded-[14px] rounded-tl-[4px] bg-field shadow-[0_0_0_1px_var(--color-line)]'">
                                                <a x-show="p.lampiran" :href="p.lampiran" target="_blank" rel="noopener" class="block">
                                                    <img :src="p.lampiran" alt="Foto lampiran" loading="lazy" class="max-h-72 w-full object-cover">
                                                </a>
                                                <p x-show="p.isi" class="px-3.5 py-2 break-words whitespace-pre-line" x-text="p.isi"></p>
                                            </div>
                                            <p class="tabular mt-1 text-[11px] text-muted" :class="p.pengirim === 'STAF' && 'text-right'" x-text="jam(p.waktu)"></p>
                                        </div>
                                    </div>
                                </li>
                            </template>
                        </ol>
                    </div>

                    {{-- Pengingat tepat di atas kolom balasan, tempat mata admin berada. --}}
                    <p x-show="keadaanTunggu(terpilih) === 'terlambat'" x-cloak
                       class="shrink-0 border-t border-merah/25 bg-merah-50 px-5 py-2 text-sm text-merah-tua">
                        <span x-text="terpilih.nama"></span> sudah menunggu
                        <strong x-text="lamaMenunggu(terpilih.menunggu_sejak)"></strong>.
                    </p>

                    <form x-show="{{ $awal['aktif'] ? 'true' : 'false' }}" @submit.prevent="kirim()" class="relative shrink-0 border-t border-line bg-field px-3 py-3 sm:px-5">
                        {{-- Template sebagai daftar yang bisa dicari, bukan deretan tombol
                             yang harus digeser. Mengetik "/" di awal kolom membukanya. --}}
                        <div x-show="popover" x-cloak @click.outside="popover = false" x-transition.opacity.duration.100ms
                             class="absolute bottom-full left-3 z-10 mb-2 w-[min(24rem,calc(100%-1.5rem))] overflow-hidden rounded-[12px] bg-field shadow-xl shadow-navy/15 ring-1 ring-line sm:left-5">
                            <div class="border-b border-line p-2">
                                <input x-ref="cariTemplate" x-model="cariTemplate" @keydown="tombol($event)" type="search"
                                       placeholder="Cari template" class="kotak-input py-1.5 text-sm">
                            </div>
                            <ul class="max-h-72 overflow-y-auto py-1">
                                <template x-for="(t, i) in templateCocok" :key="t.id">
                                    <li>
                                        <button type="button" @click="pakaiTemplate(t)" @mouseenter="sorotTemplate = i"
                                                class="block w-full px-3 py-2 text-left" :class="sorotTemplate === i && 'bg-navy-50'">
                                            <span class="block text-sm font-semibold" x-text="t.judul"></span>
                                            <span class="line-clamp-2 text-xs leading-relaxed text-muted" x-text="t.isi"></span>
                                        </button>
                                    </li>
                                </template>
                                <li x-show="!templateCocok.length" class="px-3 py-4 text-center text-sm text-muted">
                                    Tidak ada template yang cocok.
                                </li>
                            </ul>
                            <a href="{{ route('panel.cs.template.index') }}" class="block border-t border-line px-3 py-2 text-xs text-muted hover:text-ink">
                                Kelola template
                            </a>
                        </div>

                        <p x-show="lampiran" x-cloak class="mb-2 flex items-center gap-2 text-xs text-muted">
                            Foto: <span class="truncate" x-text="lampiran?.name"></span>
                            <button type="button" class="tautan" @click="lampiran = null; $refs.berkas.value = ''">batal</button>
                        </p>

                        <div class="flex items-end gap-2">
                            <button type="button" @click="bukaTemplate()" :aria-expanded="popover"
                                    class="flex shrink-0 items-center gap-1.5 rounded-[7px] border border-line-strong px-2.5 py-2 text-sm font-medium text-ink hover:border-navy-300 hover:bg-navy-50"
                                    title="Template balasan (atau ketik / di awal pesan)">
                                <svg class="size-4 text-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4 5h12M4 10h8M4 15h10"/>
                                </svg>
                                <span class="sr-only sm:not-sr-only">Template</span>
                            </button>

                            <label class="shrink-0 cursor-pointer rounded-[7px] p-2 text-muted hover:bg-navy-50 hover:text-ink" title="Lampirkan foto">
                                <input type="file" accept="image/*" class="sr-only" x-ref="berkas" @change="pilihLampiran($event)">
                                <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true">
                                    <path d="M16.5 9.5 10 16a4 4 0 0 1-5.7-5.7l7-7a2.6 2.6 0 0 1 3.7 3.7l-6.9 6.9a1.3 1.3 0 0 1-1.9-1.8L12.5 6"/>
                                </svg>
                                <span class="sr-only">Lampirkan foto</span>
                            </label>

                            <label for="isian-cs" class="sr-only">Balasan</label>
                            <textarea id="isian-cs" x-ref="isian" x-model="isi" @input="saatMengetik()" @keydown="tombol($event)" rows="1" maxlength="2000"
                                      class="kotak-input min-h-10 flex-1 resize-none overflow-hidden py-2 text-[15px] leading-relaxed"
                                      placeholder="Tulis balasan"></textarea>

                            <button type="submit" class="tombol tombol-navy shrink-0" :disabled="mengirim || (!isi.trim() && !lampiran)">
                                <span x-text="mengirim ? 'Mengirim' : 'Kirim'">Kirim</span>
                            </button>
                        </div>
                        <p class="mt-1.5 hidden text-[11px] text-muted sm:block">Enter untuk kirim, Shift+Enter untuk baris baru, ketik / di awal untuk template.</p>
                    </form>
                </div>
            </template>
        </main>

        <div x-show="infoTerbuka" x-cloak x-transition.opacity @click="infoTerbuka = false"
             class="fixed inset-0 z-20 bg-ink/30 xl:hidden" aria-hidden="true"></div>

        {{-- ================= Konteks pelanggan ================= --}}
        {{-- Tetap tampil di layar lebar; di layar lebih kecil jadi panel geser
             lewat tombol "Info pelanggan". --}}
        <aside x-show="terpilih" x-cloak
               class="min-h-0 overflow-y-auto border-l border-line bg-field"
               :class="infoTerbuka ? 'fixed inset-y-0 right-0 z-30 w-[min(20rem,90vw)] shadow-2xl xl:static xl:w-auto xl:shadow-none' : 'hidden xl:block'">
            <template x-if="terpilih">
                <div class="divide-y divide-line">
                    <div class="flex items-center justify-between px-5 py-3 xl:hidden">
                        <p class="font-semibold">Info pelanggan</p>
                        <button type="button" @click="infoTerbuka = false" class="rounded-[7px] p-1.5 text-muted hover:bg-navy-50" aria-label="Tutup info">
                            <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15"/></svg>
                        </button>
                    </div>

                    <section class="px-5 py-4">
                        <h2 class="text-sm font-semibold text-muted">Kontak</h2>
                        <p class="mt-2 font-semibold" x-text="terpilih.nama"></p>
                        <p class="mt-0.5 flex items-center gap-2 text-sm">
                            <span class="min-w-0 break-all" x-text="terpilih.kontak"></span>
                            <button type="button" @click="salin(terpilih.kontak)" class="tautan shrink-0 text-xs text-muted">Salin</button>
                        </p>
                        <p class="mt-2">
                            <span x-show="terpilih.punya_akun" class="chip chip-navy">Punya akun</span>
                            <span x-show="!terpilih.punya_akun" class="chip chip-netral">Tanpa akun</span>
                        </p>
                    </section>

                    <section class="px-5 py-4">
                        <h2 class="text-sm font-semibold text-muted">Pesanan</h2>

                        <template x-if="terpilih.order">
                            <div class="mt-2">
                                <a :href="terpilih.order.url" target="_blank" rel="noopener" class="tautan tabular font-semibold" x-text="terpilih.order.kode"></a>
                                <p class="mt-0.5 text-sm" x-text="terpilih.order.status"></p>
                                <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-sm">
                                    <dt class="text-muted">Total</dt>
                                    <dd class="tabular text-right" x-text="rupiah(terpilih.order.total)"></dd>
                                    <dt class="text-muted">Sisa</dt>
                                    <dd class="tabular text-right font-semibold" :class="terpilih.order.sisa > 0 ? 'text-merah' : 'text-ok'"
                                        x-text="terpilih.order.sisa > 0 ? rupiah(terpilih.order.sisa) : 'Lunas'"></dd>
                                </dl>

                                {{-- Kode yang sekadar diketik belum membuktikan penanya adalah
                                     pemesannya (KEP-030). Peringatan ini harus terbaca sebelum
                                     staf mengubah apa pun, misalnya nama pengambil. --}}
                                <p x-show="terpilih.order.terverifikasi" class="mt-3"><span class="chip chip-ok">Terverifikasi</span></p>
                                <p x-show="!terpilih.order.terverifikasi" class="mt-3 border-l-2 border-kuning bg-kuning-50 p-3 text-xs leading-relaxed">
                                    Kode ini diketik pelanggan, belum terbukti ia pemesannya. Cocokkan email atau
                                    nomor HP pemesan sebelum mengubah apa pun di pesanan ini.
                                </p>
                            </div>
                        </template>

                        <p x-show="!terpilih.order" class="mt-2 text-sm text-muted">Belum ada pesanan yang ditautkan.</p>

                        <template x-if="terpilih.pesanan_lain.length">
                            <ul class="mt-3 space-y-1 text-sm">
                                <template x-for="o in terpilih.pesanan_lain" :key="o.kode">
                                    <li class="flex justify-between gap-3">
                                        <a :href="o.url" target="_blank" rel="noopener" class="tautan tabular" x-text="o.kode"></a>
                                        <span class="truncate text-muted" x-text="o.status"></span>
                                    </li>
                                </template>
                            </ul>
                        </template>

                        <form @submit.prevent="tautkan()" class="mt-4 flex gap-2">
                            <label for="kode-tautan" class="sr-only">Kode pesanan</label>
                            <input id="kode-tautan" x-model="kodeTautan" class="kotak-input tabular py-1.5 text-sm uppercase" placeholder="SS-2609-0001" maxlength="20">
                            <button type="submit" class="tombol tombol-garis tombol-sm shrink-0">Tautkan</button>
                        </form>
                    </section>

                    <section class="px-5 py-4 text-sm">
                        <h2 class="font-semibold text-muted">Percakapan</h2>
                        <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
                            <dt class="text-muted">Topik</dt><dd x-text="terpilih.topik"></dd>
                            <dt class="text-muted">Dimulai</dt><dd class="tabular" x-text="terpilih.dibuat && new Date(terpilih.dibuat).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })"></dd>
                            <dt class="text-muted">Status</dt><dd x-text="terpilih.status === 'TERBUKA' ? 'Berjalan' : 'Ditutup'"></dd>
                        </dl>
                    </section>
                </div>
            </template>
        </aside>
    </div>

    {{-- Sesi habis: layar ini tidak lagi menerima chat, dan admin harus tahu.
         Memuat ulang halaman membawa ke login lalu kembali ke sini. --}}
    <div x-show="sesiBerakhir" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-6" role="alertdialog" aria-labelledby="judul-sesi">
        <div class="w-full max-w-sm rounded-[14px] bg-field p-6 text-center shadow-2xl">
            <p id="judul-sesi" class="text-lg font-bold">Sesi Anda berakhir</p>
            <p class="mt-2 text-sm leading-relaxed text-muted">
                Chat baru tidak lagi masuk ke layar ini, dan pelanggan melihat CS sedang offline.
                Masuk lagi untuk melanjutkan.
            </p>
            <a href="{{ url()->current() }}" class="tombol tombol-navy mt-5 w-full">Masuk lagi</a>
        </div>
    </div>

    {{-- Galat tampil sebagai notis di pojok, tidak menggeser tata letak. --}}
    <p x-show="galat" x-cloak x-transition.opacity @click="galat = ''"
       class="fixed bottom-4 left-1/2 z-40 max-w-md -translate-x-1/2 cursor-pointer rounded-[10px] bg-ink px-4 py-2.5 text-sm text-white shadow-xl"
       x-text="galat"></p>
</div>
@endsection
