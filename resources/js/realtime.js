/*
 * Sambungan realtime chat CS (KEP-030).
 *
 * Berkas ini dimuat LAMBAT lewat import() dinamis, bukan dibundel ke app.js:
 * Echo dan pusher-js menambah puluhan kilobyte, dan sebagian besar pengunjung
 * tidak pernah membuka chat. Pengunjung yang tidak chat tidak mengunduhnya dan
 * tidak memakan jatah koneksi gratis Pusher.
 *
 * Konfigurasinya dibaca dari halaman saat dijalankan, bukan dibakar saat build,
 * supaya satu hasil build bisa dipakai di Reverb lokal maupun Pusher produksi.
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * @param {{key: string, cluster: string, host: ?string, port: ?number, tls: boolean}} konfig
 * @param {string} authEndpoint
 * @param {(keadaan: string) => void} [saatBerubah] dipanggil dengan 'connected' / 'unavailable' / 'failed'
 */
export function sambung(konfig, authEndpoint, saatBerubah = () => {}) {
    const opsi = {
        broadcaster: 'pusher',
        key: konfig.key,
        cluster: konfig.cluster,
        forceTLS: konfig.tls,
        authEndpoint,
        auth: { headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } },
    };

    // Reverb di lokal: alamatnya ditentukan sendiri, bukan klaster Pusher.
    if (konfig.host) {
        Object.assign(opsi, {
            wsHost: konfig.host,
            wsPort: konfig.port,
            wssPort: konfig.port,
            enabledTransports: konfig.tls ? ['wss'] : ['ws'],
        });
    }

    const echo = new Echo(opsi);
    const koneksi = echo.connector.pusher.connection;

    koneksi.bind('state_change', ({ current }) => saatBerubah(current));

    return echo;
}
