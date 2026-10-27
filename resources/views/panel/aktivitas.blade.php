@extends('layouts.panel')
@section('judul', 'Riwayat aktivitas')

@section('isi')
<x-kop judul="Riwayat aktivitas"
       catatan="Siapa mengubah apa, kapan. Dicatat otomatis di seluruh panel, dibaca di sini kalau ada yang perlu dicek ulang.">
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <label for="cari-aktivitas" class="sr-only">Cari jenis aktivitas</label>
        <div class="relative">
            <x-ikon nama="cari" ukuran="size-4"
                    class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-navy-300" />
            <input type="search" id="cari-aktivitas" name="q" value="{{ request('q') }}"
                   placeholder="Mis. stok, pengaturan, produk&hellip;" class="kotak-input w-48 pl-9 sm:w-60">
        </div>

        <label for="saring-pengguna" class="sr-only">Saring menurut pengguna</label>
        <select id="saring-pengguna" name="user_id" class="kotak-input w-40" data-kirim-saat-ubah>
            <option value="">Semua pengguna</option>
            @foreach ($pengguna as $p)
                <option value="{{ $p->id }}" @selected((string) request('user_id') === (string) $p->id)>
                    {{ $p->name }}
                </option>
            @endforeach
        </select>

        <button type="submit" class="tombol tombol-garis tombol-sm">Saring</button>
    </form>
</x-kop>

@if ($log->isEmpty())
    <div class="kartu mt-6 p-8 text-center">
        <x-ikon nama="jam" ukuran="size-9" class="mx-auto text-navy-300" />
        <p class="mt-3 text-lg font-bold">Belum ada aktivitas yang cocok</p>
        <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted">
            @if (request('q') || request('user_id'))
                Coba kata atau pengguna lain, atau hapus penyaring untuk melihat semuanya.
            @else
                Setiap perubahan pengaturan, stok, dan katalog akan tercatat di sini.
            @endif
        </p>
    </div>
@else
    {{-- Kolom "Rincian" bisa panjang (daftar perubahan, JSON bersarang) --
         dibungkus overflow-x-auto supaya yang melebar cuma tabelnya sendiri,
         bukan membuat seluruh halaman bisa digeser ke samping di HP. --}}
    <section class="kartu mt-6 overflow-hidden">
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Daftar riwayat aktivitas">
        <table class="tabel">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Siapa</th>
                    <th>Aktivitas</th>
                    <th>Subjek</th>
                    <th>Rincian</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($log as $l)
                    <tr>
                        <td class="tabular whitespace-nowrap text-sm text-muted">
                            {{ $l->created_at->translatedFormat('j M Y, H:i') }}
                        </td>
                        <td class="text-sm">{{ $l->user->name ?? 'Sistem / publik' }}</td>
                        <td class="text-sm font-medium">{{ $l->aksi }}</td>
                        <td class="text-sm text-muted">
                            {{ $l->subjek_tipe ? $l->subjek_tipe.' #'.$l->subjek_id : '-' }}
                        </td>
                        <td class="max-w-xs text-xs text-muted">
                            @if ($l->data_json)
                                <ul class="space-y-0.5">
                                    @foreach ($l->data_json as $kunci => $nilai)
                                        <li>
                                            <span class="font-medium">{{ $kunci }}:</span>
                                            {{ is_scalar($nilai) ? $nilai : json_encode($nilai) }}
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </section>

    <div class="mt-6">{{ $log->links() }}</div>
@endif
@endsection
