<?php

namespace App\Services;

use App\Enums\FulfillmentType;
use App\Enums\KategoriUkuran;
use App\Enums\PriceBasis;
use App\Models\ActivityLog;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemRoster;
use App\Models\OrderStatusLog;
use App\Models\ProductOption;
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
        // benar-benar baru yang memakai harga master hari ini. Modelnya ikut
        // jadi kunci (KEP-123): L berkerah dan L oblong dua harga berbeda.
        $hargaLama = $item->rosters->mapWithKeys(fn (OrderItemRoster $r) => [
            $this->kunci($r->kategori?->value, $r->ukuran, $r->option_value_id) => $r->harga_satuan,
        ]);

        /** @var ProductOption|null $opsiPemain */
        $opsiPemain = ProductOption::query()
            ->where('product_id', $item->product_id)
            ->where('per_pemain', true)
            ->with('values')
            ->first();

        $produk = $item->product->loadMissing(['prices', 'sizeCharts']);

        // Produk yang harganya tidak dibedakan per jenjang tidak punya
        // kategori sama sekali (KEP-099). Formulir lama mengirim "SD" untuk
        // produk seperti ini, dan lembar kerja lalu menyebut ukuran anak.
        $pakaiKategori = $produk->pakaiKategoriUkuran();

        $bersih = [];

        foreach (array_values($baris) as $i => $b) {
            $jumlah = (int) ($b['jumlah'] ?? 0);

            if ($jumlah < 1) {
                continue;
            }

            $kategori = $pakaiKategori ? KategoriUkuran::tryFrom(trim((string) ($b['kategori'] ?? ''))) : null;
            $ukuran = strtoupper(trim((string) ($b['ukuran'] ?? '')));
            $idModel = filled($b['model'] ?? null) ? (int) $b['model'] : null;

            // Permintaan yang tidak menyebut model sama sekali (formulir lama)
            // memakai model baris lama berukuran sama, atau satu-satunya model
            // di barang ini. Kalau tidak bisa ditebak, staf diminta memilih,
            // supaya baris berkerah tidak diam-diam dihargai sebagai oblong.
            if ($opsiPemain && ! array_key_exists('model', $b)) {
                $awalan = $this->kunci($kategori?->value, $ukuran);
                $modelDari = fn (string $kunci): string => substr($kunci, (int) strrpos($kunci, '|') + 1);

                $calon = $hargaLama->keys()->filter(fn (string $k) => str_starts_with($k, $awalan))
                    ->map($modelDari)->filter()->unique();
                $calon = $calon->isEmpty() ? $hargaLama->keys()->map($modelDari)->filter()->unique() : $calon;

                if ($calon->count() > 1) {
                    throw ValidationException::withMessages([
                        "baris.{$i}.model" => "Pilih {$opsiPemain->nama} untuk baris ke-".($i + 1).'.',
                    ]);
                }

                $idModel = $calon->isEmpty() ? null : (int) $calon->first();
            }

            /** @var OptionValue|null $model */
            $model = $idModel !== null ? $opsiPemain?->values->firstWhere('id', $idModel) : null;

            if ($idModel !== null && $model === null) {
                throw ValidationException::withMessages([
                    "baris.{$i}.model" => 'Pilihan model ini tidak tersedia untuk '.$item->nama_produk.'.',
                ]);
            }

            $kunci = $this->kunci($kategori?->value, $ukuran, $model?->id);

            if ($pakaiKategori && $kategori === null) {
                throw ValidationException::withMessages([
                    "baris.{$i}.kategori" => 'Pilih kategori ukurannya (SD, SMP, atau dewasa).',
                ]);
            }

            // Ukuran baru dicocokkan dengan panduan ukuran produk, sama seperti
            // saat memesan. Ukuran yang sudah ada di pesanan tidak diperiksa
            // ulang: ia sudah disepakati walau panduannya berubah sesudahnya.
            if (! isset($hargaLama[$kunci]) && ! in_array($ukuran, $produk->ukuranTersedia($kategori), true)) {
                throw ValidationException::withMessages([
                    "baris.{$i}.ukuran" => "Ukuran {$ukuran} tidak ada di panduan ukuran {$produk->nama}"
                        .($kategori ? ' untuk '.$kategori->label() : '').'.',
                ]);
            }

            if (! isset($hargaLama[$kunci]) && $model && ! $model->aktif) {
                throw ValidationException::withMessages([
                    "baris.{$i}.model" => "Model {$model->label} sudah tidak dijual. Pilih model lain.",
                ]);
            }

            $harga = $hargaLama[$kunci] ?? $this->hargaTerkini($item, $kategori, $ukuran, $model, $opsiPemain);

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
                'option_value_id' => $model?->id,
                'model' => $model?->label,
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

        /** @var Order $order */
        $order = $item->order;

        // Isi paket yang sudah berangkat tidak bisa diubah lagi (KEP-158).
        abort_if($order->dikirim_pada !== null, 422, 'Paketnya sudah berangkat, isi pesanan tidak bisa diubah lagi.');
    }

    /**
     * Harga master hari ini untuk ukuran yang belum pernah ada di pesanan.
     * Tambahan yang dipilih pelanggan diambil dari snapshot pesanan, bukan dari
     * katalog: yang berubah cuma ukurannya, bukan kesepakatan soal kerah dan
     * lengan panjang.
     */
    private function hargaTerkini(
        OrderItem $item,
        ?KategoriUkuran $kategori,
        string $ukuran,
        ?OptionValue $model = null,
        ?ProductOption $opsiPemain = null,
    ): ?int {
        $produk = $item->product->load(['prices', 'sizeSurcharges', 'options.values']);

        $perPcs = $item->options->where('basis', PriceBasis::PER_PCS);

        // Pesanan lama memilih model sekali untuk seluruh barang. Baris yang
        // memilih modelnya sendiri tidak ikut menanggung tambahan itu.
        if ($model && $opsiPemain) {
            $perPcs = $perPcs->where('option_nama', '!==', $opsiPemain->nama);
        }

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

        return $dasar + $tambahan + $produk->tambahanUkuran($ukuran) + ($model->tambahan_harga ?? 0);
    }

    private function kunci(?string $kategori, string $ukuran, ?int $model = null): string
    {
        return ($kategori ?? '').'|'.$ukuran.'|'.($model ?? '');
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
