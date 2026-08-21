@php
    $currentPosition = collect($progressItems)->first(fn ($item) => $item->isCurrent())?->position ?? 1;
    $stepTotal = $progressItems[0]->total ?? count($progressItems);
    $progressPercent = $progressPercent ?? (int) round((($currentPosition - 1) / max(1, $stepTotal)) * 100);
    $interactive = $interactive ?? true;
@endphp

<nav class="store-stepper" aria-label="{{ __('storefront.checkout.progress_aria') }}" data-testid="checkout-stepper">
    <div class="store-stepper__mobile">
        <p class="store-stepper__mobile-meta" aria-live="polite">
            {{ __('storefront.checkout.step_of', [
                'current' => $currentPosition,
                'total' => $stepTotal,
                'label' => __($currentLabelKey),
            ]) }}
        </p>
        <div class="store-stepper__bar" aria-hidden="true">
            <span class="store-stepper__bar-fill" style="width: {{ $progressPercent }}%"></span>
        </div>
    </div>
    <ol class="store-stepper__list">
        @foreach ($progressItems as $item)
            <li class="store-stepper__item store-stepper__item--{{ $item->state }}">
                @if ($interactive && $item->isCompleted())
                    <button type="button" class="store-stepper__link" wire:click="goToStep('{{ $item->step->value }}')">
                        <span class="store-stepper__mark store-stepper__mark--done" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
                        </span>
                        <span class="store-stepper__label">{{ __($item->step->labelKey()) }}</span>
                        <span class="visually-hidden">{{ __('storefront.checkout.step_completed') }}</span>
                    </button>
                @elseif ($item->isCompleted())
                    <span class="store-stepper__link">
                        <span class="store-stepper__mark store-stepper__mark--done" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
                        </span>
                        <span class="store-stepper__label">{{ __($item->step->labelKey()) }}</span>
                        <span class="visually-hidden">{{ __('storefront.checkout.step_completed') }}</span>
                    </span>
                @elseif ($item->isCurrent())
                    <span class="store-stepper__link" aria-current="step">
                        <span class="store-stepper__mark" aria-hidden="true">{{ $item->position }}</span>
                        <span class="store-stepper__label">{{ __($item->step->labelKey()) }}</span>
                        <span class="visually-hidden">{{ __('storefront.checkout.step_current') }}</span>
                    </span>
                @else
                    <span class="store-stepper__link store-stepper__link--upcoming">
                        <span class="store-stepper__mark" aria-hidden="true">{{ $item->position }}</span>
                        <span class="store-stepper__label">{{ __($item->step->labelKey()) }}</span>
                        <span class="visually-hidden">{{ __('storefront.checkout.step_upcoming') }}</span>
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
