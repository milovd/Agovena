@php($configurationPage = $configuration ?? false)
<div class="store-domain-search{{ $configurationPage ? ' store-domain-search--configuration' : '' }}{{ ($configurationPage && is_array($result)) ? ' store-domain-search--has-results' : '' }}">
    @if ($configurationPage && $product)
        <nav class="store-breadcrumbs store-breadcrumbs--compact" aria-label="{{ __('storefront.breadcrumb_aria') }}">
            <a href="{{ route('storefront.product', $product->slug) }}">{{ $product->name }}</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ __('storefront.product.configure_breadcrumb') }}</span>
        </nav>
    @endif

    <section class="store-domain-search__hero">
        @if ($configurationPage && $product)
            <p class="store-domain-search__eyebrow">{{ __('domains::storefront.product_eyebrow') }}</p>
            <h1 class="store-title">{{ __('domains::storefront.configuration_title', ['product' => $product->name]) }}</h1>
            <p class="store-domain-search__lede">{{ __('domains::storefront.configuration_lede') }}</p>
        @else
            <p class="store-domain-search__eyebrow">{{ __('domains::storefront.demo_note') }}</p>
            <h1 class="store-title">{{ __('domains::storefront.title') }}</h1>
            <p class="store-domain-search__lede">{{ __('domains::storefront.lede') }}</p>
        @endif
        <form class="store-domain-search__form" wire:submit="search">
            <label class="store-domain-search__label" for="domain-query">{{ __('domains::storefront.search_label') }}</label>
            <div class="store-domain-search__control">
                <input id="domain-query" class="store-input" type="text" wire:model="query" placeholder="{{ __('domains::storefront.search_placeholder') }}" autocomplete="url" required>
                <button class="store-btn store-btn--primary" type="submit" wire:loading.attr="disabled">{{ __('domains::storefront.search') }}</button>
            </div>
            @error('query') <p class="store-alert store-alert--error" role="alert">{{ $message }}</p> @enderror
        </form>
    </section>

    @if (is_array($result))
        <section class="store-domain-search__results" aria-live="polite">
            @php($requested = $result['requested'] ?? [])
            <div class="store-domain-result {{ ($requested['available'] ?? false) ? 'store-domain-result--available' : 'store-domain-result--unavailable' }}">
                <div>
                    <p class="store-domain-result__label">{{ __('domains::storefront.requested') }}</p>
                    <h2 class="store-domain-result__domain">{{ $requested['domain'] ?? $result['query'] }}</h2>
                </div>
                <div class="store-domain-result__action">
                    <strong class="store-domain-result__status">{{ ($requested['available'] ?? false) ? __('domains::storefront.available') : __('domains::storefront.unavailable') }}</strong>
                    @if (($requested['available'] ?? false) && ! empty($requested['price']))
                        <span class="store-domain-result__price">{{ $requested['price'] }}</span>
                    @endif
                    @if (($requested['available'] ?? false) && ! empty($requested['selection_token']))
                        <button type="button" class="store-btn store-btn--primary" wire:click="selectDomain('{{ $requested['selection_token'] }}')">{{ $configurationPage ? __('domains::storefront.continue') : __('domains::storefront.select') }}</button>
                    @endif
                </div>
            </div>
            @if (($requested['reason'] ?? null) === 'provider_unavailable')
                <p class="store-domain-search__notice" role="status">{{ __('domains::storefront.provider_unavailable') }}</p>
            @endif

            <div class="store-domain-search__alternatives">
                <h2 class="store-subtitle">{{ __('domains::storefront.alternatives') }}</h2>
                @if (($result['alternatives'] ?? []) === [])
                    <p class="store-muted">{{ __('domains::storefront.no_alternatives') }}</p>
                @else
                    <div class="store-domain-options">
                        @foreach ($result['alternatives'] as $alternative)
                            <article class="store-domain-option" wire:key="domain-option-{{ $alternative['domain'] }}">
                                <div>
                                    <strong>{{ $alternative['domain'] }}</strong>
                                    <span>{{ $alternative['price'] }}</span>
                                </div>
                                <button type="button" class="store-btn store-btn--secondary" wire:click="selectDomain('{{ $alternative['selection_token'] }}')">{{ $configurationPage ? __('domains::storefront.continue') : __('domains::storefront.select') }}</button>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
            <p class="store-domain-search__note">{{ __('domains::storefront.checkout_note') }}</p>
        </section>
    @endif
</div>
