/*
 * Notifikasi chat CS di halaman panel (KEP-030).
 *
 * Risiko terbesar chat CS bukan teknis: chat yang tidak dijawab lebih buruk
 * daripada tidak ada chat. Karena itu sambungan staf dipasang di SEMUA halaman
 * panel, bukan hanya di ruang CS. Admin yang sedang mengurus stok tetap
 * mendengar bunyi dan melihat angka di menu saat pelanggan menulis. Ruang CS
 * sendiri memegang sambungannya di cs-ruang.js.
 */

const POLLING_MS = 30000;

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function minta(url, opsi = {}) {
    const res = await fetch(url, {
        credentials: 'same-origin',
        ...opsi,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), ...(opsi.headers ?? {}) },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const galat = new Error((data.errors ? Object.values(data.errors)[0][0] : data.message) || 'Permintaan gagal.');
        galat.status = res.status;
        throw galat;
    }
    return data;
}

/** Dua nada pendek lewat WebAudio: tanpa berkas suara yang harus diunduh. */
function bunyi() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        [880, 1175].forEach((hz, i) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.frequency.value = hz;
            gain.gain.setValueAtTime(0.08, ctx.currentTime + i * 0.15);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.15 + 0.14);
            osc.connect(gain).connect(ctx.destination);
            osc.start(ctx.currentTime + i * 0.15);
            osc.stop(ctx.currentTime + i * 0.15 + 0.15);
        });
    } catch {
        // Peramban menolak suara sebelum ada klik pertama; angka di menu tetap naik.
    }
}

export function notifikasiCs(awal) {
    return {
        belum: new Set(awal.belumDibaca ?? []),
        _judulAsli: document.title,
        _polling: null,

        get jumlah() {
            return this.belum.size;
        },

        async init() {
            window.addEventListener('focus', () => { document.title = this._judulAsli; });

            if (!awal.realtime?.key) {
                this.mulaiPolling();
                return;
            }

            try {
                const { sambung } = await import('./realtime.js');
                const echo = sambung(awal.realtime, awal.url.auth, (keadaan) => {
                    window.csRealtime = keadaan;
                    keadaan === 'connected' ? this.hentikanPolling() : this.mulaiPolling();
                });

                echo.private('cs.staf').listen('.pesan', (e) => this.terima(e));
            } catch {
                this.mulaiPolling();
            }
        },

        terima(e) {
            const id = e.percakapan?.id;
            if (!id) return;

            const baru = new Set(this.belum);

            if (e.pesan.pengirim === 'PELANGGAN') {
                baru.add(id);
                bunyi();
                if (!document.hasFocus()) document.title = '(Chat baru) ' + this._judulAsli;
            } else {
                baru.delete(id);
            }

            this.belum = baru;
        },

        mulaiPolling() {
            if (this._polling) return;
            this._polling = setInterval(async () => {
                try {
                    const data = await minta(awal.url.ringkasan);
                    const sebelum = this.belum.size;
                    this.belum = new Set(data.belum_dibaca);
                    if (this.belum.size > sebelum) bunyi();
                } catch (e) {
                    // Sesi habis: berhenti bertanya. Halaman panel berikutnya
                    // yang dibuka akan membawa admin ke login dengan sendirinya.
                    if (e.status === 401 || e.status === 419) this.hentikanPolling();
                }
            }, POLLING_MS);
        },

        hentikanPolling() {
            clearInterval(this._polling);
            this._polling = null;
        },
    };
}
