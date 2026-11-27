{{--
    Kepala pusat akun seperti papan kanvas Kit Tim (KEP-177): inisial, sapaan,
    kontak, lalu tab Pesanan saya dan Akun saya (KEP-176). Dipakai bersama oleh
    kedua halaman supaya keduanya terasa satu tempat.

    @var \App\Models\Customer $pelanggan
    @var string $aktif
--}}
@php
    $inisial = Str::of($pelanggan->nama)->squish()->explode(' ')->take(2)
        ->map(fn (string $kata) => Str::upper(Str::substr($kata, 0, 1)))
        ->implode('');
@endphp
<div class="flex items-center gap-4">
    <span aria-hidden="true"
          class="flex size-14 shrink-0 items-center justify-center rounded-full bg-navy text-lg font-extrabold text-white">{{ $inisial }}</span>
    <div class="min-w-0">
        <h1 class="judul-tampil text-[2.25rem] text-balance sm:text-[2.5rem]">Halo, {{ $pelanggan->nama }}</h1>
        <p class="mt-1 text-sm text-muted wrap-anywhere">
            {{ $pelanggan->email }}@if ($pelanggan->no_hp) &middot; {{ $pelanggan->no_hp }}@endif
        </p>
    </div>
</div>

@include('publik.akun.tab', ['aktif' => $aktif])
