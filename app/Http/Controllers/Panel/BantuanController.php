<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Halaman bantuan untuk orang toko sendiri.
 *
 * Isinya ditulis seperti menjelaskan ke teman kerja yang baru masuk, bukan
 * seperti manual perangkat lunak: pertanyaan yang benar-benar ditanyakan, dijawab
 * dengan apa yang harus ditekan, di mana layarnya, dan kenapa sistemnya dibuat
 * begitu. Alasannya ikut ditulis karena aturan yang tidak dimengerti akan dicari
 * jalan pintasnya, dan jalan pintas di toko ini berarti uang atau kain hilang.
 *
 * Ditaruh di kode, bukan di pengaturan yang bisa disunting, supaya isinya tidak
 * pernah berselisih dengan cara sistemnya benar-benar bekerja.
 */
class BantuanController extends Controller
{
    public function __invoke(): View
    {
        return view('panel.bantuan', ['bagian' => $this->isi()]);
    }

    /**
     * @return list<array{kunci: string, judul: string, ikon: string, catatan: string,
     *     isi: list<array{t: string, j: string}>}>
     */
    private function isi(): array
    {
        return [
            [
                'kunci' => 'mulai',
                'judul' => 'Baru pegang panel ini?',
                'ikon' => 'dashboard',
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
                ],
            ],
            [
                'kunci' => 'pesanan',
                'judul' => 'Pesanan, dari masuk sampai diambil',
                'ikon' => 'pesanan',
                'catatan' => 'Urutannya selalu sama, baik pesanan dari website maupun yang Anda ketik sendiri di toko.',
                'isi' => [
                    [
                        't' => 'Pesanan masuk dari mana saja',
                        'j' => 'Dua jalan. Pelanggan memesan sendiri lewat website, atau Anda yang '
                            .'mengetikkannya lewat Pesanan → Pesanan baru untuk orang yang datang ke '
                            .'toko atau menghubungi lewat WhatsApp. Keduanya masuk ke daftar yang sama '
                            .'dan mendapat nomor pesanan, halaman lacak, lembar kerja, dan QR produksi '
                            .'yang sama persis. Jadi pelanggan yang memesan di konter tetap bisa memantau '
                            .'pesanannya dari HP.',
                    ],
                    [
                        't' => 'Pelanggan sudah transfer tapi statusnya belum berubah',
                        'j' => 'Itu memang belum otomatis, dan disengaja. Uang baru dianggap masuk setelah '
                            .'ada orang yang melihatnya di mutasi rekening atau riwayat QRIS. Buka '
                            .'pesanannya, di bagian Pembayaran tekan verifikasi, centang pernyataan bahwa '
                            .'Anda sudah mengeceknya, baru pesanannya jalan. Tangkapan layar transfer bisa '
                            .'dibuat siapa saja dalam semenit; mutasi rekening tidak.',
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
                            .'usulan; Anda yang memutuskan berapa yang benar.',
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
            [
                'kunci' => 'produksi',
                'judul' => 'Konveksi: antrian, QR, dan PIN',
                'ikon' => 'antrian',
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
                        'j' => 'Pindai QR di lembar kerja, lalu masukkan PIN Anda. Tidak perlu akun dan '
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
                            .'disepakati, dan yang menanggung kainnya toko.',
                    ],
                ],
            ],
            [
                'kunci' => 'uang',
                'judul' => 'Uang: tagihan, ongkir, dan potongan',
                'ikon' => 'uang',
                'catatan' => 'Semua angka yang muncul di tagihan pelanggan berasal dari salah satu di bawah ini.',
                'isi' => [
                    [
                        't' => 'Apa bedanya penyesuaian biaya dengan mengubah harga',
                        'j' => 'Harga barang dibekukan saat pesanan dibuat, dan memang tidak boleh berubah '
                            .'diam-diam. Kalau ada tambahan setelah kesepakatan, misalnya tambah dua '
                            .'pemain atau potongan khusus, catat sebagai penyesuaian biaya dengan '
                            .'keterangan yang bisa dibaca pelanggan. Pelanggan melihat baris itu di '
                            .'halaman pesanannya, jadi tulis apa adanya.',
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
                    [
                        't' => 'Pelanggan bayar tunai di toko',
                        'j' => 'Catat lewat "pembayaran diterima di toko" pada halaman pesanan, atau dari '
                            .'layar Serah terima kalau ia sekalian mengambil barangnya. Jangan lupa: '
                            .'kalau pelanggan sudah mengunggah bukti transfer dan Anda mencatat tunai '
                            .'lagi, pembayarannya terhitung dua kali. Layar serah terima akan '
                            .'memperingatkan Anda kalau itu terjadi.',
                    ],
                ],
            ],
            [
                'kunci' => 'katalog',
                'judul' => 'Produk, kategori, dan stok',
                'ikon' => 'produk',
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
                'kunci' => 'orang',
                'judul' => 'Siapa boleh apa',
                'ikon' => 'pekerja',
                'catatan' => 'Kalau satu menu tidak kelihatan di layar Anda, kemungkinan besar memang bukan bagian Anda.',
                'isi' => [
                    [
                        't' => 'Menu saya lebih sedikit daripada teman sebelah',
                        'j' => 'Memang begitu. Tiap peran hanya melihat yang dia kerjakan: kasir tidak '
                            .'perlu melihat harga pokok, orang jahit tidak perlu melihat data pelanggan. '
                            .'Kalau ada yang benar-benar Anda butuhkan tapi tidak muncul, minta pemilik '
                            .'toko menambahkan perannya, jangan pinjam akun orang lain.',
                    ],
                    [
                        't' => 'Pekerja konveksi tidak punya akun panel',
                        'j' => 'Betul, dan itu memang cara kerjanya. Mereka memakai QR di lembar kerja '
                            .'plus PIN, jadi tidak ada kata sandi yang perlu diingat, dan tidak ada '
                            .'harga atau nomor HP pelanggan yang ikut terlihat di HP mereka.',
                    ],
                    [
                        't' => 'Semua yang saya tekan tercatat?',
                        'j' => 'Ya, hal-hal yang penting: verifikasi pembayaran, penyesuaian stok, '
                            .'perubahan pengaturan, pengiriman, dan serah terima. Bukan untuk mengawasi '
                            .'orang, tetapi supaya kalau ada selisih, yang dicari adalah kejadiannya, '
                            .'bukan orangnya.',
                    ],
                ],
            ],
            [
                'kunci' => 'istilah',
                'judul' => 'Istilah yang sering dipakai',
                'ikon' => 'laporan',
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
                ],
            ],
        ];
    }
}
