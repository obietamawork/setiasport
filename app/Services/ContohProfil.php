<?php

namespace App\Services;

use App\Models\Portofolio;
use App\Models\Testimoni;
use Illuminate\Http\UploadedFile;

/**
 * Data contoh portofolio dan testimoni (KEP-035), untuk memperlihatkan ke klien
 * seperti apa halaman depan yang sudah terisi.
 *
 * Aturannya ketat karena testimoni karangan yang tayang di website asli sama
 * dengan ulasan palsu:
 * - Tidak ada nama lembaga sungguhan. Instansi ditulis sebagai jenisnya
 *   ("SMA di Wonogiri"), bukan nama sekolah atau dinas yang tidak pernah memesan.
 * - Gambarnya ilustrasi mockup berlabel "Contoh ilustrasi", bukan foto orang lain.
 * - Setiap baris ditandai `contoh`, dashboard memperingatkannya, dan hapus() hanya
 *   menyentuh baris bertanda itu, tidak pernah isian asli klien.
 */
class ContohProfil
{
    private const PORTOFOLIO = [
        ['futsal-navy-merah.webp', 'Jersey futsal full printing', 'Tim futsal SMA di Wonogiri', 'Jersey futsal', 2026, 24,
            'Full printing dengan nama dan nomor punggung tiap pemain. Desain dari sketsa pelatih, disetujui setelah dua kali revisi mockup.'],
        ['sepakbola-hijau.webp', 'Jersey sepak bola usia muda', 'Sekolah sepak bola usia 10-14 tahun', 'Jersey sepak bola', 2025, 36,
            'Tiga ukuran anak dalam satu pesanan. Daftar nama dan ukuran diisi orang tua pemain lewat halaman pesanan.'],
        ['voli-kuning.webp', 'Jersey voli tim desa', 'Karang taruna, turnamen voli antardesa', 'Jersey voli', 2026, 14,
            'Selesai tujuh hari kerja setelah desain disetujui, diambil sehari sebelum turnamen dimulai.'],
        ['kaos-lari-oranye.webp', 'Kaos komunitas lari', 'Komunitas lari, acara fun run 5K', 'Kaos event', 2025, 150,
            'Kaos panitia dan peserta dibedakan warnanya. Sablon di depan, nama peran di punggung.'],
        ['olahraga-sekolah-biru.webp', 'Kaos olahraga seragam sekolah', 'Sekolah dasar di Wonogiri', 'Seragam olahraga', 2026, 320,
            'Enam kelas sekaligus, dipisah per kelas saat packing supaya guru tinggal membagikan.'],
        ['polo-kantor-abu.webp', 'Polo seragam kantor', 'Kantor instansi di Wonogiri', 'Polo seragam', 2025, 60,
            'Polo berkerah dengan bordir logo di dada kiri. Ukuran dicoba di toko sebelum produksi.'],
    ];

    /** Nama panggilan umum dan peran, tanpa nama lembaga. */
    private const TESTIMONI = [
        ['Pak Hendra', 'Pelatih tim futsal SMA', 5,
            'Mockupnya dikirim dulu, anak-anak sempat minta ganti warna lengan dan langsung dibetulkan. Jadinya persis seperti yang disetujui, nomor punggung tidak ada yang salah.'],
        ['Bu Ratna', 'Guru olahraga SD', 5,
            'Pesan 320 kaos untuk enam kelas, datang sudah dipisah per kelas. Tinggal bagi. Ukurannya juga pas, tidak ada yang kekecilan.'],
        ['Mas Dimas', 'Ketua komunitas lari', 5,
            'Enak bisa lihat progresnya di halaman pesanan, jadi tidak perlu tanya terus. Kaosnya jadi dua hari sebelum acara.'],
        ['Pak Joko', 'Pengurus sekolah sepak bola', 4,
            'Bahannya bagus dan jahitannya rapi. Sempat mundur sehari karena kami telat mengisi daftar ukuran, tapi dikabari dari awal.'],
        ['Mbak Sari', 'Karang taruna', 5,
            'Tim voli kami pakai jersey dari sini sudah dua musim. Warnanya tidak luntur walau sering dicuci.'],
        ['Pak Agung', 'Staf kantor', 5,
            'Bordir logonya rapi dan polonya adem dipakai seharian.'],
    ];

    public function __construct(private PengunggahGambar $pengunggah) {}

    /** @return array{portofolio: int, testimoni: int} */
    public function pasang(): array
    {
        // Dipasang ulang dari awal, supaya menjalankannya dua kali tidak menggandakan isi.
        $this->hapus();

        foreach (self::PORTOFOLIO as $i => [$berkas, $judul, $instansi, $kategori, $tahun, $pcs, $ringkasan]) {
            $sumber = database_path('seeders/aset/contoh/'.$berkas);
            $gambar = $this->pengunggah->simpan(
                new UploadedFile($sumber, $berkas, 'image/webp', null, true),
                'portofolio'
            );

            Portofolio::create([
                'judul' => $judul,
                'slug' => 'contoh-'.pathinfo($berkas, PATHINFO_FILENAME),
                'instansi' => $instansi,
                'kategori' => $kategori,
                'tahun' => $tahun,
                'jumlah_pcs' => $pcs,
                'ringkasan' => $ringkasan,
                'gambar_path' => $gambar['path'],
                'thumb_path' => $gambar['thumb_path'],
                'aktif' => true,
                'contoh' => true,
                'urutan' => $i,
            ]);
        }

        foreach (self::TESTIMONI as $i => [$nama, $peran, $bintang, $isi]) {
            Testimoni::create([
                'nama' => $nama,
                'peran' => $peran,
                'bintang' => $bintang,
                'isi' => $isi,
                'aktif' => true,
                'contoh' => true,
                'urutan' => $i,
            ]);
        }

        return ['portofolio' => count(self::PORTOFOLIO), 'testimoni' => count(self::TESTIMONI)];
    }

    /**
     * Hanya baris bertanda contoh, beserta berkas gambarnya. Isian asli klien
     * tidak pernah tersentuh, jadi aman ditekan kapan saja.
     *
     * @return array{portofolio: int, testimoni: int}
     */
    public function hapus(): array
    {
        $portofolio = Portofolio::where('contoh', true)->get();

        foreach ($portofolio as $p) {
            $this->pengunggah->hapus($p->gambar_path, $p->thumb_path);
            $p->delete();
        }

        return [
            'portofolio' => $portofolio->count(),
            'testimoni' => Testimoni::where('contoh', true)->delete(),
        ];
    }

    public function ada(): bool
    {
        return Portofolio::where('contoh', true)->exists() || Testimoni::where('contoh', true)->exists();
    }
}
