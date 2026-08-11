@php
    $image = $section['image'] ?? null;
    $imageUrl = null;
    if (is_string($image) && $image !== '') {
        $imageUrl = str_starts_with($image, 'http')
            ? $image
            : \Illuminate\Support\Facades\Storage::disk('public')->url($image);
    }
@endphp

<section class="store-section store-promo" aria-labelledby="promo-heading">
    <div class="store-promo__layout">
        <div class="store-promo__media" aria-hidden="true">
            @if ($imageUrl)
                <img src="{{ $imageUrl }}" alt="" loading="lazy">
            @else
                <div class="store-promo__placeholder"></div>
            @endif
        </div>
        <div class="store-promo__copy">
            @if (! empty($section['title']))
                <h2 id="promo-heading" class="store-section__title">{{ $section['title'] }}</h2>
            @endif
            @if (! empty($section['body']))
                <p class="store-promo__body">{{ $section['body'] }}</p>
            @endif
            @if (! empty($section['cta_label']))
                <a class="store-btn store-btn--primary" href="{{ $section['cta_href'] ?? '#catalog' }}">{{ $section['cta_label'] }}</a>
            @endif
        </div>
    </div>
</section>
