@php
    use Illuminate\Support\Facades\Lang;

    $translate = static fn (?string $value): ?string => $value === null ? null : (Lang::has($value) ? __($value) : $value);
@endphp

<div class="admin-page">
    <x-ag.page-header
        :heading="__('admin.appearance.customize.heading', ['theme' => $theme->name])"
        :lede="__('admin.appearance.customize.lede')"
    >
        <x-slot:back>
            <x-ag.back :href="route('admin.appearance.themes')" :label="__('admin.appearance.themes.title')" />
        </x-slot:back>
        <x-slot:actions>
            <a class="ag-btn ag-btn--secondary" href="{{ route('admin.appearance.themes') }}">{{ __('admin.appearance.customize.all_themes') }}</a>
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <form class="admin-panel ag-form" wire:submit="save">
        @foreach ($groups as $groupKey => $fields)
            @php $groupLabelKey = 'admin.appearance.customize.groups.'.$groupKey; @endphp
            <fieldset class="ag-fieldset">
                <legend class="ag-fieldset__legend">
                    {{ Lang::has($groupLabelKey) ? __($groupLabelKey) : ucfirst(str_replace('_', ' ', $groupKey)) }}
                </legend>
                @foreach ($fields as $field)
                    <div class="ag-field" wire:key="field-{{ $field->key }}">
                        @if ($field->type === 'boolean')
                            <x-ag.switch
                                id="tf-{{ md5($field->key) }}"
                                wire:model="values.{{ str_replace('.', '.', $field->key) }}"
                                value="1"
                                :label="$translate($field->label)"
                            />
                        @elseif ($field->type === 'select')
                            <label class="ag-field__label" for="tf-{{ md5($field->key) }}">{{ $translate($field->label) }}</label>
                            <select id="tf-{{ md5($field->key) }}" class="ag-select" wire:model="values.{{ $field->key }}">
                                @foreach ($field->options ?? [] as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                        @elseif ($field->type === 'color')
                            <label class="ag-field__label" for="tf-{{ md5($field->key) }}">{{ $translate($field->label) }}</label>
                            <input id="tf-{{ md5($field->key) }}" class="ag-input" type="color" wire:model="values.{{ $field->key }}">
                        @elseif (! in_array($field->type, ['sections', 'usp_items'], true))
                            <label class="ag-field__label" for="tf-{{ md5($field->key) }}">{{ $translate($field->label) }}</label>
                            <input id="tf-{{ md5($field->key) }}" class="ag-input" type="text" wire:model="values.{{ $field->key }}">
                        @endif
                        @if ($field->help && ! in_array($field->type, ['sections', 'usp_items'], true))
                            <p class="ag-field__help">{{ $translate($field->help) }}</p>
                        @endif
                    </div>
                @endforeach
            </fieldset>
        @endforeach

        <fieldset class="ag-fieldset">
            <legend class="ag-fieldset__legend">{{ __('admin.appearance.customize.usp.legend') }}</legend>
            <p class="ag-field__help">{{ __('admin.appearance.customize.usp.help') }}</p>

            <div class="ag-stack">
                @foreach ($uspItems as $index => $usp)
                    <div class="admin-panel" wire:key="usp-{{ $index }}">
                        <div class="ag-toolbar">
                            <strong>{{ __('admin.appearance.customize.usp.item', ['number' => $index + 1]) }}</strong>
                            <div class="ag-toolbar__actions">
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="moveUspItem({{ $index }}, 'up')">{{ __('common.up') }}</button>
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="moveUspItem({{ $index }}, 'down')">{{ __('common.down') }}</button>
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="removeUspItem({{ $index }})">{{ __('common.remove') }}</button>
                            </div>
                        </div>
                        <div class="ag-field">
                            <label class="ag-field__label">{{ __('admin.appearance.customize.usp.text') }}</label>
                            <input class="ag-input" type="text" wire:model="uspItems.{{ $index }}.text">
                        </div>
                        <div class="ag-field">
                            <label class="ag-field__label">{{ __('admin.appearance.customize.usp.short') }}</label>
                            <input class="ag-input" type="text" wire:model="uspItems.{{ $index }}.short" placeholder="{{ __('admin.appearance.customize.usp.short_placeholder') }}">
                        </div>
                        <div class="ag-field">
                            <label class="ag-field__label">{{ __('admin.appearance.customize.usp.emphasis') }}</label>
                            <input class="ag-input" type="text" wire:model="uspItems.{{ $index }}.emphasis" placeholder="{{ __('admin.appearance.customize.usp.emphasis_placeholder') }}">
                        </div>
                        <div class="ag-field">
                            <label class="ag-field__label">{{ __('admin.appearance.customize.usp.link') }}</label>
                            <input class="ag-input" type="text" wire:model="uspItems.{{ $index }}.href" placeholder="/shipping">
                        </div>
                        <x-ag.switch
                            id="usp-highlight-{{ $index }}"
                            wire:model="uspItems.{{ $index }}.highlight"
                            value="1"
                            :label="__('admin.appearance.customize.usp.highlight')"
                        />
                    </div>
                @endforeach
            </div>

            <div class="ag-toolbar" style="margin-top: 1rem;">
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="addUspItem">{{ __('admin.appearance.customize.usp.add') }}</button>
            </div>
        </fieldset>

        <fieldset class="ag-fieldset">
            <legend class="ag-fieldset__legend">{{ __('admin.appearance.customize.sections.legend') }}</legend>
            <p class="ag-field__help">{{ __('admin.appearance.customize.sections.help') }}</p>

            <div class="ag-stack">
                @foreach ($sections as $index => $section)
                    <div class="admin-panel" wire:key="section-{{ $index }}">
                        <div class="ag-toolbar">
                            <strong>
                                @php
                                    $sectionType = $section['type'] ?? null;
                                    $sectionTypeKey = $sectionType ? 'admin.appearance.customize.sections.types.'.$sectionType : null;
                                @endphp
                                {{ $sectionTypeKey && Lang::has($sectionTypeKey) ? __($sectionTypeKey) : ($sectionType ?? __('admin.appearance.customize.sections.fallback')) }}
                            </strong>
                            <div class="ag-toolbar__actions">
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="moveSection({{ $index }}, 'up')">{{ __('common.up') }}</button>
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="moveSection({{ $index }}, 'down')">{{ __('common.down') }}</button>
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="removeSection({{ $index }})">{{ __('common.remove') }}</button>
                            </div>
                        </div>
                        @if (($section['type'] ?? '') === 'hero')
                            <div class="ag-field">
                                <label class="ag-field__label">{{ __('common.title') }}</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.title">
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label">{{ __('admin.appearance.customize.sections.lede') }}</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.lede">
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label">{{ __('admin.appearance.customize.sections.cta_label') }}</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.cta_label">
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label">{{ __('admin.appearance.customize.sections.image') }}</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.image" placeholder="demo/hero-promo.svg">
                            </div>
                        @elseif (($section['type'] ?? '') === 'featured_products')
                            <div class="ag-field">
                                <label class="ag-field__label">{{ __('common.title') }}</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.title">
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label">{{ __('admin.appearance.customize.sections.limit') }}</label>
                                <input class="ag-input" type="number" min="1" max="24" wire:model="sections.{{ $index }}.limit">
                            </div>
                        @elseif (($section['type'] ?? '') === 'promo_split')
                            <div class="ag-field">
                                <label class="ag-field__label">{{ __('common.title') }}</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.title">
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label">{{ __('admin.appearance.customize.sections.body') }}</label>
                                <textarea class="ag-input" rows="3" wire:model="sections.{{ $index }}.body"></textarea>
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label">{{ __('admin.appearance.customize.sections.cta_label') }}</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.cta_label">
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label">{{ __('admin.appearance.customize.sections.image') }}</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.image">
                            </div>
                        @elseif (($section['type'] ?? '') === 'rich_text')
                            <div class="ag-field">
                                <label class="ag-field__label">{{ __('common.title') }}</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.title">
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label">{{ __('admin.appearance.customize.sections.body') }}</label>
                                <textarea class="ag-input" rows="3" wire:model="sections.{{ $index }}.body"></textarea>
                            </div>
                        @else
                            <div class="ag-field">
                                <label class="ag-field__label">{{ __('common.title') }}</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.title">
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="ag-toolbar" style="margin-top: 1rem;">
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="addSection('hero')">{{ __('admin.appearance.customize.sections.add_hero') }}</button>
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="addSection('featured_products')">{{ __('admin.appearance.customize.sections.add_products') }}</button>
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="addSection('featured_categories')">{{ __('admin.appearance.customize.sections.add_categories') }}</button>
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="addSection('promo_split')">{{ __('admin.appearance.customize.sections.add_promo') }}</button>
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="addSection('rich_text')">{{ __('admin.appearance.customize.sections.add_rich_text') }}</button>
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="addSection('trust_strip')">{{ __('admin.appearance.customize.sections.add_trust_strip') }}</button>
            </div>
        </fieldset>

        <button type="submit" class="ag-btn ag-btn--primary">{{ __('admin.appearance.customize.save') }}</button>
    </form>
</div>
