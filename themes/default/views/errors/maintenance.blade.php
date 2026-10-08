@extends('layouts.error')

@section('title', __('errors.maintenance.title'))

@section('content')
    @php
        $maintenanceMessage = $maintenanceMessage ?? null;
        $maintenanceEndsAt = $maintenanceEndsAt ?? null;
    @endphp
    <section class="store-error store-error--maintenance" data-error-status="maintenance" aria-labelledby="error-heading">
        <h1 id="error-heading" class="store-error__title">{{ __('errors.maintenance.heading') }}</h1>

        @if (filled($maintenanceMessage))
            <p class="store-error__message">{{ $maintenanceMessage }}</p>
        @else
            <p class="store-error__lede">{{ __('errors.maintenance.lede') }}</p>
        @endif

        @if ($maintenanceEndsAt instanceof \Carbon\CarbonInterface && $maintenanceEndsAt->isFuture())
            <p class="store-error__meta">
                {{ __('errors.maintenance.back_by', ['time' => $maintenanceEndsAt->copy()->timezone(config('app.timezone'))->locale(app()->getLocale())->isoFormat('LLL')]) }}
            </p>
        @endif
    </section>
@endsection
