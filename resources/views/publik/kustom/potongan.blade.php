{{--
    Pilihan potongan Pria / Wanita (KEP-161), dari gambar klien: dua tombol lebar
    berikon kaos, lalu gambar pembanding di bawahnya, pria di atas dan wanita di
    bawah. Hanya atasan yang berbeda, jadi pembandingnya selalu pola kaos.

    @var \App\Models\ProductOption $opsi
--}}
<div class="mt-3 grid grid-cols-2 gap-2">
    @foreach ($opsi->values as $v)
        <button type="button" @click="pilihan[{{ $opsi->id }}] = {{ $v->id }}"
                :aria-pressed="pilihan[{{ $opsi->id }}] === {{ $v->id }}"
                class="flex flex-col items-center gap-1.5 rounded-[8px] border px-3 py-4 text-sm transition-colors"
                :class="pilihan[{{ $opsi->id }}] === {{ $v->id }}
                    ? 'border-navy bg-navy-50 font-semibold text-navy-800'
                    : 'border-line hover:border-navy-300'">
            <x-ikon nama="kaos" ukuran="size-5" />
            {{ $v->label }}
            @if ($v->tambahan_harga !== 0)
                <span class="text-xs font-normal text-muted">+ {{ rupiah($v->tambahan_harga) }} / pcs</span>
            @endif
        </button>
    @endforeach
</div>

<figure class="mt-4 rounded-[10px] border border-line bg-paper p-3">
    <figcaption class="text-sm text-muted">Beda potongan pria dan wanita. Harganya sama.</figcaption>
    <div class="mt-3 space-y-4">
        @foreach ($opsi->values as $v)
            @php $model = str_contains(mb_strtolower($v->label), 'wanita') ? 'wanita' : 'pria'; @endphp
            <div class="rounded-[8px] p-2 transition-colors"
                 :class="pilihan[{{ $opsi->id }}] === {{ $v->id }} && 'bg-navy-50 ring-1 ring-navy-200'">
                <p class="text-sm font-semibold">{{ $v->label }}</p>
                <x-pola :pola="\App\Enums\PolaPakaian::KAOS" :model="$model" class="mx-auto mt-1 max-w-xs" />
            </div>
        @endforeach
    </div>
</figure>
