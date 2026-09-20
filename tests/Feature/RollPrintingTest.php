<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\ProductionStage;
use App\Models\OptionValue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pekerja;
use App\Models\Product;
use App\Models\RollCetak;
use App\Models\User;
use App\Services\AlurPesanan;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Roll kertas printing (KEP-051).
 *
 * Satu roll memuat beberapa pesanan dan ditandai sekali untuk seluruh isinya,
 * karena memang selesai bersamaan. Yang dijaga: hanya tahap printing yang boleh
 * masuk roll, pesanan yang sudah telanjur pindah meja tidak menggagalkan roll,
 * dan tetap butuh PIN operator printing.
 */
class RollPrintingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed([SettingSeeder::class, RolePermissionSeeder::class, UserSeeder::class, CatalogSeeder::class]);
    }

    private function owner(): User
    {
        return User::where('email', 'owner@setiasport.test')->firstOrFail();
    }

    private function operator(string $pin = '1234'): Pekerja
    {
        return Pekerja::create([
            'nama' => 'Yudhis',
            'pin_hash' => Pekerja::hashPin($pin),
            'tahap' => [ProductionStage::PRINT->value],
            'aktif' => true,
        ]);
    }

    /** Satu pesanan kustom yang sudah sampai meja printing. */
    private function itemPrinting(string $email): OrderItem
    {
        $produk = Product::where('sku', 'CUST-JRS-ATASAN')->firstOrFail();

        $opsi = OptionValue::whereIn('label', ['Serena', 'Oblong', 'Pendek', 'Tanpa Saku'])
            ->whereHas('option', fn ($q) => $q->where('product_id', $produk->id))
            ->get()
            ->mapWithKeys(fn (OptionValue $v) => [$v->product_option_id => $v->id])
            ->all();

        $this->post(route('kustom.simpan', $produk), [
            'opsi' => $opsi,
            'baris' => [['kategori' => 'SD', 'ukuran' => 'L', 'jumlah' => 6]],
            'desain_sumber' => 'katalog',
        ]);

        $this->post('/checkout', [
            'nama' => 'Tim '.$email, 'email' => $email,
            'no_hp' => '081299990000', 'tipe_pembayaran' => 'DP', 'setuju' => '1',
        ]);

        $order = Order::latest('id')->firstOrFail();
        app(AlurPesanan::class)->terimaPembayaran($order->payments->first());

        $item = $order->items->first();
        $item->update(['status_item' => OrderItemStatus::PRINTING]);

        return $item->fresh();
    }

    public function test_satu_roll_memuat_beberapa_pesanan_dan_ditandai_sekali(): void
    {
        $a = $this->itemPrinting('a@contoh.test');
        $b = $this->itemPrinting('b@contoh.test');
        $operator = $this->operator();

        $this->actingAs($this->owner())
            ->post(route('panel.roll.simpan'), ['item' => [$a->id, $b->id], 'catatan' => 'Sisa kertas 1 meter'])
            ->assertRedirect();

        $roll = RollCetak::latest('id')->firstOrFail();

        $this->assertSame(2, $roll->items()->count());
        $this->assertStringStartsWith('ROLL-', $roll->kode);

        $this->post(route('roll.selesai', $roll->token), ['pin' => '1234'])
            ->assertSessionHas('sukses');

        $this->assertSame(OrderItemStatus::PRESS, $a->fresh()->status_item);
        $this->assertSame(OrderItemStatus::PRESS, $b->fresh()->status_item);

        $roll->refresh();
        $this->assertNotNull($roll->selesai_pada);
        $this->assertSame($operator->id, $roll->pekerja_id);
    }

    /** Pratinjau tautan di grup WhatsApp membuka halaman ini sendiri. */
    public function test_membuka_halaman_roll_tidak_mengubah_apa_pun(): void
    {
        $a = $this->itemPrinting('a@contoh.test');

        $this->actingAs($this->owner())->post(route('panel.roll.simpan'), ['item' => [$a->id]]);
        $roll = RollCetak::latest('id')->firstOrFail();

        $this->get(route('roll', $roll->token))
            ->assertOk()
            ->assertSee($roll->kode)
            ->assertSee('Printing Jersey');

        $this->assertSame(OrderItemStatus::PRINTING, $a->fresh()->status_item);
    }

    /** Yang bukan operator printing tidak boleh menandai roll. */
    public function test_pin_bagian_lain_ditolak(): void
    {
        $a = $this->itemPrinting('a@contoh.test');
        Pekerja::create([
            'nama' => 'Oko', 'pin_hash' => Pekerja::hashPin('4321'),
            'tahap' => [ProductionStage::JAHIT->value], 'aktif' => true,
        ]);

        $this->actingAs($this->owner())->post(route('panel.roll.simpan'), ['item' => [$a->id]]);
        $roll = RollCetak::latest('id')->firstOrFail();

        $this->post(route('roll.selesai', $roll->token), ['pin' => '4321'])
            ->assertSessionHasErrors('pin');

        $this->assertSame(OrderItemStatus::PRINTING, $a->fresh()->status_item);
        $this->assertNull($roll->fresh()->selesai_pada);
    }

    public function test_pesanan_di_luar_tahap_printing_ditolak_masuk_roll(): void
    {
        $a = $this->itemPrinting('a@contoh.test');
        $a->update(['status_item' => OrderItemStatus::JAHIT]);

        $this->actingAs($this->owner())
            ->post(route('panel.roll.simpan'), ['item' => [$a->id]])
            ->assertSessionHasErrors('item');

        $this->assertSame(0, RollCetak::count());
    }

    /**
     * Pesanan yang sudah telanjur dimajukan admin dilewati, bukan membuat
     * seluruh roll gagal: maksud operator tetap benar.
     */
    public function test_pesanan_yang_sudah_dimajukan_dilewati_tanpa_menggagalkan_roll(): void
    {
        $a = $this->itemPrinting('a@contoh.test');
        $b = $this->itemPrinting('b@contoh.test');
        $this->operator();

        $this->actingAs($this->owner())->post(route('panel.roll.simpan'), ['item' => [$a->id, $b->id]]);
        $roll = RollCetak::latest('id')->firstOrFail();

        // Admin memajukan salah satunya lebih dulu lewat panel.
        app(AlurPesanan::class)->majukanItem($a->fresh(), $this->owner()->id);

        $this->post(route('roll.selesai', $roll->token), ['pin' => '1234'])
            ->assertSessionHas('sukses');

        $this->assertSame(OrderItemStatus::PRESS, $a->fresh()->status_item);
        $this->assertSame(OrderItemStatus::PRESS, $b->fresh()->status_item);
        $this->assertNotNull($roll->fresh()->selesai_pada);
    }

    public function test_roll_yang_sudah_selesai_tidak_bisa_ditandai_dua_kali(): void
    {
        $a = $this->itemPrinting('a@contoh.test');
        $this->operator();

        $this->actingAs($this->owner())->post(route('panel.roll.simpan'), ['item' => [$a->id]]);
        $roll = RollCetak::latest('id')->firstOrFail();

        $this->post(route('roll.selesai', $roll->token), ['pin' => '1234']);
        $this->post(route('roll.selesai', $roll->token), ['pin' => '1234'])->assertStatus(422);
    }

    public function test_lembar_roll_memuat_qr_dan_isinya(): void
    {
        $a = $this->itemPrinting('a@contoh.test');
        $b = $this->itemPrinting('b@contoh.test');

        $this->actingAs($this->owner())->post(route('panel.roll.simpan'), ['item' => [$a->id, $b->id]]);
        $roll = RollCetak::latest('id')->firstOrFail();

        $this->actingAs($this->owner())
            ->get(route('panel.roll.show', $roll))
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertSee($a->order->kode)
            ->assertSee($b->order->kode)
            ->assertSee(route('roll', $roll->token), false);
    }
}
