<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Seeder;

/** Nilai bisnis yang pasti akan diubah pemilik toko, tidak boleh di-hardcode. */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['toko_nama', 'Setia Sport', 'string', 'toko', 'Nama Toko'],
            /*
             * Dua lokasi dari klien. Toko adalah tempat pesanan diambil dan
             * dilunasi; konveksi adalah tempat jersey dikerjakan. Nilai bawaan
             * di sini adalah data asli klien, supaya instalasi baru di hosting
             * langsung benar tanpa diketik ulang.
             */
            ['toko_alamat', 'Jl. Manyar II No.2, Sukorejo, Giripurwo, Kec. Wonogiri, Kabupaten Wonogiri, Jawa Tengah 57612', 'string', 'toko', 'Alamat Toko',
                'Tempat pesanan diambil dan dilunasi. Tampil di halaman pesanan dan email.'],
            ['toko_jam_buka', "Senin-Sabtu 08.00-16.00
Minggu 08.00-14.00", 'teks', 'toko', 'Jam Buka Toko',
                'Satu baris per rentang hari.'],
            ['konveksi_alamat', 'Donoharjo, RT.02/RW.01, Donoharjo, Wuryorejo, Kec. Wonogiri, Kabupaten Wonogiri, Jawa Tengah 57614', 'string', 'toko', 'Alamat Konveksi',
                'Tempat jersey dikerjakan. Tampil di halaman depan.'],
            ['konveksi_jam_buka', 'Senin-Sabtu 08.00-16.00', 'teks', 'toko', 'Jam Buka Konveksi'],
            ['toko_telepon', '0851-6002-5810', 'string', 'toko', 'Nomor WhatsApp Toko'],

            /*
             * Pembayaran diverifikasi manual (KEP-023): pelanggan membayar ke
             * QRIS statis atau rekening di bawah, lalu mengirim buktinya lewat
             * WhatsApp. Nilai-nilai ini muncul di halaman pesanan pelanggan.
             */
            ['bank_nama', 'Bank Mandiri', 'string', 'pembayaran', 'Nama Bank 1', 'Contoh: BCA, BRI, Mandiri.'],
            ['bank_rekening', '138 000 635 8613', 'string', 'pembayaran', 'Nomor Rekening 1'],
            ['bank_atas_nama', 'AGUS MARTANTO', 'string', 'pembayaran', 'Rekening 1 Atas Nama'],

            // Toko memakai dua rekening. Yang kedua boleh dikosongkan.
            ['bank2_nama', 'Bank Mandiri', 'string', 'pembayaran', 'Nama Bank 2'],
            ['bank2_rekening', '138 000 7096 733', 'string', 'pembayaran', 'Nomor Rekening 2',
                'Kosongkan kalau toko hanya memakai satu rekening.'],
            ['bank2_atas_nama', 'MAMIEK SUKARMI', 'string', 'pembayaran', 'Rekening 2 Atas Nama'],
            ['qris_path', '', 'berkas', 'pembayaran', 'Gambar QRIS',
                'Diunggah lewat tombol di bawah, bukan diketik.'],

            /*
             * Bawaannya 0, bukan angka tebakan: nominalnya belum final dari klien,
             * dan sistem tidak boleh menambahkan biaya yang belum disepakati.
             * Pemilik toko mengisinya sendiri begitu angkanya diputuskan.
             */
            ['biaya_layanan', '0', 'int', 'pembayaran', 'Biaya Layanan per Pesanan (Rp)',
                'Ditambahkan sekali di tiap pesanan, menutup biaya hitung ongkir dan pelacakan paket. '
                .'Isi 0 kalau tidak dipakai. Tampil terbuka di checkout dan invoice, bukan biaya tersembunyi.'],

            ['dp_minimum_persen', '50', 'int', 'pembayaran', 'DP Minimum (%)',
                'Batas bawah uang muka untuk pesanan custom. 50% dipilih karena cukup menutup biaya produksi.'],
            ['batas_bayar_jam', '24', 'int', 'pembayaran', 'Batas Waktu Pembayaran (jam)',
                'Order yang belum dibayar melewati batas ini kadaluarsa dan reservasi stoknya dilepas.'],

            ['revisi_gratis_maks', '3', 'int', 'produksi', 'Batas Revisi Desain',
                'Maksimal permintaan revisi dari pelanggan. Semuanya gratis, dan setelah habis tidak ada revisi berbayar.'],
            ['upload_maks_mb', '10', 'int', 'produksi', 'Batas Ukuran Upload Preview (MB)',
                'Berlaku untuk preview desain dan foto. File mentah siap cetak dikirim lewat tautan Drive, bukan diunggah.'],
            /*
             * Kosong berarti tombolnya tidak muncul sama sekali (KEP-065).
             * Bukan link ke sistem kami, tapi ke formulir milik toko sendiri
             * (Google Form / Dropbox File Request) yang menampung file mentah
             * tanpa pelanggan perlu tahu cara membuat tautan berbagi sendiri.
             */
            ['desain_link_unggah', '', 'string', 'produksi', 'Tautan Unggah File Besar (opsional)',
                'Untuk pelanggan yang punya file mentah (AI/PSD/CDR) tapi tidak bisa membuat tautan '
                .'berbagi sendiri. Isi tautan Google Form/Dropbox File Request milik toko. Kosongkan '
                .'untuk menyembunyikannya. Cara buatnya: docs/14-formulir-unggah-desain.md.'],

            // Dulu kata "besok" tertulis langsung di tampilan (KEP-091).
            ['stok_siap_hari', '1', 'int', 'pengambilan', 'Barang Stok Siap Diambil (hari)',
                'Jarak dari pesanan dibayar sampai barang stok siap diambil: 0 berarti hari itu juga, '
                .'1 berarti besok. Tampil di kartu produk, halaman produk, dan katalog.'],
            ['pengingat_ambil_hari', '3', 'int', 'pengambilan', 'Pengingat Belum Diambil (hari)'],
            ['hangus_hari', '30', 'int', 'pengambilan', 'Barang Hangus Setelah (hari)',
                'Barang yang tidak pernah diambil selama ini dianggap hangus dan uangnya tidak kembali. '
                .'Sistem hanya menandainya; yang menghanguskan tetap admin.'],

            ['ambil_peringatan_hari', '7', 'int', 'pengambilan', 'Tandai di Dashboard Setelah (hari)',
                'Pesanan siap tapi belum diambil selewat hari ini memakan rak toko dan sering terlupakan.'],

            ['email_tindak_lanjut_hari', '2', 'int', 'notifikasi', 'Tandai Email Tanpa Respons (hari)',
                'Email yang tidak dibuka selama ini ditandai di dashboard, supaya pelanggannya bisa dihubungi lewat WhatsApp.'],

            /*
             * Pengiriman terbatas untuk barang stok toko (KEP-026). Tidak ada
             * hitung ongkir dan tidak ada API kurir: admin mengisi nomor resi,
             * pelanggan melacaknya sendiri di situs pihak ketiga.
             */
            ['berat_bawaan_gram', '300', 'int', 'pengiriman', 'Berat Bawaan per Pcs (gram)',
                'Dipakai menghitung ongkir untuk produk yang kolom beratnya belum diisi. '
                .'Lebih baik kelebihan sedikit daripada kurang: ongkir yang kurang ditanggung toko.'],

            ['gratis_ongkir_minimal', '0', 'int', 'pengiriman', 'Gratis Ongkir di Atas (Rp)',
                'Belanja di atas nominal ini tidak dikenai ongkos kirim. Isi 0 untuk mematikan. '
                .'Ongkir tetap dibayar toko ke kurir, jadi pastikan angkanya masih menguntungkan.'],

            ['jasa_kirim_daftar', "JNE
J&T Express
SiCepat
Pos Indonesia", 'teks', 'pengiriman', 'Jasa Kirim yang Dipakai',
                'Satu nama per baris. Inilah pilihan yang muncul saat admin mengisi resi.'],
            ['panduan_resi', 'Nomor resi bisa dilacak di cekresi.com, atau di situs resmi jasa kirimnya. Nomor baru terbaca beberapa jam setelah paket diserahkan ke kurir.', 'teks', 'pengiriman', 'Panduan Melacak Resi',
                'Muncul di halaman pesanan pelanggan, tepat di bawah nomor resinya.'],

            /*
             * Area asal Biteship (KEP-057), dicari lewat kotak pencarian di
             * bawah medan ini, bukan diketik manual: kodenya tidak bisa
             * ditebak dan salah satu karakter saja membuat hitung ongkir
             * diam-diam tidak pernah menjawab. biteship_area_nama murni
             * kosmetik, supaya layar ini terbaca manusia.
             */
            ['biteship_area_asal', '', 'string', 'pengiriman', 'Area Asal Pengiriman (Biteship)',
                'Kode area toko di Biteship. Kosongkan untuk mematikan hitung ongkir otomatis; isi lewat kotak pencarian di bawah ini.'],
            ['biteship_area_nama', '', 'string', 'pengiriman', 'Area Asal (nama)',
                'Terisi otomatis dari hasil pencarian. Hanya untuk ditampilkan, tidak dipakai sistem.'],

            // Chat CS di website (KEP-030).
            // Klien membatalkan CS untuk sementara (KEP-034): mati sebagai bawaan,
            // kodenya tetap ada dan teruji supaya bisa dinyalakan kapan saja.
            ['cs_aktif', '0', 'bool', 'cs', 'Tampilkan Chat CS',
                'Kalau dimatikan: jendela chat hilang, semua teks "hubungi kami" beralih ke WhatsApp toko, dan tidak ada notifikasi chat di panel. Riwayat chat tetap bisa dibaca di menu Arsip chat.'],
            ['cs_jam_mulai', '07:00', 'string', 'cs', 'Jam Operasional Mulai (JJ:MM)',
                'Peringatan email untuk chat yang belum dibalas hanya dikirim di antara jam mulai dan selesai.'],
            ['cs_jam_selesai', '16:00', 'string', 'cs', 'Jam Operasional Selesai (JJ:MM)'],
            ['cs_jam_layanan', 'Setiap hari 07.00-16.00', 'string', 'cs', 'Jam Operasional Website',
                'Ditampilkan di jendela chat supaya pelanggan tahu kapan akan dibalas.'],
            ['cs_sapaan', 'Halo! Ada yang bisa kami bantu? Tulis pertanyaan Anda, CS kami akan membalas di sini.', 'teks', 'cs', 'Sapaan Pembuka'],
            ['cs_peringatan_menit', '15', 'int', 'cs', 'Peringatkan Staf Setelah (menit)',
                'Chat yang belum dibalas selama ini dikirimkan ke email staf. Chat yang tidak dijawab lebih buruk daripada tidak ada chat.'],

            // Halaman depan adalah profil usaha lebih dulu, katalog belakangan (KEP-024).
            ['tentang_judul', 'Perlengkapan olahraga lengkap & jersey custom di Wonogiri', 'string', 'profil', 'Judul Halaman Depan'],
            ['tentang_ringkas', 'Bola, sepatu, kaos, dan aksesoris olahraga dari merek yang jelas, ditambah layanan konveksi jersey yang dikerjakan sendiri di workshop kami. Melayani ecer maupun tim.', 'teks', 'profil', 'Kalimat Pembuka'],
            /*
             * Draf, bukan naskah final. Halaman depan tanpa cerita usaha terbaca
             * seperti toko yang belum jadi, jadi lebih baik ada tulisan yang
             * masuk akal sejak awal dan tinggal dikoreksi pemilik toko dengan
             * bahasanya sendiri.
             *
             * Pemisah paragrafnya "\n\n" dalam kutip GANDA. Dulu ditulis di dalam
             * kutip tunggal, sehingga yang tersimpan garis miring dan huruf n
             * apa adanya, dan halaman depan menampilkan "\n\n" di tengah kalimat.
             */
            ['tentang_lengkap', 'Setia Sport berdiri di Wonogiri sebagai toko perlengkapan olahraga, lalu tumbuh menjadi konveksi jersey karena permintaannya datang terus dari pelanggan sendiri: sekolah yang butuh seragam olahraga, klub futsal yang ingin jersey dengan nama dan nomor punggung, dan komunitas lari yang memesan kaos untuk acara.'
                ."\n\n".'Jersey dikerjakan di workshop kami sendiri, bukan dilempar ke pihak lain. Itu yang membuat revisi desain bisa cepat, ukuran bisa dicampur dari anak SD sampai dewasa dalam satu pesanan, dan kalau ada yang kurang pas, kami perbaiki tanpa harus menunggu kiriman dari kota lain.'
                ."\n\n".'Barang yang ada di rak bisa diambil hari itu juga di toko. Pesanan konveksi punya halaman sendiri di website ini: progres pengerjaan, tagihan, dan nomor resi bisa dilihat kapan saja tanpa perlu menelepon toko.', 'teks', 'profil', 'Tentang Usaha',
                'Beberapa paragraf tentang toko: sejak kapan berdiri, siapa yang dilayani, apa yang membedakan.'],
            /*
             * Media sosial (KEP-063). Disimpan sebagai tautan penuh, bukan nama
             * pengguna: tiap platform menyusun alamatnya dengan aturan sendiri,
             * dan menebaknya dari nama pengguna selalu meleset untuk salah satu
             * di antaranya. Kosong berarti ikonnya tidak muncul sama sekali.
             */
            ['sosial_instagram', '', 'string', 'profil', 'Instagram',
                'Tempel alamat lengkap profilnya, misalnya https://instagram.com/namatoko'],
            ['sosial_tiktok', '', 'string', 'profil', 'TikTok',
                'Tempel alamat lengkap profilnya, misalnya https://tiktok.com/@namatoko'],
            ['sosial_facebook', '', 'string', 'profil', 'Facebook',
                'Tempel alamat lengkap halamannya.'],

            ['maps_embed', '', 'string', 'profil', 'Tautan Peta Google Maps',
                'Tempel tautan berbagi dari Google Maps. Dipakai sebagai tombol menuju lokasi.'],

            /*
             * Kebijakan Privasi dan Syarat & Ketentuan (KEP-085). Draf dari cara
             * kerja sistem ini; Kesiapan tayang menahannya sebagai butir wajib
             * selama teksnya belum disesuaikan pemilik toko.
             */
            ['kebijakan_privasi', \App\Support\DrafHukum::kebijakanPrivasi(), 'markdown', 'hukum', 'Kebijakan Privasi',
                'Tampil di halaman /kebijakan-privasi dan ditautkan dari checkout.'],
            ['syarat_ketentuan', \App\Support\DrafHukum::syaratKetentuan(), 'markdown', 'hukum', 'Syarat & Ketentuan',
                'Tampil di halaman /syarat-ketentuan dan ditautkan dari checkout.'],
        ];

        foreach ($data as $baris) {
            [$key, $value, $tipe, $grup, $label] = $baris;

            /*
             * NILAI hanya ditulis saat kuncinya baru. Seeder ini dijalankan ulang
             * setiap kali ada pengaturan baru; kalau nilainya ikut ditimpa,
             * nomor rekening dan gambar QRIS yang sudah diisi pemilik toko
             * kembali kosong tanpa ada yang sadar, dan pelanggan tidak lagi tahu
             * harus transfer ke mana. Label dan keterangan boleh diperbarui.
             */
            $setting = Setting::firstOrNew(['key' => $key]);

            if (! $setting->exists) {
                $setting->value = $value;
            }

            $setting->fill([
                'tipe' => $tipe,
                'grup' => $grup,
                'label' => $label,
                'deskripsi' => $baris[5] ?? null,
            ])->save();
        }

        $this->pasangQris();
    }

    /**
     * QRIS asli dari klien ikut repositori (database/seeders/aset), supaya
     * instalasi baru langsung bisa menerima pembayaran. Disalin APA ADANYA,
     * tidak lewat pengunggah gambar: kode pembayaran tidak boleh melewati
     * kompresi apa pun. Hanya dipasang kalau toko belum punya QRIS, jadi
     * QRIS yang diganti pemilik toko lewat panel tidak pernah tertimpa.
     */
    private function pasangQris(): void
    {
        $sumber = database_path('seeders/aset/qris.jpeg');

        if (Setting::ambil('qris_path') || ! is_file($sumber)) {
            return;
        }

        $tujuan = 'qris/qris-setia-sport.jpeg';
        Storage::disk('public')->put($tujuan, file_get_contents($sumber));
        Setting::simpan('qris_path', $tujuan);
    }
}
