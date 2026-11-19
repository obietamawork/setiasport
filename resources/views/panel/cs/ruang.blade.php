@extends('layouts.cs')

@section('isi')
{{-- Ruang CS mengikuti gambar klien (KEP-150): tab berangka, kepala percakapan
     dengan kode pesanan dan WhatsApp, Balasan Cepat, lalu kartu Detail
     Pelanggan, Detail Pesanan, dan Catatan Internal. Tetap layar penuh
     (KEP-031): di dalam panel, kolom chat tersisa sempit di laptop. --}}
<div x-data="ruangCs(@js($awal))" class="flex h-full flex-col">

    {{-- Bilah atas. Tipis: yang penting di sini hanya dua saklar yang menentukan
         apakah pelanggan akan dilayani, yaitu "Menerima chat" dan suara. --}}
    <header class="flex h-12 shrink-0 items-center gap-3 bg-navy-900 px-3 text-white sm:px-4">
        <a href="{{ route('panel.dashboard') }}" class="flex items-center gap-3" title="Kembali ke panel">
            <x-logo :terang="true" />
        </a>
        <span class="hidden h-5 w-px bg-white/20 sm:block"></span>
        {{-- Judul halaman; di HP disembunyikan dari mata saja, tidak dari pembaca layar. --}}
        <h1 class="sr-only text-sm font-semibold sm:not-sr-only">Customer Service</h1>

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
    <div class="grid min-h-0 flex-1 grid-cols-1 md:grid-cols-[20rem_minmax(0,1fr)] xl:grid-cols-[20rem_minmax(0,1fr)_20rem]">

        {{-- ================= Daftar percakapan ================= --}}
        <aside class="flex min-h-0 flex-col border-r border-line bg-field" :class="terpilih ? 'hidden md:flex' : 'flex'">
            <div class="space-y-3 border-b border-line p-3">
                {{-- Tab berangka seperti gambar klien: Baru = menunggu balasan,
                     Diproses = sedang ditangani, Selesai = ditutup. --}}
                <div class="grid grid-cols-4 gap-1 rounded-[10px] bg-paper p-1 text-[13px]" role="tablist" aria-label="Saring percakapan">
                    <template x-for="[nilai, label] in [['semua', 'Semua'], ['menunggu', 'Baru'], ['terbuka', 'Diproses'], ['selesai', 'Selesai']]" :key="nilai">
                        <button type="button" role="tab" @click="gantiSaring(nilai)" :aria-selected="(saring === nilai).toString()"
                                class="flex flex-col items-center justify-center rounded-[7px] px-1 py-1.5 leading-tight font-semibold transition-colors"
                                :class="saring === nilai ? 'bg-navy text-white shadow-sm' : 'text-muted hover:bg-field hover:text-ink'">
                            <span x-text="label"></span>
                            <span class="tabular text-[11px] font-medium"
                                  :class="saring === nilai ? 'text-white/80' : (nilai === 'menunggu' && jumlah.menunggu > 0 ? 'text-merah' : '')"
                                  x-text="'(' + (jumlah[nilai] ?? 0) + ')'"></span>
                        </button>
                    </template>
                </div>

                <div class="flex gap-2">
                    <div class="relative min-w-0 flex-1">
                        <label for="cari-cs" class="sr-only">Cari percakapan</label>
                        <x-ikon nama="cari" ukuran="size-4" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted" />
                        <input id="cari-cs" type="search" x-model="cari" placeholder="Cari percakapan..."
                               class="kotak-input py-2 pl-9 text-sm">
                    </div>
                    <div class="relative" @click.outside="saringBuka = false">
                        <button type="button" @click="saringBuka = !saringBuka" :aria-expanded="saringBuka.toString()"
                                class="flex size-10 items-center justify-center rounded-[8px] border border-line-strong text-muted hover:border-navy-300 hover:text-ink"
                                :class="topik && 'border-navy bg-navy-50 text-navy'" title="Saring per topik">
                            <x-ikon nama="saring" ukuran="size-4" />
                            <span class="sr-only">Saring per topik</span>
                        </button>
                        <div x-show="saringBuka" x-cloak x-transition.opacity.duration.100ms
                             class="absolute right-0 z-20 mt-1 w-52 overflow-hidden rounded-[10px] bg-field py-1 shadow-xl shadow-navy/15 ring-1 ring-line">
                            <p class="px-3 pt-1.5 pb-1 text-xs font-semibold text-muted">Topik</p>
                            <template x-for="t in [{ nilai: '', label: 'Semua topik' }, ...awalTopik]" :key="t.nilai">
                                <button type="button" @click="gantiTopik(t.nilai)"
                                        class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-navy-50">
                                    <span x-text="t.label"></span>
                                    <span x-show="topik === t.nilai" class="text-navy" aria-hidden="true">&check;</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="min-h-0 flex-1 overflow-y-auto" :class="memuatDaftar && 'opacity-60'">
                <template x-for="p in daftar" :key="p.id">
                    <li>
                        {{-- Garis tepi kiri: kuning selama pelanggan menunggu dalam batas
                             wajar, merah begitu melewatinya (KEP-031). --}}
                        <button type="button" @click="pilih(p.id)"
                                class="relative flex w-full gap-3 border-b border-line py-3 pr-3 pl-4 text-left transition-colors"
                                :class="terpilih?.id === p.id ? 'bg-navy-50' : 'hover:bg-paper'">
                            <span class="absolute inset-y-0 left-0 w-[3px]"
                                  :class="{ 'bg-kuning': keadaanTunggu(p) === 'menunggu', 'bg-merah': keadaanTunggu(p) === 'terlambat' }"></span>

                            <span class="relative flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                                  :class="p.topik_kode === 'KOMPLAIN' ? 'bg-merah-50 text-merah-tua' : 'bg-navy-100 text-navy-800'"
                                  x-text="inisial(p.nama)"></span>

                            <span class="min-w-0 flex-1">
                                <span class="flex items-baseline gap-2">
                                    <span class="min-w-0 flex-1 truncate font-bold" x-text="p.nama"></span>
                                    <span class="tabular shrink-0 text-xs text-muted" x-text="waktuDaftar(p.pesan_terakhir_pada)"></span>
                                </span>
                                <span class="mt-0.5 block truncate text-sm font-semibold text-ink" x-text="p.topik"></span>
                                <span class="mt-0.5 flex items-center gap-2">
                                    <span class="min-w-0 flex-1 truncate text-sm"
                                          :class="p.belum_dibaca > 0 ? 'font-medium text-ink' : 'text-muted'">
                                        <span x-show="p.cuplikan_dari === 'STAF'" class="text-muted">Anda: </span><span x-text="p.cuplikan"></span>
                                    </span>
                                    <span class="chip shrink-0" :class="labelStatus(p)[1]" x-text="labelStatus(p)[0]"></span>
                                </span>
                                <span x-show="keadaanTunggu(p) !== 'tenang' || p.belum_dibaca > 0" class="mt-1 flex items-center gap-2 text-xs">
                                    <span x-show="keadaanTunggu(p) !== 'tenang'" class="font-semibold"
                                          :class="keadaanTunggu(p) === 'terlambat' ? 'text-merah' : 'text-warn'"
                                          x-text="'menunggu ' + lamaMenunggu(p.menunggu_sejak)"></span>
                                    <span x-show="p.belum_dibaca > 0" class="tabular font-semibold text-navy"
                                          x-text="p.belum_dibaca + ' belum dibaca'"></span>
                                </span>
                            </span>
                        </button>
                    </li>
                </template>

                <li x-show="!daftar.length && !memuatDaftar" class="px-6 py-12 text-center text-sm text-muted">
                    <span x-show="cari || topik">Tidak ada percakapan yang cocok.</span>
                    <span x-show="!cari && !topik && saring === 'menunggu'">Tidak ada yang menunggu balasan.</span>
                    <span x-show="!cari && !topik && saring !== 'menunggu'">Belum ada percakapan di sini.</span>
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
                            <x-ikon nama="kembali" ukuran="size-5" />
                        </button>

                        <span class="hidden size-10 shrink-0 items-center justify-center rounded-full bg-navy-100 text-sm font-bold text-navy-800 sm:flex"
                              x-text="inisial(terpilih.nama)"></span>

                        <div class="min-w-0 flex-1">
                            <p class="truncate font-bold" x-text="terpilih.nama"></p>
                            <p class="truncate text-xs">
                                <span x-show="keadaanTunggu(terpilih) !== 'tenang'" class="font-semibold"
                                      :class="keadaanTunggu(terpilih) === 'terlambat' ? 'text-merah' : 'text-warn'"
                                      x-text="'Menunggu balasan ' + lamaMenunggu(terpilih.menunggu_sejak)"></span>
                                <span x-show="keadaanTunggu(terpilih) === 'tenang'" class="text-muted" x-text="terpilih.topik"></span>
                            </p>
                        </div>

                        {{-- Kode pesanan sebagai cip, seperti gambar klien; membuka detail pesanan. --}}
                        <template x-if="terpilih.order">
                            <a :href="terpilih.order.url" target="_blank" rel="noopener"
                               class="hidden shrink-0 items-center gap-1.5 rounded-[8px] border border-line-strong px-2.5 py-1.5 text-sm font-semibold hover:border-navy-300 hover:bg-navy-50 sm:flex"
                               :title="'Buka pesanan ' + terpilih.order.kode">
                                <x-ikon nama="pesanan" ukuran="size-4" class="text-navy-400" />
                                <span class="tabular" x-text="terpilih.order.kode"></span>
                            </a>
                        </template>

                        <a x-show="terpilih.pelanggan?.wa" :href="terpilih.pelanggan?.wa" target="_blank" rel="noopener"
                           class="tombol-ikon shrink-0" title="Chat WhatsApp pelanggan">
                            <x-ikon nama="whatsapp" ukuran="size-5" class="text-ok" />
                            <span class="sr-only">Chat WhatsApp pelanggan</span>
                        </a>

                        <a x-show="terpilih.order || terpilih.pelanggan?.riwayat_url" :href="terpilih.order?.url ?? terpilih.pelanggan?.riwayat_url"
                           target="_blank" rel="noopener" class="tombol-ikon hidden shrink-0 sm:flex" title="Buka di tab baru">
                            <x-ikon nama="buka-luar" ukuran="size-4" />
                            <span class="sr-only">Buka pesanan di tab baru</span>
                        </a>

                        <button type="button" @click="infoTerbuka = !infoTerbuka" class="tombol tombol-halus tombol-sm shrink-0 xl:hidden" :aria-expanded="infoTerbuka">
                            Info<span class="hidden sm:inline">&nbsp;pelanggan</span>
                        </button>

                        <details class="relative shrink-0" x-data @click.outside="$el.removeAttribute('open')">
                            <summary class="tombol-ikon" title="Aksi lain">
                                <x-ikon nama="titik-tiga" ukuran="size-4" />
                                <span class="sr-only">Aksi lain</span>
                            </summary>
                            <div class="menu-tarik menu-aksi-isi right-0 w-56">
                                <button type="button" x-show="terpilih.status === 'TERBUKA'" @click="$el.closest('details').removeAttribute('open'); tutup()">
                                    <x-ikon nama="centang" ukuran="size-4" class="text-navy-400" /> Tandai selesai
                                </button>
                                <p x-show="terpilih.status === 'SELESAI'" class="px-3 py-2 text-sm text-muted">Percakapan sudah selesai.</p>
                                <a href="{{ route('panel.cs.template.index') }}">
                                    <x-ikon nama="daftar" ukuran="size-4" class="text-navy-400" /> Kelola balasan cepat
                                </a>
                            </div>
                        </details>
                    </div>

                    <div x-ref="daftarPesan" class="min-h-0 flex-1 overflow-y-auto px-3 py-4 sm:px-6" :class="memuatPercakapan && 'opacity-60'">
                        <ol class="mx-auto max-w-3xl">
                            <template x-for="(p, i) in pesan" :key="p.id">
                                <li>
                                    <p x-show="pemisah(i)" class="my-4 text-center">
                                        <span class="rounded-full bg-field px-3 py-1 text-xs font-medium text-muted shadow-[0_0_0_1px_var(--color-line)]" x-text="pemisah(i)"></span>
                                    </p>

                                    <p x-show="p.pengirim === 'SISTEM'" class="my-3 text-center text-xs text-muted" x-text="p.isi"></p>

                                    <div x-show="p.pengirim !== 'SISTEM'" class="flex items-end gap-2"
                                         :class="[p.pengirim === 'STAF' ? 'justify-end' : 'justify-start', awalRentetan(i) ? 'mt-3' : 'mt-1']">
                                        <span x-show="p.pengirim !== 'STAF'" class="flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                              :class="awalRentetan(i) ? 'bg-navy-100 text-navy-800' : 'invisible'" x-text="inisial(p.nama)" aria-hidden="true"></span>
                                        <div class="max-w-[78%]">
                                            <p x-show="awalRentetan(i)" class="mb-1 text-xs font-semibold"
                                               :class="p.pengirim === 'STAF' ? 'text-right text-navy' : 'text-muted'" x-text="p.nama"></p>
                                            <div class="relative overflow-hidden text-[15px] leading-relaxed"
                                                 :class="p.pengirim === 'STAF'
                                                    ? 'rounded-[14px] rounded-br-[4px] bg-navy text-white'
                                                    : 'rounded-[14px] rounded-bl-[4px] bg-field shadow-[0_0_0_1px_var(--color-line)]'">
                                                <template x-if="p.lampiran">
                                                    <div class="relative">
                                                        <a :href="p.lampiran" target="_blank" rel="noopener" class="block">
                                                            <img :src="p.lampiran" alt="Foto lampiran" loading="lazy" class="max-h-72 w-full object-cover">
                                                        </a>
                                                        {{-- Unduh desain dari pelanggan sebagai berkas (KEP-150). --}}
                                                        <a :href="p.lampiran + '?unduh=1'" download
                                                           class="absolute top-2 right-2 flex size-8 items-center justify-center rounded-full bg-white/90 text-ink shadow hover:bg-white"
                                                           title="Unduh foto">
                                                            <x-ikon nama="unduh" ukuran="size-4" />
                                                            <span class="sr-only">Unduh foto</span>
                                                        </a>
                                                    </div>
                                                </template>
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
                        {{-- Balasan Cepat seperti gambar klien: template teratas sebagai
                             cip. Daftar lengkap yang bisa dicari tetap lewat "Lainnya"
                             atau "/" di awal kolom (KEP-031). --}}
                        <div class="mb-2.5 rounded-[10px] border border-line bg-paper px-3 py-2" x-show="template.length">
                            <p class="flex items-center gap-1.5 text-xs font-semibold text-muted">
                                <x-ikon nama="chat" ukuran="size-3.5" /> Balasan Cepat
                            </p>
                            {{-- Di HP sebaris yang bisa digeser, supaya ruang pesan tidak termakan. --}}
                            <div class="geser-x mt-1.5 flex gap-1.5 sm:flex-wrap">
                                <template x-for="t in templateCepat" :key="t.id">
                                    <button type="button" @click="pakaiTemplate(t)" :title="t.isi"
                                            class="shrink-0 rounded-full border border-line-strong bg-field px-3 py-1 text-sm whitespace-nowrap hover:border-navy-300 hover:bg-navy-50"
                                            x-text="t.judul"></button>
                                </template>
                                <button type="button" x-show="template.length > templateCepat.length" @click="bukaTemplate()"
                                        class="shrink-0 rounded-full px-3 py-1 text-sm font-medium whitespace-nowrap text-navy hover:bg-navy-50">Lainnya&hellip;</button>
                            </div>
                        </div>

                        <div x-show="popover" x-cloak @click.outside="popover = false" x-transition.opacity.duration.100ms
                             class="absolute bottom-full left-3 z-10 mb-2 w-[min(24rem,calc(100%-1.5rem))] overflow-hidden rounded-[12px] bg-field shadow-xl shadow-navy/15 ring-1 ring-line sm:left-5">
                            <div class="border-b border-line p-2">
                                <input x-ref="cariTemplate" x-model="cariTemplate" @keydown="tombol($event)" type="search"
                                       placeholder="Cari balasan cepat" class="kotak-input py-1.5 text-sm">
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
                                    Tidak ada balasan cepat yang cocok.
                                </li>
                            </ul>
                            <a href="{{ route('panel.cs.template.index') }}" class="block border-t border-line px-3 py-2 text-xs text-muted hover:text-ink">
                                Kelola balasan cepat
                            </a>
                        </div>

                        <p x-show="lampiran" x-cloak class="mb-2 flex items-center gap-2 text-xs text-muted">
                            Foto: <span class="truncate" x-text="lampiran?.name"></span>
                            <button type="button" class="tautan" @click="lampiran = null; $refs.berkas.value = ''">batal</button>
                        </p>

                        <div class="flex items-end gap-2">
                            <label class="shrink-0 cursor-pointer rounded-[8px] border border-line-strong p-2 text-muted hover:border-navy-300 hover:text-ink" title="Lampirkan foto">
                                <input type="file" accept="image/*" class="sr-only" x-ref="berkas" @change="pilihLampiran($event)">
                                <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true">
                                    <path d="M16.5 9.5 10 16a4 4 0 0 1-5.7-5.7l7-7a2.6 2.6 0 0 1 3.7 3.7l-6.9 6.9a1.3 1.3 0 0 1-1.9-1.8L12.5 6"/>
                                </svg>
                                <span class="sr-only">Lampirkan foto</span>
                            </label>

                            <div class="relative min-w-0 flex-1">
                                <label for="isian-cs" class="sr-only">Balasan</label>
                                <textarea id="isian-cs" x-ref="isian" x-model="isi" @input="saatMengetik()" @keydown="tombol($event)" rows="1" maxlength="2000"
                                          class="kotak-input min-h-10 w-full resize-none overflow-hidden py-2 pr-10 text-[15px] leading-relaxed"
                                          placeholder="Ketik pesan..."></textarea>
                                <div class="absolute right-1.5 bottom-1.5" @click.outside="emojiBuka = false">
                                    <button type="button" @click="emojiBuka = !emojiBuka" :aria-expanded="emojiBuka.toString()"
                                            class="rounded-[6px] p-1 text-muted hover:bg-navy-50 hover:text-ink" title="Sisipkan emoji">
                                        <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
                                            <circle cx="10" cy="10" r="7"/><path d="M7.2 11.8a3.3 3.3 0 0 0 5.6 0"/><path d="M7.5 8h.01M12.5 8h.01" stroke-width="2.2"/>
                                        </svg>
                                        <span class="sr-only">Sisipkan emoji</span>
                                    </button>
                                    <div x-show="emojiBuka" x-cloak x-transition.opacity.duration.100ms
                                         class="absolute right-0 bottom-full z-10 mb-2 grid w-52 grid-cols-6 gap-1 rounded-[10px] bg-field p-2 shadow-xl shadow-navy/15 ring-1 ring-line">
                                        <template x-for="e in emoji" :key="e">
                                            <button type="button" @click="sisipkan(e)" class="rounded-[6px] p-1 text-xl hover:bg-navy-50" x-text="e"></button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="tombol tombol-navy shrink-0 px-3" :disabled="mengirim || (!isi.trim() && !lampiran)" title="Kirim">
                                <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M17.5 2.5 9 11M17.5 2.5l-5.5 15-3-6.5-6.5-3z"/>
                                </svg>
                                <span class="sr-only" x-text="mengirim ? 'Mengirim' : 'Kirim'">Kirim</span>
                            </button>
                        </div>
                        <p class="mt-1.5 hidden text-[11px] text-muted sm:block">Enter untuk kirim, Shift+Enter untuk baris baru, ketik / di awal untuk balasan cepat.</p>
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
               class="min-h-0 overflow-y-auto border-l border-line bg-paper"
               :class="infoTerbuka ? 'fixed inset-y-0 right-0 z-30 w-[min(21rem,92vw)] shadow-2xl xl:static xl:w-auto xl:shadow-none' : 'hidden xl:block'">
            <template x-if="terpilih">
                <div class="space-y-3 p-3">
                    <div class="flex items-center justify-between px-1 xl:hidden">
                        <p class="font-semibold">Info pelanggan</p>
                        <button type="button" @click="infoTerbuka = false" class="rounded-[7px] p-1.5 text-muted hover:bg-navy-50" aria-label="Tutup info">
                            <x-ikon nama="silang" ukuran="size-5" />
                        </button>
                    </div>

                    {{-- Detail Pelanggan --}}
                    <section class="rounded-[12px] border border-line bg-field p-4">
                        <h2 class="flex items-center gap-2 font-bold"><x-ikon nama="orang" ukuran="size-5" class="text-navy-400" /> Detail Pelanggan</h2>
                        <dl class="mt-3 grid grid-cols-[6.75rem_minmax(0,1fr)] gap-x-2 gap-y-1.5 text-sm">
                            <dt class="text-muted">Nama</dt>
                            <dd class="font-medium break-words" x-text="terpilih.pelanggan?.nama"></dd>
                            <dt class="text-muted">No. WhatsApp</dt>
                            <dd class="flex items-center gap-1.5">
                                <span class="tabular whitespace-nowrap" x-text="terpilih.pelanggan?.no_hp ?? '-'"></span>
                                <a x-show="terpilih.pelanggan?.wa" :href="terpilih.pelanggan?.wa" target="_blank" rel="noopener" title="Chat WhatsApp">
                                    <x-ikon nama="whatsapp" ukuran="size-4" class="text-ok" />
                                </a>
                            </dd>
                            <dt class="text-muted">Email</dt>
                            <dd class="break-all" x-text="terpilih.pelanggan?.email ?? '-'"></dd>
                            <dt class="text-muted">Pelanggan sejak</dt>
                            <dd x-text="terpilih.pelanggan?.sejak ?? 'Tamu, tanpa akun'"></dd>
                            <dt class="text-muted">Total pesanan</dt>
                            <dd class="tabular" x-text="terpilih.pelanggan?.total_pesanan + ' pesanan'"></dd>
                        </dl>
                        {{-- Panel belum punya halaman profil pelanggan: tombol ini membuka
                             daftar Pesanan yang tersaring kontak pelanggan ini. --}}
                        <a x-show="terpilih.pelanggan?.riwayat_url" :href="terpilih.pelanggan?.riwayat_url" target="_blank" rel="noopener"
                           class="tombol tombol-garis tombol-sm mt-3 w-full">
                            <x-ikon nama="orang" ukuran="size-4" /> Lihat riwayat pesanan
                        </a>
                    </section>

                    {{-- Detail Pesanan --}}
                    <section class="rounded-[12px] border border-line bg-field p-4">
                        <h2 class="flex items-center gap-2 font-bold"><x-ikon nama="produk" ukuran="size-5" class="text-navy-400" /> Detail Pesanan</h2>

                        <template x-if="terpilih.order">
                            <div class="mt-3">
                                <div class="flex flex-wrap items-center justify-between gap-2 rounded-[8px] bg-paper px-3 py-2">
                                    <span class="tabular font-bold" x-text="terpilih.order.kode"></span>
                                    <span class="chip" :class="terpilih.order.status_warna" x-text="terpilih.order.status"></span>
                                </div>
                                <dl class="mt-3 grid grid-cols-[6.75rem_minmax(0,1fr)] gap-x-2 gap-y-1.5 text-sm">
                                    <dt class="text-muted">Jenis</dt>
                                    <dd x-text="terpilih.order.jenis + (terpilih.order.barang ? ', ' + terpilih.order.barang : '')"></dd>
                                    <dt class="text-muted">Jumlah</dt>
                                    <dd class="tabular" x-text="terpilih.order.jumlah"></dd>
                                    <dt class="text-muted">Tanggal pesan</dt>
                                    <dd class="tabular" x-text="terpilih.order.tanggal"></dd>
                                    <dt class="text-muted">Target selesai</dt>
                                    <dd class="tabular" x-text="terpilih.order.target ?? '-'"></dd>
                                    <dt class="text-muted">Sisa tagihan</dt>
                                    <dd class="tabular font-semibold" :class="terpilih.order.sisa > 0 ? 'text-merah' : 'text-ok'"
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

                                <a :href="terpilih.order.url" target="_blank" rel="noopener" class="tombol tombol-garis tombol-sm mt-3 w-full">
                                    <x-ikon nama="buka-luar" ukuran="size-4" /> Lihat detail pesanan
                                </a>
                            </div>
                        </template>

                        <p x-show="!terpilih.order" class="mt-2 text-sm text-muted">Belum ada pesanan yang ditautkan.</p>

                        <template x-if="terpilih.pesanan_lain.length">
                            <ul class="mt-3 space-y-1 border-t border-line pt-3 text-sm">
                                <template x-for="o in terpilih.pesanan_lain" :key="o.kode">
                                    <li class="flex justify-between gap-3">
                                        <a :href="o.url" target="_blank" rel="noopener" class="tautan tabular" x-text="o.kode"></a>
                                        <span class="truncate text-muted" x-text="o.status"></span>
                                    </li>
                                </template>
                            </ul>
                        </template>

                        <form @submit.prevent="tautkan()" class="mt-3 flex gap-2">
                            <label for="kode-tautan" class="sr-only">Kode pesanan</label>
                            <input id="kode-tautan" x-model="kodeTautan" class="kotak-input tabular py-1.5 text-sm uppercase" placeholder="SS-2611-0001" maxlength="20">
                            <button type="submit" class="tombol tombol-garis tombol-sm shrink-0">Tautkan</button>
                        </form>
                    </section>

                    {{-- Catatan Internal: hanya untuk staf, tidak pernah sampai ke pelanggan. --}}
                    <section class="rounded-[12px] border border-line bg-field p-4">
                        <h2 class="flex items-center gap-2 font-bold"><x-ikon nama="daftar" ukuran="size-5" class="text-navy-400" /> Catatan Internal</h2>
                        <label for="catatan-internal" class="sr-only">Catatan internal</label>
                        <textarea id="catatan-internal" x-model="catatan" rows="4" maxlength="2000"
                                  class="kotak-input mt-3 text-sm leading-relaxed"
                                  placeholder="Catatan untuk tim. Pelanggan tidak bisa melihatnya."></textarea>
                        <button type="button" @click="simpanCatatan()" :disabled="menyimpanCatatan || catatan === (terpilih.catatan_internal ?? '')"
                                class="tombol tombol-navy tombol-sm mt-2 w-full">
                            <span x-text="menyimpanCatatan ? 'Menyimpan' : (catatan === (terpilih.catatan_internal ?? '') && catatan ? 'Tersimpan' : 'Simpan catatan')"></span>
                        </button>
                    </section>

                    <p class="px-1 text-xs text-muted">
                        Dimulai <span class="tabular" x-text="terpilih.dibuat && new Date(terpilih.dibuat).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })"></span>,
                        topik <span x-text="terpilih.topik"></span>.
                    </p>
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
