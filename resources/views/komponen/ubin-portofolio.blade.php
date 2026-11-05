@php
    /**
     * Ubin foto portofolio (KEP-106): foto penuh dengan judul di atas bayangan
     * gelap di bagian bawahnya, seperti galeri pada contoh klien. Tanpa foto,
     * judulnya yang mengisi ubin, jadi barisnya tetap rata.
     *
     * @var \App\Models\Portofolio $p
     */
@endphp
<figure class="group relative aspect-4/3 overflow-hidden rounded-[12px] border border-line bg-navy-50">
    @if ($p->gambar_path)
        <img src="{{ asset('storage/'.($p->thumb_path ?: $p->gambar_path)) }}"
             alt="{{ $p->judul }}" loading="lazy" decoding="async"
             class="absolute inset-0 size-full object-cover transition-transform duration-300 group-hover:scale-[1.03] motion-reduce:transition-none">
        <figcaption class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-navy-900/90 via-navy-900/55 to-transparent px-3 pt-10 pb-3 text-white">
            <p class="line-clamp-2 text-sm leading-snug font-semibold">{{ $p->judul }}</p>
            @if ($p->keterangan())
                <p class="mt-0.5 truncate text-xs text-white/85">{{ $p->keterangan() }}</p>
            @endif
        </figcaption>
    @else
        <figcaption class="absolute inset-0 flex flex-col items-center justify-center px-3 text-center">
            <p class="line-clamp-3 text-sm leading-snug font-semibold">{{ $p->judul }}</p>
            @if ($p->keterangan())
                <p class="mt-1 line-clamp-2 text-xs text-muted">{{ $p->keterangan() }}</p>
            @endif
        </figcaption>
    @endif
</figure>
