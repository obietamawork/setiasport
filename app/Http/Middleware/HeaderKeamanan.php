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
 * sendiri (Alpine butuh 'unsafe-eval' dan tata letak memakai skrip sebaris),
 * huruf dari Google Fonts, gambar boleh dari CDN mana pun karena gambar
 * produk bisa berupa tautan luar, dan WebSocket untuk chat CS. Yang ditutup:
 * plugin (object), penggantian <base>, formulir ke domain lain, dan halaman
 * ini dibungkus situs lain (clickjacking).
 */
class HeaderKeamanan
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $h->set('Content-Security-Policy', $this->kebijakanKonten());

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

    private function kebijakanKonten(): string
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
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'".$vite,
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com".$vite,
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "connect-src 'self' https: wss: ws:".$vite,
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }
}
