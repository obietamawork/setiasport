<?php

namespace Database\Seeders;

use App\Models\TemplateBalasan;
use Illuminate\Database\Seeder;

/**
 * Titik awal template balasan CS (KEP-030). Pemilik toko menyuntingnya dari
 * panel; seeder ini hanya mengisi kalau tabelnya masih kosong, supaya tidak
 * pernah menimpa kalimat yang sudah disesuaikan.
 */
class TemplateBalasanSeeder extends Seeder
{
    public function run(): void
    {
        if (TemplateBalasan::exists()) {
            return;
        }

        $data = [
            ['Harga jersey custom',
                "Harga jersey custom tergantung jumlah dan pilihan bahan. Harga per pcs-nya langsung terlihat di katalog: buka tab Kustom, lalu isi \"Untuk berapa pcs?\" sesuai jumlah tim Anda.\n\nNama dan nomor punggung, kerah, atau lengan panjang bisa dipilih di halaman produknya, dan totalnya dihitung otomatis."],
            ['Minimal pemesanan',
                'Untuk jersey custom, minimal pemesanannya tertera di halaman masing-masing produk. Di bawah jumlah itu, biaya setup desain dan cetaknya jadi terlalu mahal per pcs.'],
            ['Lama pengerjaan',
                "Pengerjaan custom sekitar 7 hari kerja, dihitung sejak desain Anda setujui, bukan sejak pesanan masuk.\n\nUrutannya: DP, desain dan data pemain, lalu produksi (printing, potong kain, press, jahit, finishing), pelunasan, dan siap diambil. Setiap tahapnya terlihat di halaman pesanan Anda."],
            ['Cara pesan custom',
                "Caranya:\n1. Pilih produk di tab Kustom, isi jumlah dan pilihannya, lalu checkout.\n2. Bayar DP ke rekening yang tertera di halaman pesanan, lalu unggah buktinya di halaman itu.\n3. Isi daftar nama dan nomor punggung, lalu setujui mockup yang kami kirim.\n4. Kami produksi, dan Anda dikabari begitu tinggal pelunasan."],
            ['Pembayaran resmi',
                'Pembayaran hanya ke rekening dan QRIS yang tertera di halaman pesanan Anda, dan buktinya diunggah di halaman itu juga. Kami tidak pernah meminta transfer lewat WhatsApp atau ke rekening lain.'],
        ];

        foreach ($data as $i => [$judul, $isi]) {
            TemplateBalasan::create(['judul' => $judul, 'isi' => $isi, 'urutan' => $i]);
        }
    }
}
