<div class="admin-page">
    <x-ag.page-header :heading="__('admin.plan_changes.title')" :lede="__('admin.plan_changes.lede')" />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    @can('plan-changes.manage')
        <form wire:submit="save" class="admin-panel ag-form" novalidate>
            <div class="ag-field">
                <label class="ag-field__label" for="plan-from">{{ __('admin.plan_changes.from') }}</label>
                <select id="plan-from" class="ag-select" wire:model.number="from_product_id" required>
                    <option value="">{{ __('common.select') }}</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
                @error('from_product_id') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="plan-to">{{ __('admin.plan_changes.to') }}</label>
                <select id="plan-to" class="ag-select" wire:model.number="to_product_id" required>
                    <option value="">{{ __('common.select') }}</option>
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
            <x-ag.switch id="plan-active" wire:model="is_active" :label="__('common.active')" />
            <button class="ag-btn ag-btn--primary" type="submit">{{ __('common.save') }}</button>
        </form>
    @endcan

    <div class="ag-table-wrap">
        <table class="ag-table">
            <thead><tr>
                <th>{{ __('admin.plan_changes.from') }}</th>
                <th>{{ __('admin.plan_changes.to') }}</th>
                <th>{{ __('admin.plan_changes.type') }}</th>
                <th>{{ __('admin.plan_changes.timing') }}</th>
                <th>{{ __('common.status') }}</th>
                <th><span class="visually-hidden">{{ __('common.actions') }}</span></th>
            </tr></thead>
            <tbody>
                @forelse ($changes as $change)
                    <tr wire:key="plan-change-{{ $change->id }}">
                        <td>{{ $change->fromProduct->name }}</td>
                        <td>{{ $change->toProduct->name }}</td>
                        <td>{{ __('admin.plan_changes.types.'.$change->change_type) }}</td>
                        <td>{{ __('admin.plan_changes.timings.'.$change->timing) }}</td>
                        <td>{{ $change->is_active ? __('common.active') : __('common.inactive') }}</td>
                        <td>
                            @can('plan-changes.manage')
                                <button class="ag-btn ag-btn--ghost" type="button" wire:click="delete({{ $change->id }})">{{ __('common.delete') }}</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">{{ __('admin.plan_changes.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
