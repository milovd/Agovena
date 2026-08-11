@php $items = $section['items'] ?? []; @endphp
@if (is_array($items) && $items !== [])
<section class="store-section store-trust" aria-label="Why shop here">
    <ul class="store-trust__list" role="list">
        @foreach ($items as $item)
            <li class="store-trust__item">
                <p class="store-trust__title">{{ $item['title'] ?? '' }}</p>
                @if (! empty($item['text']))
                    <p class="store-trust__text">{{ $item['text'] }}</p>
                @endif
            </li>
        @endforeach
    </ul>
</section>
@endif
