<?php

/*
 * Fitur yang dipasang atau dilepas per instalasi, bukan diatur pemilik toko
 * dari panel.
 */
return [

    /*
     * Chat CS di website (KEP-030). Klien membatalkannya untuk sementara
     * (KEP-034), jadi bawaannya mati dan tabnya tidak tampil di Pengaturan:
     * saklar untuk fitur yang tidak dipakai hanya membingungkan pemilik toko.
     * Menyalakan kembali: FITUR_CS=true, lalu nyalakan saklarnya di
     * Pengaturan > Chat CS. Langkah lengkapnya di docs/12-deploy.md.
     */
    'cs' => (bool) env('FITUR_CS', false),

];
