{{-- Tombol keluar di bawah pusat akun, seperti papan kanvas Kit Tim (KEP-177). --}}
<form method="POST" action="{{ route('keluar') }}" class="mt-4 text-center">
    @csrf
    <button type="submit" class="tombol tombol-halus">Keluar dari akun</button>
</form>
