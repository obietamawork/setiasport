{{--
    Chat CS (KEP-030). Klien ingin pelanggan bertanya tanpa meninggalkan
    website, dan menetapkan komplain hanya dilayani lewat sini.

    Keadaan awal dihitung di server supaya tombol bisa langsung menampilkan
    tanda "ada balasan" tanpa satu permintaan pun. Cookie percakapannya
    httpOnly, jadi JavaScript memang tidak bisa membacanya sendiri.
--}}
@php
    $csPercakapan = \App\Models\PercakapanCs::dariToken(request()->cookie('cs_percakapan'));
    $csPelanggan = auth('pelanggan')->user();

    $csAwal = [
        'channel' => $csPercakapan?->channel(),
        'status' => $csPercakapan?->status,
        'belumDibaca' => $csPercakapan?->belum_dibaca_pelanggan ?? 0,
        // Ada staf yang sedang membuka ruang CS dengan "Menerima chat" menyala (KEP-031).
        'online' => app(\App\Services\LayananCs::class)->adaYangHadir(),
        // Dibuka dari halaman pesanan bertoken: pesanannya ikut tertaut dan terverifikasi.
        'tokenPesanan' => request()->routeIs('lacak.token') ? request()->route('token') : null,
        'realtime' => config('broadcasting.default') === 'pusher' ? config('broadcasting.klien') : null,
        'url' => [
            'status' => route('cs.status'),
            'mulai' => route('cs.mulai'),
            'kirim' => route('cs.kirim'),
            'pesan' => route('cs.pesan'),
            'auth' => route('cs.auth'),
        ],
    ];
@endphp

<div x-data="widgetCs(@js($csAwal))" @keydown.escape.window="buka && bukaTutup(false)" class="print:hidden">

    {{-- Tombol. Di layar sempit cukup ikon dan satu kata: ia mengambang di
         atas konten, jadi tidak boleh menutupi lebih dari yang perlu. --}}
    <button type="button" @click="bukaTutup()" x-show="!buka"
            class="fixed right-4 bottom-4 z-40 flex items-center gap-2 rounded-full bg-navy px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-navy/25 transition-transform hover:-translate-y-0.5 sm:right-6 sm:bottom-6"
            aria-label="Buka chat dengan CS">
        <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <path d="M4 4.5h12a1.5 1.5 0 0 1 1.5 1.5v7A1.5 1.5 0 0 1 16 14.5H9l-4 3v-3H4A1.5 1.5 0 0 1 2.5 13V6A1.5 1.5 0 0 1 4 4.5Z" stroke-linejoin="round"/>
        </svg>
        <span>Chat CS</span>
        <span x-show="belumDibaca > 0" x-cloak
              class="tabular -mr-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-merah px-1 text-xs"
              x-text="belumDibaca"></span>
    </button>

    {{-- Jendela. Layar penuh di HP, panel kecil di layar lebar. --}}
    <section x-show="buka" x-cloak x-transition.opacity
             class="fixed inset-0 z-40 flex flex-col bg-field sm:inset-auto sm:right-6 sm:bottom-6 sm:h-[36rem] sm:max-h-[calc(100vh-3rem)] sm:w-[23rem] sm:overflow-hidden sm:rounded-[12px] sm:border sm:border-line sm:shadow-2xl sm:shadow-navy/20"
             role="dialog" aria-modal="false" aria-labelledby="judul-cs">

        <header class="relative shrink-0 bg-navy px-4 pt-4 pb-3 text-white">
            <span class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-merah via-merah to-kuning"></span>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p id="judul-cs" class="font-bold">CS {{ setting('toko_nama', 'Setia Sport') }}</p>
                    {{-- Janji yang jujur lebih baik daripada diam: pelanggan tahu
                         apakah ada yang sedang membaca, atau kapan akan dibalas. --}}
                    <p x-show="online" class="mt-0.5 flex items-center gap-1.5 text-xs text-white/80">
                        <span class="size-2 rounded-full bg-emerald-400"></span>
                        Online, biasanya membalas dalam beberapa menit
                    </p>
                    <p x-show="!online" x-cloak class="mt-0.5 text-xs text-white/70">
                        Sedang offline. Pesan tetap kami terima dan dibalas pada jam operasional,
                        {{ Str::lcfirst((string) setting('cs_jam_layanan')) }}.
                    </p>
                </div>
                <button type="button" @click="bukaTutup(false)" class="-mr-1 rounded-[7px] p-1.5 text-white/80 hover:bg-white/10 hover:text-white"
                        aria-label="Tutup chat">
                    <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                        <path d="M5 5l10 10M15 5L5 15"/>
                    </svg>
                </button>
            </div>
        </header>

        <div x-ref="daftar" class="min-h-0 flex-1 overflow-y-auto bg-paper px-4 py-4">

            <p x-show="tahap === 'memuat'" class="py-10 text-center text-sm text-muted">Memuat percakapan...</p>

            {{-- Sapaan selalu tampil di atas, termasuk sebelum percakapan dimulai. --}}
            <div x-show="tahap === 'mulai' || tahap === 'obrolan'" class="mb-3 flex">
                <p class="max-w-[85%] rounded-[12px] rounded-tl-[4px] border border-line bg-field px-3 py-2 text-sm leading-relaxed">
                    {{ setting('cs_sapaan') }}
                </p>
            </div>

            {{-- Formulir pembuka. --}}
            <form x-show="tahap === 'mulai'" @submit.prevent="mulai()" class="space-y-3">
                <fieldset>
                    <legend class="label-form">Mau tanya soal apa?</legend>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (\App\Enums\TopikCs::cases() as $t)
                            <label class="cursor-pointer">
                                <input type="radio" name="topik" value="{{ $t->value }}" x-model="form.topik" class="peer sr-only" required>
                                <span class="inline-block rounded-full border border-line-strong bg-field px-3 py-1.5 text-sm transition-colors peer-checked:border-navy peer-checked:bg-navy peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-navy-300">
                                    {{ $t->label() }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                @unless ($csPelanggan)
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="cs-nama" class="label-form">Nama</label>
                            <input id="cs-nama" x-model="form.nama" class="kotak-input" maxlength="100" required autocomplete="name">
                        </div>
                        <div>
                            <label for="cs-kontak" class="label-form">Email / No. HP</label>
                            <input id="cs-kontak" x-model="form.kontak" class="kotak-input" maxlength="100" required autocomplete="email">
                        </div>
                    </div>
                @endunless

                <div x-show="form.topik === 'KOMPLAIN' && !@js($csAwal['tokenPesanan'])">
                    <label for="cs-kode" class="label-form">Kode pesanan</label>
                    <input id="cs-kode" x-model="form.kode_pesanan" class="kotak-input tabular uppercase" maxlength="20" placeholder="SS-2609-0001">
                </div>

                <div>
                    <label for="cs-isi-awal" class="label-form">Pesan</label>
                    <textarea id="cs-isi-awal" x-model="form.isi" @keydown="tombol($event)" rows="3" maxlength="2000"
                              class="kotak-input leading-relaxed" placeholder="Tulis pertanyaan Anda"></textarea>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <label class="cursor-pointer text-sm text-muted hover:text-ink">
                        <input type="file" accept="image/*" class="sr-only" x-ref="berkasAwal" @change="pilihLampiran($event)">
                        <span x-text="lampiran ? lampiran.name : '+ Lampirkan foto'"></span>
                    </label>
                    <button type="submit" class="tombol tombol-utama" :disabled="mengirim">
                        <span x-text="mengirim ? 'Mengirim...' : 'Kirim'">Kirim</span>
                    </button>
                </div>

                <p class="text-xs leading-relaxed text-muted">
                    Percakapan ini disimpan sebagai catatan layanan, termasuk untuk menangani komplain.
                </p>
            </form>

            {{-- Percakapan. --}}
            <ol x-show="tahap === 'obrolan'" class="space-y-3" aria-live="polite">
                <template x-for="p in pesan" :key="p.id">
                    <li :class="{
                            'flex justify-end': p.pengirim === 'PELANGGAN',
                            'flex': p.pengirim === 'STAF',
                            'text-center': p.pengirim === 'SISTEM',
                        }">
                        <p x-show="p.pengirim === 'SISTEM'" class="mx-auto inline-block rounded-full bg-navy-50 px-3 py-1 text-xs text-muted" x-text="p.isi"></p>

                        <div x-show="p.pengirim !== 'SISTEM'" class="max-w-[85%]">
                            <p x-show="p.pengirim === 'STAF'" class="mb-0.5 text-xs font-semibold text-navy" x-text="p.nama"></p>
                            <div :class="p.pengirim === 'PELANGGAN'
                                    ? 'rounded-[12px] rounded-tr-[4px] bg-navy text-white'
                                    : 'rounded-[12px] rounded-tl-[4px] border border-line bg-field'"
                                 class="overflow-hidden text-sm leading-relaxed">
                                <a x-show="p.lampiran" :href="p.lampiran" target="_blank" rel="noopener" class="block">
                                    <img :src="p.lampiran" alt="Foto lampiran" loading="lazy" class="max-h-56 w-full object-cover">
                                </a>
                                {{-- x-text, bukan x-html: isi pesan datang dari orang asing. --}}
                                <p x-show="p.isi" class="px-3 py-2 break-words whitespace-pre-line" x-text="p.isi"></p>
                            </div>
                            <p class="tabular mt-0.5 text-[11px] text-muted"
                               :class="p.pengirim === 'PELANGGAN' ? 'text-right' : ''" x-text="waktu(p.waktu)"></p>
                        </div>
                    </li>
                </template>
            </ol>
        </div>

        {{-- Kolom tulis. --}}
        <form x-show="tahap === 'obrolan'" @submit.prevent="kirim()" class="shrink-0 border-t border-line bg-field p-3">
            <p x-show="percakapan && percakapan.status === 'SELESAI'" class="mb-2 text-xs text-muted">
                Percakapan sudah ditutup. Kirim pesan untuk membukanya kembali.
            </p>

            <div class="flex items-end gap-2">
                <label class="shrink-0 cursor-pointer rounded-[7px] p-2 text-muted hover:bg-navy-50 hover:text-ink" title="Lampirkan foto">
                    <input type="file" accept="image/*" class="sr-only" x-ref="berkas" @change="pilihLampiran($event)">
                    <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true">
                        <path d="M16.5 9.5 10 16a4 4 0 0 1-5.7-5.7l7-7a2.6 2.6 0 0 1 3.7 3.7l-6.9 6.9a1.3 1.3 0 0 1-1.9-1.8L12.5 6"/>
                    </svg>
                    <span class="sr-only">Lampirkan foto</span>
                </label>

                <label for="cs-isi" class="sr-only">Tulis pesan</label>
                <textarea id="cs-isi" x-model="form.isi" @keydown="tombol($event)" rows="1" maxlength="2000"
                          class="kotak-input max-h-28 min-h-10 flex-1 resize-none py-2 leading-relaxed"
                          placeholder="Tulis pesan"></textarea>

                <button type="submit" class="tombol tombol-navy shrink-0 px-3" :disabled="mengirim || (!form.isi.trim() && !lampiran)"
                        aria-label="Kirim pesan">
                    <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M3.1 3.3a.75.75 0 0 1 .8-.1l13 6.1a.75.75 0 0 1 0 1.4l-13 6.1a.75.75 0 0 1-1-.9L4.6 10 2.9 4.1a.75.75 0 0 1 .2-.8ZM6 10.75l-1.2 4.2L14.9 10 4.8 5.05 6 9.25h5a.75.75 0 0 1 0 1.5H6Z"/>
                    </svg>
                </button>
            </div>

            <p x-show="lampiran" x-cloak class="mt-1.5 truncate text-xs text-muted">
                Foto: <span x-text="lampiran?.name"></span>
                <button type="button" class="tautan ml-1" @click="lampiran = null; $refs.berkas.value = ''">batal</button>
            </p>
        </form>

        <p x-show="galat" x-cloak class="shrink-0 border-t border-merah/30 bg-merah-50 px-4 py-2 text-sm" x-text="galat"></p>

        {{-- Alasan klien membuat chat ini: penipu yang menyamar sebagai toko. --}}
        <p class="shrink-0 border-t border-line bg-field px-4 py-2 text-[11px] leading-snug text-muted">
            CS resmi kami hanya di sini. Pembayaran hanya ke rekening yang tertera di halaman pesanan Anda.
        </p>
    </section>
</div>
