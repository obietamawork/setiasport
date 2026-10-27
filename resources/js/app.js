import Alpine from 'alpinejs';
import { widgetCs } from './cs-widget.js';
import { notifikasiCs } from './cs-panel.js';
import { ruangCs } from './cs-ruang.js';

window.Alpine = Alpine;

// Chat CS (KEP-030). Sambungan realtime-nya sendiri dimuat terpisah, hanya
// saat dibutuhkan; lihat realtime.js.
Alpine.data('widgetCs', widgetCs);
Alpine.data('notifikasiCs', notifikasiCs);
Alpine.data('ruangCs', ruangCs);

Alpine.start();

/*
 * Konfirmasi sebelum aksi yang tidak bisa diurungkan (hapus, batalkan, hanguskan):
 *
 *   <form data-konfirmasi="Hapus varian X?" data-konfirmasi-pesan="..." data-konfirmasi-tombol="Hapus">
 *
 * Pengganti onsubmit="return confirm(...)". Yang itu berjalan SESUDAH penjaga
 * kirim ganda di bawah (penjaga memakai fase capture), jadi menekan Batal
 * meninggalkan tombol "Memproses..." yang mati sampai halaman dimuat ulang.
 * Karena itu yang ini didaftarkan lebih dulu dan menahan kiriman sebelum
 * penjaga sempat berjalan; setelah dikonfirmasi formulir dikirim ulang lewat
 * requestSubmit() dan melewati penjaga seperti biasa.
 */
const dialogKonfirmasi = document.getElementById('dialog-konfirmasi');
let menungguKonfirmasi = null;

document.addEventListener('submit', (e) => {
    const form = e.target;

    if (!(form instanceof HTMLFormElement) || form.dataset.konfirmasi === undefined) return;

    if (form.dataset.dikonfirmasi === '1') {
        delete form.dataset.dikonfirmasi;
        return;
    }

    e.preventDefault();
    e.stopImmediatePropagation();

    const pengirim = e.submitter && e.submitter.form === form ? e.submitter : null;

    // Halaman tanpa dialog (mis. lembar cetak) tetap terlindungi.
    if (!dialogKonfirmasi) {
        if (window.confirm(form.dataset.konfirmasi)) kirimTerkonfirmasi(form, pengirim);
        return;
    }

    // Fokus dikembalikan ke tombol pengirim, bukan activeElement: Safari tidak
    // memfokuskan tombol yang diklik, jadi di sana activeElement adalah <body>.
    menungguKonfirmasi = { form, pengirim, fokusAsal: pengirim || document.activeElement };
    dialogKonfirmasi.querySelector('[data-konfirmasi-judul]').textContent = form.dataset.konfirmasi;

    const pesan = dialogKonfirmasi.querySelector('[data-konfirmasi-pesan]');
    pesan.textContent = form.dataset.konfirmasiPesan || '';
    pesan.hidden = !form.dataset.konfirmasiPesan;

    dialogKonfirmasi.querySelector('[data-konfirmasi-ya]').textContent = form.dataset.konfirmasiTombol || 'Ya, lanjutkan';
    dialogKonfirmasi.returnValue = '';
    dialogKonfirmasi.showModal();
    // Fokus awal di Batal: Enter yang kebablasan tidak boleh langsung menghapus.
    dialogKonfirmasi.querySelector('[data-konfirmasi-batal]').focus();
}, true);

function kirimTerkonfirmasi(form, pengirim) {
    form.dataset.dikonfirmasi = '1';
    form.requestSubmit(pengirim || undefined);
}

if (dialogKonfirmasi) {
    dialogKonfirmasi.querySelector('[data-konfirmasi-ya]').addEventListener('click', () => dialogKonfirmasi.close('ya'));
    dialogKonfirmasi.querySelector('[data-konfirmasi-batal]').addEventListener('click', () => dialogKonfirmasi.close('batal'));

    // Ketuk di luar kotak berarti batal, seperti menekan Esc.
    dialogKonfirmasi.addEventListener('click', (e) => {
        if (e.target === dialogKonfirmasi) dialogKonfirmasi.close('batal');
    });

    dialogKonfirmasi.addEventListener('close', () => {
        const tunggu = menungguKonfirmasi;
        menungguKonfirmasi = null;
        if (!tunggu) return;

        if (dialogKonfirmasi.returnValue === 'ya') {
            kirimTerkonfirmasi(tunggu.form, tunggu.pengirim);
        } else {
            tunggu.fokusAsal?.focus?.();
        }
    });
}

/*
 * Penjaga kirim ganda.
 *
 * Di shared hosting checkout bisa memakan dua sampai tiga detik. Klik dua kali
 * menghasilkan dua pesanan dan dua reservasi stok, dan itu kerusakan data yang
 * harus dibersihkan manual. Dipasang sekali di sini, bukan di tiap formulir,
 * supaya tidak ada yang terlewat.
 *
 * Formulir pencarian dikecualikan: mengirimnya dua kali tidak merusak apa pun,
 * dan menonaktifkan tombolnya justru mengganggu.
 */
document.addEventListener('submit', (e) => {
    const form = e.target;

    if (!(form instanceof HTMLFormElement)) return;
    if (form.dataset.izinkanGanda !== undefined) return;
    if ((form.method || 'get').toLowerCase() === 'get') return;

    if (form.dataset.terkirim === '1') {
        e.preventDefault();
        return;
    }

    form.dataset.terkirim = '1';

    form.querySelectorAll('button[type="submit"], button:not([type])').forEach((tombol) => {
        // Disimpan sebagai HTML, bukan teks: tombol panel memuat ikon, dan
        // menyimpan teksnya saja akan menghapus ikon itu tanpa bisa dikembalikan
        // saat halaman dipulihkan dari cache tombol Kembali.
        tombol.dataset.isiAsli = tombol.innerHTML;
        tombol.textContent = tombol.dataset.teksProses || 'Memproses...';
        tombol.prepend(Object.assign(document.createElement('span'), {
            className: 'pemuat',
            ariaHidden: 'true',
        }));

        // Ditunda satu tick: menonaktifkan tombol sebelum peramban selesai
        // menyusun data formulir bisa menggugurkan nilainya.
        setTimeout(() => { tombol.disabled = true; }, 0);
    });
}, true);

/*
 * Baris tabel yang seluruhnya bisa diklik (daftar pesanan dan produk).
 *
 * Dulu memakai tautan ber-::before selebar baris di <tr class="relative">.
 * WebKit (Safari, dan semua browser di iPhone/iPad) tidak menjadikan baris
 * tabel acuan posisi, jadi lapisan tautan baris lain menutupi seluruh tabel:
 * mengetuk pesanan SS-...-0002 membuka SS-...-0001. Tautan di sel pertama
 * tetap ada untuk papan ketik dan pembaca layar; klik di bagian lain baris
 * diteruskan ke alamat yang sama.
 */
document.addEventListener('click', (e) => {
    const baris = e.target.closest('tr[data-tautan]');

    if (!baris || e.button !== 0) return;
    // Tautan, tombol, dan isian di dalam baris tetap bekerja sendiri.
    if (e.target.closest('a, button, input, select, textarea, label, summary')) return;
    // Sedang memblok teks (mis. menyalin kode pesanan), bukan membuka.
    if (String(window.getSelection())) return;

    if (e.ctrlKey || e.metaKey || e.shiftKey) {
        window.open(baris.dataset.tautan, '_blank', 'noopener');
    } else {
        window.location.assign(baris.dataset.tautan);
    }
});

/*
 * Tombol − + di kiri-kanan kotak jumlah (components/jumlah, KEP-091).
 *
 * 'input' dikirim seketika supaya x-model Alpine ikut berubah. 'change'
 * menyusul setelah jeda: formulir yang langsung tersimpan saat berubah
 * (keranjang) tidak boleh terkirim di ketukan pertama dari tiga ketukan +.
 */
function perbaruiTombolJumlah(wadah) {
    const isian = wadah.querySelector('input');
    const [kurang, tambah] = wadah.querySelectorAll('[data-langkah-jumlah]');
    const nilai = Number(isian.value);

    kurang.disabled = isian.disabled || (isian.min !== '' && nilai <= Number(isian.min));
    tambah.disabled = isian.disabled || (isian.max !== '' && nilai >= Number(isian.max));
}

document.addEventListener('click', (e) => {
    const tombol = e.target.closest('[data-langkah-jumlah]');
    const wadah = tombol?.closest('[data-jumlah]');
    const isian = wadah?.querySelector('input');

    if (!isian || isian.disabled || isian.readOnly) return;

    const min = isian.min === '' ? -Infinity : Number(isian.min);
    const max = isian.max === '' ? Infinity : Number(isian.max);
    const sekarang = Number(isian.value) || 0;
    const baru = Math.min(max, Math.max(min, sekarang + Number(tombol.dataset.langkahJumlah)));

    if (baru === sekarang) return;

    isian.value = baru;
    isian.dispatchEvent(new Event('input', { bubbles: true }));
    perbaruiTombolJumlah(wadah);

    clearTimeout(isian._tundaUbah);
    isian._tundaUbah = setTimeout(() => isian.dispatchEvent(new Event('change', { bubbles: true })), 600);
});

document.addEventListener('input', (e) => {
    const wadah = e.target.closest?.('[data-jumlah]');
    if (wadah) perbaruiTombolJumlah(wadah);
});

// Keadaan awal, setelah Alpine selesai mengisi nilai dan menggambar baris.
setTimeout(() => document.querySelectorAll('[data-jumlah]').forEach(perbaruiTombolJumlah), 0);

// Saringan yang langsung berlaku saat pilihannya diganti (katalog, riwayat
// aktivitas). Pengganti onchange sebaris, yang tidak dijalankan CSP (KEP-088).
document.addEventListener('change', (e) => {
    if (e.target.matches?.('[data-kirim-saat-ubah]')) e.target.form?.requestSubmit();
});

// Klik tengah membuka tab baru, sama seperti tautan biasa.
document.addEventListener('auxclick', (e) => {
    const baris = e.target.closest('tr[data-tautan]');

    if (!baris || e.button !== 1 || e.target.closest('a, button')) return;
    window.open(baris.dataset.tautan, '_blank', 'noopener');
});

// Tombol kembali memulihkan halaman dari cache; formulirnya harus bisa dipakai lagi.
window.addEventListener('pageshow', (e) => {
    if (!e.persisted) return;

    document.querySelectorAll('form[data-terkirim]').forEach((form) => {
        delete form.dataset.terkirim;

        form.querySelectorAll('button[data-isi-asli]').forEach((tombol) => {
            tombol.disabled = false;
            tombol.innerHTML = tombol.dataset.isiAsli;
        });
    });
});
