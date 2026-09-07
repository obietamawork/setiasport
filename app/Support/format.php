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

        if ($n >= 1_000_000) {
            return 'Rp '.rtrim(rtrim(number_format($n / 1_000_000, 1, ',', '.'), '0'), ',').' jt';
        }

        if ($n >= 1_000) {
            return 'Rp '.rtrim(rtrim(number_format($n / 1_000, 0, ',', '.'), '0'), ',').' rb';
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
