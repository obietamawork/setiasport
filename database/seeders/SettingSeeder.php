<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/** Nilai bisnis yang pasti akan diubah pemilik toko — tidak boleh di-hardcode. */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['toko_nama', 'Setia Sport', 'string', 'toko', 'Nama Toko'],
            ['toko_alamat', 'Alamat toko belum diisi', 'string', 'toko', 'Alamat Toko'],
            ['toko_telepon', '', 'string', 'toko', 'Nomor WhatsApp Toko'],
            ['toko_jam_buka', 'Senin-Sabtu 08.00-17.00', 'string', 'toko', 'Jam Operasional'],

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
        ];

        foreach ($data as $baris) {
            [$key, $value, $tipe, $grup, $label] = $baris;

            Setting::updateOrCreate(['key' => $key], [
                'value' => $value,
                'tipe' => $tipe,
                'grup' => $grup,
                'label' => $label,
                'deskripsi' => $baris[5] ?? null,
            ]);
        }
    }
}
