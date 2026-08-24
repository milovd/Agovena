@props([
    'label' => null,
    'hint' => null,
    'accept' => 'image/jpeg,image/png,image/webp,image/gif,application/pdf,.pdf',
    'multiple' => false,
    'buttonLabel' => null,
    'loadingTarget' => null,
    'disabled' => false,
    'placeholderIcon' => 'upload',
    'emptyLabel' => null,
])

@php
    $id = $attributes->get('id') ?? 'store-upload-'.str_replace('.', '-', uniqid('', true));
    $triggerLabel = $buttonLabel ?? __('common.upload');
    $defaultEmptyLabel = $emptyLabel ?? __('common.no_file_selected');
@endphp

<div
    class="store-file-upload {{ $disabled ? 'is-disabled' : '' }}"
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
        <label class="store-label" for="{{ $id }}">{{ $label }}</label>
    @endif

    <div class="store-file-upload__surface">
        <div class="store-file-upload__placeholder" aria-hidden="true">
            <x-ag.icon :name="$placeholderIcon" :size="22" />
        </div>

        <div class="store-file-upload__meta">
            <p class="store-file-upload__name" x-text="fileLabel || @js($defaultEmptyLabel)"></p>
            @if ($hint)
                <p class="store-field__hint">{{ $hint }}</p>
            @endif
        </div>

        <div class="store-file-upload__actions">
            <input
                type="file"
                id="{{ $id }}"
                class="store-file-upload__input"
                accept="{{ $accept }}"
                @if ($multiple) multiple @endif
                @disabled($disabled)
                {{ $attributes->except(['id', 'class', 'accept'])->merge(['accept' => $accept]) }}
            >
            <label for="{{ $id }}" class="store-btn store-btn--secondary store-btn--sm store-file-upload__trigger">
                <x-ag.icon name="upload" :size="16" />
                <span>{{ $triggerLabel }}</span>
            </label>
        </div>
    </div>

    @if ($loadingTarget)
        <div wire:loading wire:target="{{ $loadingTarget }}" class="store-file-upload__loading" role="status">{{ __('common.uploading') }}</div>
    @endif

    {{ $slot }}
</div>
