{{-- Data contoh (KEP-035) tampil di halaman depan seperti isian asli. Banner ini
     memastikan tidak ada yang lupa: testimoni karangan yang tayang untuk umum
     sama dengan ulasan palsu. --}}
@if (app(\App\Services\ContohProfil::class)->ada())
    <div class="mt-6 flex flex-wrap items-center gap-4 border-l-2 border-kuning bg-kuning-50 px-4 py-3 text-sm leading-relaxed">
        <p class="min-w-60 flex-1">
            <strong>Sebagian isi di sini adalah contoh</strong> untuk memperlihatkan tampilan halaman
            depan. Hapus sebelum website dibuka untuk umum, lalu isi dengan pekerjaan dan pelanggan
            yang asli.
        </p>
        <form method="POST" action="{{ route('panel.profil.contoh.hapus') }}"
              data-konfirmasi="Hapus semua portofolio dan testimoni contoh?"
              data-konfirmasi-pesan="Isian asli tidak ikut terhapus."
              data-konfirmasi-tombol="Hapus contoh">
            @csrf @method('DELETE')
            <button type="submit" class="tombol tombol-garis tombol-sm">Hapus semua data contoh</button>
        </form>
    </div>
@endif
