<div class="admin-page">
    <div class="admin-page__header">
        <div>
            <h2 class="admin-page__heading">{{ __('admin.currencies.title') }}</h2>
            <p class="admin-page__lede">
                {{ __('admin.currencies.lede') }}
                {{ __('admin.currencies.base_currency') }}
                <strong>{{ $baseCurrency }}</strong>
            </p>
        </div>
        <div class="admin-page__actions">
            @can('currencies.update')
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="syncRates" wire:loading.attr="disabled">
                    {{ __('admin.currencies.sync_rates') }}
                </button>
            @endcan
            @can('currencies.create')
                <button type="button" class="ag-btn ag-btn--primary" wire:click="create">{{ __('admin.currencies.add') }}</button>
            @endcan
        </div>
    </div>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    @if ($showForm)
        <form wire:submit="save" class="admin-panel ag-form" novalidate>
            <h3 class="admin-panel__title">{{ $editingId ? __('admin.currencies.edit') : __('admin.currencies.new') }}</h3>
            <div class="ag-field">
                <label class="ag-field__label" for="currency-code">{{ __('admin.currencies.code') }}</label>
                <input id="currency-code" class="ag-input" type="text" maxlength="3" wire:model="code" required @disabled($editingId !== null)>
                <p class="ag-field__help">{{ __('admin.currencies.code_hint') }}</p>
                @error('code') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="currency-name">{{ __('admin.currencies.display_name') }}</label>
                <input id="currency-name" class="ag-input" type="text" wire:model="name" required>
                @error('name') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="currency-prefix">{{ __('admin.currencies.prefix') }}</label>
                <input id="currency-prefix" class="ag-input" type="text" wire:model="prefix" placeholder="€">
                <p class="ag-field__help">{{ __('admin.currencies.prefix_hint') }}</p>
                @error('prefix') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="currency-suffix">{{ __('admin.currencies.suffix') }}</label>
                <input id="currency-suffix" class="ag-input" type="text" wire:model="suffix" placeholder=" kr">
                <p class="ag-field__help">{{ __('admin.currencies.suffix_hint') }}</p>
                @error('suffix') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="currency-precision">{{ __('admin.currencies.precision') }}</label>
                <input id="currency-precision" class="ag-input" type="number" min="0" max="6" wire:model.number="precision" required>
                <p class="ag-field__help">{{ __('admin.currencies.precision_hint') }}</p>
                @error('precision') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="currency-exchange-rate">{{ __('admin.currencies.exchange_rate') }}</label>
                <input id="currency-exchange-rate" class="ag-input" type="text" inputmode="decimal" wire:model="exchange_rate" required @disabled(strtoupper($code) === strtoupper($baseCurrency))>
                <p class="ag-field__help">{{ __('admin.currencies.exchange_rate_hint', ['base' => $baseCurrency]) }}</p>
                @error('exchange_rate') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <p class="ag-field__help" aria-live="polite">
                {{ __('admin.currencies.preview_label') }}
                @php
                    $previewCurrency = new \App\Models\Currency([
                        'prefix' => $prefix,
                        'suffix' => $suffix,
                        'precision' => (int) $precision,
                    ]);
                @endphp
                <strong>{{ $previewCurrency->previewSample() }}</strong>
            </p>
            <x-ag.switch id="currency-active" wire:model="is_active" :label="__('common.active')" />
            <div class="ag-form__actions">
                <button type="submit" class="ag-btn ag-btn--primary">{{ __('common.save') }}</button>
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancel">{{ __('common.cancel') }}</button>
            </div>
        </form>
    @endif

    <div class="ag-table-wrap">
        <table class="ag-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('admin.currencies.code') }}</th>
                    <th scope="col">{{ __('common.name') }}</th>
                    <th scope="col">{{ __('admin.currencies.prefix_column') }}</th>
                    <th scope="col">{{ __('admin.currencies.suffix') }}</th>
                    <th scope="col">{{ __('admin.currencies.precision_column') }}</th>
                    <th scope="col">{{ __('admin.currencies.exchange_rate_column') }}</th>
                    <th scope="col">{{ __('common.preview') }}</th>
                    <th scope="col">{{ __('common.status') }}</th>
                    <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($currencies as $currency)
                    <tr wire:key="currency-{{ $currency->id }}">
                        <td>
                            {{ $currency->code }}
                            @if ($currency->code === $baseCurrency)
                                <span class="ag-badge">{{ __('admin.currencies.base_badge') }}</span>
                            @endif
                        </td>
                        <td>{{ $currency->name }}</td>
                        <td><code>{{ $currency->prefix !== '' ? $currency->prefix : __('common.em_dash') }}</code></td>
                        <td><code>{{ $currency->suffix !== '' ? $currency->suffix : __('common.em_dash') }}</code></td>
                        <td>{{ $currency->normalizedPrecision() }}</td>
                        <td><code>{{ bcadd((string) ($currency->exchange_rate ?? '1'), '0', 8) }}</code></td>
                        <td>{{ $currency->previewSample() }}</td>
                        <td><span class="ag-badge">{{ $currency->is_active ? __('common.active') : __('common.inactive') }}</span></td>
                        <td>
                            @can('currencies.update')
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="edit({{ $currency->id }})">{{ __('common.edit') }}</button>
                            @endcan
                            @if ($canSetBase && $currency->is_active && $currency->code !== $baseCurrency)
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="setAsBase({{ $currency->id }})">{{ __('admin.currencies.set_base') }}</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $currencies->links() }}
</div>
