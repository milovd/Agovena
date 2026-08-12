<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <h1 class="store-account-panel__title">{{ __('customer.addresses.heading') }}</h1>
            <p class="store-account-panel__lede">{{ __('customer.addresses.lede') }}</p>
        </header>

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
            <p class="store-account-panel__empty">{{ __('customer.addresses.empty') }}</p>
        @endif

        <hr class="store-account-panel__divider">

        <h2>{{ $editingId ? __('customer.addresses.edit_heading') : __('customer.addresses.add_heading') }}</h2>

        <form class="store-auth__form" wire:submit="save">
            <div class="store-field">
                <label class="store-field__label" for="address-label">{{ __('customer.addresses.label') }}</label>
                <input id="address-label" class="store-input" type="text" wire:model="label">
                @error('label') <p class="store-field__error">{{ $message }}</p> @enderror
            </div>
            <div class="store-field">
                <label class="store-field__label" for="address-name">{{ __('customer.addresses.name') }}</label>
                <input id="address-name" class="store-input" type="text" wire:model="name" required>
                @error('name') <p class="store-field__error">{{ $message }}</p> @enderror
            </div>
            <div class="store-field">
                <label class="store-field__label" for="address-company">{{ __('customer.addresses.company') }}</label>
                <input id="address-company" class="store-input" type="text" wire:model="company">
            </div>
            <div class="store-field">
                <label class="store-field__label" for="address-line1">{{ __('customer.addresses.line1') }}</label>
                <input id="address-line1" class="store-input" type="text" wire:model="line1" required>
                @error('line1') <p class="store-field__error">{{ $message }}</p> @enderror
            </div>
            <div class="store-field">
                <label class="store-field__label" for="address-line2">{{ __('customer.addresses.line2') }}</label>
                <input id="address-line2" class="store-input" type="text" wire:model="line2">
            </div>
            <div class="store-field">
                <label class="store-field__label" for="address-city">{{ __('customer.addresses.city') }}</label>
                <input id="address-city" class="store-input" type="text" wire:model="city" required>
                @error('city') <p class="store-field__error">{{ $message }}</p> @enderror
            </div>
            <div class="store-field">
                <label class="store-field__label" for="address-region">{{ __('customer.addresses.region') }}</label>
                <input id="address-region" class="store-input" type="text" wire:model="region">
            </div>
            <div class="store-field">
                <label class="store-field__label" for="address-postal">{{ __('customer.addresses.postal_code') }}</label>
                <input id="address-postal" class="store-input" type="text" wire:model="postal_code" required>
                @error('postal_code') <p class="store-field__error">{{ $message }}</p> @enderror
            </div>
            <div class="store-field">
                <label class="store-field__label" for="address-country">{{ __('customer.addresses.country') }}</label>
                <select id="address-country" class="store-input" wire:model="country" required>
                    @foreach ($countries as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('country') <p class="store-field__error">{{ $message }}</p> @enderror
            </div>
            <div class="store-field">
                <label class="store-field__label" for="address-phone">{{ __('customer.addresses.phone') }}</label>
                <input id="address-phone" class="store-input" type="text" wire:model="phone">
            </div>
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
    </section>
</div>
