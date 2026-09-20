<?php

namespace App\Services;

use App\Enums\ProductionStage;
use App\Models\ActivityLog;
use App\Models\OrderItem;
use App\Models\Pekerja;
use App\Models\RollCetak;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Roll kertas printing (KEP-051).
 *
 * Satu roll memuat beberapa pesanan sekaligus, dan pesanan kecil diselipkan
 * untuk menggenapi sisa kertas. Operator menandainya sekali untuk seluruh isi
 * roll, karena memang selesai bersamaan.
 *
 * Roll hanya menyentuh tahap printing. Sesudah kain terpisah per pesanan,
 * pekerjaannya kembali berjalan sendiri-sendiri.
 */
class RollPrinting
{
    public function __construct(private AlurPesanan $alur) {}

    /**
     * @param  list<int>  $itemIds
     */
    public function buat(array $itemIds, ?string $catatan, ?int $olehUserId): RollCetak
    {
        $item = OrderItem::whereIn('id', $itemIds)->with('order')->get();

        $salah = $item->reject(fn (OrderItem $i) => $this->diTahapPrinting($i));

        if ($item->isEmpty() || $salah->isNotEmpty()) {
            throw ValidationException::withMessages([
                'item' => $item->isEmpty()
                    ? 'Pilih dulu pesanan yang masuk roll ini.'
                    : 'Ada pesanan yang sudah tidak berada di tahap printing: '
                        .$salah->pluck('nama_produk')->implode(', ').'.',
            ]);
        }

        return DB::transaction(function () use ($item, $catatan, $olehUserId) {
            $roll = RollCetak::create([
                'kode' => RollCetak::kodeBaru(),
                'token' => Str::random(48),
                'catatan' => $catatan,
                'dibuat_oleh' => $olehUserId,
            ]);

            $roll->items()->attach($item->pluck('id'));

            ActivityLog::catat('roll.dibuat', null, [
                'roll' => $roll->kode,
                'pesanan' => $item->pluck('order.kode')->unique()->values()->all(),
            ]);

            return $roll;
        });
    }

    /**
     * Menandai seluruh isi roll selesai printing.
     *
     * Pesanan yang sudah telanjur dimajukan admin dilewati diam-diam, bukan
     * membuat seluruh roll gagal: yang dituju operator adalah "printing roll ini
     * sudah selesai", dan itu tetap benar walau satu pesanan sudah lebih dulu
     * pindah meja.
     *
     * @return int jumlah pesanan yang benar-benar dimajukan
     */
    public function selesaikan(RollCetak $roll, Pekerja $pekerja): int
    {
        abort_if($roll->selesai(), 422, 'Roll ini sudah ditandai selesai.');

        if (! $pekerja->bolehMenandai(ProductionStage::PRINT)) {
            throw ValidationException::withMessages([
                'pin' => $pekerja->nama.' tidak memegang bagian printing.',
            ]);
        }

        return DB::transaction(function () use ($roll, $pekerja) {
            $maju = 0;

            foreach ($roll->items()->with('order')->get() as $item) {
                if (! $this->diTahapPrinting($item)) {
                    continue;
                }

                $this->alur->majukanItem($item, null, $pekerja->id);
                $maju++;
            }

            $roll->update(['selesai_pada' => now(), 'pekerja_id' => $pekerja->id]);

            ActivityLog::catat('roll.selesai', null, [
                'roll' => $roll->kode,
                'pekerja' => $pekerja->nama,
                'pesanan_dimajukan' => $maju,
            ]);

            return $maju;
        });
    }

    private function diTahapPrinting(OrderItem $item): bool
    {
        return $item->status_item->tahapProduksi() === ProductionStage::PRINT;
    }
}
