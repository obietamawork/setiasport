@props(['nama', 'ukuran' => 'size-5'])

@php
    /*
     * Ikon panel, digambar sendiri sebagai SVG sebaris.
     *
     * Bukan pustaka ikon: panel dipakai di konveksi yang jaringannya putus-putus,
     * dan ikon yang gagal dimuat membuat menu jadi deretan kotak kosong. Semuanya
     * satu gaya: garis 1.7, kotak 24, ujung membulat, supaya tidak terlihat
     * seperti kumpulan tempelan dari tempat berbeda.
     *
     * Ikon hanya menemani teks, tidak pernah menggantikannya (kecuali di rail
     * sempit, yang tetap membawa label lewat title dan pembaca layar). Nama yang
     * tidak dikenal menghasilkan titik netral, bukan halaman rusak.
     */
    $jalur = [
        // Navigasi
        'dashboard' => '<path d="M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-16v4h6V4h-6Z"/>',
        'pesanan' => '<path d="M6 3h9l5 5v13H6V3Z"/><path d="M14 3v6h6"/><path d="M9 13h7M9 17h5"/>',
        'antrian' => '<path d="M4 6h10M4 12h16M4 18h7"/><path d="M18 5.5 19.5 7 22 4.5"/>',
        'serah-terima' => '<path d="M3 11h4l2.5 2.5a2 2 0 0 0 2.8 0L15 11h6"/><path d="M6 11V5h12v6"/><path d="M3 11v8h18v-8"/>',
        'chat' => '<path d="M20 15a2 2 0 0 1-2 2H8l-4 4V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v9Z"/>',
        'produk' => '<path d="M3.5 8 12 3.5 20.5 8 12 12.5 3.5 8Z"/><path d="M3.5 8v8L12 20.5 20.5 16V8"/><path d="M12 12.5v8"/>',
        'kategori' => '<path d="M4 5h7v7H4V5Zm9 0h7v7h-7V5ZM4 14h7v5H4v-5Zm9 0h7v5h-7v-5Z"/>',
        'bahan' => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 9.5c2-1.5 4-1.5 6 0s4 1.5 6 0 4-1.5 4-1.5M4 15c2-1.5 4-1.5 6 0s4 1.5 6 0 4-1.5 4-1.5"/>',
        'stok' => '<path d="M4 7.5 12 4l8 3.5v9L12 20l-8-3.5v-9Z"/><path d="M4 7.5 12 11l8-3.5M12 11v9"/>',
        'portofolio' => '<path d="M4 6h16v13H4V6Z"/><path d="m4 15 4.5-4 3.5 3.2L16 10l4 4.2"/><circle cx="9" cy="9.5" r="1.2"/>',
        'testimoni' => '<path d="m12 4 2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.6-4.8 2.6.9-5.4L4.2 9.7l5.4-.8L12 4Z"/>',
        'pekerja' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0"/><path d="M16 6.2a3 3 0 0 1 0 5.6M18 19a5 5 0 0 0-2.5-4.3"/>',
        'laporan' => '<path d="M4 20V10M10 20V4m6 16v-7m6 7V8"/>',
        'pengaturan' => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21M5.6 5.6l1.8 1.8m9.2 9.2 1.8 1.8M18.4 5.6l-1.8 1.8M7.4 16.6l-1.8 1.8"/>',

        // Tindakan
        'tambah' => '<path d="M12 5v14M5 12h14"/>',
        'cari' => '<circle cx="11" cy="11" r="6"/><path d="m20 20-3.5-3.5"/>',
        'saring' => '<path d="M4 6h16M7 12h10M10 18h4"/>',
        'unduh' => '<path d="M12 4v10m0 0 4-4m-4 4-4-4"/><path d="M5 18h14"/>',
        'cetak' => '<path d="M7 9V4h10v5"/><path d="M5 9h14a2 2 0 0 1 2 2v5h-4v4H7v-4H3v-5a2 2 0 0 1 2-2Z"/>',
        'salin' => '<path d="M9 9h10v10H9z"/><path d="M5 15V5h10"/>',
        'buka-luar' => '<path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'kembali' => '<path d="M15 6l-6 6 6 6"/>',
        'lanjut' => '<path d="M9 6l6 6-6 6"/>',
        'turun' => '<path d="M6 9l6 6 6-6"/>',
        'centang' => '<path d="m5 12.5 4.5 4.5L19 7"/>',
        'silang' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'hapus' => '<path d="M4 7h16"/><path d="M10 11v6M14 11v6"/><path d="M6 7l1 13h10l1-13"/><path d="M9 7V4h6v3"/>',
        'jam' => '<circle cx="12" cy="12" r="8"/><path d="M12 7.5V12l3 1.8"/>',
        'peringatan' => '<path d="M12 4 2.8 20h18.4L12 4Z"/><path d="M12 10v4.5M12 17.4v.2"/>',
        'uang' => '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/>',
        'kirim' => '<path d="M3 7h10v8H3V7Z"/><path d="M13 10h4l3 3v2h-7v-5Z"/><circle cx="7" cy="17.5" r="1.6"/><circle cx="16.5" cy="17.5" r="1.6"/>',
        'toko' => '<path d="M4 9h16v11H4V9Z"/><path d="M3 9l1.6-5h14.8L21 9"/><path d="M9.5 20v-6h5v6"/>',
        'orang' => '<circle cx="12" cy="8" r="3.4"/><path d="M5 19.5a7 7 0 0 1 14 0"/>',
        'keluar' => '<path d="M14 5h4a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-4"/><path d="M10 8l-4 4 4 4"/><path d="M6 12h9"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'qr' => '<path d="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Z"/><path d="M14 14h2.5v2.5H14V14Zm5.5 0H20v2.5M14 19.5h2.5V20M19.5 19.5H20"/>',
        'bantuan' => '<circle cx="12" cy="12" r="9"/><path d="M9.6 9.3a2.5 2.5 0 0 1 4.9.7c0 1.7-2.5 2-2.5 3.5"/><path d="M12 17v.2"/>',
        /*
         * Tiga ikon berikut digambar sebagai bidang penuh (fill), bukan garis,
         * karena begitulah lambang merek itu dikenali. Mereka satu-satunya
         * pengecualian dari gaya garis di atas.
         */
        'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1.1" fill="currentColor" stroke="none"/>',
        'tiktok' => '<path d="M14.2 3.5v10.9a3.3 3.3 0 1 1-2.8-3.26"/><path d="M14.2 5.2a4.6 4.6 0 0 0 4.3 3.3"/>',
        'facebook' => '<path d="M13.5 21v-8h2.6l.4-3h-3V8.3c0-.9.3-1.5 1.6-1.5H16.7V4.1A22 22 0 0 0 14.4 4c-2.3 0-3.9 1.4-3.9 4v2H8v3h2.5v8"/>',
        'whatsapp' => '<path d="M20 11.7a8 8 0 0 1-11.9 7L4 20l1.4-4a8 8 0 1 1 14.6-4.3Z"/><path d="M9.2 9.1c.2-.4.4-.4.6-.4h.5c.2 0 .4 0 .6.5l.7 1.7c.1.2.1.4 0 .5l-.4.5c-.1.2-.3.3-.1.6a6 6 0 0 0 2.8 2.4c.3.1.4 0 .6-.1l.6-.7c.2-.2.3-.2.5-.1l1.6.8c.2.1.4.2.4.3a2 2 0 0 1-1.3 1.6c-.4.1-.9.2-2.6-.5a9 9 0 0 1-4-3.6c-.2-.3-.8-1.4-.8-2.6 0-1.2.6-1.8.8-2Z"/>',
        'gambar' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m5 17 4.5-4.5 3 2.6L16 11l3 3.4"/><circle cx="9" cy="9.5" r="1.3"/>',
    ];

    $isi = $jalur[$nama] ?? '<circle cx="12" cy="12" r="2.5"/>';
@endphp

<svg {{ $attributes->merge(['class' => $ukuran.' shrink-0']) }} viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true">{!! $isi !!}</svg>
