{{-- Header product search with live suggestions (Alpine scope: storefrontHeader).
     Rendered twice by theme::partials.header: desktop and mobile ('mobile' => true). --}}
@php
    $mobile = (bool) ($mobile ?? false);
    $inputId = $mobile ? 'store-mobile-header-search' : 'store-header-search';
    $suggestId = $mobile ? 'store-mobile-header-search-suggest' : 'store-search-suggest';
@endphp
<div class="store-header__search-wrap{{ $mobile ? ' store-header__search-wrap--mobile' : '' }}" @click.outside="closeSuggest()">
    <form class="store-header__search{{ $mobile ? ' store-header__search--mobile' : '' }}" action="{{ route('storefront.home') }}" method="get" role="search">
        <label class="visually-hidden" for="{{ $inputId }}">{{ __('storefront.search.label') }}</label>
        <input
            id="{{ $inputId }}"
            class="store-header__search-input"
            type="search"
            name="q"
            x-model="suggestQuery"
            @input="onSuggestInput()"
            @focus="onSuggestInput()"
            placeholder="{{ __('storefront.search.placeholder') }}"
            autocomplete="off"
            aria-autocomplete="list"
            aria-controls="{{ $suggestId }}"
        >
        <button
            type="button"
            class="store-header__search-clear"
            x-show="(suggestQuery || '').length > 0"
            x-cloak
            @click="clearSuggest()"
            aria-label="{{ __('storefront.search.clear') }}"
        >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                <path d="M18 6 6 18"/>
                <path d="m6 6 12 12"/>
            </svg>
        </button>
        <button type="submit" class="store-header__search-icon-btn" aria-label="{{ __('storefront.search.label') }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
        </button>
    </form>
    <div
        id="{{ $suggestId }}"
        class="store-search-suggest{{ $mobile ? ' store-header__mobile-search-suggest' : '' }}"
        x-show="suggestOpen"
        x-cloak
        @mousedown.prevent
        role="listbox"
        aria-label="{{ __('storefront.search.suggestions') }}"
    >
        <template x-if="suggestLoading">
            <p class="store-search-suggest__status" x-text="labels.searching"></p>
        </template>
        <template x-if="!suggestLoading && suggestItems.length === 0 && (suggestQuery || '').trim().length >= 2">
            <p class="store-search-suggest__status" x-text="labels.noMatches"></p>
        </template>
        <template x-for="item in suggestItems" :key="item.slug">
            <a class="store-search-suggest__item" :href="item.url" role="option">
                <span class="store-search-suggest__media" aria-hidden="true">
                    <template x-if="item.image">
                        <img :src="item.image" alt="">
                    </template>
                </span>
                <span class="store-search-suggest__copy">
                    <span class="store-search-suggest__name" x-text="item.name"></span>
                    <span class="store-search-suggest__meta" x-text="item.category || ''"></span>
                </span>
                <span class="store-search-suggest__price" x-text="item.price"></span>
            </a>
        </template>
        <a
            class="store-search-suggest__all"
            :href="searchResultsUrl()"
            x-show="(suggestQuery || '').trim().length >= 2"
            x-text="labels.viewAll"
        ></a>
    </div>
</div>
