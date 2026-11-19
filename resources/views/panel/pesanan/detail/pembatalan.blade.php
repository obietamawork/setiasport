{{-- Bagian dari detail pesanan panel (KEP-124). Isinya dipindah dari halaman lama apa adanya. --}}
<section id="pembatalan" class="kartu scroll-mt-32 overflow-hidden border-kuning">
    <h2 class="kartu-kop bg-kuning-50">Pengajuan pembatalan</h2>
    <div class="space-y-4 p-5">
        <div>
            <p class="text-sm text-muted">
                Diajukan pelanggan {{ $pembatalan->created_at->translatedFormat('j M Y, H:i') }}
                &middot; {{ $pembatalan->tahap->label() }}
            </p>
            <p class="mt-2 leading-relaxed">"{{ $pembatalan->alasan }}"</p>
        </div>

        <dl class="grid gap-2 border-y border-line py-3 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-muted">Sudah dibayar</dt>
                <dd class="tabular font-medium">{{ rupiah($pembatalan->sudah_dibayar) }}</dd>
            </div>
            <div>
                <dt class="text-muted">Biaya setup tercatat</dt>
                <dd class="tabular font-medium">{{ rupiah($biayaSetup) }}</dd>
            </div>
            <div>
                <dt class="text-muted">Usulan pengembalian</dt>
                <dd class="tabular font-medium">{{ rupiah($pembatalan->refund_usulan) }}</dd>
            </div>
        </dl>

        <p class="text-sm leading-relaxed text-muted">{{ $pembatalan->tahap->ketentuan() }}</p>

        @if ($izin->punyaIzin('order.batal'))
            <form method="POST" action="{{ route('panel.pesanan.pembatalan.setujui', [$pesanan, $pembatalan]) }}"
                  class="space-y-3 border-t border-line pt-4"
                  data-konfirmasi="Setujui pembatalan?"
                  data-konfirmasi-pesan="Pesanan dibatalkan dan reservasi stoknya dilepas."
                  data-konfirmasi-tombol="Setujui pembatalan">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="refund_jumlah" class="label-form">Pengembalian ke pelanggan</label>
                        <input type="number" id="refund_jumlah" name="refund_jumlah"
                               value="{{ old('refund_jumlah', $pembatalan->refund_usulan) }}"
                               min="0" max="{{ $pembatalan->sudah_dibayar }}"
                               class="kotak-input tabular" required>
                        <p class="mt-1 text-xs text-muted">
                            Isi 0 kalau tidak ada pengembalian. Maksimal {{ rupiah($pembatalan->sudah_dibayar) }}.
                        </p>
                    </div>
                    <div>
                        <label for="refund_metode" class="label-form">Dikembalikan lewat</label>
                        <select id="refund_metode" name="refund_metode" class="kotak-input">
                            <option value="TRANSFER">Transfer</option>
                            <option value="TUNAI">Tunai di toko</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label for="catatan_admin" class="label-form">Catatan untuk pelanggan (opsional)</label>
                    <input type="text" id="catatan_admin" name="catatan_admin" maxlength="1000"
                           class="kotak-input" value="{{ old('catatan_admin') }}">
                </div>
                <button type="submit" class="tombol tombol-navy">Setujui pembatalan</button>
            </form>

            <form method="POST" action="{{ route('panel.pesanan.pembatalan.tolak', [$pesanan, $pembatalan]) }}"
                  class="space-y-3 border-t border-line pt-4">
                @csrf
                <div>
                    <label for="alasan_tolak" class="label-form">Alasan menolak</label>
                    <input type="text" id="alasan_tolak" name="catatan_admin" maxlength="1000"
                           class="kotak-input" required minlength="10"
                           placeholder="Dibaca pelanggan di halaman pesanannya">
                </div>
                <button type="submit" class="tombol tombol-garis">Tolak pengajuan</button>
            </form>
        @endif
    </div>
</section>
