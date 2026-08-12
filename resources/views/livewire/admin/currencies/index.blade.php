<div class="admin-page">
    <div class="admin-page__header">
        <div>
            <h2 class="admin-page__heading">Currencies</h2>
            <p class="admin-page__lede">
                Configure ISO codes, symbols, and decimal precision. Amounts stay in integer minor units
                (for example cents when precision is 2). Base currency:
                <strong>{{ $baseCurrency }}</strong>
            </p>
        </div>
        @can('currencies.create')
            <button type="button" class="ag-btn ag-btn--primary" wire:click="create">Add currency</button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="admin-panel ag-form" novalidate>
            <h3 class="admin-panel__title">{{ $editingId ? 'Edit currency' : 'New currency' }}</h3>
            <div class="ag-field">
                <label class="ag-field__label" for="currency-code">Code</label>
                <input id="currency-code" class="ag-input" type="text" maxlength="3" wire:model="code" required @disabled($editingId !== null)>
                <p class="ag-field__help">ISO 4217 code, three letters (EUR, USD, JPY, …).</p>
                @error('code') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="currency-name">Display name</label>
                <input id="currency-name" class="ag-input" type="text" wire:model="name" required>
                @error('name') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="currency-prefix">Symbol / prefix</label>
                <input id="currency-prefix" class="ag-input" type="text" wire:model="prefix" placeholder="€">
                <p class="ag-field__help">Shown before the amount (leave empty when you use a suffix only).</p>
                @error('prefix') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="currency-suffix">Suffix</label>
                <input id="currency-suffix" class="ag-input" type="text" wire:model="suffix" placeholder=" kr">
                <p class="ag-field__help">Shown after the amount.</p>
                @error('suffix') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="currency-precision">Decimal precision</label>
                <input id="currency-precision" class="ag-input" type="number" min="0" max="6" wire:model.number="precision" required>
                <p class="ag-field__help">Usually 2. Use 0 for currencies like JPY. Money is always stored as integer minor units of this scale.</p>
                @error('precision') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <p class="ag-field__help" aria-live="polite">
                Preview:
                @php
                    $previewCurrency = new \App\Models\Currency([
                        'prefix' => $prefix,
                        'suffix' => $suffix,
                        'precision' => (int) $precision,
                    ]);
                @endphp
                <strong>{{ $previewCurrency->previewSample() }}</strong>
            </p>
            <x-ag.switch id="currency-active" wire:model="is_active" label="Active" />
            <div class="ag-form__actions">
                <button type="submit" class="ag-btn ag-btn--primary">Save</button>
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancel">Cancel</button>
            </div>
        </form>
    @endif

    <div class="ag-table-wrap">
        <table class="ag-table">
            <thead>
                <tr>
                    <th scope="col">Code</th>
                    <th scope="col">Name</th>
                    <th scope="col">Prefix</th>
                    <th scope="col">Suffix</th>
                    <th scope="col">Precision</th>
                    <th scope="col">Preview</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($currencies as $currency)
                    <tr wire:key="currency-{{ $currency->id }}">
                        <td>
                            {{ $currency->code }}
                            @if ($currency->code === $baseCurrency)
                                <span class="ag-badge">base</span>
                            @endif
                        </td>
                        <td>{{ $currency->name }}</td>
                        <td><code>{{ $currency->prefix !== '' ? $currency->prefix : '—' }}</code></td>
                        <td><code>{{ $currency->suffix !== '' ? $currency->suffix : '—' }}</code></td>
                        <td>{{ $currency->normalizedPrecision() }}</td>
                        <td>{{ $currency->previewSample() }}</td>
                        <td><span class="ag-badge">{{ $currency->is_active ? 'active' : 'inactive' }}</span></td>
                        <td>
                            @can('currencies.update')
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="edit({{ $currency->id }})">Edit</button>
                            @endcan
                            @if ($canSetBase && $currency->is_active && $currency->code !== $baseCurrency)
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="setAsBase({{ $currency->id }})">Set as base</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $currencies->links() }}
</div>
