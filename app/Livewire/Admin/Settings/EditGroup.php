<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Settings;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\InMemoryAdminRegistrar;
use App\Agovena\Admin\SettingsField;
use App\Agovena\Settings\SettingsRepository;
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

    public function mount(string $group, AdminRegistrar $admin): void
    {
        $this->authorize('settings.view');

        /** @var InMemoryAdminRegistrar $admin */
        $definition = $admin->settingsGroupById($group);
        abort_if($definition === null, 404);

        $this->group = $group;

        $repo = app(SettingsRepository::class);
        foreach ($admin->settingsFieldsFor($group) as $field) {
            $stored = $repo->get($field->group, $field->key, $field->default);
            $this->values[$field->key] = $this->normalizeForForm($field, $stored);
        }
    }

    public function save(AdminRegistrar $admin, SettingsRepository $settings): void
    {
        $this->authorize('settings.update');

        /** @var InMemoryAdminRegistrar $admin */
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
                }

                continue;
            }

            $value = $this->values[$field->key] ?? $field->default;
            if ($field->type === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
            $settings->set($field->group, $field->key, $value);
        }

        session()->flash('status', $definition->label.' settings saved.');
    }

    public function render(AdminRegistrar $admin)
    {
        /** @var InMemoryAdminRegistrar $admin */
        $group = $admin->settingsGroupById($this->group);
        abort_if($group === null, 404);

        return view('livewire.admin.settings.edit-group', [
            'groupDefinition' => $group,
            'fields' => $admin->settingsFieldsFor($this->group),
            'canUpdate' => auth('staff')->user()?->can('settings.update') ?? false,
        ])->layout('layouts.admin', [
            'title' => $group->label.' settings',
            'navigation' => $admin->navigationItems(),
        ]);
    }

    /** @return list<string|In> */
    private function rulesFor(SettingsField $field): array
    {
        return match ($field->type) {
            'boolean' => ['nullable', 'boolean'],
            'select' => ['required', 'string', Rule::in($field->options ?? [])],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'timezone'],
            'text' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'string', 'max:255'],
            default => ['nullable', 'string', 'max:255'],
        };
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
}
