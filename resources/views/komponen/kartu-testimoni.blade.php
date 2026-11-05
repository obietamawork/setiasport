@php
    /**
     * Kartu testimoni (KEP-106): lingkaran inisial, nama, bintang, lalu
     * kutipannya, seperti contoh dari klien. Inisial, bukan foto: testimoni
     * ditulis ulang admin dari pesan WhatsApp, dan foto pelanggan tidak ada.
     *
     * @var \App\Models\Testimoni $t
     * @var bool|null $ringkas  potong kutipan panjang (beranda)
     */
    $ringkas ??= false;
@endphp
<figure class="kartu flex h-full flex-col p-5 sm:p-6">
    <figcaption class="flex items-center gap-3">
        <span aria-hidden="true"
              class="flex size-11 shrink-0 items-center justify-center rounded-full bg-navy text-sm font-bold tracking-wide text-white">
            {{ $t->inisial() }}
        </span>
        <span class="min-w-0">
            <span class="block truncate font-semibold">{{ $t->nama }}</span>
            @if ($t->peran)
                <span class="block truncate text-sm text-muted">{{ $t->peran }}</span>
            @endif
        </span>
    </figcaption>

    @if ($t->bintang)
        {{-- role="img": aria-label pada div biasa diabaikan pembaca layar. Lima
             tempat selalu digambar supaya 4 bintang terbaca 4 dari 5, bukan
             barisan yang sekadar lebih pendek. --}}
        <div class="mt-4 flex gap-0.5" role="img" aria-label="Nilai {{ $t->bintang }} dari 5">
            @for ($i = 1; $i <= 5; $i++)
                <x-ikon nama="testimoni" ukuran="size-4" fill="currentColor"
                        :class="$i <= $t->bintang ? 'text-kuning' : 'text-line-strong'" />
            @endfor
        </div>
    @endif

    <blockquote @class(['mt-3 flex-1 leading-relaxed text-ink', 'line-clamp-6' => $ringkas])>
        &ldquo;{{ $t->isi }}&rdquo;
    </blockquote>
</figure>
