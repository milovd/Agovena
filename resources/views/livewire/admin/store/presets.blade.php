<div class="admin-page">
    <x-ag.page-header :heading="__('admin.store_presets.title')" :lede="__('admin.store_presets.lede')" />

    <section class="admin-panel">
        <p>{{ __('admin.store_presets.modules_note') }}</p>
        <p>
            <a href="{{ route('admin.modules.index') }}">{{ __('admin.store_presets.open_modules') }}</a>
        </p>
        @if ($enabledModules !== [])
            <p class="ag-muted">{{ __('admin.store_presets.currently_enabled', ['modules' => implode(', ', $enabledModules)]) }}</p>
        @else
            <p class="ag-muted">{{ __('admin.store_presets.none_enabled') }}</p>
        @endif
    </section>

    <form wire:submit="apply" class="admin-panel ag-form">
        <fieldset>
            <legend class="admin-panel__title">{{ __('admin.store_presets.choose') }}</legend>
            @foreach ($rows as $row)
                @php $preset = $row['preset']; @endphp
                <label class="ag-check" wire:key="preset-{{ $preset->id }}">
                    <input type="checkbox" value="{{ $preset->id }}" wire:model="selected">
                    <span>
                        <strong>{{ __($preset->labelKey) }}</strong>
                        <span class="ag-muted">{{ __($preset->ledeKey) }}</span>
                        @if ($row['modules'] !== [])
                            <span class="ag-muted">{{ __('admin.store_presets.enables', ['modules' => implode(', ', $row['modules'])]) }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </fieldset>
        <div class="ag-form__actions">
            <button class="ag-btn ag-btn--primary" type="submit">{{ __('admin.store_presets.apply') }}</button>
        </div>
    </form>
</div>
