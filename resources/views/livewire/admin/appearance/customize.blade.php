<div class="admin-page">
    <x-ag.page-header :heading="'Customize — '.$theme->name" lede="Theme-owned settings for the active storefront Theme. Logos stay under Settings → Branding.">
        <x-slot:back>
            <x-ag.back :href="route('admin.appearance.themes')" label="Themes" />
        </x-slot:back>
        <x-slot:actions>
            <a class="ag-btn ag-btn--secondary" href="{{ route('admin.appearance.themes') }}">All themes</a>
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <form class="admin-panel ag-form" wire:submit="save">
        @foreach ($groups as $groupKey => $fields)
            <fieldset class="ag-fieldset">
                <legend class="ag-fieldset__legend">{{ ucfirst(str_replace('_', ' ', $groupKey)) }}</legend>
                @foreach ($fields as $field)
                    <div class="ag-field" wire:key="field-{{ $field->key }}">
                        @if ($field->type === 'boolean')
                            <x-ag.switch
                                id="tf-{{ md5($field->key) }}"
                                wire:model="values.{{ str_replace('.', '.', $field->key) }}"
                                value="1"
                                :label="$field->label"
                            />
                        @elseif ($field->type === 'select')
                            <label class="ag-field__label" for="tf-{{ md5($field->key) }}">{{ $field->label }}</label>
                            <select id="tf-{{ md5($field->key) }}" class="ag-select" wire:model="values.{{ $field->key }}">
                                @foreach ($field->options ?? [] as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                        @elseif ($field->type === 'color')
                            <label class="ag-field__label" for="tf-{{ md5($field->key) }}">{{ $field->label }}</label>
                            <input id="tf-{{ md5($field->key) }}" class="ag-input" type="color" wire:model="values.{{ $field->key }}">
                        @elseif (! in_array($field->type, ['sections', 'usp_items'], true))
                            <label class="ag-field__label" for="tf-{{ md5($field->key) }}">{{ $field->label }}</label>
                            <input id="tf-{{ md5($field->key) }}" class="ag-input" type="text" wire:model="values.{{ $field->key }}">
                        @endif
                        @if ($field->help && ! in_array($field->type, ['sections', 'usp_items'], true))
                            <p class="ag-field__help">{{ $field->help }}</p>
                        @endif
                    </div>
                @endforeach
            </fieldset>
        @endforeach

        <fieldset class="ag-fieldset">
            <legend class="ag-fieldset__legend">USP / benefits bar</legend>
            <p class="ag-field__help">Items across the top of the storefront (shipping, returns, etc.). Use emphasis for a bold lead word; highlight for the special callout.</p>

            <div class="ag-stack">
                @foreach ($uspItems as $index => $usp)
                    <div class="admin-panel" wire:key="usp-{{ $index }}">
                        <div class="ag-toolbar">
                            <strong>Item {{ $index + 1 }}</strong>
                            <div class="ag-toolbar__actions">
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="moveUspItem({{ $index }}, 'up')">Up</button>
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="moveUspItem({{ $index }}, 'down')">Down</button>
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="removeUspItem({{ $index }})">Remove</button>
                            </div>
                        </div>
                        <div class="ag-field">
                            <label class="ag-field__label">Text</label>
                            <input class="ag-input" type="text" wire:model="uspItems.{{ $index }}.text">
                        </div>
                        <div class="ag-field">
                            <label class="ag-field__label">Short text (narrow screens)</label>
                            <input class="ag-input" type="text" wire:model="uspItems.{{ $index }}.short" placeholder="Optional — drops words on small screens">
                        </div>
                        <div class="ag-field">
                            <label class="ag-field__label">Emphasis (bold word)</label>
                            <input class="ag-input" type="text" wire:model="uspItems.{{ $index }}.emphasis" placeholder="e.g. Free">
                        </div>
                        <div class="ag-field">
                            <label class="ag-field__label">Link (optional)</label>
                            <input class="ag-input" type="text" wire:model="uspItems.{{ $index }}.href" placeholder="/shipping">
                        </div>
                        <x-ag.switch id="usp-highlight-{{ $index }}" wire:model="uspItems.{{ $index }}.highlight" value="1" label="CTA button (right side)" />
                    </div>
                @endforeach
            </div>

            <div class="ag-toolbar" style="margin-top: 1rem;">
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="addUspItem">Add USP item</button>
            </div>
        </fieldset>

        <fieldset class="ag-fieldset">
            <legend class="ag-fieldset__legend">Homepage sections</legend>
            <p class="ag-field__help">Reorder sections for the storefront homepage. Full page builder comes later.</p>

            <div class="ag-stack">
                @foreach ($sections as $index => $section)
                    <div class="admin-panel" wire:key="section-{{ $index }}">
                        <div class="ag-toolbar">
                            <strong>{{ $section['type'] ?? 'section' }}</strong>
                            <div class="ag-toolbar__actions">
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="moveSection({{ $index }}, 'up')">Up</button>
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="moveSection({{ $index }}, 'down')">Down</button>
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="removeSection({{ $index }})">Remove</button>
                            </div>
                        </div>
                        @if (($section['type'] ?? '') === 'hero')
                            <div class="ag-field">
                                <label class="ag-field__label">Title</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.title">
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label">Lede</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.lede">
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label">CTA label</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.cta_label">
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label">Image path</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.image" placeholder="demo/hero-promo.svg">
                            </div>
                        @elseif (($section['type'] ?? '') === 'featured_products')
                            <div class="ag-field">
                                <label class="ag-field__label">Title</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.title">
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label">Limit</label>
                                <input class="ag-input" type="number" min="1" max="24" wire:model="sections.{{ $index }}.limit">
                            </div>
                        @elseif (($section['type'] ?? '') === 'promo_split')
                            <div class="ag-field">
                                <label class="ag-field__label">Title</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.title">
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label">Body</label>
                                <textarea class="ag-input" rows="3" wire:model="sections.{{ $index }}.body"></textarea>
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label">CTA label</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.cta_label">
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label">Image path</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.image">
                            </div>
                        @elseif (($section['type'] ?? '') === 'rich_text')
                            <div class="ag-field">
                                <label class="ag-field__label">Title</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.title">
                            </div>
                            <div class="ag-field">
                                <label class="ag-field__label">Body</label>
                                <textarea class="ag-input" rows="3" wire:model="sections.{{ $index }}.body"></textarea>
                            </div>
                        @else
                            <div class="ag-field">
                                <label class="ag-field__label">Title</label>
                                <input class="ag-input" type="text" wire:model="sections.{{ $index }}.title">
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="ag-toolbar" style="margin-top: 1rem;">
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="addSection('hero')">Add hero</button>
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="addSection('featured_products')">Add products</button>
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="addSection('featured_categories')">Add categories</button>
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="addSection('promo_split')">Add promo</button>
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="addSection('rich_text')">Add rich text</button>
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="addSection('trust_strip')">Add trust strip</button>
            </div>
        </fieldset>

        <button type="submit" class="ag-btn ag-btn--primary">Save theme settings</button>
    </form>
</div>
