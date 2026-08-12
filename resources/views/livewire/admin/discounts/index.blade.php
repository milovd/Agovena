<div class="admin-page">
    <x-ag.page-header :heading="__('admin.discounts.title')" :lede="__('admin.discounts.lede')">
        <x-slot:actions>
            @can('discounts.manage')
                <button type="button" class="ag-btn ag-btn--primary" wire:click="create">{{ __('admin.discounts.add') }}</button>
            @endcan
        </x-slot:actions>
    </x-ag.page-header>

    @error('delete') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror

    @if ($showForm)
        <form wire:submit="save" class="admin-panel ag-form" novalidate>
            <h3 class="admin-panel__title">{{ $editingId ? __('admin.discounts.edit') : __('admin.discounts.new') }}</h3>
            <div class="ag-field">
                <label class="ag-field__label" for="discount-code">{{ __('admin.discounts.code') }}</label>
                <input id="discount-code" class="ag-input" wire:model="code" required>
                @error('code') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="discount-type">{{ __('common.type') }}</label>
                <select id="discount-type" class="ag-input ag-select" wire:model.live="type">
                    <option value="percent">{{ __('admin.discounts.percent') }}</option>
                    <option value="fixed">{{ __('admin.discounts.fixed') }}</option>
                </select>
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="discount-value">{{ __('admin.discounts.value') }}</label>
                <input id="discount-value" class="ag-input" type="number" min="0" wire:model.number="value" required>
                <p class="ag-field__help">{{ $type === 'fixed' ? __('admin.discounts.fixed_help') : __('admin.discounts.percent_help') }}</p>
                @error('value') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            @if ($type === 'fixed')
                <div class="ag-field">
                    <label class="ag-field__label" for="discount-currency">{{ __('admin.discounts.currency') }}</label>
                    <input id="discount-currency" class="ag-input" maxlength="3" wire:model="currency" required>
                    @error('currency') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
            @endif
            <div class="ag-field">
                <label class="ag-field__label" for="discount-minimum">{{ __('admin.discounts.minimum') }}</label>
                <input id="discount-minimum" class="ag-input" type="number" min="0" wire:model.number="min_subtotal_amount">
                <p class="ag-field__help">{{ __('admin.discounts.minor_units_help') }}</p>
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="discount-starts">{{ __('admin.discounts.starts_at') }}</label>
                <input id="discount-starts" class="ag-input" type="datetime-local" wire:model="starts_at">
                @error('starts_at') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="discount-ends">{{ __('admin.discounts.ends_at') }}</label>
                <input id="discount-ends" class="ag-input" type="datetime-local" wire:model="ends_at">
                @error('ends_at') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="discount-max">{{ __('admin.discounts.max_uses') }}</label>
                <input id="discount-max" class="ag-input" type="number" min="1" wire:model.number="max_uses">
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="discount-customer-max">{{ __('admin.discounts.max_per_customer') }}</label>
                <input id="discount-customer-max" class="ag-input" type="number" min="1" wire:model.number="max_uses_per_customer">
            </div>
            <x-ag.switch id="discount-active" wire:model="is_active" :label="__('common.active')" />
            <div class="ag-form__actions">
                <button class="ag-btn ag-btn--primary" type="submit">{{ __('common.save') }}</button>
                <button class="ag-btn ag-btn--secondary" type="button" wire:click="cancel">{{ __('common.cancel') }}</button>
            </div>
        </form>
    @endif

    @if ($discounts->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.discounts.empty') }}</p>
            <p class="ag-empty__text">{{ __('admin.discounts.empty_text') }}</p>
        </div>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
                <thead><tr>
                    <th>{{ __('admin.discounts.code') }}</th>
                    <th>{{ __('common.type') }}</th>
                    <th>{{ __('admin.discounts.value') }}</th>
                    <th>{{ __('admin.discounts.uses') }}</th>
                    <th>{{ __('common.status') }}</th>
                    <th><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                </tr></thead>
                <tbody>
                    @foreach ($discounts as $discount)
                        <tr wire:key="discount-{{ $discount->id }}">
                            <td><code>{{ $discount->code }}</code></td>
                            <td>{{ __('admin.discounts.'.$discount->type) }}</td>
                            <td>{{ $discount->type === 'percent' ? $discount->value.'%' : \App\Support\MoneyFormatter::format($discount->value, $discount->currency) }}</td>
                            <td>{{ $discount->redemptions_count }}{{ $discount->max_uses ? ' / '.$discount->max_uses : '' }}</td>
                            <td><span class="ag-badge">{{ $discount->is_active ? __('common.active') : __('common.inactive') }}</span></td>
                            <td>
                                @can('discounts.manage')
                                    <button class="ag-btn ag-btn--ghost" type="button" wire:click="edit({{ $discount->id }})">{{ __('common.edit') }}</button>
                                    <button class="ag-btn ag-btn--ghost" type="button" wire:click="delete({{ $discount->id }})" wire:confirm="{{ __('admin.discounts.delete_confirm') }}">{{ __('common.delete') }}</button>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $discounts->links() }}
    @endif
</div>
