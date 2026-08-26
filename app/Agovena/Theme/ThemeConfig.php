<?php

declare(strict_types=1);

namespace App\Agovena\Theme;

use App\Agovena\Settings\SettingsRepository;

/**
 * Merges Theme schema defaults with persisted overrides (settings group theme.{id}).
 */
final class ThemeConfig
{
    public function __construct(
        private readonly Theme $theme,
        private readonly ThemeSettingsSchema $schema,
        private readonly SettingsRepository $settings,
    ) {}

    public function theme(): Theme
    {
        return $this->theme;
    }

    public function schema(): ThemeSettingsSchema
    {
        return $this->schema;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $stored = $this->settings->get($this->theme->settingsGroup(), $key, null);
        if ($stored !== null) {
            return $stored;
        }

        $field = $this->schema->field($key);
        if ($field !== null) {
            return $field->default;
        }

        return $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $values = $this->schema->defaults();
        $stored = $this->settings->allInGroup($this->theme->settingsGroup());

        foreach ($stored as $key => $value) {
            $values[$key] = $value;
        }

        return $values;
    }

    public function set(string $key, mixed $value): void
    {
        $this->settings->set($this->theme->settingsGroup(), $key, $value);
    }

    /** @param array<string, mixed> $values */
    public function setMany(array $values): void
    {
        $this->settings->setMany($this->theme->settingsGroup(), $values);
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_string($value) ? $value : $default;
    }

    /** @return list<array<string, mixed>> */
    public function sections(): array
    {
        $sections = $this->get('homepage.sections', []);

        return is_array($sections) ? $this->normalizeSections($sections) : [];
    }

    /** @param list<mixed> $sections @return list<array<string, mixed>> */
    public function normalizeSections(array $sections): array
    {
        $allowed = ['hero', 'featured_products', 'featured_categories', 'trust_strip', 'promo_split', 'rich_text'];
        $out = [];

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            $type = (string) ($section['type'] ?? '');
            if (! in_array($type, $allowed, true)) {
                continue;
            }

            $base = ['type' => $type];
            if ($type === 'hero') {
                $out[] = $base + [
                    'eyebrow' => $this->cleanText($section['eyebrow'] ?? '', 160),
                    'title' => $this->cleanText($section['title'] ?? '', 240),
                    'lede' => $this->cleanText($section['lede'] ?? '', 500),
                    'cta_label' => $this->cleanText($section['cta_label'] ?? '', 120),
                    'cta_href' => $this->safeHref($section['cta_href'] ?? ''),
                    'image' => $this->safeMediaPath($section['image'] ?? ''),
                ];

                continue;
            }
            if ($type === 'featured_products') {
                $out[] = $base + [
                    'title' => $this->cleanText($section['title'] ?? '', 240),
                    'lede' => $this->cleanText($section['lede'] ?? '', 500),
                    'limit' => max(1, min(24, (int) ($section['limit'] ?? 8))),
                ];

                continue;
            }
            if ($type === 'featured_categories') {
                $out[] = $base + [
                    'title' => $this->cleanText($section['title'] ?? '', 240),
                    'lede' => $this->cleanText($section['lede'] ?? '', 500),
                ];

                continue;
            }
            if ($type === 'trust_strip') {
                $items = [];
                foreach (is_array($section['items'] ?? null) ? $section['items'] : [] as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $title = $this->cleanText($item['title'] ?? '', 160);
                    $text = $this->cleanText($item['text'] ?? '', 300);
                    if ($title !== '' || $text !== '') {
                        $items[] = ['title' => $title, 'text' => $text];
                    }
                }
                $out[] = $base + ['items' => array_slice($items, 0, 6)];

                continue;
            }
            if ($type === 'promo_split') {
                $out[] = $base + [
                    'title' => $this->cleanText($section['title'] ?? '', 240),
                    'body' => $this->cleanText($section['body'] ?? '', 5000),
                    'cta_label' => $this->cleanText($section['cta_label'] ?? '', 120),
                    'cta_href' => $this->safeHref($section['cta_href'] ?? ''),
                    'image' => $this->safeMediaPath($section['image'] ?? ''),
                ];

                continue;
            }

            $out[] = $base + [
                'title' => $this->cleanText($section['title'] ?? '', 240),
                'body' => $this->cleanText($section['body'] ?? '', 10000),
            ];
        }

        return $out;
    }

    /** @return list<array{text: string, short: string, emphasis: string, href: string, highlight: bool}> */
    public function uspItems(): array
    {
        $items = $this->get('header.usp_items', []);
        if (! is_array($items) || $items === []) {
            $legacy = $this->string('header.announcement_text', '');
            if ($legacy === '') {
                return [];
            }

            return [[
                'text' => $this->cleanText($legacy, 240),
                'short' => '',
                'emphasis' => '',
                'href' => $this->safeHref($this->string('header.announcement_link', '')),
                'highlight' => false,
            ]];
        }

        return $this->normalizeUspItems($items);
    }

    /** @param list<mixed> $items @return list<array{text: string, short: string, emphasis: string, href: string, highlight: bool}> */
    public function normalizeUspItems(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $text = $this->cleanText($item['text'] ?? '', 240);
            if ($text === '') {
                continue;
            }
            $out[] = [
                'text' => $text,
                'short' => $this->cleanText($item['short'] ?? '', 120),
                'emphasis' => $this->cleanText($item['emphasis'] ?? '', 120),
                'href' => $this->safeHref($item['href'] ?? ''),
                'highlight' => filter_var($item['highlight'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return array_slice($out, 0, 8);
    }

    private function cleanText(mixed $value, int $limit): string
    {
        $text = trim(strip_tags((string) $value));

        return mb_substr($text, 0, $limit);
    }

    private function safeHref(mixed $value): string
    {
        $href = trim((string) $value);
        if ($href === '') {
            return '';
        }
        if (preg_match('/\A#[a-zA-Z0-9_-]{1,80}\z/', $href)) {
            return $href;
        }
        if (str_starts_with($href, '/') && ! str_starts_with($href, '//') && ! str_contains($href, "\r") && ! str_contains($href, "\n")) {
            return $href;
        }
        $parts = parse_url($href);
        if (($parts['scheme'] ?? '') === 'https' && is_string($parts['host'] ?? null) && $parts['host'] !== '') {
            return $href;
        }

        return '';
    }

    private function safeMediaPath(mixed $value): string
    {
        $path = trim((string) $value);
        if ($path === '' || str_contains($path, '..') || str_contains($path, '\\') || ! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._\/-]{0,255}\z/', $path)) {
            return '';
        }

        return $path;
    }
}
