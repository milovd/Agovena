@props([
    'title' => null,
    'description' => null,
])

<section {{ $attributes->class(['ag-danger-zone']) }}>
    <header class="ag-danger-zone__header">
        <x-ag.icon name="trash" :size="18" />
        <h3 class="ag-danger-zone__title">{{ $title ?? __('common.danger_zone') }}</h3>
    </header>

    @if ($description)
        <p class="ag-danger-zone__text">{{ $description }}</p>
    @endif

    <div class="ag-danger-zone__actions">
        {{ $slot }}
    </div>
</section>
