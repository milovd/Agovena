<div class="admin-page">
    <x-ag.page-header :heading="__('admin.store_presets.title')" :lede="__('admin.store_presets.lede')" />

    <section class="ag-card" aria-labelledby="store-presets-modules-heading">
        <header class="ag-card__header">
            <h2 id="store-presets-modules-heading" class="ag-card__title">{{ __('admin.modules.title') }}</h2>
        </header>
        <div class="ag-card__content">
            <p>{{ __('admin.store_presets.modules_note') }}</p>
            <p>
                <a href="{{ route('admin.modules.index') }}">{{ __('admin.store_presets.open_modules') }}</a>
            </p>
            @if ($enabledModules !== [])
                <p class="ag-muted">{{ __('admin.store_presets.currently_enabled', ['modules' => implode(', ', $enabledModules)]) }}</p>
            @else
                <p class="ag-muted">{{ __('admin.store_presets.none_enabled') }}</p>
            @endif
        </div>
    </section>

    <section class="ag-card" aria-labelledby="store-presets-choose-heading">
        <header class="ag-card__header">
            <h2 id="store-presets-choose-heading" class="ag-card__title">{{ __('admin.store_presets.choose') }}</h2>
        </header>
        <div class="ag-card__content">
            <form wire:submit="apply" class="ag-form">
                <fieldset class="ag-preset-fieldset">
                    <legend class="visually-hidden">{{ __('admin.store_presets.choose') }}</legend>
                    <div class="ag-preset-options">
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
                    </div>
                </fieldset>
                <div class="ag-form__actions">
                    <button class="ag-btn ag-btn--primary" type="submit">{{ __('admin.store_presets.apply') }}</button>
                </div>
            </form>
        </div>
    </section>
</div>
