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

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @param  array<string, mixed>  $values
     */
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

    /**
     * @return list<array<string, mixed>>
     */
    public function sections(): array
    {
        $sections = $this->get('homepage.sections', []);
        if (! is_array($sections)) {
            return [];
        }

        $out = [];
        foreach ($sections as $section) {
            if (is_array($section) && isset($section['type']) && is_string($section['type'])) {
                $out[] = $section;
            }
        }

        return $out;
    }

    /**
     * @return list<array{text: string, short: string, emphasis: string, href: string, highlight: bool}>
     */
    public function uspItems(): array
    {
        $items = $this->get('header.usp_items', []);
        if (! is_array($items) || $items === []) {
            $legacy = $this->string('header.announcement_text', '');
            if ($legacy === '') {
                return [];
            }

            return [[
                'text' => $legacy,
                'short' => '',
                'emphasis' => '',
                'href' => $this->string('header.announcement_link', ''),
                'highlight' => false,
            ]];
        }

        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $text = isset($item['text']) ? trim((string) $item['text']) : '';
            if ($text === '') {
                continue;
            }
            $out[] = [
                'text' => $text,
                'short' => isset($item['short']) ? trim((string) $item['short']) : '',
                'emphasis' => isset($item['emphasis']) ? trim((string) $item['emphasis']) : '',
                'href' => isset($item['href']) ? trim((string) $item['href']) : '',
                'highlight' => filter_var($item['highlight'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $out;
    }
}
