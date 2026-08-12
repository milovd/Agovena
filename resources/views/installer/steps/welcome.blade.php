@php
    /** @var list<\App\Agovena\Installation\RequirementCheck> $checks */
@endphp

<h1 id="install-step-heading" class="install-panel__title">{{ __('installer.welcome.heading') }}</h1>
<p class="install-panel__lede">{{ __('installer.welcome.lede') }}</p>

<ul class="install-checks" role="list">
    @foreach ($checks as $check)
        <li class="install-checks__item @if ($check->passed) is-pass @elseif ($check->required) is-fail @else is-warn @endif">
            <span class="install-checks__status" aria-hidden="true">
                @if ($check->passed)
                    ✓
                @elseif ($check->required)
                    ✕
                @else
                    !
                @endif
            </span>
            <div>
                <p class="install-checks__label">{{ __($check->label) }}</p>
                @if ($check->detail)
                    <p class="install-checks__detail">{{ $check->detail }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ul>

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
