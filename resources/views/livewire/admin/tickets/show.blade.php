<div class="admin-page c-ticket-workspace">
    <x-ag.page-header :heading="$ticket->subject" :lede="$ticket->number">
        <x-slot:breadcrumbs>
            <x-ag.breadcrumbs :items="[
                ['label' => __('admin.nav_groups.overview'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.tickets.title'), 'url' => route('admin.tickets.index')],
                ['label' => $ticket->number],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:back>
            <x-ag.back :href="route('admin.tickets.index')" :label="__('admin.tickets.back')" />
        </x-slot:back>
        <x-slot:actions>
            @can('tickets.manage')
                <button class="ag-btn ag-btn--secondary" type="button" wire:click="assignSelf">{{ __('admin.tickets.assign_self') }}</button>
            @endcan
        </x-slot:actions>
    </x-ag.page-header>

    <div class="c-ticket-workspace__layout">
        @can('tickets.manage')
            <form class="admin-panel ag-form c-ticket-workspace__status" wire:submit="updateStatus">
                <header class="c-ticket-workspace__section-heading">
                    <x-ag.icon name="ticket" :size="20" />
                    <h2>{{ __('common.status') }}</h2>
                </header>
                <div class="ag-field">
                    <label class="visually-hidden" for="ticket-status">{{ __('common.status') }}</label>
                    <select id="ticket-status" class="ag-select" wire:model="status">
                        @foreach (\App\Enums\TicketStatus::cases() as $option)
                            <option value="{{ $option->value }}">{{ __('admin.tickets.status.'.$option->value) }}</option>
                        @endforeach
                    </select>
                    @error('status') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="ag-form__actions">
                    <button class="ag-btn ag-btn--secondary" type="submit">{{ __('common.save') }}</button>
                </div>
            </form>
        @endcan

        <div class="c-ticket-workspace__main">
            <section class="admin-panel c-ticket-workspace__conversation" aria-labelledby="ticket-conversation-heading">
                <header class="c-ticket-workspace__section-heading">
                    <x-ag.icon name="file-text" :size="20" />
                    <h2 id="ticket-conversation-heading">{{ __('admin.tickets.conversation') }}</h2>
                </header>
                <div class="c-ticket-workspace__messages">
                    @foreach ($ticket->messages as $message)
                        <article class="c-ticket-workspace__message" wire:key="ticket-message-{{ $message->id }}">
                            <header class="c-ticket-workspace__message-header">
                                <strong>{{ __('admin.tickets.author.'.$message->author_type) }}</strong>
                                <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->translatedFormat('d M Y H:i') }}</time>
                                @if ($message->is_internal)<span class="ag-badge ag-badge--warning">{{ __('admin.tickets.internal') }}</span>@endif
                            </header>
                            <p class="c-ticket-workspace__message-body">{{ $message->body }}</p>
                            @if ($message->attachments->isNotEmpty())
                                <ul class="ag-attachment-list c-ticket-workspace__attachments">
                                    @foreach ($message->attachments as $attachment)
                                        <li><a href="{{ route('admin.ticket-attachments.download', $attachment) }}">{{ $attachment->original_filename }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>

            @can('tickets.manage')
                <form class="admin-panel ag-form c-ticket-workspace__reply" wire:submit="sendReply">
                    <header class="c-ticket-workspace__section-heading">
                        <x-ag.icon name="mail" :size="20" />
                        <h2>{{ __('admin.tickets.reply') }}</h2>
                    </header>
                    <div class="ag-field">
                        <label class="visually-hidden" for="ticket-reply">{{ __('admin.tickets.reply') }}</label>
                        <textarea id="ticket-reply" class="ag-input" rows="7" wire:model="reply" required></textarea>
                        @error('reply') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <x-ag.file-upload
                        id="ticket-attachments"
                        :label="__('admin.tickets.attachments')"
                        :hint="__('admin.tickets.attachments_hint', ['max' => $maxAttachments, 'mb' => (int) ($maxKilobytes / 1024)])"
                        accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,image/jpeg,image/png,image/webp,image/gif,application/pdf"
                        multiple
                        placeholder-icon="upload"
                        wire:model="attachments"
                        loading-target="attachments"
                    >
                        @error('attachments') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                        @error('attachments.*') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                        @if ($attachments !== [])
                            <ul class="ag-file-upload__selected" role="list">
                                @foreach ($attachments as $index => $file)
                                    <li class="ag-file-upload__selected-item">
                                        <span>{{ is_object($file) && method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : __('admin.tickets.attachment') }}</span>
                                        <button type="button" class="ag-btn ag-btn--ghost ag-btn--sm" wire:click="removeAttachment({{ $index }})">{{ __('common.remove') }}</button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-ag.file-upload>
                    <div class="c-ticket-workspace__reply-actions">
                        <x-ag.checkbox id="ticket-internal" wire:model="is_internal" :label="__('admin.tickets.internal_note')" />
                        <button class="ag-btn ag-btn--primary" type="submit" wire:loading.attr="disabled">{{ __('admin.tickets.send') }}</button>
                    </div>
                </form>
            @endcan
        </div>
    </div>
</div>
