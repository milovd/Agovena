@extends('layouts.system')

@section('title', __('errors.505.title'))
@section('tagline', __('errors.tagline'))
@section('footer', __('errors.footer'))

@section('content')
    <section class="install-panel" aria-labelledby="error-heading">
        <h1 id="error-heading" class="install-panel__title">{{ __('errors.505.heading') }}</h1>
        <p class="install-panel__lede">{{ __('errors.505.lede') }}</p>
        <div class="install-panel__actions">
            <a class="ag-btn ag-btn--primary" href="{{ url('/') }}">{{ __('errors.actions.home') }}</a>
        </div>
    </section>
@endsection
