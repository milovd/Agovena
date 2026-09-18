<div class="install-wizard">
    <section class="install-panel" aria-labelledby="install-step-heading">
        <header class="install-panel__brand">
            <x-ag.logo class="install-panel__logo" :alt="__('installer.brand_alt')" />
            <div class="install-panel__brand-copy">
                <p class="install-panel__product">Agovena</p>
                <p class="install-panel__tagline">{{ __('installer.tagline') }}</p>
            </div>
        </header>

        @if ($step !== 'complete')
            <nav class="install-progress" aria-label="{{ __('installer.progress_aria') }}">
                <ol class="install-progress__list">
                    @foreach ($progressSteps as $index => $key)
                        <li class="install-progress__item @if ($index < $stepIndex) is-done @elseif ($index === $stepIndex) is-current @endif">
                            <span class="install-progress__index" aria-hidden="true">{{ $index + 1 }}</span>
                            <span class="install-progress__label">{{ __('installer.steps.'.$key) }}</span>
                        </li>
                    @endforeach
                </ol>
                <p class="install-progress__status" aria-live="polite">
                    {{ __('installer.progress_status', ['current' => $stepIndex + 1, 'total' => $stepCount]) }}
                </p>
            </nav>
        @endif

        <div class="install-panel__body">
        @if ($step === 'welcome')
            @include('installer.steps.welcome')
        @elseif ($step === 'owner')
            @include('installer.steps.owner')
        @elseif ($step === 'store')
            @include('installer.steps.store')
        @elseif ($step === 'catalog')
            @include('installer.steps.catalog')
        @elseif ($step === 'regional')
            @include('installer.steps.regional')
        @elseif ($step === 'branding')
            @include('installer.steps.branding')
        @elseif ($step === 'theme')
            @include('installer.steps.theme')
        @else
            @include('installer.steps.complete')
        @endif
        </div>
    </section>
</div>
