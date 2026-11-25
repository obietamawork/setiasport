@props(['rasio' => null, 'ukuran' => null, 'format' => 'JPG, PNG, atau WebP'])

{{--
    Keterangan di bawah setiap tempat unggah gambar (KEP-159): rasio dan ukuran
    yang pas untuk tempat gambar itu tampil, lalu format dan batas yang benar-benar
    diterima server (batas_unggah_mb), bukan sekadar angka di Pengaturan.
    Slot berisi saran khusus tempat itu, misalnya di mana tulisan aman diletakkan.
--}}
<p {{ $attributes->merge(['class' => 'mt-1 text-xs leading-relaxed text-muted']) }}>
    @if ($rasio || $ukuran)
        <span class="font-semibold text-ink">{{ collect([$rasio ? 'Rasio '.$rasio : null, $ukuran])->filter()->implode(', ') }}.</span>
    @endif
    @if (trim($slot) !== '')
        {{ $slot }}
    @endif
    {{ $format }}, maksimal {{ batas_unggah_mb() }} MB; dikecilkan otomatis.
</p>
