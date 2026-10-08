<div class="admin-page" id="data-exports">
    <x-ag.page-header
        :heading="__('admin.exports.title')"
        :lede="__('admin.exports.lede')"
    />

    @if ($entityOptions === [])
        <x-ag.empty :title="__('admin.exports.empty_title')">
            <x-slot:icon><x-ag.icon name="download" :size="22" /></x-slot:icon>
            <x-slot:description>{{ __('admin.exports.empty_text') }}</x-slot:description>
        </x-ag.empty>
    @else
        <section class="ag-card" aria-labelledby="data-export-form-heading">
            <header class="ag-card__header">
                <h2 id="data-export-form-heading" class="ag-card__title">{{ __('admin.exports.form_title') }}</h2>
                <p class="ag-card__description">{{ __('admin.exports.form_description') }}</p>
            </header>
            <div class="ag-card__content">
                <form class="ag-form" wire:submit="export">
                    <div class="ag-grid ag-grid--2">
                        <div class="ag-field">
                            <label class="ag-field__label" for="data-export-entity">{{ __('admin.exports.entity') }}</label>
                            <select id="data-export-entity" class="ag-input" wire:model.live="entity" required>
                                @foreach ($entityOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('entity')
                                <p class="ag-field__error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="ag-field">
                            <label class="ag-field__label" for="data-export-format">{{ __('admin.exports.format') }}</label>
                            <select id="data-export-format" class="ag-input" wire:model="format" required>
                                @foreach ($formatOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('format')
                                <p class="ag-field__error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="ag-field">
                            <label class="ag-field__label" for="data-export-created-from">{{ __('admin.exports.created_from') }}</label>
                            <input id="data-export-created-from" type="date" class="ag-input" wire:model="createdFrom" aria-describedby="data-export-date-help">
                            @error('createdFrom')
                                <p class="ag-field__error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="ag-field">
                            <label class="ag-field__label" for="data-export-created-to">{{ __('admin.exports.created_to') }}</label>
                            <input id="data-export-created-to" type="date" class="ag-input" wire:model="createdTo" aria-describedby="data-export-date-help">
                            @error('createdTo')
                                <p class="ag-field__error">{{ $message }}</p>
                            @enderror
                        </div>

                        <p id="data-export-date-help" class="ag-field__help ag-grid__span-2">{{ __('admin.exports.date_help') }}</p>
                    </div>

                    <div class="ag-form__actions">
                        <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled" wire:target="export">
                            <x-ag.icon name="download" :size="16" />
                            <span>{{ __('admin.exports.download') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="ag-card" aria-labelledby="data-export-columns-heading">
            <header class="ag-card__header">
                <h2 id="data-export-columns-heading" class="ag-card__title">{{ __('admin.exports.columns_title') }}</h2>
                <p class="ag-card__description">{{ __('admin.exports.columns_help') }}</p>
            </header>
            <div class="ag-card__content ag-stack">
                @if ($columns !== [])
                    <p><code>{{ implode(', ', $columns) }}</code></p>
                @endif
                <p class="ag-field__help">{{ __('admin.exports.privacy_note') }}</p>
            </div>
        </section>
    @endif
</div>
