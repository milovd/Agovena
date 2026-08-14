<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! (bool) config('agovena.security.headers.enabled', true)) {
            return $response;
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', (string) config('agovena.security.headers.frame', 'DENY'));
        $response->headers->set('Referrer-Policy', (string) config('agovena.security.headers.referrer', 'strict-origin-when-cross-origin'));
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('X-DNS-Prefetch-Control', 'off');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));

        if ((bool) config('agovena.security.headers.hsts', true) && $request->isSecure()) {
            $maxAge = max(0, (int) config('agovena.security.headers.hsts_max_age', 31536000));
            $response->headers->set('Strict-Transport-Security', 'max-age='.$maxAge.'; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(Request $request): string
    {
        $override = config('agovena.security.headers.csp');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        $script = "'self' 'unsafe-inline' 'unsafe-eval'";
        $style = "'self' 'unsafe-inline' https://fonts.googleapis.com";
        $font = "'self' https://fonts.gstatic.com data:";
        $img = "'self' data: blob: https:";
        $connect = "'self'";

        if (app()->environment('local') && (bool) config('app.debug')) {
            $vite = $this->viteDevOrigins();
            $script .= ' '.$vite;
            $style .= ' '.$vite;
            $connect .= ' '.$vite;
        }

        $directives = [
            "default-src 'self'",
            'script-src '.$script,
            'style-src '.$style,
            'font-src '.$font,
            'img-src '.$img,
            'connect-src '.$connect,
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
        ];

        if ($request->isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }

    private function viteDevOrigins(): string
    {
        $origins = [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            'http://[::1]:5173',
            'ws://localhost:5173',
            'ws://127.0.0.1:5173',
            'ws://[::1]:5173',
        ];

        $hot = public_path('hot');
        if (is_file($hot)) {
            $url = trim((string) file_get_contents($hot));
            if ($url !== '' && preg_match('#^https?://#i', $url) === 1) {
                $origins[] = $url;
                $origins[] = (string) preg_replace('#^http#i', 'ws', $url);
            }
        }

        return implode(' ', array_unique($origins));
    }
}
