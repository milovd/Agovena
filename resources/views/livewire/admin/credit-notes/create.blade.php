<div class="admin-page">
    <x-ag.page-header
        :heading="__('admin.credit_notes.create_title', ['number' => $invoice->number])"
        :lede="__('admin.credit_notes.create_lede')"
    >
        <x-slot:back>
            <x-ag.back :href="route('admin.invoices.show', $invoice)" :label="$invoice->number" />
        </x-slot:back>
    </x-ag.page-header>

    @if ($errors->any())
        <div class="ag-alert ag-alert--danger" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="ag-section">
        <p>{{ __('admin.credit_notes.remaining', ['amount' => \App\Support\MoneyFormatter::format($invoice->remainingCreditable(), $invoice->currency)]) }}</p>

        <div class="ag-field">
            <label class="ag-field__label" for="credit-mode">{{ __('admin.credit_notes.mode') }}</label>
            <select id="credit-mode" class="ag-select" wire:model.live="mode">
                <option value="full">{{ __('admin.credit_notes.mode_full') }}</option>
                <option value="partial">{{ __('admin.credit_notes.mode_partial') }}</option>
            </select>
        </div>

        @if ($mode === 'partial')
            <div class="ag-table-wrap">
                <table class="ag-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.invoices.items') }}</th>
                            <th>{{ __('admin.credit_notes.available_qty') }}</th>
                            <th>{{ __('admin.credit_notes.credit_qty') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($creditableItems as $item)
                            <tr wire:key="credit-line-{{ $item->id }}">
                                <td>
                                    {{ $item->label }}
                                    <span class="ag-muted">{{ \App\Support\MoneyFormatter::format($item->unit_amount, $item->currency) }}</span>
                                </td>
                                <td>{{ $invoice->remainingQuantityFor($item) }}</td>
                                <td>
                                    <input
                                        class="ag-input"
                                        type="number"
                                        min="0"
                                        max="{{ $invoice->remainingQuantityFor($item) }}"
                                        wire:model="quantities.{{ $item->id }}"
                                        aria-label="{{ $item->label }}"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="ag-field">
            <label class="ag-field__label" for="credit-reason">{{ __('admin.credit_notes.reason') }}</label>
            <textarea id="credit-reason" class="ag-input" rows="3" wire:model="reason" required></textarea>
        </div>

        @if (! $confirming)
            <button type="button" class="ag-btn ag-btn--primary" wire:click="startConfirm">
                {{ __('admin.credit_notes.issue') }}
            </button>
        @else
            <div class="ag-confirm" role="dialog" aria-labelledby="confirm-credit-title" aria-modal="true">
                <h4 id="confirm-credit-title">{{ __('admin.credit_notes.confirm_title') }}</h4>
                <p>{{ __('admin.credit_notes.confirm_text') }}</p>
                <div class="ag-confirm__actions">
                    <button type="button" class="ag-btn ag-btn--primary" wire:click="issue">{{ __('common.confirm') }}</button>
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelConfirm">{{ __('common.cancel') }}</button>
                </div>
            </div>
        @endif
    </section>
</div>
