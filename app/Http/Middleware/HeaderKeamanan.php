<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan HTTP untuk setiap respons (KEP-086).
 *
 * Kebijakan konten (CSP) di sini sengaja disusun dari apa yang BENAR-BENAR
 * dimuat aplikasi ini, bukan dari daftar contoh: skrip hanya dari domain
 * sendiri, gambar boleh dari CDN mana pun karena gambar produk bisa berupa
 * tautan luar, dan WebSocket untuk chat CS. Yang ditutup: plugin (object),
 * penggantian <base>, formulir ke domain lain, dan halaman ini dibungkus
 * situs lain (clickjacking).
 *
 * Skrip sebaris hanya berjalan kalau membawa nonce permintaan ini (KEP-088):
 * <script> yang berhasil disisipkan penyerang tidak ikut dijalankan. Karena
 * itu tidak ada lagi onclick/onsubmit sebaris di tampilan. 'unsafe-eval'
 * tetap ada karena Alpine mengevaluasi ekspresi x-data/@click; huruf kini
 * di-host sendiri, jadi Google Fonts tidak lagi diizinkan.
 */
class HeaderKeamanan
{
    public function handle(Request $request, Closure $next): Response
    {
        // Dibuat sebelum tampilan dirender: @vite dan <script nonce> membacanya.
        $nonce = Vite::useCspNonce();

        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        // Halaman galat bawaan Laravel saat APP_DEBUG=true memakai skrip
        // sebaris tanpa nonce; tanpa pengecualian ini jejak galat di lokal
        // tidak bisa dibaca. Di produksi APP_DEBUG wajib false.
        if (! (config('app.debug') && $response->isServerError())) {
            $h->set('Content-Security-Policy', $this->kebijakanKonten($nonce));
        }

        // HSTS hanya lewat HTTPS. Tanpa includeSubDomains: subdomain bawaan
        // cPanel (mail, webmail) belum tentu semuanya HTTPS.
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // Versi PHP tidak perlu diumumkan ke siapa pun.
        $h->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }

    private function kebijakanKonten(string $nonce): string
    {
        // Saat `npm run dev`, aset datang dari server Vite dan HMR lewat WebSocket.
        $vite = '';
        if (Vite::isRunningHot()) {
            $asal = rtrim((string) @file_get_contents(public_path('hot')));
            $ws = preg_replace('/^http/', 'ws', $asal);
            $vite = ' '.$asal.' '.$ws;
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'".$vite,
            "style-src 'self' 'unsafe-inline'".$vite,
            "font-src 'self' data:",
            "img-src 'self' data: blob: https:",
            "connect-src 'self' https: wss: ws:".$vite,
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }
}
