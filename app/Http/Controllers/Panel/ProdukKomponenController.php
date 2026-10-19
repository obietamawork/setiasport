<?php

namespace App\Http\Controllers\Panel;

use App\Enums\PriceBasis;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductPriceTier;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Varian, tier harga, dan opsi produk. Dipisah dari ProdukController karena
 * masing-masing punya formulir kecil sendiri di halaman ubah produk, jauh
 * lebih tahan salah daripada satu formulir raksasa.
 */
class ProdukKomponenController extends Controller
{
    public function simpanVarian(Request $request, Product $produk): RedirectResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('product_variants', 'sku')],
            'ukuran' => ['nullable', 'string', 'max:20'],
            'warna' => ['nullable', 'string', 'max:40'],
            'tambahan_harga' => ['nullable', 'integer', 'min:-999999999', 'max:999999999'],
            'stok' => ['required', 'integer', 'min:0', 'max:999999'],
        ], [], ['tambahan_harga' => 'selisih harga']);

        $bentrok = $produk->variants()
            ->where('ukuran', $data['ukuran'] ?? null)
            ->where('warna', $data['warna'] ?? null)
            ->exists();

        if ($bentrok) {
            return back()->withErrors('Varian dengan ukuran dan warna itu sudah ada.')->withInput();
        }

        $varian = $produk->variants()->create($data + ['tambahan_harga' => $data['tambahan_harga'] ?? 0]);

        ActivityLog::catat('varian.dibuat', $varian, ['produk' => $produk->sku, 'sku' => $varian->sku]);

        return back()->with('sukses', "Varian {$varian->sku} ditambahkan.");
    }

    public function hapusVarian(Product $produk, ProductVariant $varian): RedirectResponse
    {
        abort_unless($varian->product_id === $produk->id, 404);

        if ($varian->stok_dipesan > 0) {
            return back()->withErrors(
                'Varian ini sedang direservasi pesanan yang belum selesai, jadi belum bisa dihapus.'
            );
        }

        $sku = $varian->sku;
        $varian->delete();

        ActivityLog::catat('varian.dihapus', null, ['produk' => $produk->sku, 'sku' => $sku]);

        return back()->with('sukses', "Varian {$sku} dihapus.");
    }

    public function simpanTier(Request $request, Product $produk): RedirectResponse
    {
        $data = $request->validate([
            'min_qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'max_qty' => ['nullable', 'integer', 'gte:min_qty', 'max:100000'],
            'harga_satuan' => ['required', 'integer', 'min:1', 'max:999999999'],
        ], [
            'max_qty.gte' => 'Jumlah maksimum tidak boleh lebih kecil daripada jumlah minimum.',
        ], [
            'min_qty' => 'jumlah minimum',
            'max_qty' => 'jumlah maksimum',
            'harga_satuan' => 'harga per pcs',
        ]);

        // Tier yang tumpang tindih membuat harga jadi ambigu; tolak sejak awal.
        $tumpang = $produk->priceTiers()
            ->where('min_qty', '<=', $data['max_qty'] ?? PHP_INT_MAX)
            ->where(fn ($q) => $q->whereNull('max_qty')->orWhere('max_qty', '>=', $data['min_qty']))
            ->exists();

        if ($tumpang) {
            return back()->withErrors(
                'Rentang jumlah ini bertabrakan dengan tier yang sudah ada. '
                .'Satu jumlah hanya boleh cocok dengan satu tier.'
            )->withInput();
        }

        $produk->priceTiers()->create($data);

        ActivityLog::catat('tier.dibuat', $produk, $data);

        return back()->with('sukses', 'Tier harga ditambahkan.');
    }

    public function hapusTier(Product $produk, ProductPriceTier $tier): RedirectResponse
    {
        abort_unless($tier->product_id === $produk->id, 404);
        $tier->delete();

        return back()->with('sukses', 'Tier harga dihapus.');
    }

    public function simpanOpsi(Request $request, Product $produk): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:60'],
        ], [], ['nama' => 'nama opsi']);

        $opsi = $produk->options()->create([
            'nama' => $data['nama'],
            'wajib' => $request->boolean('wajib'),
            'urutan' => $produk->options()->count(),
        ]);

        return back()->with('sukses', "Opsi \"{$opsi->nama}\" ditambahkan. Sekarang isi pilihannya.");
    }

    public function hapusOpsi(Product $produk, ProductOption $opsi): RedirectResponse
    {
        abort_unless($opsi->product_id === $produk->id, 404);
        $nama = $opsi->nama;
        $opsi->delete();

        return back()->with('sukses', "Opsi \"{$nama}\" dihapus.");
    }

    public function simpanNilai(Request $request, Product $produk, ProductOption $opsi): RedirectResponse
    {
        abort_unless($opsi->product_id === $produk->id, 404);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            // Opsional: menautkan ke kamus bahan (KEP-072) supaya pelanggan
            // awam melihat penjelasan bahan ini, bukan cuma namanya.
            'material_id' => ['nullable', 'exists:materials,id'],
            'tambahan_harga' => ['required', 'integer', 'min:-999999999', 'max:999999999'],
            'basis' => ['required', Rule::enum(PriceBasis::class)],
        ], [], ['tambahan_harga' => 'selisih harga', 'basis' => 'cara hitung', 'material_id' => 'bahan terkait']);

        $opsi->values()->create($data + [
            'urutan' => $opsi->values()->count(),
            'aktif' => true,
        ]);

        return back()->with('sukses', "Pilihan \"{$data['label']}\" ditambahkan.");
    }

    public function hapusNilai(Product $produk, ProductOption $opsi, OptionValue $nilai): RedirectResponse
    {
        abort_unless($opsi->product_id === $produk->id && $nilai->product_option_id === $opsi->id, 404);
        $nilai->delete();

        return back()->with('sukses', 'Pilihan dihapus.');
    }
}
