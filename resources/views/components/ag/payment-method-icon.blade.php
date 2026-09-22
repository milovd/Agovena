@props([
    'icon' => null,
    'methodId' => null,
    'size' => 36,
])

@php
    $iconValue = is_string($icon) ? trim($icon) : '';
    $remoteIcon = filter_var($iconValue, FILTER_VALIDATE_URL) ? $iconValue : null;
    $localIcon = str_starts_with($iconValue, 'ag:') ? substr($iconValue, 3) : null;

    if (! is_string($localIcon) || preg_match('/^payment-method\/[a-z0-9_]+$/', $localIcon) !== 1) {
        $localIcon = null;
    }

    if ($localIcon === null && is_string($methodId)) {
        $normalizedMethodId = preg_replace('/^.*:/', '', trim($methodId));
        if (is_string($normalizedMethodId) && preg_match('/^[a-z0-9_]+$/', $normalizedMethodId) === 1) {
            $candidate = 'payment-method/'.$normalizedMethodId;
            if (is_file(public_path('images/payment-methods/'.$normalizedMethodId.'.svg'))) {
                $localIcon = $candidate;
            }
        }
    }
@endphp

@if ($remoteIcon)
    <img
        {{ $attributes->class(['ag-payment-method-icon'])->merge(['aria-hidden' => 'true', 'alt' => '', 'width' => $size, 'height' => $size, 'loading' => 'lazy']) }}
        src="{{ $remoteIcon }}"
    >
@elseif ($localIcon)
    <x-ag.icon :name="$localIcon" :size="$size" {{ $attributes->class(['ag-payment-method-icon']) }} />
@else
    <x-ag.icon name="payment-bank" :size="$size" {{ $attributes->class(['ag-payment-method-icon']) }} />
@endif
