<div class="store-account-addresses">
@if ($addresses->isNotEmpty())
    <ul class="store-address-list" role="list">
        @foreach ($addresses as $address)
            <li class="store-address-list__item">
                <div>
                    <strong>{{ $address->label ?: $address->name }}</strong>
                    <p>{{ $address->name }}</p>
                    <p>{{ $address->line1 }}</p>
                    @if ($address->line2)<p>{{ $address->line2 }}</p>@endif
                    <p>{{ $address->postal_code }} {{ $address->city }}</p>
                    <p>{{ $address->country }}</p>
                    <p class="store-address-list__flags">
                        @if ($address->is_default_billing)
                            <span>{{ __('customer.addresses.default_billing') }}</span>
                        @endif
                        @if ($address->is_default_shipping)
                            <span>{{ __('customer.addresses.default_shipping') }}</span>
                        @endif
                    </p>
                </div>
                <div class="store-address-list__actions">
                    <button type="button" class="store-btn store-btn--ghost" wire:click="edit({{ $address->id }})">{{ __('customer.addresses.edit') }}</button>
                    <button type="button" class="store-btn store-btn--ghost" wire:click="delete({{ $address->id }})" wire:confirm="{{ __('customer.addresses.delete_confirm') }}">{{ __('customer.addresses.delete') }}</button>
                </div>
            </li>
        @endforeach
    </ul>
@else
    <x-ag.empty :title="__('customer.addresses.empty')">
        <x-slot:icon>
            <x-ag.icon name="store" :size="20" />
        </x-slot:icon>
        <x-slot:description>{{ __('customer.addresses.empty_hint') }}</x-slot:description>
    </x-ag.empty>
@endif

<div class="store-account-addresses__form">
    <h3 class="store-account-panel__subheading">{{ $editingId ? __('customer.addresses.edit_heading') : __('customer.addresses.add_heading') }}</h3>
    <p class="store-field__hint">{{ __('customer.addresses.form_lede') }}</p>

    <form class="store-auth__form" wire:submit="save">
        <div class="store-field">
            <label class="store-field__label" for="address-label">{{ __('customer.addresses.label') }}</label>
            <input id="address-label" class="store-input" type="text" wire:model="label">
            @error('label') <p class="store-field__error">{{ $message }}</p> @enderror
        </div>
        @include('partials.custom-property-fields', [
            'propertyDefinitions' => $propertyDefinitions,
            'propertyValues' => $propertyValues,
            'actor' => $actor,
            'addressSuggestionScope' => 'account',
        ])
        <label class="store-check">
            <input type="checkbox" wire:model="is_default_billing">
            <span>{{ __('customer.addresses.default_billing') }}</span>
        </label>
        <label class="store-check">
            <input type="checkbox" wire:model="is_default_shipping">
            <span>{{ __('customer.addresses.default_shipping') }}</span>
        </label>

        <div class="store-account-panel__actions">
            <button class="store-btn store-btn--primary" type="submit">{{ __('customer.addresses.save') }}</button>
            @if ($editingId)
                <button class="store-btn store-btn--ghost" type="button" wire:click="resetForm">{{ __('customer.addresses.cancel') }}</button>
            @endif
        </div>
    </form>
</div>
</div>
