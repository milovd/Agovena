<?php

declare(strict_types=1);

namespace App\Agovena\Theme;

use App\Agovena\Settings\SettingsRepository;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class ThemeManager
{
    /** @var array<string, Theme>|null */
    private ?array $themes = null;

    private ?Theme $active = null;

    public function __construct(private readonly SettingsRepository $settings) {}

    public function active(): Theme
    {
        if ($this->active !== null) {
            return $this->active;
        }

        $id = 'default';
        try {
            $id = (string) $this->settings->get('appearance', 'active_theme', 'default');
        } catch (\Throwable) {
            $id = 'default';
        }

        $themes = $this->all();

        if (! isset($themes[$id])) {
            $id = isset($themes['default']) ? 'default' : array_key_first($themes);
        }

        if ($id === null || ! isset($themes[$id])) {
            throw new RuntimeException('No Themes are installed under themes/.');
        }

        return $this->active = $themes[$id];
    }

    public function activate(string $id): void
    {
        $themes = $this->all();
        if (! isset($themes[$id])) {
            throw new RuntimeException("Theme [{$id}] is not installed.");
        }

        $this->settings->set('appearance', 'active_theme', $id);
        $this->active = $themes[$id];
    }

    /** @return array<string, Theme> */
    public function all(): array
    {
        if ($this->themes !== null) {
            return $this->themes;
        }

        $this->themes = [];
        $root = base_path('themes');

        if (! is_dir($root)) {
            return $this->themes;
        }

        foreach (File::directories($root) as $dir) {
            $theme = $this->loadTheme($dir);
            if ($theme !== null) {
                $this->themes[$theme->id] = $theme;
            }
        }

        ksort($this->themes);

        return $this->themes;
    }

    public function find(string $id): ?Theme
    {
        return $this->all()[$id] ?? null;
    }

    public function errorTheme(int $status): ?Theme
    {
        $candidates = [];

        try {
            $candidates[] = $this->active();
        } catch (\Throwable) {
            // The error renderer must remain available when theme settings are unavailable.
        }

        $default = $this->find('default');
        if ($default !== null) {
            $candidates[] = $default;
        }

        $seen = [];
        foreach ($candidates as $theme) {
            if (isset($seen[$theme->id])) {
                continue;
            }
            $seen[$theme->id] = true;

            if ($theme->hasErrorPage($status)) {
                return $theme;
            }
        }

        return null;
    }

    public function themeFor(ThemeSurface $surface): Theme
    {
        $active = $this->active();
        if ($active->provides($surface)) {
            return $active;
        }

        $default = $this->find('default');
        if ($default !== null && $default->provides($surface)) {
            return $default;
        }

        return $active;
    }

    public function config(?Theme $theme = null): ThemeConfig
    {
        $theme ??= $this->active();

        return new ThemeConfig($theme, $this->schemaFor($theme), $this->settings);
    }

    public function schemaFor(Theme $theme): ThemeSettingsSchema
    {
        $path = $theme->basePath.DIRECTORY_SEPARATOR.'settings.schema.php';
        if (! is_file($path)) {
            return new ThemeSettingsSchema([]);
        }

        /** @var mixed $result */
        $result = require $path;
        if ($result instanceof ThemeSettingsSchema) {
            return $result;
        }

        if (is_array($result)) {
            $fields = [];
            foreach ($result as $item) {
                if ($item instanceof ThemeSettingField) {
                    $fields[] = $item;
                }
            }

            return new ThemeSettingsSchema($fields);
        }

        return new ThemeSettingsSchema([]);
    }

    private function loadTheme(string $dir): ?Theme
    {
        $manifestPath = $dir.DIRECTORY_SEPARATOR.'theme.json';
        $id = basename($dir);

        if (! is_file($manifestPath)) {
            // Legacy folder without manifest - only allow "default".
            if ($id !== 'default') {
                return null;
            }

            $manifest = new ThemeManifest(
                id: 'default',
                name: 'Default',
                version: '1.0.0',
                cssEntry: 'themes/default/resources/css/theme.css',
                description: 'Official Agovena storefront Theme.',
                capabilities: ['storefront', 'admin', 'homepage-sections'],
                adminCssEntry: 'themes/default/resources/css/admin.css',
            );
        } else {
            /** @var mixed $json */
            $json = json_decode((string) file_get_contents($manifestPath), true);
            if (! is_array($json)) {
                return null;
            }
            $manifest = ThemeManifest::fromArray($json, $id);
        }

        return new Theme(
            id: $manifest->id,
            name: $manifest->name,
            viewsPath: $dir.DIRECTORY_SEPARATOR.'views',
            cssEntry: $manifest->cssEntry,
            version: $manifest->version,
            description: $manifest->description,
            previewPath: $manifest->preview !== null
                ? $dir.DIRECTORY_SEPARATOR.ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $manifest->preview), DIRECTORY_SEPARATOR)
                : null,
            capabilities: $manifest->capabilities,
            basePath: $dir,
            adminCssEntry: $manifest->adminCssEntry,
        );
    }
}
