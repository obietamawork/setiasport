{{-- Satu isian pengaturan. Semua isian menunjuk ke formulir yang sama lewat
     atribut form, jadi boleh diletakkan di tab mana pun, dan kartu QRIS (yang
     punya formulir unggahnya sendiri) bisa berada di tengah-tengahnya. --}}
@php
    // Satuan di akhir label, misalnya "(hari)", pindah ke samping isian.
    preg_match('/^(.*?)\s*\(([^)]+)\)\s*$/', $s->label, $m);
    $label = $m[1] ?? $s->label;
    $satuan = $m[2] ?? null;
    $nama = 'setting['.$s->key.']';
    $id = 's-'.$s->key;
    $nilai = old('setting.'.$s->key, $s->value);
@endphp

<div>
    @if ($s->tipe === 'bool')
        <input type="hidden" name="{{ $nama }}" value="0" form="form-pengaturan">
        <label for="{{ $id }}" class="flex cursor-pointer items-start gap-3">
            <input type="checkbox" id="{{ $id }}" name="{{ $nama }}" value="1" form="form-pengaturan"
                   class="peer sr-only" @checked((bool) $nilai)
                   @if ($s->key === 'cs_aktif') x-model="csAktif" @endif>
            <span class="relative mt-0.5 h-6 w-11 shrink-0 rounded-full bg-line-strong transition-colors
                         after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition-transform
                         peer-checked:bg-navy peer-checked:after:translate-x-5
                         peer-focus-visible:ring-2 peer-focus-visible:ring-navy-300"></span>
            <span>
                <span class="font-medium">{{ $label }}</span>
                @if ($s->deskripsi)
                    <span class="mt-0.5 block text-xs leading-relaxed text-muted">{{ $s->deskripsi }}</span>
                @endif
            </span>
        </label>
    @else
        <label for="{{ $id }}" class="label-form">
            {{ $label }}
            @if ($satuan && $s->tipe !== 'int')
                <span class="font-normal text-muted">({{ $satuan }})</span>
            @endif
        </label>

        @if ($s->tipe === 'teks')
            <textarea id="{{ $id }}" name="{{ $nama }}" form="form-pengaturan"
                      rows="{{ $s->key === 'tentang_lengkap' ? 8 : (str_contains($s->key, 'jam_buka') ? 3 : 4) }}"
                      class="kotak-input leading-relaxed">{{ $nilai }}</textarea>
        @elseif ($s->tipe === 'int')
            <div class="flex items-center gap-2">
                <input type="number" id="{{ $id }}" name="{{ $nama }}" form="form-pengaturan" value="{{ $nilai }}"
                       min="0" class="kotak-input tabular max-w-32">
                @if ($satuan)
                    <span class="text-sm text-muted">{{ $satuan }}</span>
                @endif
            </div>
        @else
            <input type="text" id="{{ $id }}" name="{{ $nama }}" form="form-pengaturan" value="{{ $nilai }}"
                   class="kotak-input {{ str_contains($s->key, 'rekening') ? 'tabular' : '' }}">
        @endif

        @if ($s->deskripsi)
            <p class="mt-1 text-xs leading-relaxed text-muted">{{ $s->deskripsi }}</p>
        @endif
    @endif

    @error('setting.'.$s->key)
        <p class="mt-1 text-xs font-medium text-merah">{{ $message }}</p>
    @enderror
</div>
