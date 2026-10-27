@props(['label' => 'jumlah', 'min' => 1, 'max' => null])

{{--
    Kotak jumlah dengan tombol − + di kiri dan kanan (KEP-091), seperti toko
    daring besar: menambah satu dua barang cukup diketuk, tanpa membuka papan
    ketik HP. Atribut lain (id, name, value, x-model, ::name, required)
    diteruskan ke <input>. Tombolnya ditangani app.js.

    Jangan dibungkus <label>: tombol "−" akan menjadi kontrol yang dilabeli,
    dan mengetuk tulisan labelnya malah mengurangi angka. Pakai <label for>
    dengan id, atau biarkan aria-label bawaan di bawah.
--}}
<div class="jumlah" data-jumlah>
    <button type="button" class="jumlah-tombol" data-langkah-jumlah="-1" aria-label="Kurangi {{ $label }}">
        <span aria-hidden="true">&minus;</span>
    </button>
    <input type="number" inputmode="numeric"
           @if ($min !== null) min="{{ $min }}" @endif
           @if ($max !== null) max="{{ $max }}" @endif
           @unless ($attributes->has('id')) aria-label="{{ Str::ucfirst($label) }}" @endunless
           {{ $attributes->merge(['class' => 'jumlah-isian']) }}>
    <button type="button" class="jumlah-tombol" data-langkah-jumlah="1" aria-label="Tambah {{ $label }}">
        <span aria-hidden="true">+</span>
    </button>
</div>
