@php
    use Illuminate\Support\Facades\Lang;

    $translate = static fn (?string $value): ?string => $value === null ? null : (Lang::has($value) ? __($value) : $value);
@endphp

<div class="admin-page theme-customizer">
    <x-ag.page-header
        :heading="__('admin.appearance.customize.heading', ['theme' => $theme->name])"
        :lede="__('admin.appearance.customize.lede')"
    >
        <x-slot:back>
            <x-ag.back :href="route('admin.appearance.themes')" :label="__('admin.appearance.themes.title')" />
        </x-slot:back>
        <x-slot:actions>
            <a class="ag-btn ag-btn--secondary" href="{{ route('admin.appearance.themes') }}">
                <x-ag.icon name="palette" :size="16" />
                {{ __('admin.appearance.customize.all_themes') }}
            </a>
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <div class="theme-customizer__tabs">
        @include('livewire.admin.partials.package-tabs', [
            'active' => $tab,
            'tabs' => $tabs,
            'ariaLabel' => __('admin.appearance.customize.navigation_aria'),
        ])
    </div>

    <form class="theme-customizer__workspace" wire:submit="save">
        @if ($tab !== 'homepage')
            @php
                $groupKeys = match ($tab) {
                    'header' => ['header'],
                    'storefront' => ['footer', 'catalog'],
                    default => ['appearance', 'branding'],
                };
            @endphp

            <section class="theme-customizer__panel" aria-labelledby="customizer-{{ $tab }}-heading">
                <header class="theme-customizer__panel-heading">
                    <div>
                        <p class="theme-customizer__eyebrow">{{ $tabs[$tab] }}</p>
                        <h2 id="customizer-{{ $tab }}-heading">{{ __('admin.appearance.customize.tab_heading_'.$tab) }}</h2>
                        <p>{{ __('admin.appearance.customize.tab_help_'.$tab) }}</p>
                    </div>
                </header>

                <div class="theme-customizer__settings-grid">
                    @foreach ($groupKeys as $groupKey)
                        @if (isset($groups[$groupKey]))
                            @php $groupLabelKey = 'admin.appearance.customize.groups.'.$groupKey; @endphp
                            <fieldset class="theme-customizer__card" wire:key="group-{{ $groupKey }}">
                                <legend class="theme-customizer__card-title">
                                    {{ Lang::has($groupLabelKey) ? __($groupLabelKey) : ucfirst(str_replace('_', ' ', $groupKey)) }}
                                </legend>
                                <div class="theme-customizer__fields">
                                    @foreach ($groups[$groupKey] as $field)
                                        @if (! in_array($field->type, ['sections', 'usp_items'], true))
                                            @php $fieldId = 'tf-'.md5($field->key); @endphp
                                            <div class="ag-field" wire:key="field-{{ $field->key }}">
                                                @if ($field->type === 'boolean')
                                                    <x-ag.switch
                                                        :id="$fieldId"
                                                        wire:model="values.{{ $field->key }}"
                                                        value="1"
                                                        :label="$translate($field->label)"
                                                    />
                                                @elseif ($field->type === 'select')
                                                    <label class="ag-field__label" for="{{ $fieldId }}">{{ $translate($field->label) }}</label>
                                                    <select id="{{ $fieldId }}" class="ag-select" wire:model="values.{{ $field->key }}">
                                                        @foreach ($field->options ?? [] as $option)
                                                            <option value="{{ $option }}">
                                                                {{ $field->key === 'appearance.default_color_mode' ? __('admin.appearance.customize.color_modes.'.$option) : $option }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @elseif ($field->type === 'color')
                                                    <label class="ag-field__label" for="{{ $fieldId }}">{{ $translate($field->label) }}</label>
                                                    <div class="theme-customizer__color-field">
                                                        <input id="{{ $fieldId }}" class="theme-customizer__color" type="color" wire:model="values.{{ $field->key }}">
                                                        <code>{{ data_get($values, $field->key) }}</code>
                                                    </div>
                                                @else
                                                    <label class="ag-field__label" for="{{ $fieldId }}">{{ $translate($field->label) }}</label>
                                                    <input id="{{ $fieldId }}" class="ag-input" type="text" wire:model="values.{{ $field->key }}">
                                                @endif
                                                @if ($field->help)
                                                    <p class="ag-field__help">{{ $translate($field->help) }}</p>
                                                @endif
                                                @error('values.'.$field->key)
                                                    <p class="ag-field__error" role="alert">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </fieldset>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        @if ($tab === 'homepage')
            <section class="theme-customizer__panel" aria-labelledby="customizer-homepage-heading">
                <header class="theme-customizer__panel-heading">
                    <div>
                        <p class="theme-customizer__eyebrow">{{ __('admin.appearance.customize.tabs.homepage') }}</p>
                        <h2 id="customizer-homepage-heading">{{ __('admin.appearance.customize.sections.heading') }}</h2>
                        <p>{{ __('admin.appearance.customize.sections.help') }}</p>
                    </div>
                    <div class="theme-customizer__add-menu" role="group" aria-label="{{ __('admin.appearance.customize.sections.add_aria') }}">
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="addSection('hero')">{{ __('admin.appearance.customize.sections.add_hero') }}</button>
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="addSection('featured_products')">{{ __('admin.appearance.customize.sections.add_products') }}</button>
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="addSection('featured_categories')">{{ __('admin.appearance.customize.sections.add_categories') }}</button>
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="addSection('promo_split')">{{ __('admin.appearance.customize.sections.add_promo') }}</button>
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="addSection('rich_text')">{{ __('admin.appearance.customize.sections.add_rich_text') }}</button>
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="addSection('trust_strip')">{{ __('admin.appearance.customize.sections.add_trust_strip') }}</button>
                    </div>
                </header>

                <div class="theme-customizer__subpanel">
                    <div class="theme-customizer__subpanel-heading">
                        <div>
                            <p class="theme-customizer__eyebrow">{{ __('admin.appearance.customize.usp.legend') }}</p>
                            <h3>{{ __('admin.appearance.customize.usp.heading') }}</h3>
                            <p>{{ __('admin.appearance.customize.usp.help') }}</p>
                        </div>
                        <button type="button" class="ag-btn ag-btn--secondary" wire:click="addUspItem">
                            <x-ag.icon name="plus" :size="16" />
                            {{ __('admin.appearance.customize.usp.add') }}
                        </button>
                    </div>

                    <div class="theme-customizer__repeaters">
                        @forelse ($uspItems as $index => $usp)
                            <article class="theme-customizer__repeater" wire:key="usp-{{ $index }}">
                                <header class="theme-customizer__repeater-heading">
                                    <div class="theme-customizer__repeater-number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</div>
                                    <div>
                                        <h3>{{ __('admin.appearance.customize.usp.item', ['number' => $index + 1]) }}</h3>
                                        <p>{{ $usp['text'] ?: __('admin.appearance.customize.usp.empty_item') }}</p>
                                    </div>
                                    <div class="theme-customizer__repeater-actions">
                                        <button type="button" class="ag-icon-btn" wire:click="moveUspItem({{ $index }}, 'up')" title="{{ __('common.up') }}" aria-label="{{ __('common.up') }}"><x-ag.icon name="chevron-up" :size="16" /></button>
                                        <button type="button" class="ag-icon-btn" wire:click="moveUspItem({{ $index }}, 'down')" title="{{ __('common.down') }}" aria-label="{{ __('common.down') }}"><x-ag.icon name="chevron-down" :size="16" /></button>
                                        <button type="button" class="ag-icon-btn ag-icon-btn--danger" wire:click="removeUspItem({{ $index }})" title="{{ __('common.remove') }}" aria-label="{{ __('common.remove') }}"><x-ag.icon name="trash" :size="16" /></button>
                                    </div>
                                </header>
                                <div class="theme-customizer__repeater-fields">
                                    <div class="ag-field"><label class="ag-field__label" for="usp-text-{{ $index }}">{{ __('admin.appearance.customize.usp.text') }}</label><input id="usp-text-{{ $index }}" class="ag-input" type="text" wire:model="uspItems.{{ $index }}.text"></div>
                                    <div class="ag-field"><label class="ag-field__label" for="usp-short-{{ $index }}">{{ __('admin.appearance.customize.usp.short') }}</label><input id="usp-short-{{ $index }}" class="ag-input" type="text" wire:model="uspItems.{{ $index }}.short" placeholder="{{ __('admin.appearance.customize.usp.short_placeholder') }}"></div>
                                    <div class="ag-field"><label class="ag-field__label" for="usp-emphasis-{{ $index }}">{{ __('admin.appearance.customize.usp.emphasis') }}</label><input id="usp-emphasis-{{ $index }}" class="ag-input" type="text" wire:model="uspItems.{{ $index }}.emphasis" placeholder="{{ __('admin.appearance.customize.usp.emphasis_placeholder') }}"></div>
                                    <div class="ag-field"><label class="ag-field__label" for="usp-link-{{ $index }}">{{ __('admin.appearance.customize.usp.link') }}</label><input id="usp-link-{{ $index }}" class="ag-input" type="text" wire:model="uspItems.{{ $index }}.href" placeholder="/shipping"></div>
                                </div>
                                <x-ag.switch id="usp-highlight-{{ $index }}" wire:model="uspItems.{{ $index }}.highlight" value="1" :label="__('admin.appearance.customize.usp.highlight')" />
                            </article>
                        @empty
                            <div class="theme-customizer__empty">{{ __('admin.appearance.customize.usp.empty') }}</div>
                        @endforelse
                    </div>
                </div>

                <div class="theme-customizer__subpanel">
                    <div class="theme-customizer__subpanel-heading">
                        <div>
                            <p class="theme-customizer__eyebrow">{{ __('admin.appearance.customize.sections.legend') }}</p>
                            <h3>{{ __('admin.appearance.customize.sections.heading') }}</h3>
                        </div>
                    </div>
                    <div class="theme-customizer__repeaters">
                        @forelse ($sections as $index => $section)
                            @php
                                $sectionType = $section['type'] ?? null;
                                $sectionTypeKey = $sectionType ? 'admin.appearance.customize.sections.types.'.$sectionType : null;
                                $sectionLabel = $sectionTypeKey && Lang::has($sectionTypeKey) ? __($sectionTypeKey) : ($sectionType ?? __('admin.appearance.customize.sections.fallback'));
                            @endphp
                            <article class="theme-customizer__repeater" wire:key="section-{{ $index }}">
                                <header class="theme-customizer__repeater-heading">
                                    <div class="theme-customizer__repeater-number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</div>
                                    <div>
                                        <h3>{{ $sectionLabel }}</h3>
                                        <p>{{ __('admin.appearance.customize.sections.item', ['number' => $index + 1]) }}</p>
                                    </div>
                                    <div class="theme-customizer__repeater-actions">
                                        <button type="button" class="ag-icon-btn" wire:click="moveSection({{ $index }}, 'up')" title="{{ __('common.up') }}" aria-label="{{ __('common.up') }}"><x-ag.icon name="chevron-up" :size="16" /></button>
                                        <button type="button" class="ag-icon-btn" wire:click="moveSection({{ $index }}, 'down')" title="{{ __('common.down') }}" aria-label="{{ __('common.down') }}"><x-ag.icon name="chevron-down" :size="16" /></button>
                                        <button type="button" class="ag-icon-btn ag-icon-btn--danger" wire:click="removeSection({{ $index }})" title="{{ __('common.remove') }}" aria-label="{{ __('common.remove') }}"><x-ag.icon name="trash" :size="16" /></button>
                                    </div>
                                </header>
                                <div class="theme-customizer__repeater-fields">
                                    @if ($sectionType === 'hero')
                                        <div class="ag-field"><label class="ag-field__label" for="section-{{ $index }}-title">{{ __('common.title') }}</label><input id="section-{{ $index }}-title" class="ag-input" type="text" wire:model="sections.{{ $index }}.title"></div>
                                        <div class="ag-field"><label class="ag-field__label" for="section-{{ $index }}-lede">{{ __('admin.appearance.customize.sections.lede') }}</label><input id="section-{{ $index }}-lede" class="ag-input" type="text" wire:model="sections.{{ $index }}.lede"></div>
                                        <div class="ag-field"><label class="ag-field__label" for="section-{{ $index }}-cta">{{ __('admin.appearance.customize.sections.cta_label') }}</label><input id="section-{{ $index }}-cta" class="ag-input" type="text" wire:model="sections.{{ $index }}.cta_label"></div>
                                        <div class="ag-field"><label class="ag-field__label" for="section-{{ $index }}-image">{{ __('admin.appearance.customize.sections.image') }}</label><input id="section-{{ $index }}-image" class="ag-input" type="text" wire:model="sections.{{ $index }}.image" placeholder="demo/hero-promo.svg"></div>
                                    @elseif ($sectionType === 'featured_products')
                                        <div class="ag-field"><label class="ag-field__label" for="section-{{ $index }}-title">{{ __('common.title') }}</label><input id="section-{{ $index }}-title" class="ag-input" type="text" wire:model="sections.{{ $index }}.title"></div>
                                        <div class="ag-field"><label class="ag-field__label" for="section-{{ $index }}-limit">{{ __('admin.appearance.customize.sections.limit') }}</label><input id="section-{{ $index }}-limit" class="ag-input" type="number" min="1" max="24" wire:model="sections.{{ $index }}.limit"></div>
                                    @elseif ($sectionType === 'promo_split')
                                        <div class="ag-field"><label class="ag-field__label" for="section-{{ $index }}-title">{{ __('common.title') }}</label><input id="section-{{ $index }}-title" class="ag-input" type="text" wire:model="sections.{{ $index }}.title"></div>
                                        <div class="ag-field"><label class="ag-field__label" for="section-{{ $index }}-body">{{ __('admin.appearance.customize.sections.body') }}</label><textarea id="section-{{ $index }}-body" class="ag-input" rows="3" wire:model="sections.{{ $index }}.body"></textarea></div>
                                        <div class="ag-field"><label class="ag-field__label" for="section-{{ $index }}-cta">{{ __('admin.appearance.customize.sections.cta_label') }}</label><input id="section-{{ $index }}-cta" class="ag-input" type="text" wire:model="sections.{{ $index }}.cta_label"></div>
                                        <div class="ag-field"><label class="ag-field__label" for="section-{{ $index }}-image">{{ __('admin.appearance.customize.sections.image') }}</label><input id="section-{{ $index }}-image" class="ag-input" type="text" wire:model="sections.{{ $index }}.image"></div>
                                    @elseif ($sectionType === 'rich_text')
                                        <div class="ag-field"><label class="ag-field__label" for="section-{{ $index }}-title">{{ __('common.title') }}</label><input id="section-{{ $index }}-title" class="ag-input" type="text" wire:model="sections.{{ $index }}.title"></div>
                                        <div class="ag-field"><label class="ag-field__label" for="section-{{ $index }}-body">{{ __('admin.appearance.customize.sections.body') }}</label><textarea id="section-{{ $index }}-body" class="ag-input" rows="3" wire:model="sections.{{ $index }}.body"></textarea></div>
                                    @else
                                        <div class="ag-field"><label class="ag-field__label" for="section-{{ $index }}-title">{{ __('common.title') }}</label><input id="section-{{ $index }}-title" class="ag-input" type="text" wire:model="sections.{{ $index }}.title"></div>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="theme-customizer__empty">{{ __('admin.appearance.customize.sections.empty') }}</div>
                        @endforelse
                    </div>
                </div>
            </section>
        @endif

        <div class="theme-customizer__save-bar">
            <div>
                <strong>{{ __('admin.appearance.customize.save_heading') }}</strong>
                <span>{{ __('admin.appearance.customize.save_help') }}</span>
            </div>
            <button type="submit" class="ag-btn ag-btn--primary">
                <x-ag.icon name="check" :size="16" />
                {{ __('admin.appearance.customize.save') }}
            </button>
        </div>
    </form>
</div>
