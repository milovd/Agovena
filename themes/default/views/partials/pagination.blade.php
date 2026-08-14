@if ($paginator->hasPages())
    <nav class="store-pagination" aria-label="{{ __('storefront.catalog.pagination') }}">
        <ul class="pagination">
            <li class="{{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                @if ($paginator->onFirstPage())
                    <span>{{ __('storefront.catalog.previous') }}</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev">
                        {{ __('storefront.catalog.previous') }}
                    </a>
                @endif
            </li>
            @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 1), min($paginator->lastPage(), $paginator->currentPage() + 1)) as $page => $url)
                <li class="{{ $page === $paginator->currentPage() ? 'active' : '' }}">
                    @if ($page === $paginator->currentPage())
                        <span aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                </li>
            @endforeach
            <li class="{{ $paginator->hasMorePages() ? '' : 'disabled' }}">
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('storefront.catalog.next') }}</a>
                @else
                    <span>{{ __('storefront.catalog.next') }}</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
