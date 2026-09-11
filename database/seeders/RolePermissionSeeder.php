<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Dua peran saja, sesuai permintaan pemilik toko.
 *
 * Daftar permission tetap rinci supaya peran baru bisa ditambahkan kapan saja
 * lewat panel tanpa menyentuh kode. Yang berubah hanya siapa memegang apa:
 * Admin menjalankan seluruh operasi harian, Owner memegang tambahan kunci
 * pengaturan, pengguna, dan laporan.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'order' => [
                'order.lihat' => 'Lihat Pesanan',
                'order.kelola' => 'Kelola Pesanan',
                'order.batal' => 'Batalkan Pesanan',
                'order.penyesuaian' => 'Tambah Penyesuaian Biaya',
            ],
            'pembayaran' => [
                'pembayaran.catat' => 'Catat Pembayaran',
                'pembayaran.verifikasi' => 'Verifikasi Pembayaran',
                'serah_terima.proses' => 'Proses Serah Terima',
            ],
            'produksi' => [
                'produksi.desain' => 'Antrian Desain',
                'produksi.print' => 'Antrian Printing',
                'produksi.potong' => 'Antrian Potong Kain',
                'produksi.press' => 'Antrian Press / Sublime',
                'produksi.jahit' => 'Antrian Jahit',
                'produksi.qc' => 'Antrian QC',
                'produksi.packing' => 'Antrian Packing',
            ],
            'katalog' => [
                'katalog.kelola' => 'Kelola Produk & Kategori',
                'stok.kelola' => 'Kelola Stok',
                'profil.kelola' => 'Kelola Portofolio & Testimoni',
            ],
            'cs' => [
                'cs.balas' => 'Balas Chat CS',
            ],
            'sistem' => [
                'pengguna.kelola' => 'Kelola Pengguna & Peran',
                'pengaturan.kelola' => 'Ubah Pengaturan',
                'laporan.lihat' => 'Lihat Laporan',
            ],
        ];

        foreach ($permissions as $grup => $daftar) {
            foreach ($daftar as $slug => $nama) {
                Permission::updateOrCreate(['slug' => $slug], ['nama' => $nama, 'grup' => $grup]);
            }
        }

        $roles = [
            'owner' => [
                'Pemilik',
                'Akses penuh: seluruh operasi harian ditambah pengaturan, pengguna, dan laporan.',
                '*',
            ],

            'admin' => [
                'Admin',
                'Menjalankan operasi harian: pesanan, pembayaran, produksi, katalog, stok, dan serah terima.',
                [
                    'order.lihat', 'order.kelola', 'order.batal', 'order.penyesuaian',
                    'pembayaran.catat', 'pembayaran.verifikasi', 'serah_terima.proses',
                    'produksi.desain', 'produksi.print', 'produksi.potong',
                    'produksi.press', 'produksi.jahit', 'produksi.qc', 'produksi.packing',
                    'katalog.kelola', 'stok.kelola', 'profil.kelola', 'cs.balas',
                ],
            ],
        ];

        // Peran lama dari susunan sebelumnya dibersihkan agar panel tidak
        // menampilkan pilihan yang tidak lagi dipakai toko.
        Role::whereIn('slug', [
            'admin-cs', 'desainer', 'operator-printing', 'operator-jahit', 'katalog', 'gudang',
        ])->each(fn (Role $r) => $r->delete());

        foreach ($roles as $slug => [$nama, $deskripsi, $izin]) {
            $role = Role::updateOrCreate(['slug' => $slug], [
                'nama' => $nama,
                'deskripsi' => $deskripsi,
                'bawaan' => true,
            ]);

            $role->permissions()->sync(
                $izin === '*' ? Permission::pluck('id') : Permission::whereIn('slug', $izin)->pluck('id')
            );
        }
    }
}
