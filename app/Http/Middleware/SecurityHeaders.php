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
        $paymentPolicy = $request->routeIs('paddle.checkout')
            ? 'payment=(self "https://buy.paddle.com" "https://sandbox-buy.paddle.com")'
            : 'payment=()';
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), '.$paymentPolicy);
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

        $script = "'self' 'unsafe-inline'";
        $style = "'self' 'unsafe-inline' https://fonts.googleapis.com";
        $font = "'self' https://fonts.gstatic.com data:";
        $img = "'self' data: blob: https:";
        $connect = "'self'";
        $paddleCheckout = $request->routeIs('paddle.checkout');

        if ($paddleCheckout) {
            $script .= ' https://cdn.paddle.com';
            $connect .= ' https://*.paddle.com';
        }

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

        if ($paddleCheckout) {
            $directives[] = 'frame-src https://*.paddle.com';
        }

        if ($request->isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }

    private function viteDevOrigins(): string
    {
        // CSP host-source grammar has no IPv6 literals, so "[::1]" is left out: browsers reject it.
        $origins = [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            'ws://localhost:5173',
            'ws://127.0.0.1:5173',
        ];

        $hot = public_path('hot');
        if (is_file($hot)) {
            $origin = $this->cspOrigin(trim((string) file_get_contents($hot)));
            if ($origin !== null) {
                $origins[] = $origin;
                $origins[] = (string) preg_replace('#^http#i', 'ws', $origin);
            }
        }

        return implode(' ', array_unique($origins));
    }

    /** Reduces a dev server URL to a CSP-valid "scheme://host[:port]", or null when it cannot be one. */
    private function cspOrigin(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || preg_match('/\A[A-Za-z0-9.-]+\z/', $host) !== 1) {
            return null;
        }

        return $scheme.'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
