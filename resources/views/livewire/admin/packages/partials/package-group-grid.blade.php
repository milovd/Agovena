@props([
    'groups',
    'groupLabelPrefix',
    'cardPartial',
    'emptyTitle',
    'emptyText',
])

@if ($groups === [])
    <div class="ag-empty" role="status">
        <p class="ag-empty__title">{{ $emptyTitle }}</p>
        <p class="ag-empty__text">{{ $emptyText }}</p>
    </div>
@else
    @foreach ($groups as $group => $items)
        <section class="admin-panel">
            <h2 class="admin-panel__title">{{ __($groupLabelPrefix.$group) }}</h2>
            <div class="ag-package-grid">
                @foreach ($items as $row)
                    @include($cardPartial, ['row' => $row])
                @endforeach
            </div>
        </section>
    @endforeach
@endif
