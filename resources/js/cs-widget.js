/*
 * Widget chat CS untuk pelanggan (KEP-030).
 *
 * Isi pesan SELALU dipasang lewat x-text (textContent), tidak pernah lewat
 * x-html. Pesan datang dari orang asing; memasangnya sebagai HTML berarti
 * siapa pun bisa menjalankan skrip di halaman staf maupun pelanggan lain.
 */

const POLLING_MS = 15000;

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

let urlStatus = null;

/*
 * Tab yang dibiarkan terbuka berjam-jam kehilangan sesinya, dan token CSRF di
 * halaman ikut basi. Daripada meminta pelanggan memuat ulang halaman, token
 * baru diambil dari server lalu permintaannya diulang sekali. Pesan yang
 * sedang diketik tidak hilang.
 */
async function segarkanToken() {
    const res = await fetch(urlStatus, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    const data = await res.json().catch(() => ({}));
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (data.csrf && meta) meta.content = data.csrf;
}

async function minta(url, opsi = {}, bolehUlang = true) {
    const res = await fetch(url, {
        credentials: 'same-origin',
        ...opsi,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), ...(opsi.headers ?? {}) },
    });

    if (res.status === 419 && bolehUlang && urlStatus) {
        await segarkanToken();
        return minta(url, opsi, false);
    }

    const data = await res.json().catch(() => ({}));

    if (!res.ok) {
        const pesan = res.status === 429
            ? 'Terlalu banyak pesan dalam waktu singkat. Tunggu sebentar lalu coba lagi.'
            : (data.errors ? Object.values(data.errors)[0][0] : data.message) || 'Pesan belum terkirim. Coba lagi.';
        throw new Error(pesan);
    }

    return data;
}

export function widgetCs(awal) {
    return {
        buka: false,
        tahap: 'tutup',          // tutup | memuat | mulai | obrolan
        pesan: [],
        percakapan: null,
        belumDibaca: awal.belumDibaca ?? 0,
        online: awal.online ?? false,
        form: { topik: '', nama: '', kontak: '', kode_pesanan: '', isi: '' },
        lampiran: null,
        mengirim: false,
        galat: '',
        realtime: 'mati',
        _echo: null,
        _polling: null,
        _dimuat: false,

        init() {
            urlStatus = awal.url.status;
            window.addEventListener('buka-cs', (e) => this.bukaDengan(e.detail ?? {}));

            // Percakapan yang masih berjalan disambungkan sejak halaman dibuka,
            // supaya balasan CS memunculkan tanda di tombol meski jendela
            // chat sedang tertutup.
            if (awal.channel && awal.status === 'TERBUKA') {
                this.sambungRealtime(awal.channel);
            }
        },

        bukaDengan({ topik } = {}) {
            if (topik && !this.form.topik) this.form.topik = topik;
            this.bukaTutup(true);
        },

        async bukaTutup(paksa = null) {
            this.buka = paksa ?? !this.buka;

            if (!this.buka) {
                this.hentikanPolling();
                return;
            }

            this.belumDibaca = 0;

            if (!this._dimuat) {
                await this.muat();
            } else if (this.realtime !== 'connected' && this.percakapan) {
                await this.ambilBaru();
            }

            this.mulaiPollingBilaPerlu();

            // Formulir pembuka dibaca dari atas; hanya percakapan yang digulir ke pesan terakhir.
            if (this.tahap === 'obrolan') this.gulir();
        },

        async muat() {
            this.tahap = 'memuat';

            try {
                const data = await minta(awal.url.status);
                this.percakapan = data.percakapan;
                this.online = data.online ?? this.online;
                this.pesan = [...data.pesan].sort((a, b) => a.id - b.id);
                this.tahap = data.percakapan ? 'obrolan' : 'mulai';
                this._dimuat = true;

                if (data.percakapan && !this._echo) this.sambungRealtime(data.percakapan.channel);
            } catch (e) {
                this.galat = e.message;
                this.tahap = 'mulai';
            }
        },

        pilihLampiran(e) {
            this.lampiran = e.target.files[0] ?? null;
        },

        async mulai() {
            if (this.mengirim) return;
            this.galat = '';
            this.mengirim = true;

            const data = new FormData();
            Object.entries(this.form).forEach(([k, v]) => v && data.append(k, v));
            if (awal.tokenPesanan) data.append('token_pesanan', awal.tokenPesanan);
            if (this.lampiran) data.append('lampiran', this.lampiran);

            try {
                const hasil = await minta(awal.url.mulai, { method: 'POST', body: data });
                this.percakapan = hasil.percakapan;
                this.pesan = [hasil.pesan];
                this.tahap = 'obrolan';
                this.form.isi = '';
                this.lampiran = null;
                this.sambungRealtime(hasil.percakapan.channel);
                this.mulaiPollingBilaPerlu();
                this.gulir();
            } catch (e) {
                this.galat = e.message;
            } finally {
                this.mengirim = false;
            }
        },

        async kirim() {
            if (this.mengirim || (!this.form.isi.trim() && !this.lampiran)) return;
            this.galat = '';
            this.mengirim = true;

            const data = new FormData();
            if (this.form.isi.trim()) data.append('isi', this.form.isi.trim());
            if (this.lampiran) data.append('lampiran', this.lampiran);

            try {
                const hasil = await minta(awal.url.kirim, { method: 'POST', body: data });
                this.tambah(hasil.pesan);
                this.form.isi = '';
                this.lampiran = null;
                if (this.$refs.berkas) this.$refs.berkas.value = '';
                if (this.percakapan) this.percakapan.status = 'TERBUKA';
            } catch (e) {
                this.galat = e.message;
            } finally {
                this.mengirim = false;
            }
        },

        // Enter mengirim, Shift+Enter membuat baris baru: kebiasaan aplikasi chat.
        tombol(e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                e.preventDefault();
                this.tahap === 'mulai' ? this.mulai() : this.kirim();
            }
        },

        tambah(p) {
            if (this.pesan.some((x) => x.id === p.id)) return;

            this.pesan.push(p);
            this.pesan.sort((a, b) => a.id - b.id);

            if (p.pengirim === 'SISTEM' && this.percakapan) this.percakapan.status = 'SELESAI';
            if (p.pengirim !== 'PELANGGAN' && !this.buka) this.belumDibaca++;

            this.gulir();
        },

        async ambilBaru() {
            const setelah = this.pesan.length ? this.pesan[this.pesan.length - 1].id : 0;

            try {
                const data = await minta(`${awal.url.pesan}?setelah=${setelah}`);
                data.pesan.forEach((p) => this.tambah(p));
                if (this.percakapan) this.percakapan.status = data.status;
            } catch {
                // Polling berikutnya akan mencoba lagi; tidak perlu mengganggu pelanggan.
            }
        },

        async sambungRealtime(channel) {
            if (this._echo || !awal.realtime?.key) return;

            this.realtime = 'menyambung';

            try {
                const { sambung } = await import('./realtime.js');

                this._echo = sambung(awal.realtime, awal.url.auth, (keadaan) => {
                    this.realtime = keadaan;
                    keadaan === 'connected' ? this.hentikanPolling() : this.mulaiPollingBilaPerlu();
                });

                // Nama dari server berawalan "private-"; Echo menambahkannya sendiri.
                this._echo.private(channel.replace(/^private-/, ''))
                    .listen('.pesan', (e) => this.tambah(e.pesan));
            } catch {
                this.realtime = 'gagal';
                this.mulaiPollingBilaPerlu();
            }
        },

        /*
         * Cadangan: kalau realtime tidak tersambung, widget bertanya ke server
         * tiap 15 detik, TAPI hanya selama jendela chat terbuka. Chat tetap
         * jalan saat kuota Pusher habis, tanpa membebani hosting sepanjang hari.
         */
        mulaiPollingBilaPerlu() {
            if (this._polling || !this.buka || !this.percakapan || this.realtime === 'connected') return;
            this._polling = setInterval(() => this.ambilBaru(), POLLING_MS);
        },

        hentikanPolling() {
            clearInterval(this._polling);
            this._polling = null;
        },

        gulir() {
            this.$nextTick(() => {
                const el = this.$refs.daftar;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        waktu(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            const hariIni = d.toDateString() === new Date().toDateString();
            return hariIni
                ? d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
                : d.toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
        },
    };
}
