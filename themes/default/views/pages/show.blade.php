<article class="store-page">
    <nav class="store-breadcrumbs" aria-label="{{ __('storefront.breadcrumb_aria') }}">
        <a href="{{ route('storefront.home') }}">{{ __('storefront.nav.home') }}</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">{{ $page->title }}</span>
    </nav>
    <h1 class="store-title">{{ $page->title }}</h1>
    <div class="store-page__body">
        {!! nl2br(e($page->body ?? '')) !!}
    </div>
</article>
