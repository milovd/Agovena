@props(['groups', 'title', 'emptyTitle', 'emptyText'])

@php
    $rows = collect($groups)->flatten(1);
@endphp

@if ($rows->isEmpty())
    <div class="ag-empty" role="status">
        <p class="ag-empty__title">{{ $emptyTitle }}</p>
        <p class="ag-empty__text">{{ $emptyText }}</p>
    </div>
@else
    <section class="ag-package-section" aria-label="{{ $title }}">
        <h2 class="admin-panel__title">{{ $title }}</h2>
        <div class="ag-package-grid">
            @foreach ($rows as $row)
                @include('livewire.admin.packages.partials.extension-card', ['row' => $row])
            @endforeach
        </div>
    </section>
@endif
