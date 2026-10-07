{{-- Delivery and returns perk cards. Expects $showDeliveryCard, $showReturnsCard and their titles/texts. --}}
@if ($showDeliveryCard || $showReturnsCard)
<div class="store-product__perks" role="list">
    @if ($showDeliveryCard)
    <div class="store-product__perk" role="listitem">
        <span class="store-product__perk-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h13l2 7H6"/><circle cx="9" cy="19" r="1"/><circle cx="17" cy="19" r="1"/></svg>
        </span>
        <div>
            <p class="store-product__perk-title">{{ $deliveryTitle }}</p>
            <p class="store-product__perk-text">{{ $deliveryText }}</p>
        </div>
    </div>
    @endif
    @if ($showReturnsCard)
    <div class="store-product__perk" role="listitem">
        <span class="store-product__perk-icon" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16v12H4z"/><path d="M8 7V5h8v2"/></svg>
        </span>
        <div>
            <p class="store-product__perk-title">{{ $returnsTitle }}</p>
            <p class="store-product__perk-text">{{ $returnsText }}</p>
        </div>
    </div>
    @endif
</div>
@endif
