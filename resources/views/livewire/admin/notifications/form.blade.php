<div class="admin-page admin-page--form notification-templates notification-templates--form">
    <x-ag.page-header
        :heading="$isCreate ? __('admin.notifications.create_title') : __('admin.notifications.edit_title')"
        :lede="__('admin.notifications.form_lede')"
    >
        <x-slot:breadcrumbs>
            <x-ag.breadcrumbs :items="[
                ['label' => __('admin.nav_groups.overview'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.notifications.title'), 'url' => route('admin.notifications')],
                ['label' => $isCreate ? __('admin.notifications.create_title') : ($definition ? __($definition->label) : __('admin.notifications.edit_title'))],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:back>
            <x-ag.back :href="route('admin.notifications')" :label="__('admin.notifications.title')" />
        </x-slot:back>
    </x-ag.page-header>

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <div class="notification-templates__form-heading">
        <div>
            <p class="notification-templates__eyebrow">{{ __('admin.notifications.event') }}</p>
            <h2>{{ $definition ? __($definition->label) : __('admin.notifications.title') }}</h2>
        </div>
        @if ($definition)
            <div class="notification-templates__placeholders">
                <span>{{ __('admin.notifications.placeholders') }}</span>
                <code>{{ $placeholderList }}</code>
            </div>
        @endif
    </div>

    @if ($isCreate)
        <div class="notification-templates__event-selector">
            <div class="ag-field">
                <label class="ag-field__label" for="tpl-event">{{ __('admin.notifications.event') }}</label>
                <select id="tpl-event" class="ag-select" wire:model.live="selected">
                    @foreach ($definitions as $item)
                        <option value="{{ $item->key }}">{{ __($item->label) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    <div class="notification-templates__tabs">
        @include('livewire.admin.partials.package-tabs', [
            'active' => $tab,
            'tabs' => $tabs,
            'ariaLabel' => __('admin.notifications.form_tabs_aria'),
        ])
    </div>

    <form id="notification-template-form" wire:submit="save" class="ag-form ag-form--product notification-templates__form-page" novalidate>
        @if ($tab === 'mail')
            <section class="ag-section" aria-labelledby="notification-mail-heading">
                <header class="ag-section__header">
                    <h3 id="notification-mail-heading" class="ag-section__title">{{ __('admin.notifications.mail_heading') }}</h3>
                    <p class="ag-section__lede">{{ __('admin.notifications.mail_help_short') }}</p>
                </header>
                <div class="ag-section__body">
                    <x-ag.switch id="tpl-mail-enabled" wire:model="mailEnabled" value="1" :label="__('admin.notifications.mail_enabled')" />

                    <div class="ag-grid ag-grid--2">
                        <div class="ag-field">
                            <label class="ag-field__label" for="tpl-mail-format">{{ __('admin.notifications.mail_format') }}</label>
                            <select id="tpl-mail-format" class="ag-select" wire:model="mailFormat">
                                @foreach (__('admin.notifications.formats') as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('mailFormat') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="ag-field">
                            <label class="ag-field__label" for="tpl-subject">{{ __('admin.notifications.subject') }}</label>
                            <input id="tpl-subject" class="ag-input" wire:model="subject" required>
                            @error('subject') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="ag-field ag-grid__span-2">
                            <label class="ag-field__label" for="tpl-body">{{ __('admin.notifications.body') }}</label>
                            <textarea id="tpl-body" class="ag-input notification-templates__body" rows="16" wire:model="body" required></textarea>
                            @error('body') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </section>
        @else
            <section class="ag-section" aria-labelledby="notification-in-app-heading">
                <header class="ag-section__header">
                    <h3 id="notification-in-app-heading" class="ag-section__title">{{ __('admin.notifications.notification_heading') }}</h3>
                    <p class="ag-section__lede">{{ __('admin.notifications.notification_help_short') }}</p>
                </header>
                <div class="ag-section__body">
                    <div class="ag-grid ag-grid--2">
                        <div class="ag-field">
                            <label class="ag-field__label" for="tpl-notification-title">{{ __('admin.notifications.notification_title') }}</label>
                            <input id="tpl-notification-title" class="ag-input" wire:model="notificationTitle" placeholder="{{ __('admin.notifications.notification_title_placeholder') }}">
                            @error('notificationTitle') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                        <div class="ag-field notification-templates__switch-field">
                            <x-ag.switch id="tpl-notification-enabled" wire:model="notificationEnabled" value="1" :label="__('admin.notifications.notification_enabled')" />
                        </div>
                        <div class="ag-field ag-grid__span-2">
                            <label class="ag-field__label" for="tpl-notification-body">{{ __('admin.notifications.notification_body') }}</label>
                            <textarea id="tpl-notification-body" class="ag-input" rows="6" wire:model="notificationBody" placeholder="{{ __('admin.notifications.notification_body_placeholder') }}"></textarea>
                            @error('notificationBody') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="notification-templates__channel-list">
                        <div class="notification-templates__channel-row">
                            <strong>{{ __('admin.notifications.push_short') }}</strong>
                            <x-ag.switch id="tpl-push-enabled" wire:model="pushEnabled" value="1" :label="__('admin.notifications.push_enabled')" />
                        </div>
                        <div class="notification-templates__channel-row notification-templates__channel-row--choice">
                            <strong>{{ __('admin.notifications.user_choice') }}</strong>
                            <x-ag.switch id="tpl-user-choice" wire:model="userChoice" value="1" :label="__('admin.notifications.user_choice_enabled')" />
                        </div>
                    </div>
                </div>
            </section>
        @endif
    </form>

    <div class="ag-form__sticky ag-form__sticky--page notification-templates__actions" role="group" aria-label="{{ __('admin.notifications.form_actions_aria') }}">
        <a class="ag-btn ag-btn--secondary" href="{{ route('admin.notifications') }}">{{ __('common.cancel') }}</a>
        <button type="submit" form="notification-template-form" class="ag-btn ag-btn--primary" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">{{ $isCreate ? __('admin.notifications.create') : __('admin.notifications.save_changes') }}</span>
            <span wire:loading wire:target="save">{{ __('common.saving') }}</span>
        </button>
    </div>
</div>
