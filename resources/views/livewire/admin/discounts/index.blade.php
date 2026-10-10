<div class="admin-page ag-list-page admin-page--discounts">
    <x-ag.page-header :heading="__('admin.discounts.title')" :lede="__('admin.discounts.lede')">
        <x-slot:actions>
            @can('discounts.manage')
                <button type="button" class="ag-btn ag-btn--primary" wire:click="create">{{ __('admin.discounts.add') }}</button>
            @endcan
        </x-slot:actions>
    </x-ag.page-header>

    @error('delete') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror

    @if ($showForm)
        <form wire:submit="save" class="ag-section ag-form ag-form--constrained ag-catalog-form" novalidate>
            <header class="ag-section__header ag-catalog-form__header">
                <div class="ag-catalog-form__heading">
                    <x-ag.icon-tile name="ticket" tone="blue" />
                    <h2 class="ag-section__title">{{ $editingId ? __('admin.discounts.edit') : __('admin.discounts.new') }}</h2>
                </div>
            </header>
            <div class="ag-section__body">
                <div class="ag-grid ag-grid--2">
                    <div class="ag-field">
                        <label class="ag-field__label" for="discount-code">{{ __('admin.discounts.code') }}</label>
                        <input id="discount-code" class="ag-input" wire:model="code" required>
                        @error('code') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="discount-type">{{ __('common.type') }}</label>
                        <select id="discount-type" class="ag-select" wire:model.live="type">
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
                    <div class="ag-field ag-grid__span-2 ag-catalog-form__switch">
                        <x-ag.switch id="discount-active" wire:model="is_active" :label="__('common.active')" />
                    </div>
                </div>
                <div class="ag-form__actions">
                    <button class="ag-btn ag-btn--primary" type="submit" wire:loading.attr="disabled" wire:target="save">{{ __('common.save') }}</button>
                    <button class="ag-btn ag-btn--secondary" type="button" wire:click="cancel">{{ __('common.cancel') }}</button>
                </div>
            </div>
        </form>
    @endif

    @if ($discounts->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.discounts.empty') }}</p>
            <p class="ag-empty__text">{{ __('admin.discounts.empty_text') }}</p>
        </div>
    @else
        <div class="ag-table-wrap ag-catalog-table-wrap" role="region" aria-label="{{ __('admin.discounts.title') }}" tabindex="0">
            <table class="ag-table ag-table--discounts">
                <thead><tr>
                    <th scope="col">{{ __('admin.discounts.code') }}</th>
                    <th scope="col">{{ __('common.type') }}</th>
                    <th scope="col">{{ __('admin.discounts.value') }}</th>
                    <th scope="col">{{ __('admin.discounts.uses') }}</th>
                    <th scope="col">{{ __('common.status') }}</th>
                    <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                </tr></thead>
                <tbody>
                    @foreach ($discounts as $discount)
                        <tr wire:key="discount-{{ $discount->id }}">
                            <td class="ag-catalog-cell--identity"><span class="ag-table__primary"><code class="ag-table__name">{{ $discount->code }}</code></span></td>
                            <td class="ag-catalog-cell--detail" data-label="{{ __('common.type') }}">{{ __('admin.discounts.'.$discount->type) }}</td>
                            <td class="ag-catalog-cell--detail" data-label="{{ __('admin.discounts.value') }}">{{ $discount->type === 'percent' ? $discount->value.'%' : \App\Support\MoneyFormatter::format($discount->value, $discount->currency) }}</td>
                            <td class="ag-catalog-cell--detail" data-label="{{ __('admin.discounts.uses') }}">{{ $discount->redemptions_count }}{{ $discount->max_uses ? ' / '.$discount->max_uses : '' }}</td>
                            <td class="ag-catalog-cell--detail" data-label="{{ __('common.status') }}"><x-ag.badge :variant="$discount->is_active ? 'success' : 'muted'">{{ $discount->is_active ? __('common.active') : __('common.inactive') }}</x-ag.badge></td>
                            <td class="ag-table__actions">
                                <div class="ag-row-actions">
                                    @can('discounts.manage')
                                        <button
                                            type="button"
                                            class="ag-icon-btn"
                                            wire:click="edit({{ $discount->id }})"
                                            title="{{ __('admin.discounts.actions.edit') }}"
                                            aria-label="{{ __('admin.discounts.actions.edit_aria', ['code' => $discount->code]) }}"
                                        >
                                            <x-ag.icon name="pencil" :size="16" />
                                        </button>
                                        <button
                                            type="button"
                                            class="ag-icon-btn ag-icon-btn--danger"
                                            wire:click="delete({{ $discount->id }})"
                                            wire:confirm="{{ __('admin.discounts.delete_confirm') }}"
                                            title="{{ __('admin.discounts.actions.delete') }}"
                                            aria-label="{{ __('admin.discounts.actions.delete_aria', ['code' => $discount->code]) }}"
                                        >
                                            <x-ag.icon name="trash" :size="16" />
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="ag-pagination">{{ $discounts->links() }}</div>
    @endif
</div>
