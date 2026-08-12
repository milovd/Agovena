@php
    /** @var list<\App\Agovena\Installation\RequirementCheck> $checks */
    $failures = array_values(array_filter($checks, static fn ($c) => $c->required && ! $c->passed));
    $warnings = array_values(array_filter($checks, static fn ($c) => ! $c->required && ! $c->passed));
@endphp

<div class="install-welcome">
    <div class="install-welcome__hero">
        <h1 id="install-step-heading" class="install-panel__title">{{ __('installer.welcome.heading') }}</h1>
        <p class="install-panel__lede">{{ __('installer.welcome.lede') }}</p>
    </div>

    @if ($ready)
        <div class="install-status install-status--ready" role="status">
            <span class="install-status__icon" aria-hidden="true">✓</span>
            <div>
                <p class="install-status__title">{{ __('installer.welcome.ready_title') }}</p>
                <p class="install-status__text">{{ __('installer.welcome.ready_text') }}</p>
            </div>
        </div>
    @else
        <div class="install-status install-status--blocked" role="alert">
            <span class="install-status__icon" aria-hidden="true">!</span>
            <div>
                <p class="install-status__title">{{ __('installer.welcome.blocked_title') }}</p>
                <p class="install-status__text">{{ __('installer.welcome.blocked_text') }}</p>
            </div>
        </div>

        <ul class="install-checks" role="list">
            @foreach ($failures as $check)
                <li class="install-checks__item is-fail">
                    <span class="install-checks__status" aria-hidden="true">✕</span>
                    <div>
                        <p class="install-checks__label">{{ __($check->label) }}</p>
                        @if ($check->detail)
                            <p class="install-checks__detail">{{ $check->detail }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($warnings !== [])
        <details class="install-warnings">
            <summary>
                <span class="install-warnings__label">{{ __('installer.welcome.warnings_summary', ['count' => count($warnings)]) }}</span>
                <span class="install-warnings__chevron" aria-hidden="true">
                    <x-ag.icon name="chevron-down" :size="16" />
                </span>
            </summary>
            <ul class="install-checks install-checks--compact" role="list">
                @foreach ($warnings as $check)
                    <li class="install-checks__item is-warn">
                        <span class="install-checks__status" aria-hidden="true">!</span>
                        <div>
                            <p class="install-checks__label">{{ __($check->label) }}</p>
                            @if ($check->detail)
                                <p class="install-checks__detail">{{ $check->detail }}</p>
                            @endif
                            @if ($check->technicalDetail)
                                <details class="install-warnings__technical">
                                    <summary>
                                        <span>{{ __('installer.welcome.technical_details') }}</span>
                                        <span class="install-warnings__technical-chevron" aria-hidden="true">
                                            <x-ag.icon name="chevron-down" :size="14" />
                                        </span>
                                    </summary>
                                    <p class="install-checks__detail">{{ $check->technicalDetail }}</p>
                                </details>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif

    <p class="install-welcome__doctor">{{ __('installer.welcome.doctor_hint') }}</p>

    @error('welcome')
        <p class="ag-field__error" role="alert">{{ $message }}</p>
    @enderror

    <div class="install-panel__actions">
        <button
            type="button"
            class="ag-btn ag-btn--primary"
            wire:click="next"
            @disabled(! $ready)
        >
            {{ __('installer.actions.continue') }}
        </button>
    </div>
</div>
