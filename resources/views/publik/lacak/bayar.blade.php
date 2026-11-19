{{--
    Tagihan yang sedang harus dibayar, dengan tata letak gambar contoh klien
    (KEP-121): ringkasan pesanan, total dan batas waktu, pilihan QRIS atau
    transfer beserta caranya, unggah bukti, dan catatan penting.

    Berlaku untuk DP di awal maupun pelunasan setelah barang jadi (KEP-028).
    Tidak ada gerbang pembayaran (KEP-023) dan tidak ada jalur WhatsApp
    (KEP-029): pelanggan membayar ke tujuan yang tertera di sini, mengunggah
    buktinya di sini, dan admin mencocokkannya dengan mutasi.

    @var \App\Models\Order $order
    @var array{jenis: string, jumlah: int, batas: ?\Carbon\CarbonInterface, pembayaran: ?\App\Models\Payment} $tagihan
--}}
@php
    use App\Enums\OrderStatus;
    use App\Enums\PaymentStatus;

    $buktiAda = $tagihan['pembayaran']?->bukti_path;

    // Penolakan pada putaran tagihan ini saja (KEP-097): penolakan lama
    // sebelum pembayaran yang sudah lunas tidak relevan lagi, dan begitu
    // bukti baru terkirim, catatannya tidak perlu tampil.
    $ditolak = $buktiAda ? null : $order->payments
        ->where('status', PaymentStatus::GAGAL)
        ->whereNotNull('alasan_tolak')
        ->sortByDesc('id')
        ->first();

    if ($ditolak && $order->payments->contains(fn ($p) => $p->status === PaymentStatus::LUNAS && $p->id > $ditolak->id)) {
        $ditolak = null;
    }

    // Toko memakai dua rekening; yang kedua boleh kosong.
    $rekening = array_values(array_filter([
        ['nama' => setting('bank_nama'), 'no' => setting('bank_rekening'), 'an' => setting('bank_atas_nama')],
        ['nama' => setting('bank2_nama'), 'no' => setting('bank2_rekening'), 'an' => setting('bank2_atas_nama')],
    ], fn ($r) => trim((string) $r['no']) !== ''));
    $qris = setting('qris_path') ? asset('storage/'.setting('qris_path')) : null;

    /*
     * Sisa waktu dihitung di server lebih dulu, lalu diteruskan detik demi
     * detik oleh Alpine. Batas yang sudah lewat tidak pernah terbaca sebagai
     * angka negatif (KEP-121).
     */
    $detikSisa = $tagihan['batas'] ? max(0, (int) now()->diffInSeconds($tagihan['batas'], false)) : null;
    $teksSisa = function (?int $detik): string {
        if ($detik === null) {
            return '';
        }
        if ($detik <= 0) {
            return 'Batas sudah lewat';
        }
        $hari = intdiv($detik, 86400);
        $jam = intdiv($detik % 86400, 3600);
        $menit = intdiv($detik % 3600, 60);

        return match (true) {
            $hari > 0 => $hari.' hari '.$jam.' jam',
            $jam > 0 => $jam.' jam '.$menit.' menit',
            default => max(1, $menit).' menit',
        };
    };

    // Gambar ringkasan: mockup yang disetujui, kalau ada, atau foto produknya.
    $itemUtama = $order->items->first();
    $gambarUtama = $itemUtama?->approvedDesign?->path
        ? asset('storage/'.$itemUtama->approvedDesign->path)
        : $itemUtama?->product?->images->first()?->thumb_url;
@endphp

<section class="mt-8 space-y-4" aria-labelledby="judul-bayar">
    <h2 id="judul-bayar" class="text-xl font-extrabold">
        {{-- Teks klien (KEP-148): "... pembayaran saat ini". --}}
        {{ $tagihan['jenis'] === 'pelunasan' ? 'Pelunasan' : 'Pembayaran saat ini' }}
    </h2>

    @if ($ditolak)
        <p class="rounded-[12px] border-l-4 border-merah bg-merah-50 p-4 text-sm leading-relaxed">
            <strong>Bukti sebelumnya belum bisa kami terima:</strong> {{ $ditolak->alasan_tolak }}
            Periksa lagi transfernya, lalu kirim ulang buktinya di bawah.
        </p>
    @endif

    {{-- Ringkasan pesanan: barang apa, kapan dipesan, berapa banyak. --}}
    <div class="kartu-bayar flex flex-wrap items-center gap-x-6 gap-y-4">
        <div class="flex min-w-0 flex-1 basis-64 items-center gap-4">
            @if ($gambarUtama)
                <img src="{{ $gambarUtama }}" alt="" width="72" height="72" class="size-18 shrink-0 rounded-[10px] border border-line object-cover">
            @else
                <span class="flex size-18 shrink-0 items-center justify-center rounded-[10px] bg-navy-50 text-navy-300">
                    <x-ikon nama="kaos" ukuran="size-8" />
                </span>
            @endif
            <div class="min-w-0">
                <p class="font-bold">
                    {{ $itemUtama?->nama_produk }}
                    @if ($order->items->count() > 1)
                        <span class="font-normal text-muted">+{{ $order->items->count() - 1 }} barang lain</span>
                    @endif
                </p>
                <p class="mt-1 flex flex-wrap items-center gap-x-2 text-sm text-muted" x-data="{ tersalin: false }">
                    No. pesanan: <span class="tabular font-semibold whitespace-nowrap text-ink">{{ $order->kode }}</span>
                    <button type="button" class="text-navy-400 hover:text-navy" title="Salin nomor pesanan"
                            @click="navigator.clipboard.writeText(@js($order->kode)); tersalin = true; setTimeout(() => tersalin = false, 2000)">
                        <x-ikon nama="salin" ukuran="size-4" />
                        <span class="sr-only">Salin nomor pesanan</span>
                    </button>
                    <span x-show="tersalin" x-cloak class="text-xs text-ok">Tersalin</span>
                </p>
            </div>
        </div>
        <div class="flex items-center gap-3 sm:border-l sm:border-line sm:pl-6">
            <x-ikon nama="kalender" class="text-navy-400" />
            <div>
                <p class="text-xs text-muted">Tanggal pesanan</p>
                <p class="tabular font-semibold">{{ $order->created_at->translatedFormat('j M Y, H.i') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 sm:border-l sm:border-line sm:pl-6">
            <x-ikon nama="stok" class="text-navy-400" />
            <div>
                <p class="text-xs text-muted">Jumlah pesanan</p>
                <p class="tabular font-semibold">{{ $order->items->sum('qty') }} pcs</p>
            </div>
        </div>
    </div>

    {{-- Total dan batas waktu. Bukti yang sudah terkirim menahan pembatalan
         otomatis (KEP-098), jadi batas yang lewat tidak boleh terbaca seperti
         ancaman bagi orang yang sudah membayar. --}}
    <div class="kartu-bayar grid items-center gap-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
        <div>
            <p class="text-sm font-semibold text-muted">Total pembayaran</p>
            <p class="numeral mt-1 text-4xl text-merah sm:text-5xl">{{ rupiah($tagihan['jumlah']) }}</p>
            @if ($tagihan['jenis'] === 'pelunasan')
                <p class="mt-2 text-sm leading-relaxed">
                    @if ($order->dikirim())
                        Barang Anda sudah selesai dikerjakan. Setelah sisa ini lunas, paketnya kami kirim ke alamat Anda.
                    @elseif ($order->status === OrderStatus::SIAP_DIAMBIL)
                        Barang Anda sudah siap diambil. Sisa ini bisa ditransfer sekarang, atau dibayar di kasir saat mengambil.
                    @else
                        Barang Anda sudah selesai dikerjakan. Setelah sisa ini lunas, kode ambil terbit dan barang bisa diambil.
                    @endif
                </p>
            @endif
        </div>

        @if ($tagihan['batas'] && $buktiAda)
            <p class="rounded-[12px] border border-navy-100 bg-navy-50 p-4 text-sm leading-relaxed">
                Bukti transfer Anda sudah kami terima dan sedang dicocokkan dengan mutasi
                rekening. Selama itu pesanan Anda tetap kami simpan walau batas pembayarannya
                lewat, sampai
                <strong>{{ $tagihan['batas']->copy()->addHours(\App\Services\AlurPesanan::TENGGANG_BUKTI_JAM)->translatedFormat('l, j F Y H.i') }}</strong>.
            </p>
        @elseif ($tagihan['batas'])
            <div class="flex flex-wrap items-center gap-4 rounded-[12px] border border-merah-100 bg-merah-50 p-4"
                 x-data="{ sisa: {{ $detikSisa }}, teks: @js($teksSisa($detikSisa)),
                           init() {
                               if (this.sisa <= 0) return;
                               const jalan = setInterval(() => {
                                   this.sisa = Math.max(0, this.sisa - 30);
                                   const h = Math.floor(this.sisa / 86400), j = Math.floor(this.sisa % 86400 / 3600), m = Math.floor(this.sisa % 3600 / 60);
                                   this.teks = this.sisa <= 0 ? 'Batas sudah lewat' : (h > 0 ? h + ' hari ' + j + ' jam' : (j > 0 ? j + ' jam ' + m + ' menit' : Math.max(1, m) + ' menit'));
                                   if (this.sisa <= 0) clearInterval(jalan);
                               }, 30000);
                           } }">
                <x-ikon nama="jam" ukuran="size-7" class="text-merah" />
                {{-- basis-48: di HP kotak sisa waktu turun ke baris berikutnya,
                     bukan menjepit tanggal sampai terlipat kata per kata. --}}
                <div class="min-w-0 flex-1 basis-48">
                    <p class="text-sm font-semibold text-merah-tua">Batas pembayaran</p>
                    <p class="tabular text-lg font-bold">{{ $tagihan['batas']->translatedFormat('l, j F Y') }}, pukul {{ $tagihan['batas']->format('H.i') }} WIB</p>
                    <p class="text-xs text-muted">Pesanan akan otomatis dibatalkan apabila pembayaran belum diterima hingga batas waktu tersebut.</p>
                </div>
                <div class="overflow-hidden rounded-[10px] border border-merah text-center">
                    <p class="bg-merah px-4 py-1 text-xs font-semibold text-white">Sisa waktu</p>
                    <p class="tabular bg-field px-4 py-1.5 font-bold text-merah-tua" x-text="teks">{{ $teksSisa($detikSisa) }}</p>
                </div>
            </div>
        @elseif ($tagihan['jenis'] === 'pelunasan' && ! $order->dikirim())
            <p class="text-sm text-muted">Bisa juga dibayar tunai atau QRIS langsung di kasir saat datang.</p>
        @endif
    </div>

    {{-- Dua cara bayar seperti gambar klien (KEP-134): Transfer / QRIS dengan
         bukti yang diunggah, atau tunai di kasir toko. Setelah bukti transfer
         terkirim, pilihannya tidak ditawarkan lagi. --}}
    <div class="space-y-5" x-data="{ cara: 'transfer' }">
    @unless ($buktiAda)
        <div class="grid gap-3 sm:grid-cols-2" role="tablist" aria-label="Cara bayar">
            <button type="button" role="tab" :aria-selected="(cara === 'transfer').toString()" @click="cara = 'transfer'"
                    class="pilih-metode" :class="cara === 'transfer' && 'pilih-metode-aktif'">
                <x-ikon nama="uang" ukuran="size-7" />
                <span class="text-left"><span class="block font-bold">Transfer / QRIS</span><span class="block text-sm opacity-80">Bayar lewat bank atau QRIS, lalu unggah buktinya</span></span>
            </button>
            <button type="button" role="tab" :aria-selected="(cara === 'tunai').toString()" @click="cara = 'tunai'"
                    class="pilih-metode" :class="cara === 'tunai' && 'pilih-metode-aktif'">
                <x-ikon nama="toko" ukuran="size-7" />
                <span class="text-left"><span class="block font-bold">Tunai di toko</span><span class="block text-sm opacity-80">Bayar langsung di kasir toko</span></span>
            </button>
        </div>

        <div class="kartu-bayar" x-show="cara === 'tunai'" x-cloak>
            <h3 class="flex items-center gap-2.5 text-lg font-extrabold">
                <x-ikon nama="toko" ukuran="size-6" class="text-navy-400" /> Bayar tunai di toko
            </h3>
            <ol class="langkah-bayar mt-4">
                <li>
                    Datang ke <strong>{{ setting('toko_nama', 'Setia Sport') }}</strong>,
                    {{ setting('toko_alamat') }}.
                    @if (setting('toko_jam_buka'))
                        <span class="block text-sm text-muted">Jam buka: {{ preg_replace('/\s*\R\s*/', ', ', trim((string) setting('toko_jam_buka'))) }}</span>
                    @endif
                </li>
                <li>Sebutkan kode pesanan <strong class="tabular">{{ $order->kode }}</strong> di kasir.</li>
                <li>Bayar <strong class="tabular">{{ rupiah($tagihan['jumlah']) }}</strong>. Kasir mencatatnya, dan pesanan Anda langsung kami proses.</li>
            </ol>
            <p class="mt-4 rounded-[10px] bg-kuning-50 px-4 py-3 text-sm leading-relaxed">
                @if ($tagihan['batas'])
                    Bayar sebelum <strong>{{ $tagihan['batas']->translatedFormat('l, j F Y H.i') }}</strong>.
                    Lewat dari itu, pesanan dibatalkan otomatis.
                @else
                    Bayar sebelum atau saat mengambil pesanan.
                @endif
                Tidak perlu mengunggah bukti.
            </p>
        </div>
    @endunless

    {{-- Tujuan pembayaran. Begitu buktinya terkirim, ditutup di balik
         ringkasan: orang yang sudah membayar dan masih melihat QRIS besar di
         layar gampang mengira pembayarannya belum masuk, lalu membayar lagi. --}}
    <div class="kartu-bayar" x-show="cara === 'transfer'" x-data="{ metode: @js($qris ? 'qris' : 'transfer') }">
        @if ($buktiAda)
            <p class="font-bold">Transfer sudah Anda kirim</p>
            <details class="mt-2">
                <summary class="cursor-pointer text-sm text-muted">Perlu melihat tujuan transfernya lagi?</summary>
                <div class="mt-4">
        @else
            <h3 class="text-lg font-extrabold">Pilih metode pembayaran</h3>
        @endif

        @if ($qris && $rekening !== [])
            <div class="mt-4 grid gap-3 sm:grid-cols-2" role="tablist" aria-label="Metode pembayaran">
                <button type="button" role="tab" :aria-selected="(metode === 'qris').toString()" @click="metode = 'qris'"
                        class="pilih-metode" :class="metode === 'qris' && 'pilih-metode-aktif'">
                    <x-ikon nama="qr" ukuran="size-7" />
                    <span class="text-left"><span class="block font-bold">QRIS</span><span class="block text-sm opacity-80">Bayar cepat dari aplikasi bank atau e-wallet</span></span>
                </button>
                <button type="button" role="tab" :aria-selected="(metode === 'transfer').toString()" @click="metode = 'transfer'"
                        class="pilih-metode" :class="metode === 'transfer' && 'pilih-metode-aktif'">
                    <x-ikon nama="toko" ukuran="size-7" />
                    <span class="text-left"><span class="block font-bold">Transfer bank</span><span class="block text-sm opacity-80">Lewat rekening {{ collect($rekening)->pluck('nama')->implode(', ') }}</span></span>
                </button>
            </div>
        @endif

        @if ($qris)
            <div class="mt-5 grid gap-5 md:grid-cols-2" x-show="metode === 'qris'">
                <div class="rounded-[12px] border border-line bg-white p-4 text-center">
                    <p class="font-extrabold tracking-wide">{{ Str::upper(setting('toko_nama', 'Setia Sport')) }}</p>
                    <a href="{{ $qris }}" target="_blank" rel="noopener" class="mt-3 block">
                        <img src="{{ $qris }}" alt="Kode QRIS {{ setting('toko_nama') }}" class="mx-auto w-full max-w-64">
                    </a>
                    {{-- Membayar dari HP berarti tidak bisa memindai layar sendiri.
                         Aplikasi bank di Indonesia umumnya bisa memindai dari galeri. --}}
                    {{-- Teks klien (KEP-148); "simpan kode QR" membuka gambar ukuran
                         penuh seperti tautan "buka ukuran penuh" sebelumnya. --}}
                    <p class="mt-3 text-xs leading-relaxed text-muted">
                        Pembayaran dapat dilakukan melalui aplikasi bank atau e-wallet yang mendukung QRIS. Jika menggunakan perangkat yang sama,
                        <a href="{{ $qris }}" target="_blank" rel="noopener" class="tautan">simpan kode QR</a>
                        lalu pilih fitur pindai dari galeri pada aplikasi pembayaran.
                    </p>
                </div>
                <div class="rounded-[12px] bg-biru-50 p-5">
                    <h4 class="flex items-center gap-2 font-bold"><x-ikon nama="kirim" ukuran="size-5" class="text-navy-400" /> Cara bayar dengan QRIS</h4>
                    <ol class="langkah-bayar mt-4">
                        <li>Buka aplikasi m-banking atau e-wallet (OVO, DANA, GoPay, ShopeePay, dan lainnya).</li>
                        <li>Pilih menu Scan QRIS.</li>
                        <li>Pindai kode QR di samping.</li>
                        <li>Pastikan nominalnya <strong class="tabular">{{ rupiah($tagihan['jumlah']) }}</strong>, lalu selesaikan pembayaran.</li>
                    </ol>
                </div>
            </div>
        @endif

        @if ($rekening !== [])
            <div class="mt-5 grid gap-5 md:grid-cols-2" x-show="metode === 'transfer'" @if ($qris) x-cloak @endif>
                <div class="space-y-4 rounded-[12px] border border-line bg-white p-4">
                    @if (count($rekening) > 1)
                        <p class="text-sm text-muted">Transfer bank (pilih salah satu)</p>
                    @endif
                    @foreach ($rekening as $r)
                        <div @class(['border-t border-line pt-4' => ! $loop->first])>
                            <p class="font-semibold">{{ $r['nama'] }}</p>
                            {{-- Yang disalin angkanya saja: sebagian aplikasi bank menolak
                                 nomor berspasi saat ditempel. --}}
                            <div class="mt-1 flex flex-wrap items-center gap-3" x-data="{ tersalin: false }">
                                <p class="numeral text-2xl">{{ $r['no'] }}</p>
                                <button type="button" class="tombol tombol-garis tombol-sm"
                                        @click="navigator.clipboard.writeText('{{ preg_replace('/\D/', '', $r['no']) }}'); tersalin = true; setTimeout(() => tersalin = false, 2000)">
                                    <span x-text="tersalin ? 'Tersalin' : 'Salin'">Salin</span>
                                </button>
                            </div>
                            <p class="mt-1 text-sm text-muted">a.n. {{ $r['an'] }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="rounded-[12px] bg-biru-50 p-5">
                    <h4 class="flex items-center gap-2 font-bold"><x-ikon nama="toko" ukuran="size-5" class="text-navy-400" /> Cara bayar lewat transfer bank</h4>
                    <ol class="langkah-bayar mt-4">
                        <li>Buka m-banking, internet banking, atau ATM.</li>
                        <li>Transfer ke {{ count($rekening) > 1 ? 'salah satu rekening' : 'rekening' }} di samping.</li>
                        <li>Isi nominal tepat <strong class="tabular">{{ rupiah($tagihan['jumlah']) }}</strong>.</li>
                        <li>Simpan bukti transfernya, lalu unggah di bawah.</li>
                    </ol>
                </div>
            </div>
        @endif

        @if (! $qris && $rekening === [])
            <p class="mt-3 text-sm text-muted">
                Tujuan transfer belum tercantum di sini. Tanyakan lebih dulu lewat {{ kanal_kontak() }},
                dan jangan transfer ke rekening yang dikirim lewat jalur lain.
            </p>
        @endif

        @if ($buktiAda)
                </div>
            </details>
        @endif
    </div>

    {{-- Unggah bukti. --}}
    <div class="kartu-bayar" x-show="cara === 'transfer'">
        <h3 class="flex items-center gap-3 text-lg font-extrabold">
            <x-ikon nama="unggah" ukuran="size-7" class="text-navy-400" />
            Unggah Bukti Pembayaran
        </h3>
        @if ($buktiAda)
            <div class="mt-3 border-l-2 border-navy bg-navy-50 p-4 text-sm leading-relaxed">
                Bukti sudah kami terima dan sedang dicocokkan dengan mutasi rekening.
                Kami kabari lewat email begitu tercatat.
                <a href="{{ route('lacak.bukti.lihat', [$order->access_token, $tagihan['pembayaran']]) }}"
                   target="_blank" rel="noopener" class="tautan">Lihat yang Anda kirim</a>
            </div>
        @else
            <p class="mt-1 text-sm leading-relaxed text-muted">
                Unggah foto atau tangkapan layar bukti transfer. Pembayaran akan diverifikasi sesuai mutasi rekening pada jam operasional ({{ Str::lcfirst((string) setting('cs_jam_layanan')) }}). Konfirmasi akan dikirim melalui email.
            </p>
        @endif

        <form method="POST" action="{{ route('lacak.bukti', $order->access_token) }}"
              enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-56 flex-1">
                <label for="bukti" class="label-form">
                    {{ $buktiAda ? 'Salah kirim? Ganti buktinya' : 'Pilih file' }}
                </label>
                <input type="file" id="bukti" name="bukti" accept="image/*" class="kotak-input" required>
                <p class="mt-1 text-xs text-muted">JPG atau PNG, maksimal {{ setting('upload_maks_mb', 10) }} MB.</p>
            </div>
            <button type="submit" class="tombol {{ $buktiAda ? 'tombol-garis' : 'tombol-navy' }} mb-5">
                <x-ikon nama="unggah" ukuran="size-4" />
                {{ $buktiAda ? 'Ganti bukti' : 'Unggah bukti' }}
            </button>
        </form>
    </div>

    </div>

    {{-- Penipu yang menyamar sebagai toko biasanya bekerja lewat WhatsApp
         dengan rekening pengganti. Halaman ini, di domain toko, adalah
         satu-satunya sumber rekening yang sah. --}}
    <div class="rounded-[12px] border border-kuning bg-kuning-50 p-4 text-sm leading-relaxed">
        <p class="flex items-center gap-2 font-bold"><x-ikon nama="peringatan" ukuran="size-5" class="text-warn" /> Catatan penting</p>
        <ul class="mt-2 list-inside list-disc space-y-1">
            <li>Bayar sesuai nominal dan sebelum batas waktu yang tertera.</li>
            <li>Pesanan diproses setelah pembayaran kami verifikasi.</li>
            <li>Setelah bukti terkirim, kami cocokkan lalu kabari lewat email.</li>
            <li><strong>Pembayaran hanya melalui rekening resmi atau QRIS yang tertera di halaman ini.</strong>
                Abaikan permintaan pembayaran melalui WhatsApp atau rekening lain yang mengatasnamakan Setia Sport.</li>
        </ul>
    </div>
</section>
