@php
    $image = $section['image'] ?? null;
    $imageUrl = null;
    if (is_string($image) && $image !== '') {
        $imageUrl = str_starts_with($image, 'http')
            ? $image
            : \App\Agovena\Media\PublicMedia::url($image);
    }

    $productsBySlug = collect($spotlightProducts ?? [])
        ->keyBy('slug');
    $demoHeroDefinitions = [
        ['slug' => 'minecraft-survival-server', 'asset' => 'demo/minecraft-survival-server.jpg'],
        ['slug' => 'domain-registration-and-dns-management', 'asset' => 'demo/domain-registration-and-dns-management.jpg'],
        ['slug' => 'agovena-essential-tee', 'asset' => 'demo/agovena-essential-tee.jpg'],
    ];
    $spotlight = collect($demoHeroDefinitions)
        ->map(function (array $definition) use ($productsBySlug): ?array {
            $product = $productsBySlug->get($definition['slug']);
            $image = \Illuminate\Support\Facades\Storage::disk('public')->exists($definition['asset'])
                ? \App\Agovena\Media\PublicMedia::url($definition['asset'])
                : ($product ? \App\Agovena\Media\ProductMedia::primaryUrl($product) : null);

            if (! $image) {
                return null;
            }

            return [
                'image' => $image,
                'href' => $product
                    ? route('storefront.product', $product->slug)
                    : route('storefront.home').'#catalog',
            ];
        })
        ->filter()
        ->values();

    $brand = $siteName ?? 'Store';
@endphp

<section
    class="store-hero"
    aria-labelledby="hero-heading"
    x-data="storefrontHero"
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

            @foreach ($spotlight as $i => $card)
                <a
                    class="store-hero__plate store-hero__plate--{{ $i + 1 }}"
                    href="{{ $card['href'] }}"
                    tabindex="-1"
                >
                    <img
                        src="{{ $card['image'] }}"
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
