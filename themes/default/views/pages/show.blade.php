<article class="store-page">
    <nav class="store-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('storefront.home') }}">Home</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">{{ $page->title }}</span>
    </nav>
    <h1 class="store-title">{{ $page->title }}</h1>
    <div class="store-page__body">
        {!! nl2br(e($page->body ?? '')) !!}
    </div>
</article>
