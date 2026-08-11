<div class="admin-page">
    <div class="admin-page__header">
        <div>
            <h2 class="admin-page__heading">Currencies</h2>
            <p class="admin-page__lede">Define codes with display prefix and suffix (for example €12.00 or 12.00 kr).</p>
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
                <p class="ag-field__help">ISO 4217 code, three letters (EUR, USD, …).</p>
                @error('code') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="currency-name">Name</label>
                <input id="currency-name" class="ag-input" type="text" wire:model="name" required>
                @error('name') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="currency-prefix">Prefix</label>
                <input id="currency-prefix" class="ag-input" type="text" wire:model="prefix" placeholder="€">
                <p class="ag-field__help">Shown before the amount.</p>
                @error('prefix') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="currency-suffix">Suffix</label>
                <input id="currency-suffix" class="ag-input" type="text" wire:model="suffix" placeholder=" kr">
                <p class="ag-field__help">Shown after the amount.</p>
                @error('suffix') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <p class="ag-field__help" aria-live="polite">
                Preview:
                <strong>{{ trim(($prefix ?? '').number_format(123.45, 2, '.', ',').($suffix ?? '')) }}</strong>
            </p>
            <label class="ag-check">
                <input type="checkbox" wire:model="is_active">
                <span>Active</span>
            </label>
            <div class="ag-form__actions">
                <button type="submit" class="ag-btn ag-btn--primary">Save</button>
                <button type="button" class="ag-btn ag-btn--ghost" wire:click="cancel">Cancel</button>
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
                    <th scope="col">Preview</th>
                    <th scope="col">Status</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($currencies as $currency)
                    <tr wire:key="currency-{{ $currency->id }}">
                        <td>{{ $currency->code }}</td>
                        <td>{{ $currency->name }}</td>
                        <td><code>{{ $currency->prefix !== '' ? $currency->prefix : '—' }}</code></td>
                        <td><code>{{ $currency->suffix !== '' ? $currency->suffix : '—' }}</code></td>
                        <td>{{ $currency->previewSample() }}</td>
                        <td><span class="ag-badge">{{ $currency->is_active ? 'active' : 'inactive' }}</span></td>
                        <td>
                            @can('currencies.update')
                                <button type="button" class="ag-btn ag-btn--ghost" wire:click="edit({{ $currency->id }})">Edit</button>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $currencies->links() }}
</div>
