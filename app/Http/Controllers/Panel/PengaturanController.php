<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\Ongkir\PenyediaOngkir;
use App\Services\PengunggahGambar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PengaturanController extends Controller
{
    /*
     * Susunan halaman pengaturan: tab menurut tugasnya, bukan menurut kolom
     * `grup` di database. Tiga puluh isian yang ditumpuk ke bawah memaksa
     * pemilik toko menggulir melewati setelan email untuk mencari rekening.
     * Kunci yang belum terdaftar di sini tetap tampil di tab "Lainnya", jadi
     * pengaturan baru tidak pernah hilang dari layar hanya karena lupa ditata.
     */
    private const TAB = [
        'toko' => [
            'judul' => 'Toko & kontak',
            'catatan' => 'Tampil di halaman depan, halaman pesanan, dan email pelanggan.',
            'bagian' => [
                ['judul' => 'Toko', 'catatan' => 'Tempat pesanan diambil dan dilunasi.',
                    'kunci' => ['toko_nama', 'toko_telepon', 'toko_alamat', 'toko_jam_buka', 'maps_embed']],
                ['judul' => 'Konveksi', 'catatan' => 'Tempat jersey dikerjakan.',
                    'kunci' => ['konveksi_alamat', 'konveksi_jam_buka']],
                ['judul' => 'Website', 'kunci' => ['cs_jam_layanan']],
            ],
        ],
        'pembayaran' => [
            'judul' => 'Pembayaran',
            'catatan' => 'Rekening dan QRIS tampil apa adanya di halaman pesanan. Periksa ulang angkanya.',
            'bagian' => [
                ['judul' => 'Rekening 1', 'baris' => 'rekening', 'kunci' => ['bank_nama', 'bank_rekening', 'bank_atas_nama']],
                ['judul' => 'Rekening 2', 'baris' => 'rekening', 'kunci' => ['bank2_nama', 'bank2_rekening', 'bank2_atas_nama']],
                ['judul' => 'Aturan pembayaran', 'kunci' => ['biaya_layanan', 'dp_minimum_persen', 'batas_bayar_jam']],
            ],
        ],
        'pesanan' => [
            'judul' => 'Pesanan',
            'catatan' => 'Batas dan pengingat yang dipakai selama pesanan berjalan.',
            'bagian' => [
                ['judul' => 'Desain & produksi', 'kunci' => ['revisi_gratis_maks', 'upload_maks_mb', 'desain_link_unggah']],
                ['judul' => 'Pengambilan', 'kunci' => ['stok_siap_hari', 'pengingat_ambil_hari', 'ambil_peringatan_hari', 'hangus_hari']],
                ['judul' => 'Pengiriman', 'kunci' => [
                    'jasa_kirim_daftar', 'panduan_resi', 'berat_bawaan_gram', 'gratis_ongkir_minimal',
                    'biteship_area_asal', 'biteship_area_nama',
                ]],
                ['judul' => 'Email', 'kunci' => ['email_tindak_lanjut_hari']],
            ],
        ],
        'beranda' => [
            'judul' => 'Halaman depan',
            'catatan' => 'Teks pembuka dan cerita usaha. Portofolio dan testimoni punya menunya sendiri.',
            'bagian' => [
                ['judul' => 'Teks', 'kunci' => ['tentang_judul', 'tentang_ringkas', 'tentang_lengkap']],
                ['judul' => 'Media sosial', 'catatan' => 'Tempel alamat lengkapnya. Yang kosong, ikonnya tidak ditampilkan.',
                    'kunci' => ['sosial_instagram', 'sosial_tiktok', 'sosial_facebook']],
            ],
        ],
        'hukum' => [
            'judul' => 'Kebijakan & ketentuan',
            'catatan' => 'Tampil di halaman Kebijakan Privasi dan Syarat & Ketentuan, yang ditautkan dari checkout dan kaki halaman. '
                .'Drafnya disusun dari cara kerja sistem; baca dan sesuaikan sebelum website dibuka.',
            'bagian' => [
                ['judul' => 'Kebijakan Privasi', 'kunci' => ['kebijakan_privasi']],
                ['judul' => 'Syarat & Ketentuan', 'kunci' => ['syarat_ketentuan']],
            ],
        ],
        'cs' => [
            'judul' => 'Chat CS',
            'catatan' => 'Chat di website untuk pertanyaan dan komplain. Selama dimatikan, pelanggan diarahkan ke WhatsApp toko.',
            'bagian' => [
                ['judul' => '', 'kunci' => ['cs_aktif']],
                ['judul' => 'Saat chat CS menyala', 'hanyaSaatCs' => true,
                    'kunci' => ['cs_sapaan', 'cs_jam_mulai', 'cs_jam_selesai', 'cs_peringatan_menit']],
            ],
        ],
    ];

    public function index()
    {
        $semua = Setting::all()->keyBy('key');
        $tab = self::TAB;

        // Dihitung dari susunan lengkap, supaya isian CS yang tabnya
        // disembunyikan tidak malah muncul di tab "Lainnya".
        $tertata = collect($tab)->flatMap(fn ($t) => collect($t['bagian'])->flatMap(fn ($b) => $b['kunci']));
        $lain = $semua->keys()->diff($tertata)->reject(fn ($k) => $semua[$k]->tipe === 'berkas')->values();

        if (! config('fitur.cs')) {
            unset($tab['cs']);
        }

        if ($lain->isNotEmpty()) {
            $tab['lainnya'] = [
                'judul' => 'Lainnya',
                'catatan' => 'Pengaturan yang belum dikelompokkan.',
                'bagian' => [['judul' => '', 'kunci' => $lain->all()]],
            ];
        }

        return view('panel.pengaturan.index', [
            'tab' => $tab,
            'setting' => $semua,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $masuk = $request->input('setting', []);

        // Isian CS tidak tampil selama fiturnya tidak dipasang, jadi juga
        // tidak boleh diubah lewat kiriman formulir buatan sendiri.
        if (! config('fitur.cs')) {
            $masuk = array_diff_key($masuk, array_flip(self::kunciTab('cs')));
        }

        // Path berkas hanya boleh berubah lewat jalur unggahnya sendiri; kalau
        // tidak, satu POST bisa menunjuk gambar QRIS ke berkas lain.
        $baris = Setting::whereIn('key', array_keys($masuk))->where('tipe', '!=', 'berkas')->get();

        $aturan = [];
        $nama = [];

        foreach ($baris as $b) {
            $aturan['setting.'.$b->key] = match ($b->tipe) {
                // Batas atasnya dinaikkan jadi 10 juta (KEP-058): batas gratis
                // ongkir untuk pesanan tim bisa lewat sejuta, dan pemilik toko
                // tidak punya cara lain mengisinya.
                'int' => ['required', 'integer', 'min:0', 'max:10000000'],
                'bool' => ['required', 'in:0,1'],
                // Profil usaha ditulis berparagraf, tidak muat di batas satu baris.
                'teks' => ['nullable', 'string', 'max:5000'],
                // Dokumen kebijakan jauh lebih panjang dari teks profil (KEP-085).
                'markdown' => ['nullable', 'string', 'max:20000'],
                default => ['nullable', 'string', 'max:500'],
            };
            $nama['setting.'.$b->key] = $b->label;
        }

        /*
         * Semua divalidasi DULU, baru disimpan. Dulu setiap isian divalidasi
         * sambil disimpan, sehingga kesalahan di isian kesepuluh meninggalkan
         * sembilan isian sebelumnya telanjur tersimpan sementara layar
         * mengatakan penyimpanan gagal.
         */
        $request->validate($aturan, [], $nama);

        $diubah = [];

        foreach ($baris as $b) {
            $nilai = $masuk[$b->key];

            if ((string) $b->value !== (string) $nilai) {
                $diubah[$b->key] = ['dari' => $b->value, 'ke' => $nilai];
                Setting::simpan($b->key, $nilai);
            }
        }

        if ($diubah !== []) {
            ActivityLog::catat('pengaturan.diubah', null, $diubah);
        }

        return back()->with('sukses', $diubah === []
            ? 'Tidak ada pengaturan yang berubah.'
            : count($diubah).' pengaturan disimpan.');
    }

    /** @return list<string> */
    private static function kunciTab(string $tab): array
    {
        return collect(self::TAB[$tab]['bagian'])->flatMap(fn ($b) => $b['kunci'])->all();
    }

    /**
     * Gambar QRIS statis toko (KEP-023). Dipisahkan dari formulir pengaturan
     * biasa karena berupa berkas, bukan teks, dan karena salah unggah di sini
     * berarti uang pelanggan masuk ke rekening orang lain: yang lama dihapus
     * hanya setelah yang baru tersimpan, dan penggantinya selalu tercatat.
     */
    public function unggahQris(Request $request, PengunggahGambar $pengunggah): RedirectResponse
    {
        $request->validate([
            'qris' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(setting('upload_maks_mb', 10) * 1024)],
        ], [], ['qris' => 'gambar QRIS']);

        $lama = (string) setting('qris_path', '');
        // QRIS harus terbaca utuh oleh kamera; versi kecilnya tidak dipakai.
        $hasil = $pengunggah->simpan($request->file('qris'), 'qris', denganThumb: false);

        Setting::simpan('qris_path', $hasil['path']);
        $pengunggah->hapus($lama);

        ActivityLog::catat('pengaturan.qris', null, ['dari' => $lama, 'ke' => $hasil['path']]);

        return back()->with('sukses', 'Gambar QRIS diperbarui. Periksa tampilannya di halaman pesanan pelanggan.');
    }

    public function hapusQris(PengunggahGambar $pengunggah): RedirectResponse
    {
        $lama = (string) setting('qris_path', '');

        Setting::simpan('qris_path', '');
        $pengunggah->hapus($lama);

        ActivityLog::catat('pengaturan.qris', null, ['dari' => $lama, 'ke' => '']);

        return back()->with('sukses', 'Gambar QRIS dihapus. Pelanggan kini hanya melihat rekening bank.');
    }

    /**
     * Pencarian area Biteship (KEP-057/066), dipakai kotak pencarian area asal
     * di tab Pengiriman. Hanya butuh kunci API tersimpan di .env, bukan area
     * asal yang sudah terisi — kalau tidak, tidak ada cara mengisinya pertama
     * kali.
     */
    public function cariAreaBiteship(Request $request, PenyediaOngkir $penyedia): JsonResponse
    {
        return response()->json($penyedia->cariArea((string) $request->query('q', '')));
    }
}
