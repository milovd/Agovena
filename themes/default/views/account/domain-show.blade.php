<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => $accountSection])

    <section class="store-account__main store-account-panel">
        @include('theme::account.partials.breadcrumbs', [
            'items' => [
                ['label' => __('customer.account.nav_overview'), 'url' => route('customer.account')],
                ['label' => __('domains::customer.title'), 'url' => route('customer.domains')],
                ['label' => $registration->domain_name ?? __('domains::customer.awaiting_domain')],
            ],
        ])
        <header class="store-support-hero store-support-hero--compact">
            <div class="store-support-hero__copy">
                <span class="store-support-hero__icon" aria-hidden="true"><x-ag.icon name="globe" :size="22" /></span>
                <div>
                    <h1 class="store-support-hero__title">{{ $registration->domain_name ?? __('domains::customer.awaiting_domain') }}</h1>
                    <p class="store-support-hero__lede">{{ __('domains::customer.workspace_title') }} · {{ __('domains::customer.status') }}: {{ __('domains::status.'.$registration->status->value) }}</p>
                </div>
            </div>
        </header>

        @if (session('status'))
            <p class="store-alert store-alert--success" role="status">{{ session('status') }}</p>
        @endif

        <section class="store-account-panel__section">
            <h2>{{ __('domains::customer.dns_title') }}</h2>
            <p>{{ __('domains::customer.dns_lede') }}</p>
            @if (($zone['nameservers'] ?? []) !== [])
                <p><strong>{{ __('domains::customer.nameservers') }}:</strong> {{ implode(', ', $zone['nameservers']) }}</p>
            @endif
            <div class="store-domain-records">
                @forelse ($records as $record)
                    <article class="store-domain-record" wire:key="dns-record-{{ $record['id'] ?? $loop->index }}">
                        <div>
                            <strong>{{ $record['type'] ?? '' }} {{ $record['name'] ?? '' }}</strong>
                            <p>@isset($record['priority']){{ $record['priority'] }} @endisset{{ $record['content'] ?? '' }} · TTL {{ $record['ttl'] ?? '' }}</p>
                        </div>
                        <div class="store-domain-record__actions">
                            <button type="button" class="store-btn store-btn--secondary" wire:click="editRecord(@js($record))">{{ __('domains::customer.edit') }}</button>
                            <button type="button" class="store-btn store-btn--ghost" wire:click="deleteRecord(@js((string) ($record['id'] ?? '')))" wire:confirm="{{ __('domains::customer.delete_confirm') }}">{{ __('domains::customer.delete') }}</button>
                        </div>
                    </article>
                @empty
                    <p class="store-muted">{{ __('domains::customer.empty') }}</p>
                @endforelse
            </div>
        </section>

        <section class="store-account-panel__section">
            <h2>{{ __('domains::customer.save_record') }}</h2>
            <form class="store-domain-record-form" wire:submit="saveRecord">
                <div class="store-domain-record-form__grid">
                    <label class="store-field"><span>{{ __('domains::customer.record_type') }}</span><select class="store-input" wire:model.live="recordType"><option>A</option><option>AAAA</option><option>CNAME</option><option>MX</option><option>TXT</option></select></label>
                    <label class="store-field"><span>{{ __('domains::customer.record_name') }}</span><input class="store-input" wire:model="recordName" required></label>
                    <label class="store-field store-domain-record-form__wide"><span>{{ __('domains::customer.record_content') }}</span><input class="store-input" wire:model="recordContent" required>@error('recordContent')<p class="store-field__error">{{ $message }}</p>@enderror</label>
                    <label class="store-field"><span>{{ __('domains::customer.record_ttl') }}</span><input class="store-input" type="number" min="60" max="86400" wire:model="recordTtl" required></label>
                    @if ($recordType === 'MX')
                        <label class="store-field"><span>{{ __('domains::customer.record_priority') }}</span><input class="store-input" type="number" min="0" max="65535" wire:model="recordPriority" required>@error('recordPriority')<p class="store-field__error">{{ $message }}</p>@enderror</label>
                    @endif
                </div>
                <button type="submit" class="store-btn store-btn--primary">{{ __('domains::customer.save_record') }}</button>
            </form>
        </section>
    </section>
</div>
