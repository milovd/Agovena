@props([
    'label' => null,
    'hint' => null,
    'accept' => 'image/jpeg,image/png,image/webp,image/gif',
    'multiple' => false,
    'previewUrl' => null,
    'previewAlt' => '',
    'buttonLabel' => null,
    'replaceLabel' => null,
    'removeWireClick' => null,
    'loadingTarget' => null,
    'disabled' => false,
    'error' => null,
    'placeholderIcon' => 'image',
    'emptyLabel' => null,
])

@php
    $id = $attributes->get('id') ?? 'upload-'.str_replace('.', '-', uniqid('', true));
    $hasPreview = filled($previewUrl);
    $triggerLabel = $hasPreview
        ? ($replaceLabel ?? __('common.replace'))
        : ($buttonLabel ?? __('common.upload'));
    $defaultEmptyLabel = $hasPreview ? __('common.current_image') : ($emptyLabel ?? __('common.no_file_selected'));
@endphp

<div
    class="ag-file-upload {{ $disabled ? 'is-disabled' : '' }}"
    x-data="{ fileLabel: '' }"
    @change="
        const input = $event.target;
        if (!(input instanceof HTMLInputElement) || input.type !== 'file') return;
        const files = input.files;
        if (!files || files.length === 0) { fileLabel = ''; return; }
        fileLabel = files.length === 1 ? files[0].name : @js(__('common.files_selected', ['count' => ':count'])).replace(':count', String(files.length));
    "
>
    @if ($label)
        <label class="ag-field__label" for="{{ $id }}">{{ $label }}</label>
    @endif

    <div class="ag-file-upload__surface">
        @if ($hasPreview)
            <div class="ag-file-upload__preview">
                <img src="{{ $previewUrl }}" alt="{{ $previewAlt }}" width="72" height="72">
            </div>
        @else
            <div class="ag-file-upload__placeholder" aria-hidden="true">
                <x-ag.icon :name="$placeholderIcon" :size="22" />
            </div>
        @endif

        <div class="ag-file-upload__meta">
            <p class="ag-file-upload__name" x-text="fileLabel || @js($defaultEmptyLabel)"></p>
            @if ($hint)
                <p class="ag-file-upload__hint">{{ $hint }}</p>
            @endif
        </div>

        <div class="ag-file-upload__actions">
            <input
                type="file"
                id="{{ $id }}"
                class="ag-file-upload__input"
                accept="{{ $accept }}"
                @if ($multiple) multiple @endif
                @disabled($disabled)
                {{ $attributes->except(['id', 'class', 'accept'])->merge(['accept' => $accept]) }}
            >
            <label for="{{ $id }}" class="ag-btn ag-btn--secondary ag-btn--sm ag-file-upload__trigger">
                <x-ag.icon name="upload" :size="16" />
                <span>{{ $triggerLabel }}</span>
            </label>

            @if ($removeWireClick && $hasPreview && ! $disabled)
                <button
                    type="button"
                    class="ag-btn ag-btn--ghost ag-btn--sm"
                    wire:click="{{ $removeWireClick }}"
                >
                    {{ __('common.remove') }}
                </button>
            @endif
        </div>
    </div>

    @if ($loadingTarget)
        <div wire:loading wire:target="{{ $loadingTarget }}" class="ag-file-upload__loading" role="status">{{ __('common.uploading') }}</div>
    @endif

    @if ($error)
        <p class="ag-field__error" role="alert">{{ $error }}</p>
    @endif

    {{ $slot }}
</div>
