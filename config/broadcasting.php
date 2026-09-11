<?php

/*
 * Pengangkut realtime untuk chat CS (KEP-030).
 *
 * Hanya satu koneksi yang dipakai: 'pusher'. Di produksi ia menuju Pusher cloud,
 * karena shared hosting tidak bisa memegang koneksi websocket yang terbuka lama.
 * Di lokal ia menuju Reverb (service `reverb` di compose.yaml), yang berbicara
 * dengan protokol yang sama persis. Kode aplikasi tidak tahu bedanya, jadi yang
 * dites di lokal adalah jalur yang sama dengan yang berjalan di hosting.
 *
 * Kalau kuncinya kosong, broadcast jatuh ke 'null' dan chat tetap berjalan lewat
 * polling lambat di widget. Itu disengaja: chat CS tidak boleh mati hanya karena
 * kuota gratis Pusher habis atau kuncinya belum diisi.
 */
return [

    'default' => env('PUSHER_APP_KEY') ? env('BROADCAST_CONNECTION', 'pusher') : 'null',

    /*
     * Yang dibutuhkan browser untuk tersambung. Dioper lewat halaman saat
     * dijalankan, BUKAN dibakar ke hasil build Vite: aset dibangun sekali di
     * lokal lalu diunggah ke hosting, jadi nilai yang dibakar akan menunjuk ke
     * Reverb lokal di produksi. Kunci Pusher memang publik; rahasianya tidak
     * pernah keluar dari server.
     */
    'klien' => [
        'key' => env('PUSHER_APP_KEY'),
        'cluster' => env('PUSHER_APP_CLUSTER', 'ap1'),
        'host' => env('PUSHER_CLIENT_HOST') ?: null,
        'port' => env('PUSHER_CLIENT_PORT') ? (int) env('PUSHER_CLIENT_PORT') : null,
        'tls' => filter_var(env('PUSHER_CLIENT_TLS', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                // ap1 = Singapura, klaster Pusher terdekat dari Indonesia.
                'cluster' => env('PUSHER_APP_CLUSTER', 'ap1'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'ap1').'.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Pesan dikirim di tengah permintaan pelanggan (ShouldBroadcastNow).
                // Pusher yang lambat tidak boleh membuat tombol Kirim ikut macet.
                'timeout' => 4,
                'connect_timeout' => 3,
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
