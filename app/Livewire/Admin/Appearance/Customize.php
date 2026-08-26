<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Appearance;

use App\Agovena\Theme\ThemeManager;
use Illuminate\Support\Arr;
use Livewire\Component;

final class Customize extends Component
{
    public string $tab = 'design';

    /** @var array<string, mixed> */
    public array $values = [];

    /** @var list<array<string, mixed>> */
    public array $sections = [];

    /** @var list<array{text: string, short: string, emphasis: string, href: string, highlight: bool}> */
    public array $uspItems = [];

    public function mount(ThemeManager $themes): void
    {
        $this->authorize('theme.manage');
        $config = $themes->config();
        $flat = $config->all();
        $sections = $flat['homepage.sections'] ?? [];
        unset($flat['homepage.sections']);
        $usp = $flat['header.usp_items'] ?? null;
        unset($flat['header.usp_items'], $flat['header.announcement_text'], $flat['header.announcement_link']);
        $this->sections = is_array($sections) ? array_values($sections) : [];
        $this->uspItems = $this->normalizeUspItems(
            (is_array($usp) && $usp !== []) ? $usp : $config->uspItems()
        );
        $this->values = Arr::undot($flat);
    }

    public function save(ThemeManager $themes): void
    {
        $this->authorize('theme.manage');
        $schema = $themes->schemaFor($themes->active());
        $flat = Arr::dot($this->values);

        $this->validate([
            'values.appearance.default_color_mode' => ['required', 'in:system,light,dark'],
            'values.colors.*' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        foreach ($schema->fields as $field) {
            if (in_array($field->key, ['homepage.sections', 'header.usp_items'], true)) {
                continue;
            }
            if ($field->type === 'boolean') {
                $flat[$field->key] = filter_var($flat[$field->key] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
        }

        $flat['homepage.sections'] = $this->sections;
        $flat['header.usp_items'] = $this->normalizeUspItems($this->uspItems);
        $themes->config()->setMany($flat);

        session()->flash('status', __('admin.appearance.customize.saved'));
    }

    public function addUspItem(): void
    {
        $this->uspItems[] = [
            'text' => __('admin.appearance.defaults.usp_text'),
            'short' => '',
            'emphasis' => '',
            'href' => '',
            'highlight' => false,
        ];
    }

    public function removeUspItem(int $index): void
    {
        array_splice($this->uspItems, $index, 1);
    }

    public function moveUspItem(int $index, string $direction): void
    {
        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if (! isset($this->uspItems[$index], $this->uspItems[$swap])) {
            return;
        }
        [$this->uspItems[$index], $this->uspItems[$swap]] = [$this->uspItems[$swap], $this->uspItems[$index]];
    }

    public function moveSection(int $index, string $direction): void
    {
        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if (! isset($this->sections[$index], $this->sections[$swap])) {
            return;
        }
        [$this->sections[$index], $this->sections[$swap]] = [$this->sections[$swap], $this->sections[$index]];
    }

    public function removeSection(int $index): void
    {
        array_splice($this->sections, $index, 1);
    }

    public function addSection(string $type): void
    {
        $this->sections[] = match ($type) {
            'hero' => [
                'type' => 'hero',
                'eyebrow' => __('admin.appearance.defaults.hero_eyebrow'),
                'title' => __('admin.appearance.defaults.hero_title'),
                'lede' => '',
                'cta_label' => __('admin.appearance.defaults.hero_cta_label'),
                'cta_href' => '#catalog',
            ],
            'featured_products' => [
                'type' => 'featured_products',
                'title' => __('admin.appearance.defaults.featured_products_title'),
                'lede' => '',
                'limit' => 8,
            ],
            'featured_categories' => [
                'type' => 'featured_categories',
                'title' => __('admin.appearance.defaults.featured_categories_title'),
                'lede' => '',
            ],
            'trust_strip' => [
                'type' => 'trust_strip',
                'items' => [
                    [
                        'title' => __('admin.appearance.defaults.trust_item_title'),
                        'text' => __('admin.appearance.defaults.trust_item_text'),
                    ],
                ],
            ],
            'promo_split' => [
                'type' => 'promo_split',
                'title' => __('admin.appearance.defaults.promo_title'),
                'body' => '',
                'cta_label' => __('admin.appearance.defaults.promo_cta_label'),
                'cta_href' => '#catalog',
                'image' => '',
            ],
            default => [
                'type' => 'rich_text',
                'title' => __('admin.appearance.defaults.rich_text_title'),
                'body' => '',
            ],
        };
    }

    public function render(ThemeManager $themes)
    {
        $this->authorize('theme.view');
        $theme = $themes->active();
        $schema = $themes->schemaFor($theme);
        $groups = $schema->grouped();

        return view('livewire.admin.appearance.customize', [
            'theme' => $theme,
            'groups' => $groups,
            'tabs' => [
                'design' => __('admin.appearance.customize.tabs.design'),
                'header' => __('admin.appearance.customize.tabs.header'),
                'storefront' => __('admin.appearance.customize.tabs.storefront'),
                'homepage' => __('admin.appearance.customize.tabs.homepage'),
            ],
        ])->layout('layouts.admin', [
            'title' => __('admin.appearance.customize.title'),
        ]);
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array{text: string, short: string, emphasis: string, href: string, highlight: bool}>
     */
    private function normalizeUspItems(array $items): array
    {
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
