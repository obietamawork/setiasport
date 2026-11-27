{{--
    Penyesuaian biaya seperti gambar karyawan (KEP-124): jenis, alasan dari
    daftar tetap, nominal positif. Dicatat sebagai baris tersendiri yang
    dibaca pelanggan di halaman pesanannya (KEP-007).
--}}
@php
    use App\Http\Controllers\Panel\PenyesuaianController;

    $bolehUbah = $izin->punyaIzin('order.penyesuaian') && ! $pesanan->status->final();
@endphp

<section id="penyesuaian" class="kartu-bayar scroll-mt-32">
    <h2 class="judul-kartu"><x-ikon nama="saring" class="text-navy-400" /> Penyesuaian biaya</h2>

    <p class="mt-4 flex items-start gap-2 rounded-[10px] bg-navy-50 px-3 py-2.5 text-sm leading-relaxed">
        <x-ikon nama="bantuan" ukuran="size-4" class="mt-0.5 shrink-0 text-navy-700" />
        Gunakan untuk menambah atau mengurangi biaya setelah pesanan dibuat. Pelanggan melihatnya di halaman pesanannya.
    </p>

    @if ($bolehUbah)
        <form method="POST" action="{{ route('panel.pesanan.penyesuaian.simpan', $pesanan) }}" class="mt-4 space-y-4"
              x-data="{ jenis: @js(old('jenis', 'tambah')), alasan: @js(old('alasan', PenyesuaianController::ALASAN[0])) }">
            @csrf
            <fieldset>
                <legend class="label-form">Jenis penyesuaian</legend>
                <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <label class="flex items-center gap-2">
                        <input type="radio" name="jenis" value="tambah" x-model="jenis" class="size-4 accent-navy"
                               :disabled="alasan === 'Diskon khusus'">
                        Tambah biaya
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="jenis" value="potong" x-model="jenis" class="size-4 accent-navy">
                        Potongan harga / diskon
                    </label>
                </div>
            </fieldset>

            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_10rem]">
                <div>
                    <label for="alasan" class="label-form">Alasan penyesuaian</label>
                    <select id="alasan" name="alasan" class="kotak-input" x-model="alasan"
                            @change="if (alasan === 'Diskon khusus') jenis = 'potong'">
                        @foreach (PenyesuaianController::ALASAN as $alasan)
                            <option value="{{ $alasan }}">{{ $alasan }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="nominal" class="label-form">Nominal (Rp)</label>
                    <input type="number" id="nominal" name="nominal" min="1" max="99999999" required inputmode="numeric"
                           class="kotak-input tabular" value="{{ old('nominal') }}" placeholder="20000">
                </div>
            </div>

            <div>
                <label for="keterangan" class="label-form">
                    Keterangan
                    <span class="font-normal text-muted" x-text="alasan === 'Lainnya' ? '(wajib)' : '(opsional)'">(opsional)</span>
                </label>
                <input type="text" id="keterangan" name="keterangan" maxlength="120" class="kotak-input"
                       value="{{ old('keterangan') }}" :required="alasan === 'Lainnya'"
                       placeholder="Dibaca pelanggan, misalnya: tambah 2 pemain">
            </div>

            <button type="submit" class="tombol tombol-garis w-full border-navy text-navy-700">
                <x-ikon nama="tambah" ukuran="size-4" /> Tambah penyesuaian
            </button>
        </form>
    @endif

    @if ($pesanan->adjustments->isNotEmpty())
        <ul class="mt-4 space-y-2 text-sm">
            @foreach ($pesanan->adjustments as $p)
                <li class="flex items-center gap-3 rounded-[10px] bg-kuning-50 px-3 py-2.5">
                    <span class="min-w-0 flex-1">
                        <span class="font-medium">{{ $p->deskripsi }}</span>
                        <span class="text-xs text-muted">
                            ({{ $p->created_at->translatedFormat('j M Y, H.i') }}{{ $p->pembuat ? ', '.$p->pembuat->name : '' }})
                        </span>
                    </span>
                    <span class="tabular font-semibold whitespace-nowrap {{ $p->jumlah < 0 ? 'text-ok' : 'text-merah' }}">
                        {{ $p->jumlah < 0 ? '−' : '+' }}{{ rupiah(abs($p->jumlah)) }}
                    </span>
                    @if ($bolehUbah)
                        <form method="POST" action="{{ route('panel.pesanan.penyesuaian.hapus', [$pesanan, $p]) }}"
                              data-konfirmasi="Hapus penyesuaian &quot;{{ $p->deskripsi }}&quot;?"
                              data-konfirmasi-pesan="Tagihan pesanan ikut berubah dan pelanggan melihat angka barunya."
                              data-konfirmasi-tombol="Hapus" data-konfirmasi-bahaya>
                            @csrf @method('DELETE')
                            <button type="submit" class="tombol-ikon tombol-ikon-bahaya" title="Hapus penyesuaian">
                                <x-ikon nama="hapus" ukuran="size-4" />
                                <span class="sr-only">Hapus penyesuaian {{ $p->deskripsi }}</span>
                            </button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @elseif (! $bolehUbah)
        <p class="mt-4 text-sm text-muted">Belum ada penyesuaian biaya.</p>
    @endif
</section>
