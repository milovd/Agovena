<?php

declare(strict_types=1);

namespace App\Agovena\Content;

/**
 * Decides whether a merchant-entered menu link may be rendered as an href.
 *
 * Allowed: root-relative paths, in-page anchors, http(s) URLs with a host, mailto and tel.
 * Everything else (javascript:, data:, vbscript:, protocol-relative URLs, control
 * characters) is rejected, both when a menu item is saved and when it is rendered.
 */
final class MenuLinkTarget
{
    public static function isAllowed(string $url): bool
    {
        if ($url === '' || $url !== trim($url) || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return false;
        }

        if (preg_match('/\A#[A-Za-z0-9_-]{1,80}\z/', $url) === 1) {
            return true;
        }

        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//') && ! str_starts_with($url, '/\\');
        }

        if (preg_match('/\Amailto:[^\s@]+@[^\s@]+\z/i', $url) === 1) {
            return true;
        }

        if (preg_match('/\Atel:\+?[0-9 ().-]{3,32}\z/i', $url) === 1) {
            return true;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        return in_array($scheme, ['http', 'https'], true)
            && is_string($parts['host'] ?? null)
            && $parts['host'] !== '';
    }
}
