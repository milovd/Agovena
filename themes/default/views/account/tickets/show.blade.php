@php
    $statusClass = match ($ticket->status->value) {
        'answered' => 'is-success',
        'closed' => 'is-muted',
        'pending' => 'is-warning',
        default => 'is-open',
    };
@endphp

<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        @include('theme::account.partials.breadcrumbs', [
            'back' => [
                'url' => route('customer.tickets.index'),
                'label' => __('customer.tickets.back'),
            ],
            'items' => [
                ['label' => __('customer.account.nav_overview'), 'url' => route('customer.account')],
                ['label' => __('customer.tickets.heading'), 'url' => route('customer.tickets.index')],
                ['label' => $ticket->number],
            ],
        ])

        <header class="store-support-hero store-support-hero--compact">
            <div class="store-support-hero__copy">
                <span class="store-support-hero__icon" aria-hidden="true">
                    <x-ag.icon name="mail" :size="22" />
                </span>
                <div>
                    <h1 class="store-support-hero__title">{{ $ticket->subject }}</h1>
                    <p class="store-support-hero__lede">
                        {{ $ticket->number }}
                        ·
                        {{ __('customer.tickets.priority.'.$ticket->priority->value) }}
                    </p>
                </div>
            </div>
            <span class="store-support-card__status {{ $statusClass }}">
                {{ __('customer.tickets.status.'.$ticket->status->value) }}
            </span>
        </header>

        <div class="store-ticket-thread" role="list">
            @foreach ($ticket->messages as $message)
                <article
                    class="store-ticket-message {{ $message->author_type === 'customer' ? 'is-customer' : 'is-staff' }}"
                    role="listitem"
                >
                    <header class="store-ticket-message__meta">
                        <strong>{{ __('customer.tickets.author.'.$message->author_type) }}</strong>
                        <time datetime="{{ $message->created_at?->toIso8601String() }}">
                            {{ $message->created_at->translatedFormat('d M Y H:i') }}
                        </time>
                    </header>
                    <div class="store-ticket-message__body">{{ $message->body }}</div>
                    @if ($message->attachments->isNotEmpty())
                        <ul class="store-ticket-attachments" role="list">
                            @foreach ($message->attachments as $attachment)
                                <li>
                                    <a class="store-ticket-attachments__link" href="{{ route('customer.ticket-attachments.download', $attachment) }}">
                                        <x-ag.icon name="download" :size="14" />
                                        <span>{{ $attachment->original_filename }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            @endforeach
        </div>

        @if ($ticket->status !== \App\Enums\TicketStatus::Closed)
            <form class="store-account-form store-ticket-reply" wire:submit="reply">
                <div class="store-field">
                    <label class="store-label" for="ticket-reply">{{ __('customer.tickets.reply') }}</label>
                    <textarea id="ticket-reply" class="store-input" rows="6" wire:model="reply" required></textarea>
                    @error('reply') <p class="store-field__error">{{ $message }}</p> @enderror
                </div>
                <div class="store-field">
                    <x-store.file-upload
                        id="ticket-reply-attachments"
                        :label="__('customer.tickets.attachments')"
                        :hint="__('customer.tickets.attachments_hint', ['max' => $maxAttachments, 'mb' => (int) ($maxKilobytes / 1024)])"
                        accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,image/jpeg,image/png,image/webp,image/gif,application/pdf"
                        multiple
                        wire:model="attachments"
                        loading-target="attachments"
                    >
                        @error('attachments') <p class="store-field__error">{{ $message }}</p> @enderror
                        @error('attachments.*') <p class="store-field__error">{{ $message }}</p> @enderror
                        @if ($attachments !== [])
                            <ul class="store-file-upload__selected" role="list">
                                @foreach ($attachments as $index => $file)
                                    <li class="store-file-upload__selected-item">
                                        <span>{{ is_object($file) && method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : __('customer.tickets.attachment') }}</span>
                                        <button type="button" class="store-btn store-btn--secondary" wire:click="removeAttachment({{ $index }})">
                                            {{ __('common.remove') }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-store.file-upload>
                </div>
                <div class="store-form-actions">
                    <button class="store-btn store-btn--primary" type="submit" wire:loading.attr="disabled">{{ __('customer.tickets.send_reply') }}</button>
                </div>
            </form>
        @endif
    </section>
</div>
