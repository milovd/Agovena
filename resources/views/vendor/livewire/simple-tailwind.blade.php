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
    <nav class="ag-pagination ag-pagination--simple" role="navigation" aria-label="{{ __('pagination.aria') }}">
        <ul class="ag-pagination__list" role="list">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="ag-pagination__btn ag-pagination__btn--disabled" aria-disabled="true">{{ __('pagination.previous') }}</span>
                @else
                    <button type="button" class="ag-pagination__btn" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}">{{ __('pagination.previous') }}</button>
                @endif
            </li>
            <li>
                @if ($paginator->hasMorePages())
                    <button type="button" class="ag-pagination__btn" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}">{{ __('pagination.next') }}</button>
                @else
                    <span class="ag-pagination__btn ag-pagination__btn--disabled" aria-disabled="true">{{ __('pagination.next') }}</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
