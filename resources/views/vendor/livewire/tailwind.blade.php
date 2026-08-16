@php
if (! isset($scrollTo)) {
    $scrollTo = 'body';
}

$scrollIntoViewJsSnippet = ($scrollTo !== false)
    ? <<<JS
       (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
    JS
    : '';
@endphp

@if ($paginator->hasPages())
    <nav class="ag-pagination" role="navigation" aria-label="{{ __('pagination.aria') }}">
        <p class="ag-pagination__summary">
            {{ __('pagination.showing', [
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
                'total' => $paginator->total(),
            ]) }}
        </p>

        <ul class="ag-pagination__list" role="list">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="ag-pagination__btn ag-pagination__btn--disabled" aria-disabled="true">
                        <svg class="ag-pagination__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                        <span>{{ __('pagination.previous') }}</span>
                    </span>
                @else
                    <button
                        type="button"
                        class="ag-pagination__btn"
                        wire:click="previousPage('{{ $paginator->getPageName() }}')"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                        wire:loading.attr="disabled"
                        aria-label="{{ __('pagination.previous') }}"
                    >
                        <svg class="ag-pagination__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                        <span>{{ __('pagination.previous') }}</span>
                    </button>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="ag-pagination__ellipsis" aria-hidden="true">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                            @if ($page == $paginator->currentPage())
                                <span class="ag-pagination__btn ag-pagination__btn--current" aria-current="page">{{ $page }}</span>
                            @else
                                <button
                                    type="button"
                                    class="ag-pagination__btn"
                                    wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')"
                                    x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                    aria-label="{{ __('pagination.go_to', ['page' => $page]) }}"
                                >{{ $page }}</button>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <button
                        type="button"
                        class="ag-pagination__btn"
                        wire:click="nextPage('{{ $paginator->getPageName() }}')"
                        x-on:click="{{ $scrollIntoViewJsSnippet }}"
                        wire:loading.attr="disabled"
                        aria-label="{{ __('pagination.next') }}"
                    >
                        <span>{{ __('pagination.next') }}</span>
                        <svg class="ag-pagination__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                    </button>
                @else
                    <span class="ag-pagination__btn ag-pagination__btn--disabled" aria-disabled="true">
                        <span>{{ __('pagination.next') }}</span>
                        <svg class="ag-pagination__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                    </span>
                @endif
            </li>
        </ul>
    </nav>
@endif
