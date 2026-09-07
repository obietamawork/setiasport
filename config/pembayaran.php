<?php

return [

    /*
     * Gerbang pembayaran aktif. Dibiarkan kosong sampai merchant disetujui —
     * sistem tetap berjalan penuh dengan transfer manual (KEP-003), dan tombol
     * bayar online sekadar tidak muncul.
     */
    'gerbang' => env('PEMBAYARAN_GERBANG', ''),

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'produksi' => (bool) env('MIDTRANS_PRODUKSI', false),

        'endpoint' => [
            'sandbox' => 'https://app.sandbox.midtrans.com/snap/v1/transactions',
            'produksi' => 'https://app.midtrans.com/snap/v1/transactions',
        ],
    ],

];
