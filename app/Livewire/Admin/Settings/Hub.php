<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Settings;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\SettingsField;
use App\Agovena\Admin\SettingsGroup;
use App\Agovena\Mail\ApplyMailSettings;
use App\Agovena\Settings\SettingsRepository;
use App\Models\Currency;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

final class Hub extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    #[Url(as: 'tab')]
    public string $tab = '';

    /** @var array<string, mixed> */
    public array $values = [];

    /** @var array<string, mixed> */
    public array $uploads = [];

    public bool $useLogoAsFavicon = true;

    public function mount(AdminRegistrar $admin): void
    {
        $this->authorize('settings.view');

        $groups = $this->accessibleGroups($admin);
        if ($groups->isEmpty()) {
            return;
        }

        if ($this->tab === '' || $groups->firstWhere('id', $this->tab) === null) {
            $this->tab = (string) $groups->first()->id;
        }

        $this->loadActiveGroup($admin);
    }

    public function updatedTab(): void
    {
        $admin = app(AdminRegistrar::class);
        $groups = $this->accessibleGroups($admin);
        if ($groups->firstWhere('id', $this->tab) === null) {
            $this->tab = $groups->isEmpty() ? '' : (string) $groups->first()->id;
        }

        $this->resetValidation();
        $this->uploads = [];
        $this->loadActiveGroup($admin);
    }

    public function save(AdminRegistrar $admin, SettingsRepository $settings): void
    {
        $this->authorize('settings.update');

        $definition = $admin->settingsGroupById($this->tab);
        abort_if($definition === null, 404);
        abort_if($definition->href !== null, 404);

        $fields = $admin->settingsFieldsFor($this->tab);
        $rules = [];
        foreach ($fields as $field) {
            $rules['values.'.$field->key] = $this->rulesFor($field);
            if ($field->type === 'image') {
                $rules['uploads.'.$field->key] = ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,gif', 'max:2048'];
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
            if ($field->type === 'integer') {
                $value = (int) $value;
            }
            if ($field->type === 'currency') {
                $value = strtoupper((string) $value);
            }
            $settings->set($field->group, $field->key, $value);
        }

        if ($this->tab === 'branding' && $this->useLogoAsFavicon && filled($this->values['logo_path'] ?? null) && empty($this->uploads['logo_path'])) {
            $this->applyLogoPathAsFavicon($settings, (string) $this->values['logo_path']);
        }

        if ($this->tab === 'mail') {
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
        $groups = $this->accessibleGroups($admin);
        $active = $groups->firstWhere('id', $this->tab);

        $currencyOptions = Currency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name', 'prefix', 'suffix']);

        $tabs = $groups
            ->filter(fn (SettingsGroup $group): bool => $group->href === null)
            ->mapWithKeys(fn (SettingsGroup $group): array => [$group->id => __($group->label)])
            ->all();

        $externalGroups = $groups
            ->filter(fn (SettingsGroup $group): bool => $group->href !== null)
            ->values();

        return view('livewire.admin.settings.hub', [
            'groups' => $groups,
            'tabs' => $tabs,
            'externalGroups' => $externalGroups,
            'groupDefinition' => $active,
            'fields' => $active !== null && $active->href === null
                ? $admin->settingsFieldsFor($this->tab)
                : [],
            'canUpdate' => auth()->user()?->can('settings.update') ?? false,
            'currencyOptions' => $currencyOptions,
        ])->layout('layouts.admin', [
            'title' => $active !== null
                ? __('admin.settings.group_title', ['group' => __($active->label)])
                : __('admin.settings.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    /**
     * @return Collection<int, SettingsGroup>
     */
    private function accessibleGroups(AdminRegistrar $admin): Collection
    {
        $staff = auth()->user();

        return collect($admin->settingsGroups())
            ->filter(function (SettingsGroup $group) use ($staff): bool {
                return $group->permission === null
                    || ($staff !== null && $staff->can($group->permission));
            })
            ->values();
    }

    private function loadActiveGroup(AdminRegistrar $admin): void
    {
        if ($this->tab === '') {
            $this->values = [];

            return;
        }

        $definition = $admin->settingsGroupById($this->tab);
        if ($definition === null || $definition->href !== null) {
            $this->values = [];

            return;
        }

        $repo = app(SettingsRepository::class);
        $this->values = [];
        foreach ($admin->settingsFieldsFor($this->tab) as $field) {
            $stored = $repo->get($field->group, $field->key, $field->default);
            $this->values[$field->key] = $this->normalizeForForm($field, $stored);
        }

        if ($this->tab === 'branding') {
            $favicon = $this->values['favicon_path'] ?? null;
            $logo = $this->values['logo_path'] ?? null;
            $this->useLogoAsFavicon = blank($favicon) || $favicon === $logo;
        }
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
            'integer' => ['required', 'integer', 'min:0', 'max:365'],
            'percentage' => ['required', 'integer', 'min:0', 'max:100'],
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

        if (in_array($field->type, ['integer', 'percentage'], true)) {
            if ($stored === null) {
                return (int) $field->default;
            }

            return (int) $stored;
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
