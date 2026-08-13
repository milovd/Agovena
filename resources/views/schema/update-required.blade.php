@extends('layouts.system')

@section('title', __('schema.update_required.title'))
@section('tagline', __('schema.update_required.tagline'))
@section('footer', __('schema.update_required.footer'))

@section('content')
    <section class="install-panel" aria-labelledby="schema-heading">
        <div class="install-status install-status--blocked">
            <span class="install-status__icon" aria-hidden="true">!</span>
            <div>
                <h1 id="schema-heading" class="install-status__title">{{ __('schema.update_required.heading') }}</h1>
                <p class="install-status__text">{{ __('schema.update_required.lede') }}</p>
            </div>
        </div>

        <p>{{ __('schema.update_required.instruction') }}</p>

        <pre class="install-code" tabindex="0"><code>{{ $upgradeCommand }}</code></pre>
        <p class="install-panel__lede">{{ __('schema.update_required.migrate_alternative', ['command' => $migrateCommand]) }}</p>

        @if ($pendingCount > 0)
            <details class="install-warnings" open>
                <summary>
                    <span class="install-warnings__label">{{ trans_choice('schema.update_required.pending_summary', $pendingCount, ['count' => $pendingCount]) }}</span>
                </summary>
                <ul class="install-code-list">
                    @foreach ($pending as $migration)
                        <li><code>{{ $migration }}</code></li>
                    @endforeach
                </ul>
            </details>
        @endif

        <p class="install-panel__lede">{{ __('schema.update_required.doctor_hint') }}</p>
    </section>
@endsection
