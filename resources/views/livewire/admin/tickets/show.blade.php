<div class="admin-page">
    <x-ag.back :href="route('admin.tickets.index')" :label="__('admin.tickets.back')" />
    <x-ag.page-header :heading="$ticket->subject" :lede="$ticket->number">
        <x-slot:actions>
            @can('tickets.manage')
                <button class="ag-btn ag-btn--secondary" type="button" wire:click="assignSelf">{{ __('admin.tickets.assign_self') }}</button>
            @endcan
        </x-slot:actions>
    </x-ag.page-header>

    @can('tickets.manage')
        <form class="admin-panel ag-form" wire:submit="updateStatus">
            <div class="ag-field">
                <label class="ag-field__label" for="ticket-status">{{ __('common.status') }}</label>
                <select id="ticket-status" class="ag-select" wire:model="status">
                    @foreach (\App\Enums\TicketStatus::cases() as $option)
                        <option value="{{ $option->value }}">{{ __('admin.tickets.status.'.$option->value) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="ag-btn ag-btn--secondary" type="submit">{{ __('common.save') }}</button>
        </form>
    @endcan

    <section class="admin-panel">
        <h2 class="admin-panel__title">{{ __('admin.tickets.conversation') }}</h2>
        @foreach ($ticket->messages as $message)
            <article class="admin-panel">
                <header><strong>{{ __('admin.tickets.author.'.$message->author_type) }}</strong> · {{ $message->created_at->translatedFormat('d M Y H:i') }}</header>
                @if ($message->is_internal)<span class="ag-badge">{{ __('admin.tickets.internal') }}</span>@endif
                <p>{{ $message->body }}</p>
            </article>
        @endforeach
    </section>

    @can('tickets.manage')
        <form class="admin-panel ag-form" wire:submit="sendReply">
            <div class="ag-field">
                <label class="ag-field__label" for="ticket-reply">{{ __('admin.tickets.reply') }}</label>
                <textarea id="ticket-reply" class="ag-input" rows="7" wire:model="reply" required></textarea>
                @error('reply') <p class="ag-field__error">{{ $message }}</p> @enderror
            </div>
            <x-ag.checkbox id="ticket-internal" wire:model="is_internal" :label="__('admin.tickets.internal_note')" />
            <button class="ag-btn ag-btn--primary" type="submit">{{ __('admin.tickets.send') }}</button>
        </form>
    @endcan
</div>
