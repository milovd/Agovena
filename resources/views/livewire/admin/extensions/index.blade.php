<div class="admin-page">
    <x-ag.page-header :heading="__('admin.extensions.title')" :lede="__('admin.extensions.lede')" />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    <div class="ag-toolbar" style="margin-bottom: 1rem;">
        <select class="ag-select" wire:model.live="category" aria-label="{{ __('admin.extensions.filter_category') }}">
            <option value="">{{ __('admin.extensions.all_categories') }}</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->value }}">{{ __($cat->labelKey()) }}</option>
            @endforeach
        </select>
    </div>

    @if ($extensions === [])
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.extensions.empty.title') }}</p>
            <p class="ag-empty__text">{{ __('admin.extensions.empty.text') }}</p>
        </div>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('admin.extensions.column_name') }}</th>
                        <th scope="col">{{ __('admin.extensions.column_category') }}</th>
                        <th scope="col">{{ __('admin.extensions.column_version') }}</th>
                        <th scope="col">{{ __('admin.extensions.column_author') }}</th>
                        <th scope="col">{{ __('admin.extensions.column_compatibility') }}</th>
                        <th scope="col">{{ __('common.status') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($extensions as $row)
                        @php
                            /** @var \App\Agovena\Extensions\ExtensionManifest $manifest */
                            $manifest = $row['manifest'];
                        @endphp
                        <tr wire:key="extension-{{ $manifest->id }}">
                            <td>
                                <div class="ag-table__primary">
                                    <span class="ag-table__name">{{ $manifest->name }}</span>
                                    <span class="ag-muted">{{ $manifest->id }}</span>
                                </div>
                                <p class="ag-muted">{{ $manifest->description }}</p>
                            </td>
                            <td>{{ __($manifest->category->labelKey()) }}</td>
                            <td>{{ $manifest->version }}</td>
                            <td>{{ $manifest->author }}</td>
                            <td>
                                @if ($row['compatible'])
                                    {{ $manifest->agovena }}
                                @else
                                    <span class="ag-field__error">{{ $row['compatibility_error'] }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($row['enabled'])
                                    {{ __('admin.extensions.status.enabled') }}
                                @elseif ($row['installed'])
                                    {{ __('admin.extensions.status.installed') }}
                                @else
                                    {{ __('admin.extensions.status.available') }}
                                @endif
                            </td>
                            <td>
                                <div class="ag-table__actions">
                                    @can('extensions.manage')
                                        @if (! $row['installed'])
                                            <button type="button" class="ag-btn ag-btn--ghost" wire:click="install('{{ $manifest->id }}')" @disabled(! $row['compatible'])>
                                                {{ __('admin.extensions.actions.install') }}
                                            </button>
                                        @endif
                                        @if ($row['enabled'])
                                            <button type="button" class="ag-btn ag-btn--ghost" wire:click="disable('{{ $manifest->id }}')">
                                                {{ __('admin.extensions.actions.disable') }}
                                            </button>
                                            <button type="button" class="ag-btn ag-btn--ghost" wire:click="openSettings('{{ $manifest->id }}')">
                                                {{ __('admin.extensions.actions.settings') }}
                                            </button>
                                            <button type="button" class="ag-btn ag-btn--ghost" wire:click="runHealth('{{ $manifest->id }}')">
                                                {{ __('admin.extensions.actions.health') }}
                                            </button>
                                        @elseif ($row['compatible'])
                                            <button type="button" class="ag-btn ag-btn--secondary" wire:click="enable('{{ $manifest->id }}')">
                                                {{ __('admin.extensions.actions.enable') }}
                                            </button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="ag-muted">{{ __('admin.extensions.disable_preserves_data') }}</p>
    @endif

    @if ($settingsExtensionId)
        <div class="ag-modal" role="dialog" aria-modal="true">
            <div class="ag-modal__backdrop" wire:click="closeSettings"></div>
            <div class="ag-modal__panel">
                <h3 class="ag-modal__title">{{ __('admin.extensions.settings_title', ['extension' => $settingsExtensionId]) }}</h3>
                <form wire:submit="saveSettings" class="ag-stack">
                    @php
                        $settingsManifest = collect($extensions)->first(
                            fn ($row) => $row['manifest']->id === $settingsExtensionId
                        )['manifest'] ?? null;
                    @endphp
                    @foreach ($settingsForm as $key => $value)
                        @php
                            $settingLabel = $key;
                            if ($settingsManifest !== null) {
                                foreach ($settingsManifest->settings as $definition) {
                                    if ($definition['key'] === $key) {
                                        $settingLabel = $definition['label'];
                                        break;
                                    }
                                }
                            }
                        @endphp
                        <div class="ag-field">
                            <label class="ag-field__label" for="ext-setting-{{ $key }}">{{ __($settingLabel) }}</label>
                            <input id="ext-setting-{{ $key }}" class="ag-input" type="{{ str_contains($key, 'secret') ? 'password' : 'text' }}" wire:model="settingsForm.{{ $key }}" autocomplete="off">
                        </div>
                    @endforeach
                    <div class="ag-modal__actions">
                        <button type="button" class="ag-btn ag-btn--ghost" wire:click="closeSettings">{{ __('common.cancel') }}</button>
                        <button type="submit" class="ag-btn ag-btn--primary">{{ __('common.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
