{{-- Penutup halaman bukti (KEP-106): yang baru melihat hasil kerja tinggal
     satu ketukan dari memesan, tanpa kembali ke beranda dulu. --}}
<div class="kartu mt-10 flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <p class="text-lg font-bold">Mau dibuatkan juga?</p>
        <p class="mt-1 text-sm leading-relaxed text-muted">
            Pilih produk kustom, atur bahan dan ukuran, lalu lihat harganya langsung.
        </p>
    </div>
    <div class="kelompok-tombol shrink-0">
        <a href="{{ route('katalog', ['jalur' => 'kustom']) }}" class="tombol tombol-utama">Mulai pesan kustom</a>
        <a href="{{ tautan_wa('Halo, saya mau tanya soal pesanan kustom') }}" target="_blank" rel="noopener"
           class="tombol tombol-garis">Tanya lewat WhatsApp</a>
    </div>
</div>
