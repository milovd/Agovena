<div class="admin-page">
    <nav class="admin-page__crumb" aria-label="Breadcrumb">
        <a href="{{ route('admin.settings.index') }}">Settings</a>
        <span aria-hidden="true"> / </span>
        <span>{{ $groupDefinition->label }}</span>
    </nav>

    <x-ag.page-header :heading="$groupDefinition->label" :lede="$groupDefinition->description">
        <x-slot:back>
            <x-ag.back :href="route('admin.settings.index')" label="Settings" />
        </x-slot:back>
    </x-ag.page-header>

    <form wire:submit="save" class="admin-panel ag-form ag-form--constrained" novalidate>
        @foreach ($fields as $field)
            <div class="ag-field" wire:key="field-{{ $field->key }}">
                @if ($field->type !== 'boolean' && $field->type !== 'image')
                    <label class="ag-field__label" for="setting-{{ $field->key }}">{{ $field->label }}</label>
                @endif

                @if ($field->type === 'text')
                    <textarea
                        id="setting-{{ $field->key }}"
                        class="ag-input"
                        rows="4"
                        wire:model="values.{{ $field->key }}"
                        @disabled(! $canUpdate)
                    ></textarea>
                @elseif ($field->type === 'boolean')
                    <x-ag.switch
                        id="setting-{{ $field->key }}"
                        wire:model="values.{{ $field->key }}"
                        :label="$field->label"
                        :disabled="! $canUpdate"
                    />
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
                    <x-ag.file-upload
                        id="setting-{{ $field->key }}"
                        :label="$field->label"
                        hint="PNG, JPG, WebP, or SVG recommended."
                        accept="image/*"
                        button-label="Upload"
                        replace-label="Replace"
                        :preview-url="! empty($values[$field->key]) ? \Illuminate\Support\Facades\Storage::disk('public')->url($values[$field->key]) : null"
                        loading-target="uploads.{{ $field->key }}"
                        :disabled="! $canUpdate"
                        wire:model="uploads.{{ $field->key }}"
                    >
                        @error('uploads.'.$field->key) <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </x-ag.file-upload>

                    @if ($group === 'branding' && $field->key === 'logo_path' && $canUpdate)
                        <div style="margin-top: var(--ag-space-3); display: grid; gap: var(--ag-space-3);">
                            <x-ag.checkbox id="use-logo-as-favicon" wire:model="useLogoAsFavicon" label="Also use this logo as the favicon" />
                            @if (! empty($values['logo_path']))
                                <div>
                                    <button type="button" class="ag-btn ag-btn--secondary ag-btn--sm" wire:click="useCurrentLogoAsFavicon">
                                        Use current logo as favicon
                                    </button>
                                </div>
                            @endif
                        </div>
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
