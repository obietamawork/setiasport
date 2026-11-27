{{-- Bagian dari detail pesanan panel (KEP-124). Isinya dipindah dari halaman lama apa adanya. --}}
{{-- Tindakan berisiko dipisah dan diredam. Membatalkan pesanan tidak boleh
     sebobot kartu informasi di sebelahnya. --}}
@if ($izin->punyaIzin('order.batal') && ! $pesanan->status->final())
    <div class="zona-bahaya" x-data="{ buka: false }">
        <button type="button" @click="buka = !buka" class="tautan area-sentuh text-sm text-muted">
            Batalkan pesanan ini
        </button>

        <form method="POST" action="{{ route('panel.pesanan.batal', $pesanan) }}"
              x-show="buka" x-cloak class="notis notis-galat mt-3 max-w-xl"
              data-konfirmasi="Batalkan pesanan {{ $pesanan->kode }}?"
              data-konfirmasi-pesan="Reservasi stoknya dilepas."
              data-konfirmasi-tombol="Batalkan pesanan">
            @csrf
            <p class="font-semibold">Pesanan akan dibatalkan dan reservasi stoknya dilepas.</p>
            <label for="alasan" class="label-form mt-3">Alasan, akan masuk riwayat pesanan</label>
            <input type="text" id="alasan" name="alasan" class="kotak-input" required
                   placeholder="Dibatalkan pelanggan lewat telepon">

            {{-- Uang yang sudah masuk wajib diputuskan nasibnya (KEP-098); pelanggan
                 membaca keputusan ini di halaman pesanannya. --}}
            @if ($pesanan->sudah_dibayar > 0)
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="refund_jumlah_batal" class="label-form">Dikembalikan ke pelanggan (Rp)</label>
                        <input type="number" id="refund_jumlah_batal" name="refund_jumlah" min="0"
                               max="{{ $pesanan->sudah_dibayar }}" required class="kotak-input tabular"
                               value="{{ old('refund_jumlah', $refundUsulan) }}">
                        <p class="mt-1 text-xs text-muted">
                            Sudah dibayar {{ rupiah($pesanan->sudah_dibayar) }}; usulan sesuai ketentuan pembatalan
                            {{ rupiah($refundUsulan) }}. Isi 0 kalau tidak ada pengembalian.
                        </p>
                    </div>
                    <div>
                        <label for="refund_metode_batal" class="label-form">Lewat</label>
                        <select id="refund_metode_batal" name="refund_metode" class="kotak-input">
                            <option value="TRANSFER">Transfer</option>
                            <option value="TUNAI">Tunai di toko</option>
                        </select>
                    </div>
                </div>
            @endif

            <button type="submit" class="tombol tombol-utama tombol-sm mt-3">Ya, batalkan</button>
        </form>

        {{-- Menghanguskan hanya muncul setelah batasnya benar-benar lewat
             (KEP-043). Uang pelanggan tidak kembali, jadi tombol ini tidak
             boleh tersedia "untuk berjaga-jaga". --}}
        @if ($pesanan->bolehDihanguskan())
            <form method="POST" action="{{ route('panel.pesanan.hangus', $pesanan) }}" class="mt-5"
                  data-konfirmasi="Tandai pesanan {{ $pesanan->kode }} hangus?"
                  data-konfirmasi-pesan="Uang yang sudah dibayar tidak dikembalikan."
                  data-konfirmasi-tombol="Tandai hangus">
                @csrf
                <p class="text-sm leading-relaxed text-muted">
                    Barang sudah siap sejak {{ $pesanan->siap_pada->translatedFormat('j F Y') }} dan belum diambil,
                    melewati batas {{ setting('hangus_hari', 30) }} hari. Pastikan pelanggan sudah dihubungi
                    sebelum menghanguskan.
                </p>
                <button type="submit" class="tombol tombol-garis tombol-sm mt-2 text-merah">
                    Tandai hangus
                </button>
            </form>
        @endif
    </div>
@endif
