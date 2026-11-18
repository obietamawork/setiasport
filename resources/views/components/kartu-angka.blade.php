@props(['warna' => 'biru', 'ikon', 'label', 'angka', 'href' => null, 'aktif' => false])

{{--
    Kartu angka berwarna (KEP-111). Warnanya menandai BAGIAN, bukan hiasan:
    biru pesanan, hijau uang, jingga produksi, ungu pelunasan dan desain,
    pink siap diambil atau dikirim, kuning menunggu pembayaran, merah yang
    sudah mati. Warna yang sama dipakai chip status di daftar pesanan, jadi
    staf cukup menghafalnya sekali.

    Kartu yang bisa ditindaklanjuti dibuat menjadi tautan, yang sekadar kabar
    tidak. Kartu yang terlihat bisa diklik tetapi diam adalah janji yang tidak
    ditepati.
--}}
@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif @if ($aktif) aria-current="page" @endif
    {{ $attributes->merge(['class' => 'kartu-angka warna-'.$warna.($href ? ' kartu-angka-tautan' : '').($aktif ? ' kartu-angka-aktif' : '')]) }}>
    <span class="kartu-angka-ikon"><x-ikon :nama="$ikon" ukuran="size-5" /></span>
    <span class="kartu-angka-label">{{ $label }}</span>
    <span class="kartu-angka-nilai">{{ $angka }}</span>
</{{ $tag }}>
