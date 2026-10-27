<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Stok varian tidak lagi cukup (atau variannya hilang) saat pesanan dibuat
 * atau diubah. Pesannya ditulis untuk ditampilkan apa adanya.
 *
 * Kelas sendiri, bukan RuntimeException polos (KEP-090): checkout dulu
 * menangkap semua RuntimeException sebagai "stok habis", sehingga galat
 * pemrograman apa pun ikut tampil ke pelanggan sebagai pesan stok dan tidak
 * pernah tercatat di log.
 */
class StokTidakCukup extends RuntimeException {}
