<div class="install-wizard">
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

    <section class="install-panel" aria-labelledby="install-step-heading">
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
    </section>
</div>
