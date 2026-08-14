@php
    $image = $section['image'] ?? null;
    $imageUrl = null;
    if (is_string($image) && $image !== '') {
        $imageUrl = str_starts_with($image, 'http')
            ? $image
            : \App\Agovena\Media\PublicMedia::url($image);
    }

    $spotlight = collect($spotlightProducts ?? [])
        ->filter(fn ($product) => filled(\App\Agovena\Media\ProductMedia::primaryUrl($product)))
        ->take(3)
        ->values();

    $brand = $siteName ?? 'Store';
@endphp

<section
    class="store-hero"
    aria-labelledby="hero-heading"
    x-data="{ ready: false }"
    x-init="requestAnimationFrame(() => ready = true)"
    :class="{ 'is-ready': ready }"
>
    <div class="store-hero__stage">
        <div class="store-hero__copy">
            <p class="store-hero__brand">{{ $brand }}</p>

            @if (! empty($section['eyebrow']))
                <p class="store-hero__eyebrow">{{ $section['eyebrow'] }}</p>
            @endif

            <h1 id="hero-heading" class="store-hero__title">{{ $section['title'] ?? 'Shop the live catalog' }}</h1>

            @if (! empty($section['lede']))
                <p class="store-hero__lede">{{ $section['lede'] }}</p>
            @endif

            <div class="store-hero__actions">
                @if (! empty($section['cta_label']))
                    <a class="store-btn store-btn--primary store-btn--hero" href="{{ $section['cta_href'] ?? '#catalog' }}">
                        {{ $section['cta_label'] }}
                    </a>
                @endif
                <a class="store-btn store-btn--outline store-btn--hero-secondary" href="{{ route('storefront.categories') }}">
                    {{ __('storefront.nav.browse_categories') }}
                </a>
            </div>
        </div>

        <div class="store-hero__orbit" aria-hidden="true">
            @if ($imageUrl)
                <div class="store-hero__plate store-hero__plate--hero">
                    <img src="{{ $imageUrl }}" alt="" loading="eager">
                </div>
            @endif

            @foreach ($spotlight as $i => $product)
                <a
                    class="store-hero__plate store-hero__plate--{{ $i + 1 }}"
                    href="{{ route('storefront.product', $product->slug) }}"
                    tabindex="-1"
                >
                    <img
                        src="{{ \App\Agovena\Media\ProductMedia::primaryUrl($product) }}"
                        alt=""
                        loading="eager"
                    >
                </a>
            @endforeach

            @if (! $imageUrl && $spotlight->isEmpty())
                <div class="store-hero__plate store-hero__plate--hero store-hero__plate--empty"></div>
            @endif
        </div>
    </div>
</section>
