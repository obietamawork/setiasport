<?php

/*
 * Penyedia hitung ongkir dan pelacakan paket (KEP-057).
 *
 * Selama kuncinya belum ada, penyedianya "mati": checkout tetap jalan dan
 * ongkirnya diisi admin seperti sekarang. Itu bukan keadaan darurat, melainkan
 * cara kerja toko hari ini, dan ia harus tetap bekerja walau API mati atau
 * saldonya habis.
 */
return [
    'penyedia' => env('ONGKIR_PENYEDIA', 'mati'),

    'biteship' => [
        'kunci' => env('BITESHIP_KUNCI'),
        'url' => env('BITESHIP_URL', 'https://api.biteship.com'),

        // Area asal pengiriman, didapat dari pencarian area penyedia.
        'area_asal' => env('BITESHIP_AREA_ASAL'),
    ],

    /*
     * Kurir yang ditawarkan ke pelanggan. Klien memakai JNE dan J&T, termasuk
     * layanan kargo untuk barang besar.
     */
    'kurir' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('ONGKIR_KURIR', 'jne,jnt')))
    )),

    // Batas wajar berat satu paket sebelum dipecah; dipakai memperingatkan admin.
    'berat_maks_gram' => (int) env('ONGKIR_BERAT_MAKS', 30_000),
];
