@php
    $scope = $scope ?? 'billing';
    $show = ($addressSuggestScope ?? '') === $scope && ($addressSuggestions ?? []) !== [];
@endphp
@if ($show)
    <ul class="store-suggest__list" role="listbox" aria-label="{{ __('storefront.checkout.address_suggestions') }}">
        @foreach ($addressSuggestions as $index => $suggestion)
            <li role="option">
                <button
                    type="button"
                    class="store-suggest__option"
                    wire:mousedown.prevent="applyAddressSuggestion({{ $index }})"
                >
                    <span class="store-suggest__primary">{{ $suggestion['label'] }}</span>
                    @if (! empty($suggestion['secondary']))
                        <span class="store-suggest__secondary">{{ $suggestion['secondary'] }}</span>
                    @endif
                    @if (($suggestion['source'] ?? '') === 'saved')
                        <span class="store-suggest__badge">{{ __('storefront.checkout.saved_suggestion') }}</span>
                    @endif
                </button>
            </li>
        @endforeach
    </ul>
@endif
