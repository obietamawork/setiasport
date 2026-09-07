@extends('layouts.panel')
@section('judul', 'Pengaturan')

@section('isi')
<h1 class="t-wide text-2xl font-extrabold">Pengaturan</h1>
<p class="mt-1 text-sm text-muted">
    Nilai-nilai ini dipakai di seluruh sistem — halaman produk, checkout, dan email
    pelanggan ikut berubah begitu disimpan.
</p>

<form method="POST" action="{{ route('panel.pengaturan.update') }}" class="mt-6 max-w-2xl space-y-6">
    @csrf @method('PUT')

    @foreach ($grup as $namaGrup => $daftar)
        <section class="border border-line bg-field">
            <h2 class="border-b border-line px-5 py-3 font-semibold capitalize">{{ $namaGrup }}</h2>
            <div class="space-y-4 p-5">
                @foreach ($daftar as $s)
                    <div>
                        <label for="s-{{ $s->key }}" class="label-form">{{ $s->label }}</label>
                        <input type="{{ $s->tipe === 'int' ? 'number' : 'text' }}"
                               id="s-{{ $s->key }}"
                               name="setting[{{ $s->key }}]"
                               value="{{ old('setting.'.$s->key, $s->value) }}"
                               class="kotak-input {{ $s->tipe === 'int' ? 'tabular max-w-40' : '' }}">
                        @if ($s->deskripsi)
                            <p class="mt-1 text-xs leading-relaxed text-muted">{{ $s->deskripsi }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    <button type="submit" class="tombol tombol-lapangan">Simpan pengaturan</button>
</form>
@endsection
