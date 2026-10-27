<?php

if (! function_exists('rupiah')) {
    /** Rupiah tidak punya pecahan sen; seluruh nominal di sistem ini bilangan bulat. */
    function rupiah(int|float|null $nominal, bool $prefix = true): string
    {
        $angka = number_format((float) ($nominal ?? 0), 0, ',', '.');

        return $prefix ? 'Rp '.$angka : $angka;
    }
}

if (! function_exists('rupiah_ringkas')) {
    /** "Rp 2,2 jt" untuk tempat sempit seperti kartu produk di layar kecil. */
    function rupiah_ringkas(int|float|null $nominal): string
    {
        $n = (float) ($nominal ?? 0);

        /*
         * Nol di belakang koma dibuang, nol di belakang angka bulat TIDAK.
         * Memotongnya tanpa memeriksa koma mengubah "10 rb" menjadi "1 rb",
         * dan angka uang yang salah jauh lebih buruk daripada angka yang panjang.
         */
        $rapikan = function (string $angka): string {
            return str_contains($angka, ',')
                ? rtrim(rtrim($angka, '0'), ',')
                : $angka;
        };

        if ($n >= 1_000_000) {
            return 'Rp '.$rapikan(number_format($n / 1_000_000, 1, ',', '.')).' jt';
        }

        if ($n >= 1_000) {
            return 'Rp '.$rapikan(number_format($n / 1_000, 0, ',', '.')).' rb';
        }

        return rupiah($n);
    }
}

if (! function_exists('setting')) {
    /** Nilai bisnis yang boleh diubah pemilik toko tanpa deploy (lihat Setting). */
    function setting(string $key, mixed $default = null): mixed
    {
        return \App\Models\Setting::ambil($key, $default);
    }
}

if (! function_exists('siap_stok')) {
    /**
     * Kapan barang stok siap diambil, dari Pengaturan > Pesanan (KEP-091),
     * bukan kata "besok" yang dulu tertulis langsung di lima tampilan.
     */
    function siap_stok(): string
    {
        $hari = (int) setting('stok_siap_hari', 1);

        return match (true) {
            $hari <= 0 => 'hari ini',
            $hari === 1 => 'besok',
            $hari === 2 => 'lusa',
            default => $hari.' hari lagi',
        };
    }
}

if (! function_exists('rentang_hari_kerja')) {
    /**
     * Waktu pengerjaan produk kustom dari datanya (KEP-091): "7 hari kerja",
     * atau "7–14 hari kerja" kalau produknya berbeda-beda. Null kalau belum ada
     * produk kustom; dulu tampilan jatuh ke angka 7 yang dikarang.
     */
    function rentang_hari_kerja(?int $tercepat, ?int $terlama): ?string
    {
        if (! $tercepat) {
            return null;
        }

        return ($terlama > $tercepat ? $tercepat.'–'.$terlama : $tercepat).' hari kerja';
    }
}

if (! function_exists('urutkan_ukuran')) {
    /**
     * Mengurutkan ukuran seperti orang membacanya, bukan seperti komputer.
     * Tanpa ini, "S, M, L, XL" tampil sebagai "L, M, S, XL", dan di toko
     * jersey itu langsung terlihat salah oleh siapa pun.
     */
    function urutkan_ukuran(iterable $ukuran): array
    {
        $baku = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', 'XXXL', '3XL', '4XL', '5XL'];
        $daftar = collect($ukuran)->filter()->unique()->values()->all();

        usort($daftar, function ($a, $b) use ($baku) {
            // Ukuran angka (sepatu 39-44) diurutkan sebagai angka.
            if (is_numeric($a) && is_numeric($b)) {
                return (float) $a <=> (float) $b;
            }

            $ia = array_search(strtoupper($a), $baku, true);
            $ib = array_search(strtoupper($b), $baku, true);

            if ($ia !== false && $ib !== false) {
                return $ia <=> $ib;
            }

            // Yang dikenali selalu didahulukan; sisanya menyusul menurut abjad.
            return match (true) {
                $ia !== false => -1,
                $ib !== false => 1,
                default => strnatcasecmp((string) $a, (string) $b),
            };
        });

        return $daftar;
    }
}

if (! function_exists('nomor_wa')) {
    /**
     * Nomor HP dalam bentuk yang diterima wa.me: kode negara, tanpa tanda baca,
     * tanpa nol di depan. Orang mengetik "0812-3456-7890" atau "+62 812..."
     * cepat atau lambat, jadi kami yang merapikan.
     *
     * Dipakai tombol "Kabari lewat WhatsApp" di panel, yaitu toko yang
     * menghubungi pelanggan saat email tidak sampai (KEP-004). Arah sebaliknya,
     * pelanggan bertransaksi lewat WhatsApp, sengaja tidak ada lagi (KEP-029).
     */
    function nomor_wa(?string $mentah): ?string
    {
        $angka = preg_replace('/\D/', '', (string) $mentah);

        if ($angka === '' || $angka === null) {
            return null;
        }

        return match (true) {
            str_starts_with($angka, '62') => $angka,
            str_starts_with($angka, '0') => '62'.substr($angka, 1),
            default => '62'.$angka,
        };
    }
}

if (! function_exists('tautan_wa')) {
    /**
     * Tautan chat WhatsApp ke nomor toko, dengan pesan pembuka yang sudah terisi.
     *
     * Hanya untuk KONTAK saat chat CS dimatikan (KEP-033): pertanyaan dan
     * komplain. Bukan untuk transaksi; bukti bayar tetap diunggah di website
     * (KEP-029), dan halaman pembayaran tidak pernah memakai fungsi ini.
     */
    function tautan_wa(string $pesan = ''): ?string
    {
        $nomor = nomor_wa((string) setting('toko_telepon', ''));

        if (! $nomor) {
            return null;
        }

        return 'https://wa.me/'.$nomor.($pesan !== '' ? '?text='.rawurlencode($pesan) : '');
    }
}

if (! function_exists('daftar_setting')) {
    /**
     * Pengaturan bertipe teks yang isinya daftar, satu per baris. Dipakai untuk
     * hal-hal yang jumlahnya berubah sesuai kesepakatan toko, seperti jasa kirim
     * yang sedang bekerja sama (KEP-026), dan tidak cukup penting untuk punya
     * tabel sendiri.
     *
     * @return list<string>
     */
    function daftar_setting(string $key): array
    {
        $baris = preg_split('/\R/', (string) setting($key, '')) ?: [];

        return array_values(array_filter(array_map('trim', $baris), fn ($b) => $b !== ''));
    }
}

if (! function_exists('baris_html')) {
    /**
     * Teks pengaturan yang ditulis berbaris (jam buka toko: Senin-Sabtu di satu
     * baris, Minggu di baris berikutnya) ditampilkan dengan pindah barisnya.
     * Isinya tetap di-escape; hanya pindah baris yang diubah jadi <br>.
     */
    function baris_html(?string $teks): Illuminate\Support\HtmlString
    {
        return new Illuminate\Support\HtmlString(nl2br(e((string) $teks), false));
    }
}

if (! function_exists('tautan_peta')) {
    /**
     * Tautan Google Maps untuk sebuah alamat, dengan format pencarian resmi
     * Google. Dipakai selama pemilik toko belum menempelkan tautan peta sendiri.
     */
    function tautan_peta(string $alamat): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($alamat);
    }
}

if (! function_exists('cs_aktif')) {
    /**
     * Saklar chat CS (KEP-033). Klien belum yakin akan memakai CS, jadi satu kode
     * melayani dua versi: mematikan saklar di Pengaturan harus menghasilkan
     * website yang bersih tanpa CS, bukan website yang masih menyuruh pelanggan
     * chat ke CS yang tidak ada.
     *
     * Saklar itu sendiri hanya berlaku kalau fiturnya dipasang (config/fitur.php).
     */
    function cs_aktif(): bool
    {
        return config('fitur.cs', false) && (bool) setting('cs_aktif', true);
    }
}

if (! function_exists('kanal_kontak')) {
    /**
     * Jalur kontak yang disebut di teks halaman dan email. Mengikuti saklar CS,
     * supaya kalimat "hubungi kami lewat ..." tidak pernah menunjuk ke jalur mati.
     */
    function kanal_kontak(): string
    {
        if (cs_aktif()) {
            return 'chat CS di website kami';
        }

        $wa = setting('toko_telepon');

        return $wa ? 'WhatsApp toko '.$wa : 'telepon toko';
    }
}
