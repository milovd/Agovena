<form wire:submit="save" class="admin-panel ag-form ag-form--constrained" novalidate>
    @foreach ($fields as $field)
        <div class="ag-field" wire:key="field-{{ $field->key }}">
            @if ($field->type !== 'boolean' && $field->type !== 'image')
                <label class="ag-field__label" for="setting-{{ $field->key }}">{{ __($field->label) }}</label>
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
                    :label="__($field->label)"
                    :disabled="! $canUpdate"
                />
            @elseif ($field->type === 'select')
                <select
                    id="setting-{{ $field->key }}"
                    class="ag-select"
                    wire:model="values.{{ $field->key }}"
                    @disabled(! $canUpdate)
                >
                    @foreach ($field->options ?? [] as $value => $label)
                        @if (is_int($value))
                            @php $optionKey = 'admin.settings.options.'.$field->key.'.'.$label; @endphp
                            <option value="{{ $label }}">
                                {{ \Illuminate\Support\Facades\Lang::has($optionKey) ? __($optionKey) : ucfirst((string) $label) }}
                            </option>
                        @else
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endif
                    @endforeach
                </select>
            @elseif ($field->type === 'currency')
                <select
                    id="setting-{{ $field->key }}"
                    class="ag-select"
                    wire:model="values.{{ $field->key }}"
                    @disabled(! $canUpdate)
                >
                    @forelse ($currencyOptions as $currency)
                        <option value="{{ $currency->code }}">
                            {{ $currency->code }} - {{ $currency->name }} ({{ $currency->previewSample() }})
                        </option>
                    @empty
                        <option value="">{{ __('admin.settings.no_currencies') }}</option>
                    @endforelse
                </select>
                <p class="ag-field__help">
                    {{ __('admin.settings.currency_help') }}
                    <a href="{{ route('admin.currencies.index') }}">{{ __('admin.nav.currencies') }}</a>.
                </p>
            @elseif ($field->type === 'image')
                <x-ag.file-upload
                    id="setting-{{ $field->key }}"
                    :label="__($field->label)"
                    :hint="__('admin.settings.image_hint')"
                    accept="image/*"
                    :preview-url="\App\Agovena\Media\PublicMedia::url($values[$field->key] ?? null)"
                    loading-target="uploads.{{ $field->key }}"
                    :disabled="! $canUpdate"
                    wire:model="uploads.{{ $field->key }}"
                >
                    @error('uploads.'.$field->key) <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </x-ag.file-upload>

                @if ($groupDefinition->id === 'branding' && $field->key === 'logo_path' && $canUpdate)
                    <div style="margin-top: var(--ag-space-3); display: grid; gap: var(--ag-space-3);">
                        <x-ag.checkbox id="use-logo-as-favicon" wire:model="useLogoAsFavicon" :label="__('admin.settings.use_logo_as_favicon')" />
                        @if (! empty($values['logo_path']))
                            <div>
                                <button type="button" class="ag-btn ag-btn--secondary ag-btn--sm" wire:click="useCurrentLogoAsFavicon">
                                    {{ __('admin.settings.use_current_logo_as_favicon') }}
                                </button>
                            </div>
                        @endif
                    </div>
                @endif
            @else
                <input
                    id="setting-{{ $field->key }}"
                    class="ag-input"
                    type="{{ $field->type === 'email' ? 'email' : (in_array($field->type, ['integer', 'percentage'], true) ? 'number' : ($field->type === 'password' ? 'password' : 'text')) }}"
                    wire:model="values.{{ $field->key }}"
                    @disabled(! $canUpdate)
                    @if (in_array($field->type, ['integer', 'percentage'], true)) min="0" @endif
                    @if ($field->type === 'percentage') max="100" step="1" @endif
                    @if ($field->type === 'password') autocomplete="new-password" @endif
                >
            @endif

            @if ($field->help)
                <p class="ag-field__help">{{ __($field->help) }}</p>
            @endif
            @error('values.'.$field->key) <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
        </div>
    @endforeach

    @if ($canUpdate)
        <div class="ag-form__actions">
            <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled">{{ __('admin.settings.save') }}</button>
        </div>
    @else
        <p class="ag-field__help" role="status">{{ __('admin.settings.read_only') }}</p>
    @endif
</form>
