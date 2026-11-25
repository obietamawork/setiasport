<?php

namespace App\Support;

/**
 * Isi panduan internal panel (KEP-162), dipisah dari controller supaya mudah
 * dirawat.
 *
 * Ditulis seperti menjelaskan ke teman kerja yang baru masuk, bukan seperti
 * manual perangkat lunak: apa yang ditekan, di mana layarnya, dan kenapa
 * sistemnya dibuat begitu. Alasannya ikut ditulis karena aturan yang tidak
 * dimengerti akan dicari jalan pintasnya.
 *
 * Tiap bagian punya izin: bagian tampil kalau penggunanya punya salah satu
 * izin itu, atau semuanya kalau izinnya null. Pemilik punya semua izin.
 * Bagian berisi 'langkah' (alur bernomor, dari awal sampai akhir) dan/atau
 * 'isi' (tanya jawab). Ditaruh di kode, bukan di pengaturan yang bisa
 * disunting, supaya isinya tidak pernah berselisih dengan cara sistemnya
 * benar-benar bekerja.
 */
final class IsiPanduan
{
    /** Izin meja produksi; dipakai bagian konveksi dan halaman cetaknya. */
    public const IZIN_PRODUKSI = [
        'produksi.desain', 'produksi.print', 'produksi.press', 'produksi.potong',
        'produksi.jahit', 'produksi.qc', 'produksi.packing',
    ];

    /**
     * @return list<array{kunci: string, kelompok: string, judul: string, ikon: string, izin: ?list<string>,
     *     catatan: string, langkah?: list<array{t: string, j: string}>, isi?: list<array{t: string, j: string}>}>
     */
    public static function semua(): array
    {
        return [
            ...self::mulai(),
            ...self::alur(),
            ...self::harian(),
            ...self::pemilik(),
            ...self::konveksi(),
            ...self::kamus(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function mulai(): array
    {
        return [
            [
                'kunci' => 'mulai',
                'kelompok' => 'Mulai di sini',
                'judul' => 'Baru pegang panel ini?',
                'ikon' => 'dashboard',
                'izin' => null,
                'catatan' => 'Tiga hal yang perlu dimengerti dulu. Sisanya bisa dipelajari sambil jalan.',
                'isi' => [
                    [
                        't' => 'Panel ini sebenarnya untuk apa',
                        'j' => 'Untuk menggantikan catatan di buku dan chat WhatsApp yang berserakan. '
                            .'Semua pesanan, uang masuk, antrian jahit, dan stok dicatat di satu tempat, '
                            .'supaya pelanggan bisa memeriksa sendiri pesanannya lewat website dan tidak '
                            .'perlu menelepon toko untuk tanya "sudah jadi belum".',
                    ],
                    [
                        't' => 'Kenapa banyak hal tidak bisa saya ubah langsung',
                        'j' => 'Karena status pesanan bukan sesuatu yang diketik, melainkan akibat dari '
                            .'pekerjaan yang benar-benar terjadi. Misalnya, pesanan naik jadi "Sedang '
                            .'Diproses" bukan karena ada yang menekan tombol itu, melainkan karena '
                            .'pembayarannya sudah diverifikasi. Begitu juga "Siap Diambil": ia muncul '
                            .'sendiri setelah orang konveksi menandai tahap terakhirnya selesai. '
                            .'Kalau status bisa diketik bebas, suatu hari layar akan bilang barang sudah '
                            .'siap padahal kainnya belum dipotong.',
                    ],
                    [
                        't' => 'Kalau saya bingung harus apa di satu pesanan',
                        'j' => 'Buka pesanannya. Di paling atas selalu ada satu kotak yang menyebut '
                            .'langkah berikutnya: siapa yang sedang ditunggu, dan tombol apa yang perlu '
                            .'ditekan. Kotak itu bukan hiasan; ia dihitung dari keadaan pesanan itu sendiri.',
                    ],
                    [
                        't' => 'Saya salah tekan sesuatu',
                        'j' => 'Sebagian besar hal bisa diperbaiki: angka stok tinggal dibetulkan, '
                            .'penyesuaian biaya bisa dihapus, alamat kirim bisa diganti. Yang tidak bisa '
                            .'ditarik kembali hanya yang sudah keluar dari toko: email yang terkirim, '
                            .'resi yang dicatat, dan barang yang sudah diserahkan. Kalau yang itu yang '
                            .'salah, jangan menambal sendiri dengan angka karangan, kabari pemilik toko.',
                    ],
                    [
                        't' => 'Halaman meminta saya mengulang karena "terlalu lama terbuka"',
                        'j' => 'Sesi panel berakhir setelah lama didiamkan. Halamannya dimuat ulang '
                            .'dengan isian teks yang sudah Anda ketik, jadi cukup tekan tombolnya sekali '
                            .'lagi. Berkas yang tadi dipilih perlu dipilih ulang.',
                    ],
                ],
            ],
            [
                'kunci' => 'orang',
                'kelompok' => 'Mulai di sini',
                'judul' => 'Siapa boleh apa',
                'ikon' => 'pekerja',
                'izin' => null,
                'catatan' => 'Kalau satu menu tidak kelihatan di layar Anda, kemungkinan besar memang bukan bagian Anda.',
                'isi' => [
                    [
                        't' => 'Menu saya lebih sedikit daripada teman sebelah',
                        'j' => 'Memang begitu. Tiap peran hanya melihat yang dia kerjakan: kasir tidak '
                            .'perlu melihat harga pokok, orang jahit tidak perlu melihat data pelanggan. '
                            .'Halaman panduan ini juga begitu: yang tampil hanya bagian yang sesuai peran '
                            .'Anda. Kalau ada yang benar-benar Anda butuhkan tapi tidak muncul, minta '
                            .'pemilik toko menambahkan perannya, jangan pinjam akun orang lain.',
                    ],
                    [
                        't' => 'Pekerja konveksi tidak punya akun panel',
                        'j' => 'Betul, dan itu memang cara kerjanya. Mereka memakai QR di lembar kerja '
                            .'plus PIN divisi, jadi tidak ada kata sandi yang perlu diingat, dan tidak ada '
                            .'harga atau nomor HP pelanggan yang ikut terlihat di HP mereka. Yang '
                            .'mengajari mereka adalah pemilik dan admin; bahannya ada di bagian Panduan '
                            .'karyawan konveksi.',
                    ],
                    [
                        't' => 'Semua yang saya tekan tercatat?',
                        'j' => 'Ya, hal-hal yang penting: verifikasi pembayaran, pengembalian uang, '
                            .'penyesuaian stok, perubahan pengaturan, data pelanggan, pengiriman, dan '
                            .'serah terima. Bukan untuk mengawasi orang, tetapi supaya kalau ada selisih, '
                            .'yang dicari adalah kejadiannya, bukan orangnya.',
                    ],
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function alur(): array
    {
        return [
            [
                'kunci' => 'alur-stok',
                'kelompok' => 'Alur pesanan A sampai Z',
                'judul' => 'Pesanan barang stok',
                'ikon' => 'stok',
                'izin' => ['order.lihat'],
                'catatan' => 'Bola, sepatu, dan barang lain yang sudah ada di rak. Dibayar penuh di depan, tanpa DP.',
                'langkah' => [
                    ['t' => 'Pelanggan checkout di website', 'j' => 'Ia memilih diambil di toko atau dikirim. Kalau dikirim, ia mencari alamatnya (bisa dengan kode pos) dan memilih kurir JNE atau J&T; ongkirnya ikut di tagihan. Stok langsung dicadangkan, dan pelanggan menerima email "pesanan diterima".'],
                    ['t' => 'Pelanggan membayar', 'j' => 'Transfer atau QRIS ke rekening di halaman pesanannya, lalu mengunggah bukti di halaman yang sama. Kalau tidak dibayar sampai batas waktu, pesanan kedaluwarsa sendiri dan stoknya dilepas, kecuali buktinya sudah terkirim.'],
                    ['t' => 'Admin memverifikasi', 'j' => 'Cocokkan dengan mutasi rekening atau riwayat QRIS, isi nominal yang benar-benar masuk, lalu verifikasi. Pelanggan menerima email pembayaran diterima.'],
                    ['t' => 'Barang disiapkan', 'j' => 'Ambil barangnya dari rak, lalu majukan progresnya lewat tombol Perbarui progres di halaman pesanan. Begitu siap, pelanggan menerima email beserta kode ambil (kalau diambil di toko).'],
                    ['t' => 'Diambil di toko', 'j' => 'Pelanggan menyebut kode ambil di kasir. Buka Serah terima, cari kodenya, serahkan barangnya. Pesanan selesai.'],
                    ['t' => 'Atau dikirim', 'j' => 'Kemas, serahkan ke kurir, lalu catat jasa kirim dan nomor resi di bagian Pengambilan. Resi hanya bisa dicatat kalau pesanan sudah lunas. Pelanggan menerima email berisi resi, dan bisa menandai paketnya sudah diterima dari halaman pesanannya.'],
                ],
            ],
            [
                'kunci' => 'alur-kustom',
                'kelompok' => 'Alur pesanan A sampai Z',
                'judul' => 'Pesanan kustom dengan DP',
                'ikon' => 'kaos',
                'izin' => ['order.lihat'],
                'catatan' => 'Jersey dan kaos yang dibuat dari nol: desain, persetujuan pelanggan, produksi, lalu pelunasan.',
                'langkah' => [
                    ['t' => 'Pelanggan mengisi wizard pesan kustom', 'j' => 'Memilih model jersey (pria atau wanita), bahan, lengan, kerah, mengisi daftar pemain dan ukuran (bisa ditempel dari Excel), lalu memilih sumber desain: dari galeri, kirim desain sendiri, atau dibantu admin.'],
                    ['t' => 'Pelanggan membayar DP atau lunas', 'j' => 'DP minimal sesuai Pengaturan (bawaannya 50%) dari harga barang; ongkir tidak ikut DP. Verifikasi pembayarannya seperti pesanan stok.'],
                    ['t' => 'Tim desain mengunggah mockup', 'j' => 'Dari antrian Desain atau halaman pesanan. Pelanggan menerima email dan memeriksa mockup di halaman pesanannya.'],
                    ['t' => 'Pelanggan menyetujui atau minta revisi', 'j' => 'Hanya pelanggan yang boleh menyetujui desain; tidak bisa diwakilkan dari panel. Revisi gratis sampai batas di Pengaturan.'],
                    ['t' => 'Cetak lembar kerja', 'j' => 'Dari halaman pesanan, setelah desain disetujui. Lembar kerja ikut bersama kain ke konveksi; QR-nya dipakai menandai setiap tahap.'],
                    ['t' => 'Produksi berjalan per meja', 'j' => 'Printing, Press / Sublime, Potong Kain, Jahit, lalu Finishing & Packing. Tiap meja menandai selesai dengan memindai QR dan mengetik PIN divisinya; barang muncul sendiri di antrian meja berikutnya.'],
                    ['t' => 'Pelunasan', 'j' => 'Kalau belum lunas, barang berhenti di "Menunggu pelunasan" dan pelanggan menerima email untuk melunasi (transfer, atau tunai saat mengambil). Kalau sudah lunas di depan, barang langsung siap.'],
                    ['t' => 'Diambil atau dikirim', 'j' => 'Sama seperti pesanan stok: kode ambil di kasir, atau resi setelah lunas.'],
                ],
            ],
            [
                'kunci' => 'alur-toko',
                'kelompok' => 'Alur pesanan A sampai Z',
                'judul' => 'Pesanan dari toko atau WhatsApp',
                'ikon' => 'toko',
                'izin' => ['order.kelola'],
                'catatan' => 'Untuk pembeli yang datang ke konter, reseller, atau yang memesan lewat chat.',
                'langkah' => [
                    ['t' => 'Buka Pesanan, lalu Pesanan baru', 'j' => 'Isi nama, nomor HP, dan email pelanggan. Email itu yang menerima tautan halaman pesanan, jadi minta yang aktif.'],
                    ['t' => 'Isi barangnya', 'j' => 'Barang stok atau kustom, sama dengan yang tersedia di website. Harga dan potongan borongan dihitung sistem; biaya layanan juga ikut kalau dinyalakan di Pengaturan.'],
                    ['t' => 'Catat uang yang diterima saat itu', 'j' => 'Tunai atau transfer, berapa pun nominalnya. Sisanya ditagih saat pelunasan.'],
                    ['t' => 'Selanjutnya sama persis', 'j' => 'Pesanan ini mendapat nomor, halaman lacak, lembar kerja, dan QR produksi yang sama dengan pesanan dari website.'],
                ],
            ],
            [
                'kunci' => 'pesanan',
                'kelompok' => 'Alur pesanan A sampai Z',
                'judul' => 'Pertanyaan seputar pesanan',
                'ikon' => 'pesanan',
                'izin' => ['order.lihat'],
                'catatan' => 'Yang paling sering ditanyakan saat pesanan sedang berjalan.',
                'isi' => [
                    [
                        't' => 'Pelanggan sudah transfer tapi statusnya belum berubah',
                        'j' => 'Itu memang belum otomatis, dan disengaja. Uang baru dianggap masuk setelah '
                            .'ada orang yang melihatnya di mutasi rekening atau riwayat QRIS. Buka '
                            .'pesanannya, di bagian Pembayaran tekan verifikasi, centang pernyataan bahwa '
                            .'Anda sudah mengeceknya, baru pesanannya jalan. Tangkapan layar transfer bisa '
                            .'dibuat siapa saja dalam semenit; mutasi rekening tidak. Isi nominal yang '
                            .'benar-benar masuk: kalau pelanggan mentransfer kurang, catat yang masuk dan '
                            .'sisanya ditagih saat pelunasan. Pesanan yang buktinya sudah dikirim tidak '
                            .'dibatalkan otomatis walau batas bayarnya lewat, jadi bukti yang masuk malam '
                            .'hari aman diperiksa besok pagi.',
                    ],
                    [
                        't' => 'Kenapa ada DP dan pelunasan, bukan bayar sekali saja',
                        'j' => 'Untuk pesanan kustom, kain dan tinta sudah dibeli sebelum barangnya jadi. '
                            .'DP menutup itu. Sisanya ditagih setelah barangnya selesai, dan barang tidak '
                            .'boleh keluar sebelum lunas. Sistem menjaga itu: tombol serah terima tidak '
                            .'akan muncul selama masih ada sisa tagihan, dan kode ambil baru terbit setelah '
                            .'lunas.',
                    ],
                    [
                        't' => 'Pelanggan minta batal',
                        'j' => 'Pembatalan diajukan dari halaman pesanan milik pelanggan, lalu muncul di '
                            .'panel untuk Anda putuskan. Selama belum diputuskan, pekerjaan tetap jalan, '
                            .'jadi jangan digantung berhari-hari. Sistem menghitung usulan pengembalian '
                            .'uangnya berdasarkan sejauh mana pekerjaan sudah berjalan, tapi angka itu '
                            .'usulan; Anda yang memutuskan berapa yang benar. Kalau pelanggan minta batal '
                            .'lewat telepon, pakai Batalkan pesanan di bagian bawah halaman pesanan. Untuk '
                            .'pesanan yang sudah dibayar, isi berapa yang dikembalikan; pelanggan membaca '
                            .'angka itu di halaman pesanannya.',
                    ],
                    [
                        't' => 'Email ke pelanggan tidak sampai',
                        'j' => 'Biasanya alamatnya salah ketik. Betulkan di bagian Pelanggan pada halaman '
                            .'pesanan, lalu tekan Kirim ulang di daftar email. Kalau pelanggannya sudah '
                            .'Anda kabari lewat WhatsApp, tandai sudah dikabari supaya peringatannya '
                            .'hilang dari dashboard.',
                    ],
                    [
                        't' => 'Barang sudah lama tidak diambil',
                        'j' => 'Pesanan yang sudah siap lebih dari batas hari yang disetel di Pengaturan '
                            .'akan muncul di dashboard. Hubungi dulu pelanggannya. Menghanguskan barang '
                            .'adalah langkah terakhir, dan begitu ditandai hangus, uangnya tidak kembali '
                            .'dan stoknya dilepas.',
                    ],
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function harian(): array
    {
        return [
            [
                'kunci' => 'bayar',
                'kelompok' => 'Tugas harian',
                'judul' => 'Pembayaran: verifikasi, tunai, dan bukti ditolak',
                'ikon' => 'dompet',
                'izin' => ['pembayaran.verifikasi', 'pembayaran.catat'],
                'catatan' => 'Semua uang masuk lewat sini, dan setiap catatan menyimpan nama yang mencatatnya.',
                'isi' => [
                    [
                        't' => 'Bukti transfer yang meragukan',
                        'j' => 'Tekan Tolak dan tulis alasannya dengan sopan, misalnya nominal tidak '
                            .'terlihat. Pelanggan membaca alasan itu dan bisa mengunggah bukti baru. '
                            .'Jangan verifikasi sebelum uangnya terlihat di mutasi.',
                    ],
                    [
                        't' => 'Pelanggan bayar tunai di toko',
                        'j' => 'Catat lewat "pembayaran diterima di toko" pada halaman pesanan, atau dari '
                            .'layar Serah terima kalau ia sekalian mengambil barangnya. Jangan lupa: '
                            .'kalau pelanggan sudah mengunggah bukti transfer dan Anda mencatat tunai '
                            .'lagi, pembayarannya terhitung dua kali. Layar serah terima akan '
                            .'memperingatkan Anda kalau itu terjadi.',
                    ],
                    [
                        't' => 'Pelanggan mentransfer lebih dari tagihan',
                        'j' => 'Isi nominal yang benar-benar masuk. Kelebihannya muncul sebagai '
                            .'"Kelebihan bayar" di ringkasan pesanan; kembalikan ke pelanggan, lalu catat '
                            .'lewat "Catat sudah dikembalikan".',
                    ],
                ],
            ],
            [
                'kunci' => 'uang',
                'kelompok' => 'Tugas harian',
                'judul' => 'Tagihan berubah: penyesuaian, ongkir, dan pengembalian',
                'ikon' => 'uang',
                'izin' => ['order.penyesuaian', 'pembayaran.catat'],
                'catatan' => 'Semua angka yang muncul di tagihan pelanggan berasal dari salah satu di bawah ini.',
                'isi' => [
                    [
                        't' => 'Apa bedanya penyesuaian biaya dengan mengubah harga',
                        'j' => 'Harga barang dibekukan saat pesanan dibuat, dan memang tidak boleh berubah '
                            .'diam-diam. Kalau ada tambahan setelah kesepakatan, misalnya tambah dua '
                            .'pemain atau potongan khusus, catat sebagai penyesuaian biaya dengan '
                            .'keterangan yang bisa dibaca pelanggan. Pelanggan melihat baris itu di '
                            .'halaman pesanannya, jadi tulis apa adanya. Potongan tidak bisa melebihi '
                            .'total pesanan.',
                    ],
                    [
                        't' => 'Apakah pelanggan tahu kalau tagihannya berubah',
                        'j' => 'Ya. Sepuluh menit setelah Anda menambah penyesuaian, mengisi ongkir, atau '
                            .'mengubah isi pesanan, pelanggan menerima email "Tagihan pesanan diperbarui" '
                            .'berisi rincian terbaru dan kapan membayarnya. Beberapa perubahan dalam '
                            .'sepuluh menit itu digabung jadi satu email. Setelah paket berangkat, tagihan '
                            .'tidak bisa ditambah lagi; yang masih bisa hanya potongan.',
                    ],
                    [
                        't' => 'Pelanggan kelebihan bayar setelah ada potongan',
                        'j' => 'Ringkasan pesanan menampilkan "Kelebihan bayar". Kembalikan uangnya ke '
                            .'pelanggan, tunai atau transfer, lalu tekan "Catat sudah dikembalikan" di '
                            .'kotak yang sama. Tanpa catatan itu, laporan menghitung uang yang sebenarnya '
                            .'sudah keluar, dan tidak ada jejak siapa yang mengembalikannya. Pesanan yang '
                            .'dibatalkan tidak lewat sini: uangnya diatur keputusan pembatalan.',
                    ],
                    [
                        't' => 'Ongkos kirim masuk di mana',
                        'j' => 'Ongkir punya barisnya sendiri di tagihan, bukan penyesuaian biaya. Kalau '
                            .'pelanggan memilih dikirim saat memesan dan hitungan otomatisnya menyala, '
                            .'angkanya sudah terisi. Kalau belum, timbang dulu paketnya, lalu isi ongkir '
                            .'di bagian Pengambilan pada halaman pesanan. Selama ongkirnya nol, tagihannya '
                            .'memang masih kurang.',
                    ],
                    [
                        't' => 'Potongan borongan 12 pcs ke atas',
                        'j' => 'Sistem menghitungnya sendiri dari jumlah yang dipesan, dan menampilkannya '
                            .'sebagai potongan terpisah, bukan dengan diam-diam menurunkan harga satuan. '
                            .'Pelanggan berhak melihat harga penuh dan potongannya sebagai dua angka. '
                            .'Uang muka dihitung sebelum potongan, karena potongannya diberikan saat '
                            .'pelunasan.',
                    ],
                ],
            ],
            [
                'kunci' => 'kirim',
                'kelompok' => 'Tugas harian',
                'judul' => 'Serah terima dan pengiriman',
                'ikon' => 'serah-terima',
                'izin' => ['serah_terima.proses'],
                'catatan' => 'Barang keluar dari toko hanya lewat dua pintu ini, dan keduanya menunggu lunas.',
                'isi' => [
                    [
                        't' => 'Pelanggan datang mengambil',
                        'j' => 'Buka Serah terima, cari kode ambil atau nomor pesanannya. Tombol serahkan '
                            .'hanya muncul kalau pesanan sudah lunas; kalau masih ada sisa, catat dulu '
                            .'pembayarannya di layar yang sama. Kalau ada kelebihan bayar, layar itu juga '
                            .'mengingatkan Anda untuk mengembalikannya.',
                    ],
                    [
                        't' => 'Mengubah pesanan jadi dikirim',
                        'j' => 'Di bagian Pengambilan pada halaman pesanan, pilih dikirim, isi alamat dan '
                            .'ongkirnya. Ongkir masuk tagihan, dan pelanggan dikabari lewat email.',
                    ],
                    [
                        't' => 'Mencatat resi',
                        'j' => 'Setelah paket diserahkan ke kurir, isi jasa kirim dan nomor resinya. Resi '
                            .'ditolak selama masih ada sisa tagihan: paket tidak pernah berangkat sebelum '
                            .'lunas. Setelah berangkat, tagihan juga tidak bisa ditambah lagi.',
                    ],
                ],
            ],
            [
                'kunci' => 'produksi',
                'kelompok' => 'Tugas harian',
                'judul' => 'Konveksi: antrian, QR, dan PIN',
                'ikon' => 'antrian',
                'izin' => self::IZIN_PRODUKSI,
                'catatan' => 'Bagian ini untuk yang memegang meja desain, printing, press, potong, jahit, dan finishing.',
                'isi' => [
                    [
                        't' => 'Kenapa pekerjaan saya muncul sendiri di antrian',
                        'j' => 'Karena tahap sebelumnya sudah ditandai selesai. Antrian kerja hanya '
                            .'menampilkan yang benar-benar ada di meja Anda, bukan semua pesanan toko. '
                            .'Kalau kosong, memang tidak ada yang perlu dikerjakan.',
                    ],
                    [
                        't' => 'Menandai selesai lewat HP, tanpa akun',
                        'j' => 'Pindai QR di lembar kerja, lalu masukkan PIN divisi. Tidak perlu akun dan '
                            .'tidak perlu ingat kata sandi. PIN dipakai supaya jelas siapa yang menandai, '
                            .'karena satu tekanan itu langsung menaikkan status yang dilihat pelanggan.',
                    ],
                    [
                        't' => 'Urutan tahapnya kenapa begitu',
                        'j' => 'Printing dulu, baru press, baru potong, baru jahit, terakhir finishing dan '
                            .'packing. Urutan ini mengikuti meja kerja di konveksi: kertas hasil printing '
                            .'dipress ke kain lembaran dulu, baru kainnya dipotong menurut pola. Jangan '
                            .'ditandai selesai kalau pekerjaannya belum benar-benar selesai; orang di meja '
                            .'berikutnya langsung melihat barang itu masuk antriannya.',
                    ],
                    [
                        't' => 'Salah tandai, atau barang harus dikerjakan ulang',
                        'j' => 'Buka pesanannya, lalu di barang itu pilih Kembalikan ke tahap sebelumnya '
                            .'dan tulis alasannya, misalnya sablon luntur. Barangnya kembali ke meja '
                            .'sebelumnya, dan alasannya hanya dibaca staf. Tombol Selesai yang tertekan '
                            .'dua kali tidak lagi melompati tahap.',
                    ],
                    [
                        't' => 'Satu roll kertas isinya beberapa pesanan',
                        'j' => 'Di antrian Printing, centang pesanan yang masuk satu roll, lalu buat roll. '
                            .'QR-nya satu untuk seluruh roll: sekali pindai, semua pesanan di dalamnya '
                            .'ikut ditandai. Itu cocok dengan kenyataannya, karena pesanan kecil memang '
                            .'diselipkan untuk menggenapi sisa kertas.',
                    ],
                    [
                        't' => 'Desain sudah jadi, kok tidak ada tombol selesai',
                        'j' => 'Desain tidak "diselesaikan" sendiri oleh tim desain. Unggah mockup-nya, '
                            .'lalu pesanan berpindah ke menunggu persetujuan pelanggan. Hanya pelanggan '
                            .'yang boleh menyetujui desain, dan itu tidak bisa diwakilkan dari panel. '
                            .'Kalau tidak begitu, jersey satu tim bisa tercetak dengan logo yang belum '
                            .'disepakati, dan yang menanggung kainnya toko. Pelanggan dikabari lewat email '
                            .'begitu mockup terkirim. Kalau perlu mengganti mockup sebelum disetujui, '
                            .'misalnya ada nama yang salah ketik, pakai Kirim mockup versi baru di halaman '
                            .'pesanan.',
                    ],
                ],
            ],
            [
                'kunci' => 'cs',
                'kelompok' => 'Tugas harian',
                'judul' => 'Chat CS',
                'ikon' => 'chat',
                'izin' => ['cs.balas'],
                'catatan' => 'Pelanggan bertanya langsung dari website; jawabannya tercatat sebagai riwayat resmi.',
                'isi' => [
                    [
                        't' => 'Menerima chat atau tidak',
                        'j' => 'Saklar "Menerima chat" di bilah atas ruang CS menentukan status yang '
                            .'dilihat pelanggan di jendela chatnya. Matikan kalau tidak ada yang jaga, '
                            .'supaya pelanggan tidak menunggu tanpa jawaban.',
                    ],
                    [
                        't' => 'Daftar chat makan tempat',
                        'j' => 'Tekan tombol garis tiga di kiri atas untuk menyembunyikan atau '
                            .'menampilkan daftar chat. Di HP tombol yang sama membuka daftar di atas '
                            .'percakapan.',
                    ],
                    [
                        't' => 'Menjawab cepat',
                        'j' => 'Pakai template balasan untuk pertanyaan yang berulang, misalnya rekening '
                            .'atau jam buka. Templatenya diatur di Kelola template. Foto yang dilampirkan '
                            .'tunduk pada batas ukuran yang sama dengan unggahan lain.',
                    ],
                    [
                        't' => 'Chat tentang pesanan tertentu',
                        'j' => 'Pelanggan yang membuka chat dari halaman pesanannya membawa tautan '
                            .'pesanan itu. Panel info di kanan menampilkan pesanan-pesanannya; catatan '
                            .'internal di sana hanya dibaca staf.',
                    ],
                ],
            ],
            [
                'kunci' => 'pelanggan',
                'kelompok' => 'Tugas harian',
                'judul' => 'Akun pelanggan',
                'ikon' => 'orang',
                'izin' => ['pelanggan.kelola'],
                'catatan' => 'Menu Pelanggan: semua pemesan, yang punya akun maupun tamu.',
                'isi' => [
                    [
                        't' => 'Pelanggan lupa kata sandi',
                        'j' => 'Ia bisa memintanya sendiri lewat "Lupa kata sandi?" di halaman masuk. '
                            .'Kalau minta dibantu, buka pelanggannya dan tekan Kirim tautan atur ulang '
                            .'sandi. Staf tidak pernah melihat atau mengetik kata sandi pelanggan.',
                    ],
                    [
                        't' => 'Mengganti email pelanggan',
                        'j' => 'Hanya atas permintaan pelanggan yang sudah dipastikan, misalnya lewat chat '
                            .'CS. Begitu email diganti, tautan yang terlanjur terkirim ke email lama batal '
                            .'dan semua perangkat yang sedang masuk dikeluarkan.',
                    ],
                    [
                        't' => 'Menonaktifkan akun',
                        'j' => 'Pelanggan tidak bisa masuk dan sesinya langsung berakhir. Tautan pesanan di '
                            .'emailnya tetap berlaku, karena pesanan yang sudah dibayar tetap harus bisa '
                            .'dilacak.',
                    ],
                ],
            ],
            [
                'kunci' => 'katalog',
                'kelompok' => 'Tugas harian',
                'judul' => 'Produk, kategori, dan stok',
                'ikon' => 'produk',
                'izin' => ['katalog.kelola', 'stok.kelola'],
                'catatan' => 'Yang diatur di sini langsung tampil di website, jadi periksa sekali lagi sebelum menyimpan.',
                'isi' => [
                    [
                        't' => 'Produk sudah dibuat tapi belum bisa dipesan pelanggan',
                        'j' => 'Biasanya harganya belum lengkap. Produk kustom butuh tier harga atau '
                            .'matriks harga per ukuran; tanpa itu pelanggan tidak bisa menghitung '
                            .'biayanya sendiri dan pasti bertanya ke admin. Di daftar produk ada penyaring '
                            .'"Belum lengkap" yang mengumpulkan semua yang masih kurang gambar atau harga.',
                    ],
                    [
                        't' => 'Bedanya tier harga dan matriks harga',
                        'j' => 'Tier harga: makin banyak pesan, makin murah per potong, tanpa peduli '
                            .'ukurannya. Matriks harga: harga ditentukan per ukuran, misalnya ukuran SD '
                            .'lebih murah daripada dewasa, dan bisa berbeda per bahan. Pakai matriks '
                            .'kalau daftar harga dari toko memang dibuat per ukuran seperti itu.',
                    ],
                    [
                        't' => 'Pilihan potongan pria dan wanita',
                        'j' => 'Opsi yang nilainya "Pria" dan "Wanita" ditampilkan di website dengan gambar '
                            .'pembanding potongannya. Jersey kustom sudah punya opsi Model Jersey; produk '
                            .'lain bisa diberi opsi yang sama dari bagian Opsi di halaman produk.',
                    ],
                    [
                        't' => 'Ukuran foto produk',
                        'j' => 'Persegi, idealnya 1200 × 1200 px, karena katalog memotong foto jadi persegi. '
                            .'Setiap tempat unggah menyebut rasio dan batas ukurannya di bawah kotaknya.',
                    ],
                    [
                        't' => 'Angka stok mana yang saya isi',
                        'j' => 'Isi jumlah yang benar-benar ada di rak. Yang sudah dipesan pelanggan '
                            .'dikurangi otomatis, dan itulah yang tampil sebagai "bisa dijual". Jadi '
                            .'kalau di rak ada 10 dan 1 sudah dipesan, ketik 10, bukan 9.',
                    ],
                    [
                        't' => 'Kenapa alasan penyesuaian stok wajib diisi',
                        'j' => 'Karena stok yang berubah tanpa jejak adalah awal dari saling menyalahkan. '
                            .'Setiap perubahan dicatat lengkap dengan nama Anda, angka sebelum dan '
                            .'sesudah, serta alasannya. Tulis apa adanya: "opname 7 September", "barang '
                            .'rusak", "retur supplier".',
                    ],
                    [
                        't' => 'Kategori mau dihapus tapi ditolak',
                        'j' => 'Kategori yang masih dipakai produk tidak bisa dihapus. Pindahkan dulu '
                            .'produknya. Kalau sebenarnya hanya ingin menyembunyikannya dari pelanggan, '
                            .'jangan dihapus, cukup matikan tanda "aktif": pesanan lama tetap menyebut '
                            .'kategori itu, dan menghapusnya membuat riwayat jadi rumpang.',
                    ],
                ],
            ],
            [
                'kunci' => 'tampilan',
                'kelompok' => 'Tugas harian',
                'judul' => 'Banner, portofolio, dan testimoni',
                'ikon' => 'gambar',
                'izin' => ['profil.kelola'],
                'catatan' => 'Wajah website. Gambar yang pas ukurannya tampil tajam dan tidak terpotong.',
                'isi' => [
                    [
                        't' => 'Ukuran banner',
                        'j' => 'Gambar utama 8:3 (1920 × 720 px), tulisan penting di tengah. Gambar untuk '
                            .'HP persegi (900 × 900 px); kalau kosong, di HP gambar utama dipotong bagian '
                            .'tengahnya.',
                    ],
                    [
                        't' => 'Unggahan ditolak karena terlalu besar',
                        'j' => 'Batasnya tertulis di bawah setiap kotak unggah. Foto dari HP yang sangat '
                            .'besar kecilkan dulu, atau pakai tangkapan layarnya. Banner mengirim dua '
                            .'gambar sekaligus, jadi keduanya dihitung bersama.',
                    ],
                    [
                        't' => 'Banner yang gambarnya sudah bertulisan',
                        'j' => 'Matikan "Tampilkan judul, teks, dan tombol di atas gambar" supaya tulisannya '
                            .'tidak dobel. Teks alternatifnya tetap wajib diisi untuk pembaca layar dan '
                            .'mesin pencari.',
                    ],
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function pemilik(): array
    {
        return [
            [
                'kunci' => 'pengaturan',
                'kelompok' => 'Khusus pemilik',
                'judul' => 'Pengaturan toko',
                'ikon' => 'pengaturan',
                'izin' => ['pengaturan.kelola'],
                'catatan' => 'Angka bisnis yang berlaku untuk pesanan baru. Pesanan yang sudah dibuat memakai angka saat ia dibuat.',
                'isi' => [
                    [
                        't' => 'Biaya layanan',
                        'j' => 'Satu angka per pesanan, ditulis terbuka di checkout, halaman pesanan, dan '
                            .'invoice. Isi 0 untuk mematikannya. Berlaku juga untuk pesanan yang diketik '
                            .'di toko. Angkanya dikunci di setiap pesanan, jadi menaikkannya tidak '
                            .'mengubah tagihan yang sudah ada.',
                    ],
                    [
                        't' => 'DP minimum dan batas waktu bayar',
                        'j' => 'DP minimum dihitung dari harga barang tanpa ongkir. Pesanan yang belum '
                            .'dibayar sampai batas jam kedaluwarsa sendiri dan stoknya dilepas, kecuali '
                            .'pelanggan sudah mengunggah bukti.',
                    ],
                    [
                        't' => 'Batas revisi, batas unggah, dan barang hangus',
                        'j' => 'Batas revisi desain gratis, ukuran unggahan terbesar (yang benar-benar '
                            .'diterima juga dibatasi server hosting), dan berapa hari barang siap boleh '
                            .'menunggu diambil sebelum muncul di dashboard.',
                    ],
                    [
                        't' => 'Rekening, QRIS, dan chat CS',
                        'j' => 'Rekening dan gambar QRIS inilah yang tampil di halaman pesanan pelanggan; '
                            .'pastikan QRIS-nya tajam dan utuh. Chat CS bisa dimatikan sepenuhnya; '
                            .'riwayatnya tetap bisa dibaca.',
                    ],
                ],
            ],
            [
                'kunci' => 'laporan',
                'kelompok' => 'Khusus pemilik',
                'judul' => 'Laporan',
                'ikon' => 'laporan',
                'izin' => ['laporan.lihat'],
                'catatan' => 'Angka untuk mengambil keputusan, bukan untuk dicocokkan dengan buku tulis.',
                'isi' => [
                    [
                        't' => 'Uang masuk dan dikembalikan',
                        'j' => 'Uang masuk adalah pembayaran yang sudah diverifikasi dalam periode itu. '
                            .'Dikembalikan memuat pengembalian karena pembatalan dan kelebihan bayar. '
                            .'Selisih keduanya yang semestinya cocok dengan rekening dan laci kasir.',
                    ],
                    [
                        't' => 'Sisa tertahan',
                        'j' => 'Jumlah sisa tagihan semua pesanan yang masih berjalan: uang yang belum '
                            .'diterima, apa pun periodenya.',
                    ],
                    [
                        't' => 'Stok dan produk terlaris',
                        'j' => 'Laporan stok mengelompokkan barang aman, menipis, dan habis beserta '
                            .'nilainya menurut harga jual. Produk terlaris membantu memutuskan apa yang '
                            .'perlu ditambah.',
                    ],
                ],
            ],
            [
                'kunci' => 'pengguna',
                'kelompok' => 'Khusus pemilik',
                'judul' => 'Pengguna, peran, dan PIN pekerja',
                'ikon' => 'pekerja',
                'izin' => ['pengguna.kelola'],
                'catatan' => 'Siapa bisa masuk panel, apa yang boleh ia lakukan, dan PIN untuk meja konveksi.',
                'isi' => [
                    [
                        't' => 'Satu akun untuk satu orang',
                        'j' => 'Buat akun per orang di menu Pengguna, jangan berbagi akun: catatan siapa '
                            .'memverifikasi uang dan siapa mengubah stok jadi tidak berarti kalau satu akun '
                            .'dipakai bersama. Staf yang keluar cukup dinonaktifkan; riwayatnya tetap. Staf '
                            .'yang lupa sandi diatur ulang dari menu yang sama.',
                    ],
                    [
                        't' => 'Peran menentukan menu',
                        'j' => 'Peran berisi daftar izin. Admin menjalankan operasi harian; pemilik '
                            .'memegang pengaturan, laporan, dan pengguna. Kalau kelak dibutuhkan peran '
                            .'lain, misalnya kasir saja, minta teknisi menambahkannya; panduan ini ikut '
                            .'menyesuaikan isinya sendiri.',
                    ],
                    [
                        't' => 'PIN pekerja konveksi',
                        'j' => 'Diatur di menu Pekerja: nama, PIN, dan tahap yang boleh ia tandai. PIN '
                            .'yang lupa cukup diganti di sana. Pekerja yang keluar dinonaktifkan supaya '
                            .'PIN-nya tidak bisa dipakai lagi.',
                    ],
                    [
                        't' => 'Melihat panduan seperti peran lain',
                        'j' => 'Pilih peran di bagian atas halaman panduan ini untuk melihat apa yang '
                            .'dibaca peran itu, misalnya sebelum mengajari admin baru.',
                    ],
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function konveksi(): array
    {
        return [
            [
                'kunci' => 'konveksi',
                'kelompok' => 'Mengajari karyawan',
                'judul' => 'Panduan karyawan konveksi',
                'ikon' => 'qr',
                'izin' => self::IZIN_PRODUKSI,
                'catatan' => 'Untuk pemilik dan admin yang mengajari karyawan di meja produksi. Versi cetaknya bisa ditempel di workshop.',
                'langkah' => self::langkahKonveksi(),
            ],
        ];
    }

    /**
     * Langkah kerja di meja produksi, dipakai juga halaman cetak A4.
     *
     * @return list<array{t: string, j: string}>
     */
    public static function langkahKonveksi(): array
    {
        return [
            ['t' => 'Lembar kerja ikut bersama kain', 'j' => 'Admin mencetaknya dari halaman pesanan setelah desain disetujui pelanggan. Isinya daftar ukuran dan nama punggung, model (termasuk Pria atau Wanita), dan QR untuk menandai tahap.'],
            ['t' => 'Pindai QR dengan kamera HP', 'j' => 'Terbuka halaman "Tahap Pekerjaan Saat Ini". Pastikan tahap yang tertulis adalah meja Anda. Kalau bukan, jangan ditandai.'],
            ['t' => 'Periksa daftar ukuran dan catatan', 'j' => 'Buka Daftar Ukuran dan baca Catatan Pesanan sebelum mulai, terutama di meja potong dan jahit: potongan pria dan wanita berbeda.'],
            ['t' => 'Tandai selesai setelah benar-benar selesai', 'j' => 'Ketik PIN Divisi, lalu tekan Tandai Selesai. Barangnya langsung pindah ke antrian meja berikutnya dan pelanggan melihat progresnya.'],
            ['t' => 'PIN salah lima kali', 'j' => 'Lembar itu terkunci 15 menit. Tunggu, atau minta admin menandainya dari panel.'],
            ['t' => 'Salah tandai atau harus dikerjakan ulang', 'j' => 'Jangan diakali dengan menandai tahap lain. Lapor ke admin; admin mengembalikan barang ke tahap sebelumnya dari panel.'],
            ['t' => 'Halaman merah "Pesanan dibatalkan"', 'j' => 'Hentikan pengerjaannya, lalu serahkan kain dan lembar kerjanya ke admin.'],
            ['t' => 'Meja printing memakai QR roll', 'j' => 'Satu roll kertas bisa berisi beberapa pesanan. QR di lembar roll menandai semuanya sekaligus.'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function kamus(): array
    {
        return [
            [
                'kunci' => 'istilah',
                'kelompok' => 'Kamus',
                'judul' => 'Istilah yang sering dipakai',
                'ikon' => 'laporan',
                'izin' => null,
                'catatan' => 'Kalau ada kata yang tidak Anda kenal di layar, kemungkinan ada di daftar ini.',
                'isi' => [
                    [
                        't' => 'Kode ambil',
                        'j' => 'Kode pendek yang diterima pelanggan lewat email setelah pesanannya lunas '
                            .'dan siap. Dia menyebutkan kode itu di kasir. Kalau diwakilkan orang lain, '
                            .'cukup kodenya yang dibawa.',
                    ],
                    [
                        't' => 'Reservasi stok',
                        'j' => 'Barang yang masih di rak tetapi sudah ada yang memesan. Masih terhitung '
                            .'stok fisik, tapi tidak boleh dijual ke orang lain.',
                    ],
                    [
                        't' => 'Lembar kerja (worksheet)',
                        'j' => 'Kertas yang dicetak untuk dibawa ke konveksi: isinya nama pesanan, '
                            .'ukuran dan nama punggung, gambar desain yang sudah disetujui, dan QR untuk '
                            .'menandai tahap selesai.',
                    ],
                    [
                        't' => 'Roll printing',
                        'j' => 'Satu gulung kertas transfer yang memuat beberapa pesanan sekaligus, '
                            .'supaya kertas tidak terbuang. Ditandai selesai sekali untuk semua isinya.',
                    ],
                    [
                        't' => 'Halaman lacak',
                        'j' => 'Halaman milik pelanggan untuk memantau pesanannya sendiri, dibuka lewat '
                            .'tautan di emailnya, tanpa perlu membuat akun. Di panel ada tombol "Lihat '
                            .'seperti pelanggan" untuk memastikan yang dia lihat memang benar.',
                    ],
                    [
                        't' => 'Penyesuaian biaya',
                        'j' => 'Tambahan atau potongan setelah pesanan dibuat, misalnya tambah pemain. '
                            .'Tampil sebagai baris tersendiri di tagihan pelanggan, dan dikabarkan lewat email.',
                    ],
                ],
            ],
        ];
    }
}
