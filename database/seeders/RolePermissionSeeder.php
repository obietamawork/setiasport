<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Peran bawaan yang masuk akal, tapi tetap bisa diubah namanya, digabung, atau
 * ditambah oleh pemilik toko (docs/04). Di toko kecil satu orang lazim memegang
 * beberapa peran sekaligus, jadi relasinya many-to-many.
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
                'produksi.jahit' => 'Antrian Jahit',
                'produksi.qc' => 'Antrian QC',
                'produksi.packing' => 'Antrian Packing',
            ],
            'katalog' => [
                'katalog.kelola' => 'Kelola Produk & Kategori',
                'stok.kelola' => 'Kelola Stok',
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
            'owner' => ['Owner', 'Akses penuh, termasuk laporan keuangan dan pengaturan.', '*'],

            'admin-cs' => ['Admin / CS', 'Verifikasi pembayaran, kelola pesanan, komunikasi pelanggan.', [
                'order.lihat', 'order.kelola', 'order.batal', 'order.penyesuaian',
                'pembayaran.catat', 'pembayaran.verifikasi', 'serah_terima.proses', 'laporan.lihat',
            ]],

            'desainer' => ['Desainer', 'Mengerjakan antrian desain dan mengunggah mockup.', [
                'order.lihat', 'produksi.desain',
            ]],

            'operator-printing' => ['Operator Printing', 'Mengerjakan antrian printing.', [
                'order.lihat', 'produksi.print',
            ]],

            'operator-jahit' => ['Operator Jahit', 'Mengerjakan antrian jahit.', [
                'order.lihat', 'produksi.jahit',
            ]],

            'gudang' => ['Gudang', 'Stok, QC, packing, dan serah terima di toko.', [
                'order.lihat', 'produksi.qc', 'produksi.packing',
                'stok.kelola', 'serah_terima.proses',
            ]],

            'katalog' => ['Katalog / Konten', 'Mengelola produk, kategori, foto, dan harga.', [
                'katalog.kelola',
            ]],
        ];

        foreach ($roles as $slug => [$nama, $deskripsi, $izin]) {
            $role = Role::updateOrCreate(['slug' => $slug], [
                'nama' => $nama,
                'deskripsi' => $deskripsi,
                'bawaan' => true,
            ]);

            $ids = $izin === '*'
                ? Permission::pluck('id')
                : Permission::whereIn('slug', $izin)->pluck('id');

            $role->permissions()->sync($ids);
        }
    }
}
