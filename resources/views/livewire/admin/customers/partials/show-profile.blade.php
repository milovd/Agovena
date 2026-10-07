{{-- Customer workspace: profile card. Part of livewire.admin.customers.show. --}}
<section class="customer-workspace__card customer-workspace__card--profile" aria-labelledby="profile-heading">
    <header class="customer-workspace__section-header">
        <div>
            <p class="customer-workspace__eyebrow">{{ __('admin.customers.profile_eyebrow') }}</p>
            <h2 id="profile-heading" class="customer-workspace__section-title">{{ __('admin.customers.workspace_profile') }}</h2>
            <p class="customer-workspace__section-lede">{{ __('admin.customers.workspace_profile_lede') }}</p>
        </div>
        @can('customers.manage')
            <span class="customer-workspace__edit-hint">{{ __('admin.customers.editable_by_staff') }}</span>
        @endcan
    </header>

    @can('customers.manage')
        @if (! $customer->anonymized_at)
            <form class="customer-workspace__profile-form" wire:submit="saveProfile">
                <div class="customer-workspace__field-grid">
                    <div class="ag-field">
                        <label class="ag-field__label" for="customer-name">{{ __('common.name') }}</label>
                        <input id="customer-name" class="ag-input" type="text" wire:model="name" required>
                        @error('name') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="customer-email">{{ __('admin.customers.email') }}</label>
                        <input id="customer-email" class="ag-input" type="email" wire:model="email" required>
                        @error('email') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                        <p class="ag-field__help">{{ __('admin.customers.email_change_help') }}</p>
                    </div>
                </div>
                <button class="ag-btn ag-btn--primary" type="submit">{{ __('admin.customers.save_profile') }}</button>
            </form>
        @else
            <p class="customer-workspace__locked">{{ __('admin.customers.anonymized_locked') }}</p>
        @endif
    @else
        <dl class="customer-workspace__details">
            <div><dt>{{ __('common.name') }}</dt><dd>{{ $customer->name }}</dd></div>
            <div><dt>{{ __('admin.customers.email') }}</dt><dd>{{ $customer->email }}</dd></div>
        </dl>
    @endcan

    @if (($propertyDefinitions ?? collect())->isNotEmpty() && ! $customer->anonymized_at)
        <div class="customer-workspace__subsection">
            <header class="customer-workspace__subsection-header">
                <h3>{{ __('admin.customers.contact_details_heading') }}</h3>
                <p>{{ __('admin.customers.contact_details_lede') }}</p>
            </header>
            @can('customers.manage')
                <form class="customer-workspace__property-form" wire:submit="saveProperties">
                    <div class="customer-workspace__field-grid customer-workspace__field-grid--properties">
                        @include('partials.custom-property-fields')
                    </div>
                    <button class="ag-btn ag-btn--secondary" type="submit">{{ __('admin.customers.save_contact_details') }}</button>
                </form>
            @else
                <div class="customer-workspace__field-grid customer-workspace__field-grid--properties">
                    @include('partials.custom-property-fields', ['propertyEditable' => false])
                </div>
            @endcan
        </div>
    @endif

    <div class="customer-workspace__subsection">
        <header class="customer-workspace__subsection-header">
            <h3>{{ __('admin.customers.saved_addresses_heading') }}</h3>
            <p>{{ __('admin.customers.saved_addresses_lede') }}</p>
        </header>
        <div class="customer-workspace__address-list">
            @forelse ($addresses as $address)
                <article class="customer-workspace__address" wire:key="customer-address-{{ $address->id }}">
                    <div class="customer-workspace__address-title">
                        <strong>{{ $address->label ?: __('admin.customers.address_label') }}</strong>
                        <div>
                            @if ($address->is_default_billing)
                                <span class="ag-badge">{{ __('admin.customers.default_billing') }}</span>
                            @endif
                            @if ($address->is_default_shipping)
                                <span class="ag-badge">{{ __('admin.customers.default_shipping') }}</span>
                            @endif
                        </div>
                    </div>
                    <p>
                        {{ $address->name }}<br>
                        @if ($address->company){{ $address->company }}<br>@endif
                        {{ $address->line1 }}@if ($address->line2), {{ $address->line2 }}@endif<br>
                        {{ $address->postal_code }} {{ $address->city }}@if ($address->region), {{ $address->region }}@endif<br>
                        {{ $address->country }}
                    </p>
                </article>
            @empty
                <p class="customer-workspace__empty">{{ __('admin.customers.no_addresses') }}</p>
            @endforelse
        </div>
    </div>
</section>
