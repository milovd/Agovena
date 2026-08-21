@props([
    'title' => null,
])

<div {{ $attributes->class(['ag-empty', 'ag-empty--soft']) }} role="status">
    @isset($icon)
        <div class="ag-empty__icon" aria-hidden="true">{{ $icon }}</div>
    @endisset
    @if ($title)
        <p class="ag-empty__title">{{ $title }}</p>
    @endif
    @isset($description)
        <p class="ag-empty__text">{{ $description }}</p>
    @endisset
    {{ $slot }}
    @isset($actions)
        <div class="ag-empty__actions">{{ $actions }}</div>
    @endisset
</div>
