<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Appearance;

use App\Agovena\Theme\ThemeManager;
use Illuminate\Support\Arr;
use Livewire\Component;

final class Customize extends Component
{
    /** @var array<string, mixed> */
    public array $values = [];

    /** @var list<array<string, mixed>> */
    public array $sections = [];

    public function mount(ThemeManager $themes): void
    {
        $this->authorize('theme.manage');
        $config = $themes->config();
        $flat = $config->all();
        $sections = $flat['homepage.sections'] ?? [];
        unset($flat['homepage.sections']);
        $this->sections = is_array($sections) ? array_values($sections) : [];
        $this->values = Arr::undot($flat);
    }

    public function save(ThemeManager $themes): void
    {
        $this->authorize('theme.manage');
        $schema = $themes->schemaFor($themes->active());
        $flat = Arr::dot($this->values);

        foreach ($schema->fields as $field) {
            if ($field->key === 'homepage.sections') {
                continue;
            }
            if ($field->type === 'boolean') {
                $flat[$field->key] = filter_var($flat[$field->key] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
        }

        $flat['homepage.sections'] = $this->sections;
        $themes->config()->setMany($flat);

        session()->flash('status', 'Theme settings saved.');
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
                'eyebrow' => 'Welcome',
                'title' => 'New hero',
                'lede' => '',
                'cta_label' => 'Browse',
                'cta_href' => '#catalog',
            ],
            'featured_products' => [
                'type' => 'featured_products',
                'title' => 'Featured products',
                'lede' => '',
                'limit' => 8,
            ],
            'featured_categories' => [
                'type' => 'featured_categories',
                'title' => 'Shop by category',
                'lede' => '',
            ],
            'trust_strip' => [
                'type' => 'trust_strip',
                'items' => [
                    ['title' => 'Benefit', 'text' => 'Describe a trust signal.'],
                ],
            ],
            'promo_split' => [
                'type' => 'promo_split',
                'title' => 'Promo title',
                'body' => '',
                'cta_label' => 'Learn more',
                'cta_href' => '#catalog',
                'image' => '',
            ],
            default => [
                'type' => 'rich_text',
                'title' => 'Section title',
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
        unset($groups['homepage']);

        return view('livewire.admin.appearance.customize', [
            'theme' => $theme,
            'groups' => $groups,
        ])->layout('layouts.admin', [
            'title' => 'Customize theme',
        ]);
    }
}
