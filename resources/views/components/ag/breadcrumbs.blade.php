@props(['items' => []])

@php
    $items = array_values(array_filter($items, static fn (array $item): bool => filled($item['label'] ?? null)));
@endphp

@if ($items !== [])
    <nav class="admin-breadcrumbs" aria-label="{{ __('admin.breadcrumb_aria') }}">
        <ol class="admin-breadcrumbs__list">
            @foreach ($items as $item)
                <li class="admin-breadcrumbs__item">
                    @if (! empty($item['url']) && ! $loop->last)
                        <a class="admin-breadcrumbs__link" href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                    @else
                        <span @if ($loop->last) aria-current="page" @endif>{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
