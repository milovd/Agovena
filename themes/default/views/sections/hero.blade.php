@php
    $image = $section['image'] ?? null;
    $imageUrl = null;
    if (is_string($image) && $image !== '') {
        $imageUrl = str_starts_with($image, 'http')
            ? $image
            : \Illuminate\Support\Facades\Storage::disk('public')->url($image);
    }

    $spotlight = collect($spotlightProducts ?? [])
        ->filter(fn ($product) => filled($product->image_path))
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
    <div class="store-hero__glow" aria-hidden="true"></div>
    <div class="store-hero__grid" aria-hidden="true"></div>

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
                    <a class="store-btn store-btn--hero" href="{{ $section['cta_href'] ?? '#catalog' }}">{{ $section['cta_label'] }}</a>
                @endif
                <a class="store-hero__secondary" href="{{ route('storefront.categories') }}">Browse categories</a>
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
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->image_path) }}"
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
