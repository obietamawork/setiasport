@extends('layouts.panel')
@section('judul', 'FAQ chatbot')

@section('isi')
<x-kop judul="FAQ chatbot" :kembali="route('panel.cs.index')" kembali-label="Customer Service"
       catatan="Asisten otomatis menjawab lebih dulu: aturan pasti, lalu FAQ di halaman ini, lalu AI bila diaktifkan, dan admin untuk sisanya. Komplain, pembayaran, dan foto selalu langsung ke admin." />

{{-- Ringkasan bulan ini (KEP-151). --}}
<div class="grid grid-cols-2 gap-3 md:grid-cols-4">
    @foreach ([
        ['Dijawab FAQ', $statistik['faq'], 'text-ok'],
        ['Menu dan jawaban sistem', $statistik['otomatis'], 'text-navy'],
        ['Dijawab AI', $statistik['ai'], 'text-biru-700'],
        ['Diteruskan ke admin', $statistik['admin'], 'text-warn'],
    ] as [$label, $angka, $warna])
        <div class="kartu p-4">
            <p class="text-xs font-semibold text-muted">{{ $label }}</p>
            <p class="tabular mt-1 text-2xl font-extrabold {{ $warna }}">{{ $angka }}</p>
            <p class="text-xs text-muted">bulan ini</p>
        </div>
    @endforeach
</div>
@if ($statistik['biaya'] > 0)
    <p class="mt-2 text-xs text-muted">Biaya AI bulan ini: <span class="tabular font-semibold text-ink">US${{ number_format($statistik['biaya'], 4) }}</span></p>
@endif

<nav class="segmen mt-6" aria-label="Bagian FAQ chatbot">
    <a href="{{ route('panel.faq.index') }}" @if ($tab === 'faq') aria-current="page" @endif
       class="segmen-item {{ $tab === 'faq' ? 'segmen-item-aktif' : '' }}">FAQ ({{ $faq->count() }})</a>
    <a href="{{ route('panel.faq.index', ['tab' => 'belum']) }}" @if ($tab === 'belum') aria-current="page" @endif
       class="segmen-item {{ $tab === 'belum' ? 'segmen-item-aktif' : '' }}">Belum terjawab ({{ $jumlahBelum }})</a>
</nav>

@if ($tab === 'faq')
    <div class="mt-4 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,26rem)] lg:items-start">
        <div class="kartu divide-y divide-line overflow-hidden">
            @forelse ($faq as $f)
                <div class="flex items-start gap-4 px-4 py-3 {{ $f->aktif ? '' : 'opacity-60' }}">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ $f->pertanyaan }}</p>
                        <p class="mt-1 line-clamp-2 text-sm leading-relaxed whitespace-pre-line text-muted">{{ $f->jawaban }}</p>
                        <p class="mt-1.5 flex flex-wrap gap-1.5 text-xs">
                            @if ($f->tampil_di_menu) <span class="chip chip-navy">Di menu</span> @endif
                            @unless ($f->aktif) <span class="chip chip-netral">Nonaktif</span> @endunless
                            <span class="tabular text-muted">dipakai {{ $f->dipakai }} kali</span>
                        </p>
                    </div>
                    <a href="{{ route('panel.faq.index', ['ubah' => $f->id]) }}" class="tautan shrink-0 text-sm">Ubah</a>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-muted">Belum ada FAQ. Mulai dari pertanyaan yang paling sering datang.</p>
            @endforelse
        </div>

        @php
            $isian = $ubah ?? new \App\Models\FaqBot([
                'pertanyaan' => $dariLog?->pertanyaan,
                'jawaban' => $dariLog?->jawaban,
                'aktif' => true,
            ]);
        @endphp
        <form method="POST" action="{{ $ubah ? route('panel.faq.update', $ubah) : route('panel.faq.store') }}" class="kartu space-y-4 p-5">
            @csrf
            @if ($ubah) @method('PUT') @endif
            @if ($dariLog) <input type="hidden" name="log_id" value="{{ $dariLog->id }}"> @endif

            <h2 class="font-semibold">{{ $ubah ? 'Ubah FAQ' : ($dariLog ? 'Jadikan FAQ' : 'FAQ baru') }}</h2>

            <div>
                <label for="pertanyaan" class="label-form">Pertanyaan</label>
                <input id="pertanyaan" name="pertanyaan" value="{{ old('pertanyaan', $isian->pertanyaan) }}" class="kotak-input"
                       maxlength="200" required placeholder="Berapa minimal pemesanan?">
                <p class="bantuan-form">Tampil sebagai tombol di menu chatbot bila dicentang di bawah.</p>
            </div>

            <div>
                <label for="jawaban" class="label-form">Jawaban</label>
                <textarea id="jawaban" name="jawaban" rows="7" class="kotak-input leading-relaxed" maxlength="2000" required>{{ old('jawaban', $isian->jawaban) }}</textarea>
                <p class="bantuan-form">
                    Boleh memakai penanda yang diisi dari Pengaturan:
                    <code>{toko_nama}</code>, <code>{toko_alamat}</code>, <code>{toko_jam_buka}</code>, <code>{cs_jam_layanan}</code>,
                    <code>{dp_persen}</code>, <code>{batas_bayar_jam}</code>, <code>{moq_terkecil}</code>.
                    Jangan menulis nomor rekening; pembayaran selalu lewat halaman pesanan.
                </p>
            </div>

            <div>
                <label for="kata_kunci" class="label-form">Kata kunci</label>
                <textarea id="kata_kunci" name="kata_kunci" rows="3" class="kotak-input text-sm leading-relaxed" maxlength="1000"
                          placeholder="minimal pesan, min order, moq">{{ old('kata_kunci', $isian->kata_kunci) }}</textarea>
                <p class="bantuan-form">
                    Pisahkan dengan koma. Tulis cara pelanggan biasa bertanya, termasuk singkatan. Kata yang terlalu umum
                    seperti "pesan" atau "jersey" membuat FAQ ini menjawab pertanyaan yang bukan untuknya.
                </p>
            </div>

            <div class="space-y-2 text-sm">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="tampil_di_menu" value="1" class="kotak-centang" @checked(old('tampil_di_menu', $isian->tampil_di_menu))>
                    Tampil di menu topik chatbot
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="aktif" value="1" class="kotak-centang" @checked(old('aktif', $isian->aktif ?? true))>
                    Aktif
                </label>
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t border-line pt-4">
                <button type="submit" class="tombol tombol-navy">Simpan FAQ</button>
                @if ($ubah || $dariLog)
                    <a href="{{ route('panel.faq.index') }}" class="tautan text-sm">Batal</a>
                @endif
            </div>
        </form>
    </div>

    @if ($ubah)
        <form method="POST" action="{{ route('panel.faq.destroy', $ubah) }}" class="mt-4"
              data-konfirmasi="Hapus FAQ ini?" data-konfirmasi-tombol="Hapus">
            @csrf @method('DELETE')
            <button type="submit" class="tautan text-sm text-muted">Hapus FAQ ini</button>
        </form>
    @endif
@else
    <p class="mt-4 text-sm leading-relaxed text-muted">
        Pertanyaan yang dijawab AI atau diteruskan ke admin karena bot belum tahu jawabannya. Jadikan FAQ supaya
        pertanyaan serupa berikutnya dijawab seketika, atau abaikan bila hanya sekali lewat.
    </p>
    <div class="kartu mt-4 divide-y divide-line overflow-hidden">
        @forelse ($belum as $log)
            <div class="flex flex-wrap items-start gap-4 px-4 py-3">
                <div class="min-w-0 flex-1 basis-64">
                    <p class="font-semibold break-words">{{ $log->pertanyaan }}</p>
                    <p class="mt-0.5 text-xs text-muted">
                        {{ $log->created_at->translatedFormat('j M Y, H.i') }} ·
                        {{ $log->lapis === 'ai' ? 'Dijawab AI' : 'Diteruskan ke admin: '.$log->alasan }}
                    </p>
                    @if ($log->jawaban)
                        <p class="mt-1.5 line-clamp-3 border-l-2 border-line pl-2 text-sm text-muted">{{ $log->jawaban }}</p>
                    @endif
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <a href="{{ route('panel.faq.index', ['log' => $log->id]) }}" class="tombol tombol-garis tombol-sm">Jadikan FAQ</a>
                    <form method="POST" action="{{ route('panel.faq.abaikan', $log) }}">
                        @csrf
                        <button type="submit" class="tombol tombol-halus tombol-sm">Abaikan</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="px-4 py-8 text-center text-sm text-muted">Semua pertanyaan sudah terjawab FAQ. Bagus.</p>
        @endforelse
    </div>
@endif
@endsection
