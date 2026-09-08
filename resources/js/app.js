import Alpine from 'alpinejs';

window.Alpine = Alpine;
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
        tombol.dataset.teksAsli = tombol.textContent;
        tombol.textContent = tombol.dataset.teksProses || 'Memproses...';

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

        form.querySelectorAll('button[data-teks-asli]').forEach((tombol) => {
            tombol.disabled = false;
            tombol.textContent = tombol.dataset.teksAsli;
        });
    });
});
