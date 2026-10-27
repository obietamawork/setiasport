<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Pekerja;
use App\Services\AlurPesanan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Halaman kerja yang dibuka pekerja konveksi lewat pindai QR (KEP-047).
 *
 * Tanpa akun, tanpa panel: yang dipegang pekerja cuma tautan di lembar kerja
 * dan PIN-nya sendiri. Halamannya hanya memuat yang dibutuhkan di meja kerja,
 * yaitu barang apa, berapa potong, ukuran apa saja, dan tahap mana yang sedang
 * ia pegang. Harga dan data pelanggan tidak pernah ikut.
 *
 * Membuka halaman tidak mengubah apa pun. Tahap baru berpindah setelah PIN
 * diketik dan tombol ditekan, karena tautan yang beredar di grup WhatsApp ikut
 * dibuka pratinjau oleh aplikasinya sendiri.
 */
class KerjaController extends Controller
{
    public function __construct(private AlurPesanan $alur) {}

    public function show(string $token): View
    {
        return view('publik.kerja', ['item' => $this->item($token)]);
    }

    public function selesai(Request $request, string $token): RedirectResponse
    {
        $item = $this->item($token);

        $data = $request->validate([
            'pin' => ['required', 'string', 'min:4', 'max:8'],
        ], [
            'pin.required' => 'Masukkan PIN Anda dulu.',
        ]);

        $tahap = $item->status_item->tahapProduksi();

        abort_if($tahap === null, 422,
            'Barang ini sedang tidak berada di meja produksi. Tanyakan ke admin konveksi.');

        $pekerja = Pekerja::periksaPin($data['pin'], 'item:'.$item->id);

        /*
         * Tiap orang hanya menandai mejanya sendiri. Tanpa pagar ini, satu PIN
         * bisa menyelesaikan seluruh tahap sekaligus, dan catatan siapa
         * mengerjakan apa jadi tidak ada artinya.
         */
        if (! $pekerja->bolehMenandai($tahap)) {
            throw ValidationException::withMessages([
                'pin' => $pekerja->nama.' tidak memegang bagian '.$tahap->label()
                    .'. Yang memegangnya: '.$this->pemegang($tahap).'.',
            ]);
        }

        /*
         * AlurPesanan::majukanItem() menjaga beberapa gerbang lewat abort_if
         * (mis. tahap Desain tanpa mockup). Di panel, abort semacam itu
         * berakhir di halaman galat Laravel yang hanya dilihat staf yang
         * tahu cara membacanya -- di sini yang membuka cuma pekerja dengan
         * HP sambil berdiri di meja kerja, jadi pesannya diubah dulu jadi
         * galat inline biasa di sebelah kotak PIN, bukan halaman debug.
         */
        try {
            $this->alur->majukanItem($item, null, $pekerja->id);
        } catch (HttpExceptionInterface $e) {
            throw ValidationException::withMessages(['pin' => $e->getMessage()]);
        }

        return back()->with('sukses', $tahap->label().' ditandai selesai oleh '.$pekerja->nama.'.');
    }

    private function item(string $token): OrderItem
    {
        return OrderItem::where('kerja_token', $token)
            ->with(['order', 'product', 'rosters', 'options', 'productionJobs.pekerja'])
            ->firstOrFail();
    }

    private function pemegang(\App\Enums\ProductionStage $tahap): string
    {
        $nama = Pekerja::aktif()->get()
            ->filter(fn (Pekerja $p) => $p->bolehMenandai($tahap))
            ->pluck('nama');

        return $nama->isEmpty() ? 'belum ada yang terdaftar' : $nama->implode(', ');
    }
}
