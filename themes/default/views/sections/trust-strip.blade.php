@php $items = $section['items'] ?? []; @endphp
@if (is_array($items) && $items !== [])
<section class="store-section store-trust" aria-label="{{ __('storefront.home.trust_aria') }}">
    <ul class="store-trust__list" role="list">
        @foreach ($items as $item)
            <li class="store-trust__item">
                <span class="store-trust__icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                </span>
                <div>
                    <p class="store-trust__title">{{ $item['title'] ?? '' }}</p>
                    @if (! empty($item['text']))
                        <p class="store-trust__text">{{ $item['text'] }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</section>
@endif
