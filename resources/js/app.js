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
