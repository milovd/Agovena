<div class="admin-page notification-templates">
    <x-ag.page-header :heading="__('admin.notifications.title')" :lede="__('admin.notifications.lede')">
        <x-slot:actions>
            @can('notifications.manage')
                <a class="ag-btn ag-btn--primary" href="{{ route('admin.notifications.create') }}">
                    <x-ag.icon name="plus" :size="16" />
                    {{ __('admin.notifications.add') }}
                </a>
            @endcan
        </x-slot:actions>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
    @endif

    <div class="notification-templates__list-card">
        <div class="notification-templates__list-heading">
            <div>
                <p class="notification-templates__eyebrow">{{ __('admin.notifications.events') }}</p>
                <h2>{{ __('admin.notifications.list_heading') }}</h2>
            </div>
            <span class="notification-templates__count">{{ count($definitions) }}</span>
        </div>

        <div class="ag-table-wrap">
            <table class="ag-table notification-templates__table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('admin.notifications.event') }}</th>
                        <th scope="col">{{ __('admin.notifications.channels') }}</th>
                        <th scope="col">{{ __('admin.notifications.format') }}</th>
                        <th scope="col">{{ __('admin.notifications.source') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($definitions as $definition)
                        @php
                            $template = $templates->get($definition->key);
                            $mailEnabled = $template?->mail_enabled ?? true;
                            $inAppEnabled = $template?->in_app_enabled ?? true;
                            $pushEnabled = $template?->push_enabled ?? true;
                        @endphp
                        <tr wire:key="notification-template-{{ $definition->key }}">
                            <td>
                                <div class="ag-table__primary">
                                    <span class="ag-table__name">{{ __($definition->label) }}</span>
                                    <span class="ag-muted">{{ $definition->key }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="notification-templates__channel-badges" aria-label="{{ __('admin.notifications.channels') }}">
                                    <span @class(['ag-badge', 'ag-badge--success' => $mailEnabled, 'ag-badge--muted' => ! $mailEnabled])>{{ __('admin.notifications.mail_short') }}</span>
                                    <span @class(['ag-badge', 'ag-badge--success' => $inAppEnabled, 'ag-badge--muted' => ! $inAppEnabled])>{{ __('admin.notifications.in_app_short') }}</span>
                                    <span @class(['ag-badge', 'ag-badge--success' => $pushEnabled, 'ag-badge--muted' => ! $pushEnabled])>{{ __('admin.notifications.push_short') }}</span>
                                </div>
                            </td>
                            <td>{{ $template?->mail_format ? __('admin.notifications.formats.'.$template->mail_format) : __('admin.notifications.default_format') }}</td>
                            <td>
                                <span @class(['ag-badge', 'ag-badge--success' => $template, 'ag-badge--muted' => ! $template])>
                                    {{ $template ? __('admin.notifications.customized') : __('admin.notifications.default') }}
                                </span>
                            </td>
                            <td class="ag-table__actions">
                                <div class="ag-row-actions">
                                    @can('notifications.manage')
                                        <a
                                            class="ag-icon-btn"
                                            href="{{ route('admin.notifications.edit', $definition->key) }}"
                                            title="{{ __('common.edit') }}"
                                            aria-label="{{ __('admin.notifications.edit_aria', ['name' => __($definition->label)]) }}"
                                        >
                                            <x-ag.icon name="pencil" :size="16" />
                                        </a>
                                        @if ($template)
                                            <button
                                                type="button"
                                                class="ag-icon-btn ag-icon-btn--danger"
                                                wire:click="confirmRemove('{{ $definition->key }}')"
                                                title="{{ __('common.remove') }}"
                                                aria-label="{{ __('admin.notifications.remove_aria', ['name' => __($definition->label)]) }}"
                                            >
                                                <x-ag.icon name="trash" :size="16" />
                                            </button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($confirmingDefinition)
        <div class="ag-modal" role="dialog" aria-modal="true" aria-labelledby="remove-notification-template-title">
            <div class="ag-modal__backdrop" wire:click="cancelRemove"></div>
            <div class="ag-modal__panel">
                <h3 id="remove-notification-template-title" class="ag-modal__title">
                    {{ __('admin.notifications.remove_title', ['name' => __($confirmingDefinition->label)]) }}
                </h3>
                <p class="ag-modal__text">{{ __('admin.notifications.remove_text') }}</p>
                <div class="ag-modal__actions">
                    <button type="button" class="ag-btn ag-btn--danger" wire:click="remove">{{ __('common.remove') }}</button>
                    <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancelRemove">{{ __('common.cancel') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
