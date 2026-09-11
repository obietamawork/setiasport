<?php

namespace App\Support;

use App\Enums\FasePesanan;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Menyusun timeline yang dibaca pelanggan untuk satu item (KEP-028).
 *
 * Status internal item terlalu rinci untuk pelanggan, dan beberapa keadaan yang
 * dilihat pelanggan justru tidak tercermin di status item sama sekali: paket yang
 * sedang di jalan tercatat SELESAI di item karena sudah keluar dari toko, padahal
 * bagi pelanggan barangnya belum sampai. Aturan-aturan itu dikumpulkan di sini
 * supaya tampilan cukup menggambar, dan supaya bisa dites tanpa merender HTML.
 */
final class LinimasaItem
{
    /**
     * @return array{batal: bool, fase: list<array{
     *     fase: FasePesanan, label: string, catatan: ?string,
     *     keadaan: 'lewat'|'kini'|'nanti', waktu: ?Carbon,
     *     subtahap: list<array{label: string, keadaan: 'lewat'|'kini'|'nanti', waktu: ?Carbon}>
     * }>}
     */
    public static function untuk(OrderItem $item, Order $order): array
    {
        if ($item->status_item === OrderItemStatus::DIBATALKAN) {
            return ['batal' => true, 'fase' => []];
        }

        $log = $order->statusLogs->where('order_item_id', $item->id);
        $urutan = FasePesanan::alur($item->fulfillment_type);
        $kini = self::faseKini($item, $order);
        $posisiKini = array_search($kini, $urutan, true);

        $fase = [];

        foreach ($urutan as $i => $f) {
            $keadaan = match (true) {
                $i < $posisiKini => 'lewat',
                $i === $posisiKini => 'kini',
                default => 'nanti',
            };

            $fase[] = [
                'fase' => $f,
                'label' => self::label($f, $order, $keadaan),
                'catatan' => $keadaan === 'kini' ? self::catatan($f, $item, $order) : null,
                'keadaan' => $keadaan,
                'waktu' => self::waktuMasuk($f, $item, $order, $log),
                'subtahap' => self::subtahap($f, $item, $log),
            ];
        }

        return ['batal' => false, 'fase' => $fase];
    }

    private static function faseKini(OrderItem $item, Order $order): FasePesanan
    {
        // Barang sudah keluar dari toko tapi belum sampai. Bagi pelanggan ini
        // masih tahap "dikirim", bukan "selesai" (KEP-026).
        if ($item->status_item === OrderItemStatus::SELESAI && $order->status === OrderStatus::DIKIRIM) {
            return FasePesanan::SIAP;
        }

        return FasePesanan::untuk($item->status_item);
    }

    private static function label(FasePesanan $f, Order $order, string $keadaan): string
    {
        if ($f === FasePesanan::SIAP && $order->dikirim()) {
            return $order->dikirim_pada ? 'Dikirim' : 'Siap Dikirim';
        }

        // Di pesanan campuran, barang stok bisa siap jauh sebelum jerseynya.
        // Menyebutnya "Siap Diambil" mengundang pelanggan datang padahal kode
        // ambil belum terbit.
        if ($f === FasePesanan::SIAP && $keadaan === 'kini' && self::menungguBarangLain($order)) {
            return 'Siap';
        }

        return $f->label($order->dikirim());
    }

    private static function menungguBarangLain(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::DIPROSES, OrderStatus::MENUNGGU_PELUNASAN], true);
    }

    /**
     * Penjelasan untuk tahap yang sedang berjalan. Untuk Desain & Data dan
     * Produksi, kalimat umumnya kalah berguna dibanding keadaan sebenarnya:
     * "menunggu persetujuan Anda" memberi tahu pelanggan bahwa bola ada di
     * tangannya, "desain & data" tidak.
     */
    private static function catatan(FasePesanan $f, OrderItem $item, Order $order): string
    {
        return match (true) {
            $f === FasePesanan::DESAIN => match ($item->status_item) {
                OrderItemStatus::ANTRIAN_DESAIN => 'Menunggu giliran desainer. Sambil menunggu, lengkapi data pemain di bawah.',
                OrderItemStatus::PROSES_DESAIN => 'Desainer sedang menyiapkan mockup Anda.',
                OrderItemStatus::MENUNGGU_PERSETUJUAN => 'Mockup sudah siap dan menunggu persetujuan Anda di bawah. Produksi baru dimulai setelah Anda setuju.',
                default => $f->catatan(),
            },
            $f === FasePesanan::PRODUKSI && $item->status_item === OrderItemStatus::ANTRIAN_PRODUKSI
                => 'Desain terkunci. Menunggu giliran masuk mesin.',
            $f === FasePesanan::PELUNASAN
                => 'Barang sudah jadi. Sisa '.rupiah($order->sisa_tagihan).' dilunasi dulu, lalu kode ambil terbit.',
            $f === FasePesanan::SIAP && $order->status === OrderStatus::MENUNGGU_PELUNASAN
                => 'Barang ini sudah siap. Kode ambil terbit setelah pesanan ini lunas.',
            $f === FasePesanan::SIAP && self::menungguBarangLain($order)
                => 'Barang ini sudah siap, dan diserahkan bersamaan dengan barang lain di pesanan ini begitu semuanya selesai.',
            $f === FasePesanan::SIAP && $order->dikirim() && ! $order->dikirim_pada
                => 'Sedang kami siapkan untuk diserahkan ke jasa kirim.',
            default => $f->catatan($order->dikirim()),
        };
    }

    /**
     * Waktu sebuah tahap DIMULAI. Tahap Pembayaran sengaja tanpa waktu: ia
     * dimulai bersamaan dengan pesanan masuk, jadi menulisnya dua kali hanya
     * menambah angka tanpa menambah informasi. Kapan pembayaran diverifikasi
     * tetap terbaca, sebagai waktu mulainya tahap berikutnya.
     */
    private static function waktuMasuk(FasePesanan $f, OrderItem $item, Order $order, Collection $log): ?Carbon
    {
        if ($f === FasePesanan::PESANAN) {
            return $order->created_at;
        }

        if ($f === FasePesanan::PEMBAYARAN) {
            return null;
        }

        if ($f === FasePesanan::SIAP && $order->dikirim_pada) {
            return $order->dikirim_pada;
        }

        if ($f === FasePesanan::SELESAI && $order->diterima_pada) {
            return $order->diterima_pada;
        }

        $statusFase = collect(OrderItemStatus::cases())
            ->filter(fn (OrderItemStatus $s) => FasePesanan::untuk($s) === $f)
            ->map->value
            ->all();

        return $log->whereIn('ke', $statusFase)->sortBy('created_at')->first()?->created_at;
    }

    /** @return list<array{label: string, keadaan: string, waktu: ?Carbon}> */
    private static function subtahap(FasePesanan $f, OrderItem $item, Collection $log): array
    {
        $sub = $f->subtahap();

        if ($sub === []) {
            return [];
        }

        $alur = OrderItemStatus::alur($item->fulfillment_type);
        $posisiItem = array_search($item->status_item, $alur, true);

        return array_map(function (OrderItemStatus $s) use ($alur, $posisiItem, $log) {
            $posisi = array_search($s, $alur, true);

            return [
                'label' => $s->label(),
                'keadaan' => match (true) {
                    $posisiItem === false || $posisi === false => 'nanti',
                    $posisi < $posisiItem => 'lewat',
                    $posisi === $posisiItem => 'kini',
                    default => 'nanti',
                },
                'waktu' => $log->firstWhere('ke', $s->value)?->created_at,
            ];
        }, $sub);
    }
}
