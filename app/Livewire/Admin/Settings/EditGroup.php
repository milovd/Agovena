<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Settings;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\SettingsField;
use App\Agovena\Mail\ApplyMailSettings;
use App\Agovena\Settings\SettingsRepository;
use App\Models\Currency;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Livewire\Component;
use Livewire\WithFileUploads;

final class EditGroup extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public string $group = '';

    /** @var array<string, mixed> */
    public array $values = [];

    /** @var array<string, mixed> */
    public array $uploads = [];

    public bool $useLogoAsFavicon = true;

    public function mount(string $group, AdminRegistrar $admin): void
    {
        $this->authorize('settings.view');

        $definition = $admin->settingsGroupById($group);
        abort_if($definition === null, 404);

        $this->group = $group;

        $repo = app(SettingsRepository::class);
        foreach ($admin->settingsFieldsFor($group) as $field) {
            $stored = $repo->get($field->group, $field->key, $field->default);
            $this->values[$field->key] = $this->normalizeForForm($field, $stored);
        }

        if ($group === 'branding') {
            $favicon = $this->values['favicon_path'] ?? null;
            $logo = $this->values['logo_path'] ?? null;
            $this->useLogoAsFavicon = blank($favicon) || $favicon === $logo;
        }
    }

    public function save(AdminRegistrar $admin, SettingsRepository $settings): void
    {
        $this->authorize('settings.update');

        $definition = $admin->settingsGroupById($this->group);
        abort_if($definition === null, 404);

        $fields = $admin->settingsFieldsFor($this->group);
        $rules = [];
        foreach ($fields as $field) {
            $rules['values.'.$field->key] = $this->rulesFor($field);
            if ($field->type === 'image') {
                $rules['uploads.'.$field->key] = ['nullable', 'image', 'max:2048'];
            }
        }

        $this->validate($rules);

        foreach ($fields as $field) {
            if ($field->type === 'image') {
                $upload = $this->uploads[$field->key] ?? null;
                if ($upload !== null) {
                    $path = $upload->store('branding', 'public');
                    $previous = $settings->get($field->group, $field->key);
                    $settings->set($field->group, $field->key, $path);
                    if (is_string($previous) && $previous !== '' && $previous !== $path) {
                        Storage::disk('public')->delete($previous);
                    }
                    $this->values[$field->key] = $path;
                    unset($this->uploads[$field->key]);

                    if ($field->key === 'logo_path' && $this->useLogoAsFavicon) {
                        $this->applyLogoPathAsFavicon($settings, $path);
                    }
                }

                continue;
            }

            $value = $this->values[$field->key] ?? $field->default;
            if ($field->type === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
            if ($field->type === 'currency') {
                $value = strtoupper((string) $value);
            }
            $settings->set($field->group, $field->key, $value);
        }

        if ($this->group === 'branding' && $this->useLogoAsFavicon && filled($this->values['logo_path'] ?? null) && empty($this->uploads['logo_path'])) {
            $this->applyLogoPathAsFavicon($settings, (string) $this->values['logo_path']);
        }

        if ($this->group === 'mail') {
            app(ApplyMailSettings::class)();
        }

        session()->flash('status', __('admin.settings.saved', ['group' => __($definition->label)]));
    }

    public function useCurrentLogoAsFavicon(SettingsRepository $settings): void
    {
        $this->authorize('settings.update');
        $logo = $settings->get('branding', 'logo_path');
        abort_if(! is_string($logo) || $logo === '', 422);
        $this->applyLogoPathAsFavicon($settings, $logo);
        $this->useLogoAsFavicon = true;
        session()->flash('status', __('admin.settings.favicon_updated'));
    }

    public function render(AdminRegistrar $admin)
    {
        $group = $admin->settingsGroupById($this->group);
        abort_if($group === null, 404);

        $currencyOptions = Currency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name', 'prefix', 'suffix']);

        return view('livewire.admin.settings.edit-group', [
            'groupDefinition' => $group,
            'fields' => $admin->settingsFieldsFor($this->group),
            'canUpdate' => auth()->user()?->can('settings.update') ?? false,
            'currencyOptions' => $currencyOptions,
        ])->layout('layouts.admin', [
            'title' => __('admin.settings.group_title', ['group' => __($group->label)]),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    /** @return list<string|In> */
    private function rulesFor(SettingsField $field): array
    {
        return match ($field->type) {
            'boolean' => ['nullable', 'boolean'],
            'select' => ['required', 'string', Rule::in($this->optionValues($field))],
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::exists('currencies', 'code')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'timezone' => ['required', 'timezone'],
            'email' => ['nullable', 'email', 'max:255'],
            'text' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'string', 'max:255'],
            default => ['nullable', 'string', 'max:255'],
        };
    }

    /**
     * @return list<string>
     */
    private function optionValues(SettingsField $field): array
    {
        $options = $field->options ?? [];
        if ($options === []) {
            return [];
        }

        if (array_is_list($options)) {
            /** @var list<string> $values */
            $values = array_map(static fn ($value): string => (string) $value, $options);

            return $values;
        }

        /** @var list<string> $keys */
        $keys = array_map(static fn ($value): string => (string) $value, array_keys($options));

        return $keys;
    }

    private function normalizeForForm(SettingsField $field, mixed $stored): mixed
    {
        if ($field->type === 'boolean') {
            if ($stored === null) {
                return (bool) $field->default;
            }

            return filter_var($stored, FILTER_VALIDATE_BOOLEAN);
        }

        if ($stored === null) {
            return $field->default;
        }

        return $stored;
    }

    private function applyLogoPathAsFavicon(SettingsRepository $settings, string $logoPath): void
    {
        $settings->set('branding', 'favicon_path', $logoPath);
        $this->values['favicon_path'] = $logoPath;
    }
}
