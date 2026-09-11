/*
 * Ruang CS (KEP-031): antarmuka kerja chat yang terpisah dari panel.
 *
 * Halaman ini dibuka seharian, jadi ia bekerja seperti aplikasi: berpindah
 * percakapan, mencari, dan menyaring tanpa memuat ulang, supaya sambungan
 * realtime tidak putus setiap kali admin membuka percakapan lain.
 *
 * Isi pesan selalu dipasang lewat x-text, tidak pernah x-html: pesan datang
 * dari orang asing.
 */

const DENYUT_MS = 60000;
const POLLING_MS = 10000;

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
        const galat = new Error((data.errors ? Object.values(data.errors)[0][0] : data.message) || 'Permintaan gagal. Coba lagi.');
        galat.status = res.status;
        throw galat;
    }
    return data;
}

/** 401 dan 419 berarti sesi login sudah habis, bukan sekadar permintaan yang gagal. */
function sesiHabis(e) {
    return e?.status === 401 || e?.status === 419;
}

function simpanan(kunci, cadangan) {
    try {
        const v = localStorage.getItem(kunci);
        return v === null ? cadangan : JSON.parse(v);
    } catch {
        return cadangan;
    }
}

function simpan(kunci, nilai) {
    try {
        localStorage.setItem(kunci, JSON.stringify(nilai));
    } catch {
        // Mode privat atau penyimpanan diblokir: pengaturan kembali ke bawaan.
    }
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
        // Peramban menolak suara sebelum ada klik pertama.
    }
}

/** Urutan yang sama dengan server: yang paling lama menunggu di atas. */
function urutkan(a, b) {
    if (a.menunggu_sejak && !b.menunggu_sejak) return -1;
    if (!a.menunggu_sejak && b.menunggu_sejak) return 1;
    if (a.menunggu_sejak && b.menunggu_sejak) return new Date(a.menunggu_sejak) - new Date(b.menunggu_sejak);
    return new Date(b.pesan_terakhir_pada) - new Date(a.pesan_terakhir_pada);
}

export function ruangCs(awal) {
    return {
        daftar: awal.daftar,
        jumlah: awal.jumlah,
        saring: 'terbuka',
        cari: '',
        memuatDaftar: false,

        terpilih: awal.terpilih?.percakapan ?? null,
        pesan: awal.terpilih?.pesan ?? [],
        memuatPercakapan: false,

        isi: '',
        lampiran: null,
        mengirim: false,
        galat: '',

        template: awal.template,
        popover: false,
        cariTemplate: '',
        sorotTemplate: 0,

        kodeTautan: '',
        infoTerbuka: false,
        menerima: simpanan('cs.menerima', true),
        suara: simpanan('cs.suara', true),
        realtime: 'mati',
        sekarang: Date.now(),
        sesiBerakhir: false,
        _jadwal: [],
        _judulAsli: document.title,

        init() {
            this.gulir();

            this._jadwal.push(setInterval(() => { this.sekarang = Date.now(); }, 30000));

            // Kehadiran: denyut selama "Menerima chat" menyala (KEP-031).
            this.denyut();
            this._jadwal.push(setInterval(() => this.denyut(), DENYUT_MS));
            window.addEventListener('beforeunload', () => {
                navigator.sendBeacon?.(awal.url.hadir, new Blob(
                    [JSON.stringify({ menerima: false, _token: csrf() })],
                    { type: 'application/json' },
                ));
            });

            window.addEventListener('popstate', () => {
                const id = Number(location.pathname.split('/').pop());
                id ? this.pilih(id, false) : this.lepas(false);
            });

            this.$watch('cari', () => {
                clearTimeout(this._tundaCari);
                this._tundaCari = setTimeout(() => this.muatDaftar(), 300);
            });

            this.sambungRealtime();

            this._jadwal.push(setInterval(() => {
                if (this.realtime !== 'connected') {
                    this.muatDaftar(true);
                    this.ambilBaru();
                }
            }, POLLING_MS));

            window.addEventListener('focus', () => this.perbaruiJudul());
            this.perbaruiJudul();
        },

        /**
         * Sesi yang habis (laptop tertidur, atau lewat batas waktu login) tidak
         * boleh membuat layar ini tampak hidup padahal tidak ada chat yang
         * masuk. Semua jadwal dihentikan dan admin diminta masuk lagi.
         */
        tangani(e, tampilkan = true) {
            if (sesiHabis(e)) {
                if (!this.sesiBerakhir) {
                    this.sesiBerakhir = true;
                    this._jadwal.forEach(clearInterval);
                    document.title = 'Sesi berakhir | ' + this._judulAsli;
                }
                return;
            }
            if (tampilkan) this.galat = e.message;
        },

        /* ---- Daftar ---------------------------------------------------- */

        async muatDaftar(diam = false) {
            if (!diam) this.memuatDaftar = true;
            try {
                const q = new URLSearchParams({ saring: this.saring, q: this.cari });
                const data = await minta(`${awal.url.daftar}?${q}`);
                this.daftar = data.daftar;
                this.jumlah = data.jumlah;
                this.perbaruiJudul();
            } catch (e) {
                this.tangani(e, !diam);
            } finally {
                this.memuatDaftar = false;
            }
        },

        gantiSaring(s) {
            this.saring = s;
            this.muatDaftar();
        },

        cocokSaring(p) {
            if (this.cari) return true;
            return {
                menunggu: p.status === 'TERBUKA' && !!p.menunggu_sejak,
                terbuka: p.status === 'TERBUKA',
                selesai: p.status === 'SELESAI',
                semua: true,
            }[this.saring];
        },

        /** Masukkan atau perbarui satu baris dari kabar realtime. */
        pasangBaris(baris) {
            const lain = this.daftar.filter((p) => p.id !== baris.id);
            this.daftar = this.cocokSaring(baris) ? [...lain, baris].sort(urutkan) : lain;
        },

        /* ---- Percakapan ------------------------------------------------ */

        async pilih(id, catatRiwayat = true) {
            if (this.terpilih?.id === id && !this.memuatPercakapan) {
                this.infoTerbuka = false;
                return;
            }

            this.memuatPercakapan = true;
            this.galat = '';

            try {
                const data = await minta(`${awal.url.dasar}/${id}/detail`);
                this.terpilih = data.percakapan;
                this.pesan = data.pesan;
                this.isi = '';
                this.lampiran = null;
                this.infoTerbuka = false;

                const baris = this.daftar.find((p) => p.id === id);
                if (baris) baris.belum_dibaca = 0;

                if (catatRiwayat) history.pushState({}, '', `${awal.url.dasar}/${id}`);
                this.perbaruiJudul();
                this.gulir();
                this.$nextTick(() => this.$refs.isian?.focus());
            } catch (e) {
                this.tangani(e);
            } finally {
                this.memuatPercakapan = false;
            }
        },

        lepas(catatRiwayat = true) {
            this.terpilih = null;
            this.pesan = [];
            if (catatRiwayat) history.pushState({}, '', awal.url.dasar);
        },

        async ambilBaru() {
            if (!this.terpilih) return;
            const setelah = this.pesan.length ? this.pesan[this.pesan.length - 1].id : 0;
            try {
                const data = await minta(`${awal.url.dasar}/${this.terpilih.id}/pesan?setelah=${setelah}`);
                data.pesan.forEach((p) => this.tambah(p));
            } catch (e) {
                // Selain sesi habis, dicoba lagi di putaran berikutnya.
                this.tangani(e, false);
            }
        },

        tambah(p) {
            if (this.pesan.some((x) => x.id === p.id)) return;
            this.pesan.push(p);
            this.pesan.sort((a, b) => a.id - b.id);
            this.gulir();
        },

        async kirim() {
            if (this.mengirim || !this.terpilih || (!this.isi.trim() && !this.lampiran)) return;
            this.galat = '';
            this.mengirim = true;

            const data = new FormData();
            if (this.isi.trim()) data.append('isi', this.isi.trim());
            if (this.lampiran) data.append('lampiran', this.lampiran);

            try {
                const hasil = await minta(`${awal.url.dasar}/${this.terpilih.id}/balas`, { method: 'POST', body: data });
                this.tambah(hasil.pesan);
                this.pasangBaris(hasil.percakapan);
                this.terpilih = { ...this.terpilih, ...hasil.percakapan };
                this.isi = '';
                this.lampiran = null;
                if (this.$refs.berkas) this.$refs.berkas.value = '';
                this.$nextTick(() => this.ukurIsian());
            } catch (e) {
                this.tangani(e);
            } finally {
                this.mengirim = false;
            }
        },

        async tutup() {
            if (!this.terpilih) return;
            try {
                const data = await minta(`${awal.url.dasar}/${this.terpilih.id}/tutup`, { method: 'POST' });
                this.terpilih = data.percakapan;
                this.pesan = data.pesan;
                this.pasangBaris(data.percakapan);
                this.gulir();
            } catch (e) {
                this.tangani(e);
            }
        },

        async tautkan() {
            if (!this.terpilih || !this.kodeTautan.trim()) return;
            try {
                const data = new FormData();
                data.append('kode', this.kodeTautan.trim());
                const hasil = await minta(`${awal.url.dasar}/${this.terpilih.id}/tautkan`, { method: 'POST', body: data });
                this.terpilih = hasil.percakapan;
                this.kodeTautan = '';
            } catch (e) {
                this.tangani(e);
            }
        },

        /* ---- Kolom balasan dan template ---------------------------------- */

        tombol(e) {
            if (this.popover) {
                if (e.key === 'ArrowDown') { e.preventDefault(); this.sorotTemplate = Math.min(this.sorotTemplate + 1, this.templateCocok.length - 1); return; }
                if (e.key === 'ArrowUp') { e.preventDefault(); this.sorotTemplate = Math.max(this.sorotTemplate - 1, 0); return; }
                if (e.key === 'Enter') { e.preventDefault(); const t = this.templateCocok[this.sorotTemplate]; if (t) this.pakaiTemplate(t); return; }
                if (e.key === 'Escape') { e.preventDefault(); this.popover = false; return; }
            }

            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                e.preventDefault();
                this.kirim();
            }
        },

        /** Mengetik "/" di awal kolom membuka template, seperti perintah cepat. */
        saatMengetik() {
            this.ukurIsian();
            if (this.isi.startsWith('/')) {
                this.popover = true;
                this.cariTemplate = this.isi.slice(1);
                this.sorotTemplate = 0;
            } else if (this.popover && this._dariGaris) {
                this.popover = false;
            }
            this._dariGaris = this.isi.startsWith('/');
        },

        /**
         * Yang judulnya cocok di atas, baru yang isinya cocok. Tanpa urutan ini,
         * mengetik "/lama" menyorot template apa pun yang kebetulan memuat kata
         * "halaman", dan Enter memasang jawaban yang salah.
         */
        get templateCocok() {
            const q = this.cariTemplate.trim().toLowerCase();
            if (!q) return this.template;

            const diJudul = this.template.filter((t) => t.judul.toLowerCase().includes(q));
            const diIsi = this.template.filter((t) => !diJudul.includes(t) && t.isi.toLowerCase().includes(q));

            return [...diJudul, ...diIsi];
        },

        bukaTemplate() {
            this.popover = !this.popover;
            this.cariTemplate = '';
            this.sorotTemplate = 0;
            if (this.popover) this.$nextTick(() => this.$refs.cariTemplate?.focus());
        },

        pakaiTemplate(t) {
            const dasar = this.isi.startsWith('/') ? '' : this.isi.trimEnd();
            this.isi = dasar ? dasar + '\n\n' + t.isi : t.isi;
            this.popover = false;
            this._dariGaris = false;
            this.$nextTick(() => { this.ukurIsian(); this.$refs.isian?.focus(); });
        },

        ukurIsian() {
            const el = this.$refs.isian;
            if (!el) return;
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 160) + 'px';
            // Scrollbar hanya muncul setelah batas tinggi tercapai; sebelum itu
            // kolomnya sendiri yang memanjang.
            el.style.overflowY = el.scrollHeight > 160 ? 'auto' : 'hidden';
        },

        pilihLampiran(e) {
            this.lampiran = e.target.files[0] ?? null;
        },

        /* ---- Kehadiran, suara, realtime ---------------------------------- */

        async denyut() {
            try {
                await minta(awal.url.hadir, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ menerima: this.menerima }),
                });
            } catch (e) {
                // Selain sesi habis, denyut berikutnya akan mencoba lagi.
                this.tangani(e, false);
            }
        },

        gantiMenerima() {
            this.menerima = !this.menerima;
            simpan('cs.menerima', this.menerima);
            this.denyut();
        },

        gantiSuara() {
            this.suara = !this.suara;
            simpan('cs.suara', this.suara);
            if (this.suara) bunyi();
        },

        async sambungRealtime() {
            if (!awal.realtime?.key) return;
            try {
                const { sambung } = await import('./realtime.js');
                const echo = sambung(awal.realtime, awal.url.auth, (k) => { this.realtime = k; });
                echo.private('cs.staf').listen('.pesan', (e) => this.terima(e));
            } catch {
                this.realtime = 'gagal';
            }
        },

        terima({ pesan, percakapan }) {
            const sedangDibuka = this.terpilih?.id === percakapan.id;

            if (sedangDibuka) {
                this.tambah(pesan);
                this.terpilih = { ...this.terpilih, ...percakapan };

                // Pesan yang tampil di layar dianggap terbaca; server dicatat lewat ambilBaru.
                if (pesan.pengirim === 'PELANGGAN') {
                    percakapan = { ...percakapan, belum_dibaca: 0 };
                    this.ambilBaru();
                }
            }

            this.pasangBaris(percakapan);

            if (pesan.pengirim === 'PELANGGAN') {
                if (this.suara && (!sedangDibuka || document.hidden)) bunyi();
                this.muatJumlah();
            }

            this.perbaruiJudul();
        },

        async muatJumlah() {
            try {
                const q = new URLSearchParams({ saring: this.saring, q: this.cari });
                this.jumlah = (await minta(`${awal.url.daftar}?${q}`)).jumlah;
            } catch {
                // Angka di tab diperbarui lagi pada kabar berikutnya.
            }
        },

        perbaruiJudul() {
            const belum = this.daftar.filter((p) => p.belum_dibaca > 0).length;
            document.title = belum ? `(${belum}) ${this._judulAsli}` : this._judulAsli;
        },

        /* ---- Tampilan ---------------------------------------------------- */

        gulir() {
            this.$nextTick(() => {
                const el = this.$refs.daftarPesan;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        inisial(nama) {
            return (nama || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((k) => k[0]).join('').toUpperCase();
        },

        menit(iso) {
            return iso ? Math.max(0, Math.floor((this.sekarang - new Date(iso)) / 60000)) : null;
        },

        lamaMenunggu(iso) {
            const m = this.menit(iso);
            if (m === null) return '';
            if (m < 1) return 'baru saja';
            if (m < 60) return `${m} mnt`;
            const j = Math.floor(m / 60);
            return j < 24 ? `${j} jam` : `${Math.floor(j / 24)} hari`;
        },

        /** Kuning selama masih dalam batas, merah begitu melewatinya. */
        keadaanTunggu(p) {
            if (!p.menunggu_sejak || p.status !== 'TERBUKA') return 'tenang';
            return this.menit(p.menunggu_sejak) >= awal.slaMenit ? 'terlambat' : 'menunggu';
        },

        jam(iso) {
            return iso ? new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '';
        },

        waktuDaftar(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            const hari = new Date(this.sekarang);
            if (d.toDateString() === hari.toDateString()) return this.jam(iso);
            const kemarin = new Date(hari); kemarin.setDate(hari.getDate() - 1);
            if (d.toDateString() === kemarin.toDateString()) return 'Kemarin';
            return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
        },

        /** Pemisah tanggal di antara pesan, hanya saat harinya berganti. */
        pemisah(i) {
            const p = this.pesan[i];
            const sebelum = this.pesan[i - 1];
            if (!p?.waktu) return null;
            const d = new Date(p.waktu);
            if (sebelum?.waktu && new Date(sebelum.waktu).toDateString() === d.toDateString()) return null;
            const hari = new Date(this.sekarang);
            if (d.toDateString() === hari.toDateString()) return 'Hari ini';
            const kemarin = new Date(hari); kemarin.setDate(hari.getDate() - 1);
            if (d.toDateString() === kemarin.toDateString()) return 'Kemarin';
            return d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long' });
        },

        /** Nama pengirim hanya di pesan pertama dari rentetan orang yang sama. */
        awalRentetan(i) {
            const p = this.pesan[i];
            const sebelum = this.pesan[i - 1];
            return !sebelum || sebelum.pengirim !== p.pengirim || sebelum.nama !== p.nama || this.pemisah(i) !== null;
        },

        rupiah(n) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(n || 0);
        },

        salin(teks) {
            navigator.clipboard?.writeText(teks);
        },
    };
}
