<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])
    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <div>
                <h1 class="store-account-panel__title">{{ $ticket->subject }}</h1>
                <p class="store-account-panel__lede">{{ $ticket->number }} · {{ __('customer.tickets.status.'.$ticket->status->value) }}</p>
            </div>
        </header>
        <div class="store-account-list">
            @foreach ($ticket->messages as $message)
                <article class="store-account-row">
                    <span>
                        <strong>{{ __('customer.tickets.author.'.$message->author_type) }}</strong>
                        <small>{{ $message->created_at->translatedFormat('d M Y H:i') }}</small>
                    </span>
                    <p>{{ $message->body }}</p>
                </article>
            @endforeach
        </div>
        @if ($ticket->status !== \App\Enums\TicketStatus::Closed)
            <form class="store-auth__form" wire:submit="reply">
                <div class="store-field">
                    <label class="store-label" for="ticket-reply">{{ __('customer.tickets.reply') }}</label>
                    <textarea id="ticket-reply" class="store-input" rows="6" wire:model="reply" required></textarea>
                    @error('reply') <p class="store-field__error">{{ $message }}</p> @enderror
                </div>
                <button class="store-btn store-btn--primary" type="submit">{{ __('customer.tickets.send_reply') }}</button>
            </form>
        @endif
    </section>
</div>
