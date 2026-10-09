@php
    $descriptionKey = static fn (string $template): string => 'admin.invoice_design.templates.'.$template.'.description';
@endphp
<div class="admin-page invoice-design">
    <x-ag.page-header
        :heading="__('admin.invoice_design.title')"
        :lede="__('admin.invoice_design.lede')"
    >
        <x-slot:actions>
            <button type="button" class="ag-btn ag-btn--secondary" wire:click="downloadPreviewPdf" wire:loading.attr="disabled" wire:target="downloadPreviewPdf">
                <x-ag.icon name="download" :size="16" />
                {{ __('admin.invoice_design.download_pdf') }}
            </button>
            <button type="submit" form="invoice-design-form" class="ag-btn ag-btn--primary" wire:loading.attr="disabled" wire:target="save">
                {{ __('admin.invoice_design.save') }}
            </button>
        </x-slot:actions>
    </x-ag.page-header>

    <nav class="invoice-tabs" aria-label="{{ __('admin.invoice_design.tabs_label') }}">
        @can('invoices.view')
            <a class="invoice-tabs__tab" href="{{ route('admin.invoices.index') }}" wire:navigate>{{ __('admin.invoice_design.tab_invoices') }}</a>
        @endcan
        <a class="invoice-tabs__tab is-active" href="{{ route('admin.invoices.design') }}" aria-current="page">{{ __('admin.invoice_design.tab_design') }}</a>
    </nav>

    @if (session('status'))
        <div class="ag-alert ag-alert--success" role="status" aria-live="polite">
            <div class="ag-alert__body">{{ session('status') }}</div>
        </div>
    @endif

    @if ($themeOverride)
        <div class="ag-alert ag-alert--warning" role="status">
            <div class="ag-alert__body">{{ __('admin.invoice_design.theme_override') }}</div>
        </div>
    @endif

    <div class="invoice-design__layout">
        <form id="invoice-design-form" class="invoice-design__settings" wire:submit="save">
            <section class="ag-card" aria-labelledby="invoice-design-template-heading">
                <header class="ag-card__header">
                    <h2 id="invoice-design-template-heading" class="ag-card__title">{{ __('admin.invoice_design.template_title') }}</h2>
                    <p class="ag-card__description">{{ __('admin.invoice_design.template_description') }}</p>
                </header>
                <div class="ag-card__content">
                    <div class="invoice-design__templates" role="radiogroup" aria-labelledby="invoice-design-template-heading">
                        @foreach ($templates as $template)
                            @php
                                $selected = ($design['template'] ?? '') === $template;
                            @endphp
                            <button
                                type="button"
                                role="radio"
                                aria-checked="{{ $selected ? 'true' : 'false' }}"
                                class="invoice-design__template {{ $selected ? 'is-selected' : '' }}"
                                wire:click="selectTemplate('{{ $template }}')"
                                style="--invoice-accent: {{ $currentDesign->color }}; --invoice-accent-soft: {{ $currentDesign->tint(0.16) }};"
                            >
                                <span class="invoice-design__thumb invoice-design__thumb--{{ $template }}" aria-hidden="true">
                                    <span class="invoice-design__thumb-a"></span>
                                    <span class="invoice-design__thumb-b"></span>
                                    <span class="invoice-design__thumb-lines"></span>
                                    <span class="invoice-design__thumb-total"></span>
                                </span>
                                <span class="invoice-design__template-name">{{ __('admin.invoice_design.templates.'.$template.'.name') }}</span>
                                <span class="invoice-design__template-text">{{ __($descriptionKey($template)) }}</span>
                            </button>
                        @endforeach
                    </div>
                    @error('design.template')
                        <p class="ag-field__error" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </section>

            <section class="ag-card" aria-labelledby="invoice-design-color-heading">
                <header class="ag-card__header">
                    <h2 id="invoice-design-color-heading" class="ag-card__title">{{ __('admin.invoice_design.color_title') }}</h2>
                    <p class="ag-card__description">{{ __('admin.invoice_design.color_description') }}</p>
                </header>
                <div class="ag-card__content">
                    <div class="invoice-design__swatches" role="radiogroup" aria-labelledby="invoice-design-color-heading">
                        @foreach ($colorPresets as $preset)
                            @php
                                $selected = strtoupper((string) ($design['accent_color'] ?? '')) === $preset;
                            @endphp
                            <button
                                type="button"
                                role="radio"
                                aria-checked="{{ $selected ? 'true' : 'false' }}"
                                aria-label="{{ $preset }}"
                                title="{{ $preset }}"
                                class="invoice-design__swatch {{ $selected ? 'is-selected' : '' }}"
                                style="--swatch: {{ $preset }};"
                                wire:click="selectColor('{{ $preset }}')"
                            ></button>
                        @endforeach
                    </div>
                    <div class="invoice-design__custom-color">
                        <div class="ag-field">
                            <label class="ag-field__label" for="invoice-design-color">{{ __('admin.invoice_design.custom_color') }}</label>
                            <div class="invoice-design__color-input">
                                <input type="color" aria-label="{{ __('admin.invoice_design.color_picker') }}" value="{{ $currentDesign->color }}" wire:model.live.debounce.250ms="design.accent_color">
                                <input id="invoice-design-color" class="ag-input" type="text" maxlength="7" spellcheck="false" autocomplete="off" wire:model.live.debounce.400ms="design.accent_color">
                            </div>
                            @error('design.accent_color')
                                <p class="ag-field__error" role="alert">{{ $message }}</p>
                            @else
                                <p class="ag-field__help">{{ __('admin.invoice_design.color_help') }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
            </section>

            <section class="ag-card" aria-labelledby="invoice-design-details-heading">
                <header class="ag-card__header">
                    <h2 id="invoice-design-details-heading" class="ag-card__title">{{ __('admin.invoice_design.details_title') }}</h2>
                    <p class="ag-card__description">{{ __('admin.invoice_design.details_description') }}</p>
                </header>
                <div class="ag-card__content">
                    <ul class="invoice-design__toggles">
                        @foreach ($toggles as $toggle)
                            <li class="invoice-design__toggle">
                                <x-ag.switch id="invoice-design-{{ $toggle }}" wire:model.live="design.{{ $toggle }}">
                                    {{ __('admin.invoice_design.fields.'.$toggle) }}
                                </x-ag.switch>
                                @if ($toggle === 'show_logo' && ! $hasLogo)
                                    <p class="ag-field__help">
                                        {{ __('admin.invoice_design.no_logo') }}
                                        @can('settings.view')
                                            <a href="{{ route('admin.settings.index', ['tab' => 'branding']) }}" wire:navigate>{{ __('admin.invoice_design.no_logo_link') }}</a>
                                        @endcan
                                    </p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <p class="ag-field__help invoice-design__seller-note">
                        {{ __('admin.invoice_design.seller_note', ['name' => $sellerName !== '' ? $sellerName : __('admin.invoice_design.seller_fallback')]) }}
                        @can('settings.view')
                            <a href="{{ route('admin.settings.index', ['tab' => 'store']) }}" wire:navigate>{{ __('admin.invoice_design.seller_link') }}</a>
                        @endcan
                    </p>
                </div>
            </section>

            <section class="ag-card" aria-labelledby="invoice-design-content-heading">
                <header class="ag-card__header">
                    <h2 id="invoice-design-content-heading" class="ag-card__title">{{ __('admin.invoice_design.content_title') }}</h2>
                    <p class="ag-card__description">{{ __('admin.invoice_design.content_description') }}</p>
                </header>
                <div class="ag-card__content ag-form-stack">
                    <div class="invoice-design__field-grid">
                        @foreach (['contact_email' => 'email', 'contact_phone' => 'tel', 'website' => 'url'] as $field => $type)
                            <div class="ag-field">
                                <label class="ag-field__label" for="invoice-design-{{ $field }}">{{ __('admin.invoice_design.fields.'.$field) }}</label>
                                <input id="invoice-design-{{ $field }}" class="ag-input" type="{{ $type === 'url' ? 'text' : $type }}" inputmode="{{ $type === 'url' ? 'url' : ($type === 'tel' ? 'tel' : 'email') }}" wire:model.live.debounce.500ms="design.{{ $field }}">
                                @error('design.'.$field)
                                    <p class="ag-field__error" role="alert">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                    @foreach (['payment_details', 'notes'] as $field)
                        <div class="ag-field">
                            <label class="ag-field__label" for="invoice-design-{{ $field }}">{{ __('admin.invoice_design.fields.'.$field) }}</label>
                            <textarea id="invoice-design-{{ $field }}" class="ag-input ag-input--area" rows="3" wire:model.live.debounce.500ms="design.{{ $field }}"></textarea>
                            @error('design.'.$field)
                                <p class="ag-field__error" role="alert">{{ $message }}</p>
                            @else
                                <p class="ag-field__help">{{ __('admin.invoice_design.help.'.$field) }}</p>
                            @enderror
                        </div>
                    @endforeach
                    <div class="ag-field">
                        <label class="ag-field__label" for="invoice-design-footer_text">{{ __('admin.invoice_design.fields.footer_text') }}</label>
                        <input id="invoice-design-footer_text" class="ag-input" type="text" wire:model.live.debounce.500ms="design.footer_text">
                        @error('design.footer_text')
                            <p class="ag-field__error" role="alert">{{ $message }}</p>
                        @else
                            <p class="ag-field__help">{{ __('admin.invoice_design.help.footer_text') }}</p>
                        @enderror
                    </div>
                </div>
            </section>

            <div class="invoice-design__footer">
                <button type="button" class="ag-btn ag-btn--ghost" wire:click="resetDesign">{{ __('admin.invoice_design.discard') }}</button>
                <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled" wire:target="save">{{ __('admin.invoice_design.save') }}</button>
            </div>
        </form>

        <aside class="invoice-design__preview" aria-labelledby="invoice-design-preview-heading">
            <div class="invoice-design__preview-bar">
                <h2 id="invoice-design-preview-heading" class="invoice-design__preview-title">{{ __('admin.invoice_design.preview_title') }}</h2>
                <div class="ag-tabs" role="group" aria-label="{{ __('admin.invoice_design.preview_document') }}">
                    @foreach (['invoice', 'credit-note'] as $documentType)
                        <button
                            type="button"
                            class="ag-tabs__tab {{ $previewDocument === $documentType ? 'ag-tabs__tab--active' : '' }}"
                            aria-pressed="{{ $previewDocument === $documentType ? 'true' : 'false' }}"
                            wire:click="$set('previewDocument', '{{ $documentType }}')"
                        >{{ __('admin.invoice_design.preview_'.str_replace('-', '_', $documentType)) }}</button>
                    @endforeach
                </div>
            </div>
            <div class="invoice-design__paper" x-data="agInvoicePreview" x-bind:style="paperStyle">
                <iframe
                    wire:key="invoice-preview-{{ md5($previewHtml) }}"
                    class="invoice-design__frame"
                    title="{{ __('admin.invoice_design.preview_frame') }}"
                    sandbox="allow-same-origin"
                    srcdoc="{{ $previewHtml }}"
                ></iframe>
                <p class="invoice-design__updating" wire:loading.delay>{{ __('admin.invoice_design.updating') }}</p>
            </div>
            <p class="ag-field__help invoice-design__preview-note">{{ __('admin.invoice_design.preview_note') }}</p>
        </aside>
    </div>
</div>
