<section class="store-hero" aria-labelledby="hero-heading">
    <div class="store-hero__copy">
        @if (! empty($section['eyebrow']))
            <p class="store-hero__eyebrow">{{ $section['eyebrow'] }}</p>
        @endif
        <h1 id="hero-heading" class="store-hero__title">{{ $section['title'] ?? ($siteName ?? 'Your store') }}</h1>
        @if (! empty($section['lede']))
            <p class="store-hero__lede">{{ $section['lede'] }}</p>
        @endif
        <div class="store-hero__actions">
            @if (! empty($section['cta_label']))
                <a class="store-btn store-btn--primary" href="{{ $section['cta_href'] ?? '#catalog' }}">{{ $section['cta_label'] }}</a>
            @endif
            <a class="store-btn" href="{{ route('storefront.cart') }}">View cart</a>
        </div>
    </div>
</section>
