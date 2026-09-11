@extends('layouts.panel')
@section('judul', 'Pengaturan')

@section('isi')
<div class="kop-halaman">
    <div>
        <h1 class="kop-judul">Pengaturan</h1>
        <p class="kop-catatan">Nilai-nilai ini dipakai di seluruh sistem: halaman produk, checkout, dan email
    pelanggan ikut berubah begitu disimpan.</p>
    </div>
</div>

<div class="mt-6 max-w-2xl space-y-6">

    <form method="POST" action="{{ route('panel.pengaturan.update') }}" class="space-y-6">
        @csrf @method('PUT')

        @foreach ($grup as $namaGrup => $daftar)
            <section class="kartu overflow-hidden">
                <h2 class="border-b border-line px-5 py-3 font-semibold capitalize">{{ $namaGrup }}</h2>
                <div class="space-y-4 p-5">
                    @foreach ($daftar as $s)
                        {{-- Berkas punya formulirnya sendiri di bawah: ia perlu
                             pratinjau dan enctype yang berbeda. --}}
                        @continue($s->tipe === 'berkas')

                        <div>
                            <label for="s-{{ $s->key }}" class="label-form">{{ $s->label }}</label>

                            @if ($s->tipe === 'bool')
                                {{-- Kotak yang tidak dicentang tidak mengirim apa pun;
                                     tanpa nilai cadangan ini, mematikannya mustahil. --}}
                                <input type="hidden" name="setting[{{ $s->key }}]" value="0">
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" id="s-{{ $s->key }}" name="setting[{{ $s->key }}]" value="1"
                                           class="size-4 accent-navy"
                                           @checked(filter_var(old('setting.'.$s->key, $s->value), FILTER_VALIDATE_BOOLEAN))>
                                    Aktif
                                </label>
                            @elseif ($s->tipe === 'teks')
                                <textarea id="s-{{ $s->key }}" name="setting[{{ $s->key }}]" rows="5"
                                          class="kotak-input leading-relaxed">{{ old('setting.'.$s->key, $s->value) }}</textarea>
                            @else
                                <input type="{{ $s->tipe === 'int' ? 'number' : 'text' }}"
                                       id="s-{{ $s->key }}"
                                       name="setting[{{ $s->key }}]"
                                       value="{{ old('setting.'.$s->key, $s->value) }}"
                                       class="kotak-input {{ $s->tipe === 'int' ? 'tabular max-w-40' : '' }}">
                            @endif

                            @if ($s->deskripsi)
                                <p class="mt-1 text-xs leading-relaxed text-muted">{{ $s->deskripsi }}</p>
                            @endif
                        </div>
                    @endforeach

                    @if ($namaGrup === 'pembayaran')
                        <p class="border-t border-line pt-4 text-xs leading-relaxed text-muted">
                            Rekening dan QRIS di atas tampil apa adanya di halaman pesanan pelanggan.
                            Periksa ulang angkanya sebelum menyimpan.
                        </p>
                    @endif
                </div>
            </section>
        @endforeach

        <button type="submit" class="tombol tombol-navy">Simpan pengaturan</button>
    </form>

    {{-- Gambar QRIS terpisah dari formulir teks (KEP-023). Salah unggah di sini
         berarti uang pelanggan masuk ke rekening lain, jadi pratinjaunya
         ditampilkan besar dan penggantinya butuh tindakan tersendiri. --}}
    <section class="kartu overflow-hidden">
        <h2 class="border-b border-line px-5 py-3 font-semibold">Gambar QRIS</h2>
        <div class="space-y-4 p-5">
            @if (setting('qris_path'))
                <div class="flex flex-wrap items-start gap-5">
                    <img src="{{ asset('storage/'.setting('qris_path')) }}" alt="QRIS toko"
                         class="w-40 border border-line bg-white p-2">
                    <div class="min-w-48 flex-1 space-y-3">
                        <p class="text-sm leading-relaxed">
                            Inilah gambar yang dipindai pelanggan. Pastikan nama merchantnya
                            terbaca dan tidak tertutup.
                        </p>
                        <form method="POST" action="{{ route('panel.pengaturan.qris.hapus') }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="tautan text-merah">Hapus gambar QRIS</button>
                        </form>
                    </div>
                </div>
            @else
                <p class="text-sm leading-relaxed text-muted">
                    Belum ada. Tanpa ini, halaman pesanan pelanggan hanya menampilkan rekening bank.
                </p>
            @endif

            <form method="POST" action="{{ route('panel.pengaturan.qris') }}" enctype="multipart/form-data"
                  class="flex flex-wrap items-end gap-3 border-t border-line pt-4">
                @csrf
                <div class="min-w-56 flex-1">
                    <label for="qris" class="label-form">
                        {{ setting('qris_path') ? 'Ganti dengan gambar lain' : 'Unggah gambar QRIS' }}
                    </label>
                    <input type="file" id="qris" name="qris" accept="image/*" class="kotak-input" required>
                    <p class="mt-1 text-xs text-muted">
                        JPG, PNG, atau WebP, maksimal {{ setting('upload_maks_mb', 10) }} MB.
                    </p>
                </div>
                <button type="submit" class="tombol tombol-navy">Simpan gambar</button>
            </form>
        </div>
    </section>
</div>
@endsection
