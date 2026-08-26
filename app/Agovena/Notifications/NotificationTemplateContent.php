<?php

declare(strict_types=1);

namespace App\Agovena\Notifications;

final class NotificationTemplateContent
{
    /**
     * @param  array<string, scalar|null>  $vars
     */
    public function render(?string $template, string $fallback, array $vars): string
    {
        if (! filled($template)) {
            return $fallback;
        }

        $safe = [];
        foreach ($vars as $key => $value) {
            $safe[$key] = is_scalar($value) ? trim(str_replace(["\r", "\n"], ' ', (string) $value)) : '';
        }

        $stripped = (string) preg_replace('/@php\b.*?@endphp/s', '', $template);
        $stripped = (string) preg_replace('/\{!!.*?!!\}/s', '', $stripped);
        $rendered = (string) preg_replace_callback(
            '/\{\{.*?\}\}/s',
            static function (array $match) use ($safe): string {
                if (preg_match('/^\{\{\s*([a-z0-9_]+)\s*\}\}$/i', $match[0], $inner) !== 1) {
                    return '';
                }

                return $safe[strtolower($inner[1])] ?? '';
            },
            $stripped,
        );

        return trim(strip_tags((string) preg_replace('/@\w+/', '', $rendered))) ?: $fallback;
    }
}
