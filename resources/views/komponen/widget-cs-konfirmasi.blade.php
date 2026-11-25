{{--
    Lembar konfirmasi "Mulai percakapan baru?" (KEP-163), muncul dari bawah di
    dalam jendela chat. Pengganti dialog bawaan peramban yang berjudul alamat
    situs dan tidak bisa diberi gaya. Fokus awal di Batal: Enter yang kebablasan
    tidak boleh langsung menutup percakapan yang sedang berjalan.
--}}
<div x-show="konfirmasiBaru" x-cloak class="absolute inset-0 z-10 flex items-end"
     role="dialog" aria-modal="true" aria-labelledby="judul-cs-baru" aria-describedby="isi-cs-baru">
    <div x-show="konfirmasiBaru" x-transition.opacity class="absolute inset-0 bg-navy-900/45"
         @click="konfirmasiBaru = false" aria-hidden="true"></div>

    <div x-show="konfirmasiBaru"
         x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
         x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
         class="relative w-full rounded-t-[16px] bg-field px-5 pt-5 pb-5 shadow-2xl shadow-navy/30">
        <span class="mx-auto mb-4 block h-1 w-10 rounded-full bg-line-strong" aria-hidden="true"></span>

        <div class="flex items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-navy-50 text-navy">
                <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 4.5h12a1.5 1.5 0 0 1 1.5 1.5v7A1.5 1.5 0 0 1 16 14.5H9l-4 3v-3H4A1.5 1.5 0 0 1 2.5 13V6A1.5 1.5 0 0 1 4 4.5Z"/>
                    <path d="M10 7.5v4M8 9.5h4"/>
                </svg>
            </span>
            <div class="min-w-0">
                <p id="judul-cs-baru" class="font-bold">Mulai percakapan baru?</p>
                <p id="isi-cs-baru" class="mt-1 text-sm leading-relaxed text-muted">
                    Percakapan ini akan ditutup. Riwayatnya tetap tersimpan, dan CS kami masih bisa membacanya.
                </p>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-2">
            <button type="button" x-ref="batalBaru" @click="konfirmasiBaru = false" class="tombol tombol-garis">Batal</button>
            <button type="button" @click="mulaiBaru(true)" :disabled="mengirim" class="tombol tombol-navy">
                <span x-text="mengirim ? 'Memulai...' : 'Ya, mulai baru'">Ya, mulai baru</span>
            </button>
        </div>
    </div>
</div>
