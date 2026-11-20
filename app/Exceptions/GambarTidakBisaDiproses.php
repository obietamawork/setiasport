<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Gambar unggahan tidak bisa dibuka: isinya rusak atau terpotong, atau
 * resolusinya tidak muat di memori (KEP-153, KEP-154). Pesannya ditulis untuk
 * ditampilkan apa adanya kepada pengunggah.
 *
 * Kelas sendiri supaya bisa ditangani di satu tempat (bootstrap/app.php):
 * dulu pengecualian ini lolos sebagai galat 500 di hampir semua tempat
 * unggah, termasuk bukti bayar pelanggan dan foto chat CS.
 */
class GambarTidakBisaDiproses extends RuntimeException {}
