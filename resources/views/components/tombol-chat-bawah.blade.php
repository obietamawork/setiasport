{{--
    Tombol Chat di bilah aksi bawah HP (KEP-174). Di halaman yang punya bilah aksi,
    bilah navigasi bawah (tempat tombol Chat biasanya) disembunyikan, jadi pintu ke
    CS dibawa ke bilah ini, seperti papan kanvas Kit Tim. Hanya tampil di HP; di
    layar lebar tombol CS mengambang tetap dipakai. x-data sendiri supaya @click
    tetap hidup walau tombol ini berada di luar komponen Alpine mana pun.
--}}
@if (cs_aktif())
    <button type="button" x-data class="tombol tombol-garis aksi-bawah-chat"
            @click="window.dispatchEvent(new CustomEvent('buka-cs'))">
        <x-ikon nama="chat" />
        <span class="sr-only">Chat</span>
    </button>
@endif
