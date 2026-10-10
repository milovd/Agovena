{{-- Customer workspace sidebar: anonymize and delete actions (customers.manage). Part of livewire.admin.customers.show. --}}
@can('customers.manage')
    <section class="customer-workspace__card customer-workspace__card--danger" aria-labelledby="actions-heading">
        <header class="customer-workspace__section-header">
            <x-ag.icon name="lock" :size="20" class="customer-workspace__section-icon" />
            <div>
                <p class="customer-workspace__eyebrow">{{ __('admin.customers.actions_eyebrow') }}</p>
                <h2 id="actions-heading" class="customer-workspace__section-title">{{ __('admin.customers.actions_heading') }}</h2>
                <p class="customer-workspace__section-lede">{{ __('admin.customers.actions_lede') }}</p>
            </div>
        </header>
        @if (! $customer->anonymized_at)
            <div class="customer-workspace__danger-action">
                <strong>{{ __('admin.customers.anonymize_heading') }}</strong>
                <p>{{ __('admin.customers.anonymize_lede') }}</p>
                <button class="ag-btn ag-btn--danger-outline" type="button" wire:click="anonymize" wire:confirm="{{ __('admin.customers.anonymize_confirm') }}">{{ __('admin.customers.anonymize') }}</button>
            </div>
        @endif
        <div class="customer-workspace__danger-action">
            <strong>{{ __('admin.customers.full_delete_heading') }}</strong>
            <p>{{ __('admin.customers.full_delete_lede') }}</p>
            @if ($fullDeleteBlockers === [])
                <button class="ag-btn ag-btn--danger" type="button" wire:click="fullDelete" wire:confirm="{{ __('admin.customers.full_delete_confirm') }}">{{ __('admin.customers.full_delete') }}</button>
            @else
                <p class="customer-workspace__blocked"><strong>{{ __('admin.customers.full_delete_unavailable') }}</strong></p>
                <ul class="customer-workspace__blocked-list">
                    @foreach ($fullDeleteBlockers as $blocker)
                        <li>{{ $blocker }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endcan
