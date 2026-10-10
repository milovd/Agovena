<div class="admin-page ag-list-page c-subscriptions c-subscriptions--plans">
    <x-ag.page-header :heading="__('admin.plan_changes.title')" :lede="__('admin.plan_changes.lede')" />
    @include('livewire.admin.subscriptions.partials.tabs', ['activeTab' => 'plan-changes'])

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    @can('plan-changes.manage')
        <form wire:submit="save" class="ag-section ag-form c-subscriptions__plan-form" novalidate>
            <header class="ag-section__header">
                <div class="c-subscriptions__heading">
                    <x-ag.icon-tile name="repeat" tone="blue" :size="18" />
                    <h2 class="ag-section__title">{{ __('admin.plan_changes.title') }}</h2>
                </div>
            </header>
            <div class="ag-section__body">
                <div class="c-subscriptions__plan-fields">
                    <div class="ag-field">
                        <label class="ag-field__label" for="plan-from">{{ __('admin.plan_changes.from') }}</label>
                        <select id="plan-from" class="ag-select" wire:model.number="from_product_id" required>
                            <option value="">{{ __('common.select_placeholder') }}</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                        @error('from_product_id') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="plan-to">{{ __('admin.plan_changes.to') }}</label>
                        <select id="plan-to" class="ag-select" wire:model.number="to_product_id" required>
                            <option value="">{{ __('common.select_placeholder') }}</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                        @error('to_product_id') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="plan-type">{{ __('admin.plan_changes.type') }}</label>
                        <select id="plan-type" class="ag-select" wire:model="change_type">
                            @foreach (['upgrade', 'downgrade', 'switch'] as $type)
                                <option value="{{ $type }}">{{ __('admin.plan_changes.types.'.$type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ag-field">
                        <label class="ag-field__label" for="plan-timing">{{ __('admin.plan_changes.timing') }}</label>
                        <select id="plan-timing" class="ag-select" wire:model="timing">
                            @foreach (['immediate', 'next_period'] as $value)
                                <option value="{{ $value }}">{{ __('admin.plan_changes.timings.'.$value) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="c-subscriptions__plan-actions">
                    <x-ag.switch id="plan-active" wire:model="is_active" :label="__('common.active')" />
                    <button class="ag-btn ag-btn--primary" type="submit">{{ __('common.save') }}</button>
                </div>
            </div>
        </form>
    @endcan

    @if ($changes->isEmpty())
        <x-ag.empty :title="__('admin.plan_changes.empty')">
            <x-slot:icon><x-ag.icon name="repeat" :size="24" /></x-slot:icon>
        </x-ag.empty>
    @else
        <div class="ag-table-wrap c-subscriptions__plan-table-wrap" role="region" aria-label="{{ __('admin.plan_changes.title') }}" tabindex="0">
            <table class="ag-table c-subscriptions__plan-table">
                <thead><tr>
                    <th scope="col">{{ __('admin.plan_changes.from') }}</th>
                    <th scope="col">{{ __('admin.plan_changes.to') }}</th>
                    <th scope="col">{{ __('admin.plan_changes.type') }}</th>
                    <th scope="col">{{ __('admin.plan_changes.timing') }}</th>
                    <th scope="col">{{ __('common.status') }}</th>
                    <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                </tr></thead>
                <tbody>
                    @foreach ($changes as $change)
                        <tr wire:key="plan-change-{{ $change->id }}">
                            <td class="c-subscriptions__identity">
                                <div class="c-subscriptions__identity-inner">
                                    <x-ag.icon-tile name="repeat" tone="blue" :size="16" />
                                    <span class="ag-table__name">{{ $change->fromProduct->name }}</span>
                                </div>
                            </td>
                            <td class="c-subscriptions__detail" data-label="{{ __('admin.plan_changes.to') }}">{{ $change->toProduct->name }}</td>
                            <td class="c-subscriptions__detail" data-label="{{ __('admin.plan_changes.type') }}">{{ __('admin.plan_changes.types.'.$change->change_type) }}</td>
                            <td class="c-subscriptions__detail" data-label="{{ __('admin.plan_changes.timing') }}">{{ __('admin.plan_changes.timings.'.$change->timing) }}</td>
                            <td class="c-subscriptions__detail" data-label="{{ __('common.status') }}"><x-ag.badge :variant="$change->is_active ? 'success' : 'muted'">{{ $change->is_active ? __('common.active') : __('common.inactive') }}</x-ag.badge></td>
                            <td class="ag-table__actions c-subscriptions__row-actions">
                                @can('plan-changes.manage')
                                    <x-ag.row-actions>
                                        <button class="ag-icon-btn" type="button" wire:click="delete({{ $change->id }})" wire:confirm="{{ __('admin.plan_changes.delete_confirm') }}" title="{{ __('common.delete') }}" aria-label="{{ __('common.delete') }}: {{ $change->fromProduct->name }} → {{ $change->toProduct->name }}">
                                            <x-ag.icon name="trash" :size="16" />
                                        </button>
                                    </x-ag.row-actions>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
