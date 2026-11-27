{{--
    Tab pusat akun seperti papan kanvas Kit Tim (KEP-176). Labelnya kata yang
    sudah ada di kedua halaman: Pesanan saya dan Akun saya.
--}}
<nav class="segmen mt-6" aria-label="Akun saya">
    @foreach ([['pesanan', route('pesanan-saya'), 'Pesanan saya'], ['akun', route('akun'), 'Akun saya']] as [$kunci, $url, $label])
        <a href="{{ $url }}" @class(['segmen-item', 'segmen-item-aktif' => $aktif === $kunci])
           @if ($aktif === $kunci) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
