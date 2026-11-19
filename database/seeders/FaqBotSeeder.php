<?php

namespace Database\Seeders;

use App\Models\FaqBot;
use Illuminate\Database\Seeder;

/**
 * FAQ awal chatbot CS (KEP-151). Kalimatnya dari template balasan CS dan teks
 * klien yang sudah disetujui; data toko memakai penanda ({toko_alamat} dan
 * sejenisnya) supaya ikut berubah saat Pengaturan diubah.
 *
 * Hanya mengisi tabel yang masih kosong: FAQ yang sudah dikelola pemilik toko
 * tidak pernah ditimpa oleh db:seed saat rilis.
 */
class FaqBotSeeder extends Seeder
{
    public function run(): void
    {
        if (FaqBot::query()->exists()) {
            return;
        }

        $daftar = [
            [
                'Bagaimana cara pesan jersey custom?',
                "Caranya:\n1. Pilih produk di katalog bagian Custom, isi pilihan dan daftar pemain, lalu checkout.\n2. Bayar DP lewat rekening atau QRIS yang tertera di halaman pesanan, lalu unggah buktinya di halaman itu.\n3. Lengkapi nama dan nomor punggung, lalu setujui mockup yang kami kirim.\n4. Kami produksi, dan Anda dikabari begitu tinggal pelunasan.",
                'cara pesan, cara order, cara memesan, bagaimana pesan, bagaimana memesan, bagaimana order, langkah pesan, prosedur pesan, alur pesan, tahapan pesan',
                true,
            ],
            [
                'Berapa minimal pemesanan?',
                'Minimal pemesanan jersey custom mulai {moq_terkecil} pcs. Angka pastinya tertera di halaman masing-masing produk, karena berbeda per jenis barang.',
                'minimal pesan, minimal pemesanan, minimal order, min order, moq, paling sedikit, minimum, minimal beli, bisa satu, bisa 1',
                true,
            ],
            [
                'Berapa harga jersey custom?',
                "Harga jersey custom tergantung jumlah dan pilihan bahan. Harga per pcs langsung terlihat di katalog: buka bagian Custom, lalu isi \"Untuk berapa pcs?\" sesuai jumlah tim Anda.\n\nPilihan seperti kerah atau lengan panjang bisa dipilih di halaman produknya, dan totalnya dihitung otomatis.",
                'harga, biaya, tarif, price, harganya, berapaan, berapa per pcs, harga jersey, ongkos bikin',
                true,
            ],
            [
                'Berapa lama pengerjaannya?',
                'Pengerjaan custom sekitar 7 hari kerja, dihitung sejak desain Anda setujui, bukan sejak pesanan masuk. Setiap tahapnya bisa dipantau di halaman pesanan Anda.',
                'lama pengerjaan, berapa lama, berapa hari, kapan jadi, waktu pengerjaan, estimasi, durasi, lama produksi, proses berapa, jadi kapan, selesai kapan',
                true,
            ],
            [
                'Bagaimana cara pembayarannya?',
                "Pembayaran awal (DP) sebesar {dp_persen}%, sisanya dilunasi setelah pesanan selesai, lewat transfer, QRIS, atau langsung di kasir toko. Rekening dan QRIS resmi tertera di halaman pesanan Anda, dengan batas pembayaran {batas_bayar_jam} jam.\n\nKami tidak pernah meminta pembayaran lewat WhatsApp atau ke rekening lain.",
                'cara bayar, pembayaran, bayar, dp, uang muka, transfer, qris, pelunasan, lunas, cicil, rekening, bayar dimana',
                true,
            ],
            [
                'Apakah pesanan bisa dikirim?',
                'Bisa. Pesanan dapat diambil langsung di toko tanpa biaya pengiriman, atau dikirim ke alamat Anda; ongkos kirimnya dihitung saat checkout. Pesanan custom dikirim setelah produksi dan pelunasan selesai.',
                'kirim, dikirim, pengiriman, ongkir, ongkos kirim, ekspedisi, kurir, diantar, luar kota, cod',
                true,
            ],
            [
                'Di mana alamat dan jam buka toko?',
                '{toko_nama} ada di {toko_alamat}. Jam buka toko: {toko_jam_buka}. Pesanan custom juga diambil di toko.',
                'alamat, lokasi, dimana, di mana, letak, maps, jam buka, buka jam, tutup jam, jam operasional, buka hari, hari minggu',
                false,
            ],
            [
                'Bisa kirim desain sendiri?',
                'Bisa. Pilih "Kirim desain sendiri" saat memesan, lalu tempel tautan Google Drive atau Dropbox, atau kirim filenya lewat WhatsApp. Semua format diterima: CDR, AI, PSD, PDF, PNG, atau JPG. Kami siapkan mockup-nya untuk Anda setujui.',
                'desain sendiri, file desain, kirim desain, punya desain, google drive, dropbox, format file, cdr, psd',
                false,
            ],
            [
                'Berapa kali bisa revisi desain?',
                'Revisi desain gratis sampai 3 kali, tanpa biaya tambahan. Mockup ditampilkan di halaman pesanan Anda untuk diperiksa dan disetujui sebelum produksi dimulai.',
                'berapa kali revisi, revisi gratis, gratis revisi, jumlah revisi, revisi desain, boleh revisi',
                false,
            ],
            [
                'Bagaimana memilih ukuran?',
                'Panduan ukuran SD, SMP, dan SMA / Dewasa ada di langkah Data Pemain saat memesan, lengkap dengan lebar dada dan panjang badan. Toleransi ukuran ±1–2 cm dapat terjadi selama proses produksi.',
                'ukuran, size, panduan ukuran, size chart, lebar dada, panjang badan, ukuran anak, xxl, 3xl, jumbo',
                false,
            ],
            [
                'Apakah barang ready stock tersedia?',
                'Barang ready stock beserta sisa stoknya terlihat di katalog bagian Ready Stock. Barang yang tersedia bisa langsung dibeli dan diambil di toko atau dikirim.',
                'stok, ready, tersedia, ready stock, masih ada, ada barang, stock',
                false,
            ],
        ];

        foreach ($daftar as $i => [$pertanyaan, $jawaban, $kataKunci, $menu]) {
            FaqBot::create([
                'pertanyaan' => $pertanyaan,
                'jawaban' => $jawaban,
                'kata_kunci' => $kataKunci,
                'tampil_di_menu' => $menu,
                'aktif' => true,
                'urutan' => $i + 1,
            ]);
        }
    }
}
