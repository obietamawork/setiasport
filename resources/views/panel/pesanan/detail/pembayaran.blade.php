{{--
    Pembayaran (KEP-124): tiap tagihan dengan metode, jumlah, dan statusnya,
    lalu tombol Verifikasi pembayaran dan Lihat bukti seperti gambar karyawan.
    Isian verifikasinya tetap yang lama (KEP-023, KEP-029, KEP-098).
--}}
@php
    use App\Enums\PaymentMethod;
    use App\Enums\PaymentStatus;
    use App\Enums\PaymentType;
@endphp

<section id="pembayaran" class="kartu-bayar scroll-mt-32">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h2 class="judul-kartu"><x-ikon nama="dompet" class="text-navy-400" /> Pembayaran</h2>
        @if ($bolehCatatBayar)
            <button type="button" class="tombol tombol-garis tombol-sm" @click="$dispatch('buka-catat-bayar')">
                <x-ikon nama="tambah" ukuran="size-4" /> Catat pembayaran
            </button>
        @endif
    </div>

    <div class="mt-4 space-y-4">
        @forelse ($pesanan->payments as $bayar)
            @php
                $jejak = array_filter([
                    $bayar->pencatat ? 'dicatat '.$bayar->pencatat->name : null,
                    $bayar->verifikator ? 'diverifikasi '.$bayar->verifikator->name : null,
                    $bayar->dibayar_pada?->translatedFormat('j M Y, H.i'),
                ]);
                $bisaVerifikasi = $bayar->status === PaymentStatus::MENUNGGU && $izin->punyaIzin('pembayaran.verifikasi');
            @endphp
            <div class="rounded-[12px] border border-line p-4" x-data="{ verifikasi: false }">
                <dl class="grid grid-cols-[6rem_minmax(0,1fr)] gap-x-3 gap-y-1.5 text-sm">
                    <dt class="text-muted">Metode</dt>
                    <dd class="font-medium">{{ $bayar->metode->label() }}</dd>
                    <dt class="text-muted">Jumlah</dt>
                    {{-- Pengembalian kelebihan bayar adalah uang keluar (KEP-158). --}}
                    <dd class="tabular font-semibold">{{ $bayar->jenis === PaymentType::PENGEMBALIAN ? '−' : '' }}{{ rupiah($bayar->jumlah) }}</dd>
                    <dt class="text-muted">Jenis</dt>
                    <dd>{{ $bayar->jenis->label() }}</dd>
                    <dt class="text-muted">Status</dt>
                    <dd>
                        @if ($bayar->jenis === PaymentType::PENGEMBALIAN)
                            <span class="chip chip-netral">Sudah dikembalikan</span>
                        @else
                            <span class="chip {{ match ($bayar->status) {
                                PaymentStatus::LUNAS => 'chip-ok',
                                PaymentStatus::MENUNGGU => 'chip-kuning',
                                PaymentStatus::GAGAL, PaymentStatus::KADALUARSA => 'chip-netral',
                            } }}">{{ $bayar->status->label() }}</span>
                        @endif
                    </dd>
                </dl>

                @if ($jejak)
                    <p class="mt-2 text-xs text-muted">{{ implode(', ', $jejak) }}</p>
                @endif
                @if ($bayar->status === PaymentStatus::GAGAL && $bayar->alasan_tolak)
                    <p class="mt-1 text-xs text-merah">Ditolak: {{ $bayar->alasan_tolak }}</p>
                @endif

                @if ($bisaVerifikasi || $bayar->bukti_path)
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($bisaVerifikasi)
                            <button type="button" class="tombol tombol-garis tombol-sm" @click="verifikasi = ! verifikasi">
                                <x-ikon nama="centang" ukuran="size-4" /> Verifikasi pembayaran
                            </button>
                        @endif
                        @if ($bayar->bukti_path)
                            {{-- Lewat rute berizin, bukan alamat berkas: bukti memuat nama
                                 dan nomor rekening pelanggan (KEP-029). --}}
                            <a href="{{ route('panel.pesanan.pembayaran.bukti', [$pesanan, $bayar]) }}" target="_blank" rel="noopener"
                               class="tombol tombol-garis tombol-sm">
                                <x-ikon nama="mata" ukuran="size-4" /> Lihat bukti pembayaran
                            </a>
                        @endif
                    </div>
                @endif

                @if ($bisaVerifikasi)
                    {{-- Tidak ada gerbang yang mengabari kami (KEP-023): admin
                         mencocokkan sendiri mutasi rekening dengan bukti. --}}
                    <div class="mt-4 grid gap-4 rounded-[10px] bg-navy-50 p-4 sm:grid-cols-2" x-show="verifikasi" x-cloak>
                        <form method="POST" enctype="multipart/form-data" class="contents"
                              action="{{ route('panel.pesanan.pembayaran.verifikasi', [$pesanan, $bayar]) }}">
                            @csrf
                            <div>
                                <label for="metode-{{ $bayar->id }}" class="label-form">Uang masuk lewat</label>
                                <select id="metode-{{ $bayar->id }}" name="metode" class="kotak-input">
                                    <option value="TRANSFER" @selected($bayar->metode === PaymentMethod::TRANSFER)>Transfer bank</option>
                                    <option value="QRIS" @selected($bayar->metode === PaymentMethod::QRIS)>QRIS</option>
                                    <option value="TUNAI" @selected($bayar->metode === PaymentMethod::TUNAI)>Tunai</option>
                                </select>
                            </div>
                            {{-- Nominal yang terlihat di mutasi (KEP-098). --}}
                            <div>
                                <label for="jumlah-{{ $bayar->id }}" class="label-form">Nominal yang masuk</label>
                                <input type="number" id="jumlah-{{ $bayar->id }}" name="jumlah" min="1"
                                       max="{{ $pesanan->sisa_tagihan }}" required class="kotak-input tabular"
                                       value="{{ min($bayar->jumlah, $pesanan->sisa_tagihan) }}">
                                <p class="mt-1 text-xs text-muted">
                                    @if ($bayar->jumlah > $pesanan->sisa_tagihan)
                                        Tagihan ini {{ rupiah($bayar->jumlah) }}, tapi sisa tagihan pesanan tinggal
                                        {{ rupiah($pesanan->sisa_tagihan) }}. Kelebihannya dikembalikan ke pelanggan.
                                    @else
                                        Ubah kalau yang masuk berbeda dari tagihan {{ rupiah($bayar->jumlah) }}.
                                    @endif
                                </p>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="bukti-{{ $bayar->id }}" class="label-form">
                                    {{ $bayar->bukti_path ? 'Ganti bukti' : 'Lampirkan bukti' }}
                                </label>
                                <input type="file" id="bukti-{{ $bayar->id }}" name="bukti" accept="image/*" class="kotak-input">
                                <p class="mt-1 text-xs text-muted">
                                    {{ $bayar->bukti_path
                                        ? 'Pelanggan sudah mengunggah bukti. Kosongkan untuk memakainya.'
                                        : (cs_aktif() ? 'Untuk bukti yang diterima lewat chat CS atau di toko.' : 'Untuk bukti yang diterima di toko.').' Boleh dikosongkan.' }}
                                </p>
                                <x-keterangan-unggah>Tangkapan layar utuh, nominal dan tanggalnya terbaca.</x-keterangan-unggah>
                            </div>

                            {{-- Bukti bisa diedit dalam semenit; mutasi tidak (KEP-029). --}}
                            <label class="flex items-start gap-2 border-l-2 border-kuning bg-kuning-50 p-3 text-sm leading-relaxed sm:col-span-2">
                                <input type="checkbox" name="cek_mutasi" value="1" required class="mt-1 size-4 shrink-0 accent-navy">
                                <span>
                                    Saya sudah melihat nominal di atas benar-benar masuk
                                    di mutasi rekening atau riwayat QRIS toko, atau menerimanya tunai.
                                    Bukti transfer saja tidak cukup.
                                </span>
                            </label>

                            <div class="sm:col-span-2">
                                <button type="submit" class="tombol tombol-navy">Uang sudah masuk, verifikasi</button>
                                <p class="mt-2 text-xs leading-relaxed text-muted">
                                    Setelah ini pesanan berlanjut ke tahap berikutnya dan pelanggan dikabari
                                    lewat email. Nama Anda tercatat sebagai yang memverifikasi.
                                </p>
                            </div>
                        </form>

                        <form method="POST" class="flex flex-wrap items-end gap-3 border-t border-line pt-4 sm:col-span-2"
                              action="{{ route('panel.pesanan.pembayaran.tolak', [$pesanan, $bayar]) }}">
                            @csrf
                            <div class="min-w-56 flex-1">
                                <label for="alasan-{{ $bayar->id }}" class="label-form">Atau tandai gagal, alasannya</label>
                                <input type="text" id="alasan-{{ $bayar->id }}" name="alasan" class="kotak-input" maxlength="150"
                                       placeholder="Misal: nominal kurang, bukti tidak cocok">
                            </div>
                            <button type="submit" class="tombol tombol-garis">Tandai gagal</button>
                        </form>
                    </div>
                @endif
            </div>
        @empty
            <p class="rounded-[10px] bg-paper px-4 py-3 text-sm text-muted">Belum ada tagihan untuk pesanan ini.</p>
        @endforelse
    </div>
</section>
