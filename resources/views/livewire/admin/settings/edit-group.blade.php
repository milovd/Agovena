<div class="admin-page">
    <nav class="admin-page__crumb" aria-label="Breadcrumb">
        <a href="{{ route('admin.settings.index') }}">Settings</a>
        <span aria-hidden="true"> / </span>
        <span>{{ $groupDefinition->label }}</span>
    </nav>

    <x-ag.page-header :heading="$groupDefinition->label" :lede="$groupDefinition->description" />

    <form wire:submit="save" class="admin-panel ag-form" novalidate>
        @foreach ($fields as $field)
            <div class="ag-field" wire:key="field-{{ $field->key }}">
                <label class="ag-field__label" for="setting-{{ $field->key }}">{{ $field->label }}</label>

                @if ($field->type === 'text')
                    <textarea
                        id="setting-{{ $field->key }}"
                        class="ag-input"
                        rows="4"
                        wire:model="values.{{ $field->key }}"
                        @disabled(! $canUpdate)
                    ></textarea>
                @elseif ($field->type === 'boolean')
                    <label class="ag-check">
                        <input type="checkbox" wire:model="values.{{ $field->key }}" @disabled(! $canUpdate)>
                        <span>Enabled</span>
                    </label>
                @elseif ($field->type === 'select')
                    <select
                        id="setting-{{ $field->key }}"
                        class="ag-input"
                        wire:model="values.{{ $field->key }}"
                        @disabled(! $canUpdate)
                    >
                        @foreach ($field->options ?? [] as $option)
                            <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                @elseif ($field->type === 'currency')
                    <select
                        id="setting-{{ $field->key }}"
                        class="ag-input"
                        wire:model="values.{{ $field->key }}"
                        @disabled(! $canUpdate)
                    >
                        @forelse ($currencyOptions as $currency)
                            <option value="{{ $currency->code }}">
                                {{ $currency->code }} — {{ $currency->name }} ({{ $currency->previewSample() }})
                            </option>
                        @empty
                            <option value="">No active currencies — add one under Currencies</option>
                        @endforelse
                    </select>
                    <p class="ag-field__help">
                        Manage codes, prefixes and suffixes under
                        <a href="{{ route('admin.currencies.index') }}">Currencies</a>.
                    </p>
                @elseif ($field->type === 'image')
                    @if (! empty($values[$field->key]))
                        <div class="ag-field__preview">
                            <img
                                src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($values[$field->key]) }}"
                                alt=""
                                width="64"
                                height="64"
                                style="object-fit: contain; border: 1px solid var(--ag-color-border); border-radius: var(--ag-radius-sm); background: #fff;"
                            >
                            <p class="ag-field__help">Current file: <code>{{ $values[$field->key] }}</code></p>
                        </div>
                    @endif
                    <input
                        id="setting-{{ $field->key }}"
                        class="ag-input"
                        type="file"
                        accept="image/*"
                        wire:model="uploads.{{ $field->key }}"
                        @disabled(! $canUpdate)
                    >
                    @error('uploads.'.$field->key) <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror

                    @if ($group === 'branding' && $field->key === 'logo_path' && $canUpdate)
                        <label class="ag-check">
                            <input type="checkbox" wire:model="useLogoAsFavicon">
                            <span>Also use this logo as the favicon</span>
                        </label>
                        @if (! empty($values['logo_path']))
                            <button type="button" class="ag-btn ag-btn--ghost" wire:click="useCurrentLogoAsFavicon">
                                Use current logo as favicon
                            </button>
                        @endif
                    @endif
                @else
                    <input
                        id="setting-{{ $field->key }}"
                        class="ag-input"
                        type="text"
                        wire:model="values.{{ $field->key }}"
                        @disabled(! $canUpdate)
                    >
                @endif

                @if ($field->help)
                    <p class="ag-field__help">{{ $field->help }}</p>
                @endif
                @error('values.'.$field->key) <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
        @endforeach

        @if ($canUpdate)
            <div class="ag-form__actions">
                <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled">Save settings</button>
            </div>
        @else
            <p class="ag-field__help" role="status">You can view these settings but cannot change them.</p>
        @endif
    </form>
</div>
