<div class="admin-page">
    <x-ag.page-header :heading="__('admin.notifications.title')" :lede="__('admin.notifications.lede')" />

    @if (session('status'))
        <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
    @endif

    <div class="ag-split">
        <nav class="admin-panel" aria-label="{{ __('admin.notifications.list_aria') }}">
            <h3 class="admin-panel__title">{{ __('admin.notifications.events') }}</h3>
            <ul class="ag-plain-list">
                @foreach ($definitions as $item)
                    <li>
                        <button
                            type="button"
                            class="ag-btn {{ $selected === $item->key ? 'ag-btn--primary' : 'ag-btn--ghost' }}"
                            wire:click="select('{{ $item->key }}')"
                        >
                            {{ __($item->label) }}
                        </button>
                    </li>
                @endforeach
            </ul>
        </nav>

        <form wire:submit="save" class="admin-panel ag-form" novalidate>
            <h3 class="admin-panel__title">{{ $definition ? __($definition->label) : __('admin.notifications.title') }}</h3>
            <p class="ag-field__help">{{ __('admin.notifications.placeholders_help') }}</p>
            @if ($definition)
                <p class="ag-field__help"><code>{{ $placeholderList }}</code></p>
            @endif

            <x-ag.switch id="tpl-enabled" wire:model="enabled" :label="__('admin.notifications.enabled')" />

            <div class="ag-field">
                <label class="ag-field__label" for="tpl-subject">{{ __('admin.notifications.subject') }}</label>
                <input id="tpl-subject" class="ag-input" wire:model="subject" required>
                @error('subject') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>

            <div class="ag-field">
                <label class="ag-field__label" for="tpl-body">{{ __('admin.notifications.body') }}</label>
                <textarea id="tpl-body" class="ag-input" rows="10" wire:model="body" required></textarea>
                <p class="ag-field__help">{{ __('admin.notifications.body_help') }}</p>
                @error('body') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>

            <div class="ag-form__actions">
                <button class="ag-btn ag-btn--primary" type="submit">{{ __('common.save') }}</button>
                <button class="ag-btn ag-btn--secondary" type="button" wire:click="resetToDefault">{{ __('admin.notifications.reset') }}</button>
            </div>
        </form>
    </div>
</div>
