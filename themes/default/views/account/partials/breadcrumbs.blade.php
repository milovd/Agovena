@props([
    'items' => [],
])

@php
    /** @var list<array{label: string, url?: string|null}> $items */
    $items = array_values(array_filter($items, static fn ($item): bool => filled($item['label'] ?? null)));
@endphp

@if ($items !== [])
    <nav class="store-breadcrumbs store-breadcrumbs--compact store-account-breadcrumbs" aria-label="{{ __('customer.account.breadcrumb_aria') }}">
        @foreach ($items as $index => $item)
            @if ($index > 0)
                <span aria-hidden="true">/</span>
            @endif
            @if (! empty($item['url']) && ! $loop->last)
                <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
            @elseif ($loop->last)
                <span aria-current="page">{{ $item['label'] }}</span>
            @else
                <span>{{ $item['label'] }}</span>
            @endif
        @endforeach
    </nav>
@endif
