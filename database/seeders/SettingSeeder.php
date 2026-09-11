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

            ['dp_minimum_persen', '50', 'int', 'pembayaran', 'DP Minimum (%)',
                'Batas bawah DP untuk order kustom. 50% dipilih karena menutup biaya produksi (KEP-005).'],
            ['batas_bayar_jam', '24', 'int', 'pembayaran', 'Batas Waktu Pembayaran (jam)',
                'Order yang belum dibayar melewati batas ini kadaluarsa dan reservasi stoknya dilepas.'],

            ['revisi_gratis_maks', '3', 'int', 'produksi', 'Jumlah Revisi Desain Gratis'],
            ['upload_maks_mb', '10', 'int', 'produksi', 'Batas Ukuran Upload Preview (MB)',
                'Hanya preview yang disimpan di sistem; file mentah lewat tautan Drive (KEP-002).'],

            ['pengingat_ambil_hari', '3', 'int', 'pengambilan', 'Pengingat Belum Diambil (hari)'],
            ['ambil_peringatan_hari', '7', 'int', 'pengambilan', 'Tandai di Dashboard Setelah (hari)',
                'Pesanan siap tapi belum diambil selewat hari ini memakan rak toko dan sering terlupakan.'],

            ['email_tindak_lanjut_hari', '2', 'int', 'notifikasi', 'Tandai Email Tanpa Respons (hari)',
                'Tanpa WhatsApp API (KEP-004), sistem harus tahu kapan menyerah dan menyerahkan ke admin.'],

            /*
             * Pengiriman terbatas untuk barang stok toko (KEP-026). Tidak ada
             * hitung ongkir dan tidak ada API kurir: admin mengisi nomor resi,
             * pelanggan melacaknya sendiri di situs pihak ketiga.
             */
            ['jasa_kirim_daftar', "JNE
J&T Express
SiCepat
Pos Indonesia", 'teks', 'pengiriman', 'Jasa Kirim yang Dipakai',
                'Satu nama per baris. Inilah pilihan yang muncul saat admin mengisi resi.'],
            ['panduan_resi', 'Nomor resi bisa dilacak di cekresi.com, atau di situs resmi jasa kirimnya. Nomor baru terbaca beberapa jam setelah paket diserahkan ke kurir.', 'teks', 'pengiriman', 'Panduan Melacak Resi',
                'Muncul di halaman pesanan pelanggan, tepat di bawah nomor resinya.'],

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
            ['tentang_judul', 'Konveksi dan toko perlengkapan olahraga di Wonogiri', 'string', 'profil', 'Judul Halaman Depan'],
            ['tentang_ringkas', 'Kami mengerjakan jersey tim, kaos seragam, dan perlengkapan olahraga untuk sekolah, instansi, dan komunitas di sekitar Wonogiri.', 'teks', 'profil', 'Kalimat Pembuka'],
            ['tentang_lengkap', '', 'teks', 'profil', 'Tentang Usaha',
                'Beberapa paragraf tentang toko: sejak kapan berdiri, siapa yang dilayani, apa yang membedakan.'],
            ['maps_embed', '', 'string', 'profil', 'Tautan Peta Google Maps',
                'Tempel tautan berbagi dari Google Maps. Dipakai sebagai tombol menuju lokasi.'],
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
