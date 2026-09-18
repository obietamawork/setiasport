<?php

namespace App\Services;

use App\Enums\FulfillmentType;
use App\Enums\KategoriUkuran;
use App\Enums\PriceBasis;
use App\Models\ActivityLog;
use App\Models\OptionValue;
use App\Models\OrderItem;
use App\Models\OrderItemRoster;
use App\Models\OrderStatusLog;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Menambah atau mengurangi isi pesanan yang sudah berjalan (KEP-044).
 *
 * Ini kejadian sehari-hari di konveksi: dua pemain menyusul, satu orang batal,
 * atau ukuran salah ketik. Selama ini jalan keluarnya cuma penyesuaian biaya
 * bertuliskan "tambah 2 pemain", sementara daftar pemain dan lembar kerja tetap
 * menyebut jumlah lama. Konveksi lalu menjahit sesuai lembar kerja, bukan sesuai
 * tagihan.
 *
 * Yang diubah di sini isi pesanannya sendiri, jadi tagihan, daftar pemain, dan
 * lembar kerja tidak pernah berselisih.
 */
class UbahIsiPesanan
{
    /**
     * Daftar ukuran item kustom.
     *
     * @param  array<int, array<string, mixed>>  $baris
     */
    public function ubahUkuran(OrderItem $item, array $baris, ?int $olehUserId): void
    {
        $this->pastikanBisaDiubah($item);

        abort_unless($item->hargaPerUkuran(), 422,
            'Baris pesanan ini tidak memakai harga per ukuran, jadi jumlahnya diubah lewat penyesuaian biaya.');

        $qtyLama = $item->qty;

        // Harga yang sudah disepakati tidak dihitung ulang; hanya ukuran yang
        // benar-benar baru yang memakai harga master hari ini.
        $hargaLama = $item->rosters->mapWithKeys(fn (OrderItemRoster $r) => [
            $this->kunci($r->kategori?->value, $r->ukuran) => $r->harga_satuan,
        ]);

        $bersih = [];

        foreach (array_values($baris) as $i => $b) {
            $jumlah = (int) ($b['jumlah'] ?? 0);

            if ($jumlah < 1) {
                continue;
            }

            $kategori = KategoriUkuran::tryFrom(trim((string) ($b['kategori'] ?? '')));
            $ukuran = strtoupper(trim((string) ($b['ukuran'] ?? '')));
            $kunci = $this->kunci($kategori?->value, $ukuran);

            if ($kategori !== null && ! $kategori->punyaUkuran($ukuran)) {
                throw ValidationException::withMessages([
                    "baris.{$i}.ukuran" => "Ukuran {$ukuran} tidak tersedia untuk {$kategori->label()}.",
                ]);
            }

            $harga = $hargaLama[$kunci] ?? $this->hargaTerkini($item, $kategori, $ukuran);

            if ($harga === null) {
                throw ValidationException::withMessages([
                    "baris.{$i}.ukuran" => 'Harga untuk ukuran '.($kategori?->label().' ' ?? '').$ukuran
                        .' belum ada di katalog. Isi dulu harganya, atau pakai penyesuaian biaya.',
                ]);
            }

            $bersih[] = [
                'kategori' => $kategori?->value,
                'ukuran' => $ukuran,
                'jumlah' => $jumlah,
                'harga_satuan' => $harga,
                'nama_punggung' => trim((string) ($b['nama_punggung'] ?? '')) ?: null,
                'nomor' => trim((string) ($b['nomor'] ?? '')) ?: null,
            ];
        }

        if ($bersih === []) {
            throw ValidationException::withMessages([
                'baris' => 'Daftar ukurannya kosong. Untuk membatalkan seluruh baris ini, batalkan pesanannya.',
            ]);
        }

        $qtyBaru = array_sum(array_column($bersih, 'jumlah'));

        DB::transaction(function () use ($item, $bersih, $qtyBaru, $qtyLama, $olehUserId) {
            $item->rosters()->delete();

            foreach ($bersih as $urutan => $b) {
                $item->rosters()->create($b + ['urutan' => $urutan + 1]);
            }

            $item->update([
                'qty' => $qtyBaru,
                'harga_satuan' => intdiv(
                    array_sum(array_map(fn ($b) => $b['harga_satuan'] * $b['jumlah'], $bersih)),
                    $qtyBaru
                ),
            ]);

            $this->catat($item, $qtyLama, $qtyBaru, $olehUserId);
        });
    }

    /** Jumlah barang stok toko, berikut reservasinya. */
    public function ubahQtyStok(OrderItem $item, int $qty, ?int $olehUserId): void
    {
        $this->pastikanBisaDiubah($item);

        abort_unless($item->fulfillment_type === FulfillmentType::READY_STOCK, 422,
            'Baris ini bukan barang stok toko.');

        if ($qty < 1) {
            throw ValidationException::withMessages([
                'qty' => 'Jumlah minimal 1. Untuk membatalkan baris ini, batalkan pesanannya.',
            ]);
        }

        $qtyLama = $item->qty;

        if ($qty === $qtyLama) {
            return;
        }

        DB::transaction(function () use ($item, $qty, $qtyLama, $olehUserId) {
            if ($item->product_variant_id) {
                $varian = ProductVariant::whereKey($item->product_variant_id)->lockForUpdate()->first();

                if (! $varian) {
                    throw new RuntimeException('Varian produk tidak ditemukan.');
                }

                $selisih = $qty - $qtyLama;

                // Tambahan hanya boleh sebanyak yang benar-benar ada di rak,
                // supaya pesanan lain tidak ikut kehilangan barangnya.
                if ($selisih > 0 && $varian->stok - $varian->stok_dipesan < $selisih) {
                    throw ValidationException::withMessages([
                        'qty' => "Stok {$item->nama_produk} tinggal "
                            .max(0, $varian->stok - $varian->stok_dipesan).' pcs lagi.',
                    ]);
                }

                $varian->update(['stok_dipesan' => max(0, $varian->stok_dipesan + $selisih)]);
            }

            $item->update(['qty' => $qty]);

            $this->catat($item, $qtyLama, $qty, $olehUserId);
        });
    }

    private function pastikanBisaDiubah(OrderItem $item): void
    {
        abort_if($item->order->status->final(), 422, 'Pesanan ini sudah selesai atau dibatalkan.');
    }

    /**
     * Harga master hari ini untuk ukuran yang belum pernah ada di pesanan.
     * Tambahan yang dipilih pelanggan diambil dari snapshot pesanan, bukan dari
     * katalog: yang berubah cuma ukurannya, bukan kesepakatan soal kerah dan
     * lengan panjang.
     */
    private function hargaTerkini(OrderItem $item, ?KategoriUkuran $kategori, string $ukuran): ?int
    {
        $produk = $item->product->load(['prices', 'sizeSurcharges', 'options.values']);

        $perPcs = $item->options->where('basis', PriceBasis::PER_PCS);

        $nilaiOpsi = $perPcs->map(fn ($o) => $produk->options
            ->firstWhere('nama', $o->option_nama)
            ?->values->firstWhere('label', $o->option_value))
            ->filter();

        $bahan = $nilaiOpsi->first(fn (OptionValue $v) => $produk->prices
            ->contains(fn ($h) => $h->option_value_id === $v->id));

        $dasar = $produk->hargaPerPotong($kategori, $bahan?->id);

        if ($dasar === null) {
            return null;
        }

        $tambahan = (int) $perPcs
            ->reject(fn ($o) => $bahan !== null && $o->option_value === $bahan->label)
            ->sum('tambahan_harga');

        return $dasar + $tambahan + $produk->tambahanUkuran($ukuran);
    }

    private function kunci(?string $kategori, string $ukuran): string
    {
        return ($kategori ?? '').'|'.$ukuran;
    }

    /**
     * Perubahan jumlah masuk riwayat yang dibaca pelanggan, bukan hanya log
     * internal: tagihannya berubah, dan perubahan tagihan tanpa jejak adalah
     * hal pertama yang dipersoalkan orang.
     */
    private function catat(OrderItem $item, int $dari, int $ke, ?int $olehUserId): void
    {
        OrderStatusLog::create([
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'dari' => $item->status_item->value,
            'ke' => $item->status_item->value,
            'oleh' => $olehUserId,
            'catatan' => "Jumlah {$item->nama_produk} diubah dari {$dari} menjadi {$ke} pcs.",
            'created_at' => now(),
        ]);

        ActivityLog::catat('pesanan.jumlah-diubah', $item->order, [
            'item' => $item->nama_produk,
            'dari' => $dari,
            'ke' => $ke,
        ]);
    }
}
