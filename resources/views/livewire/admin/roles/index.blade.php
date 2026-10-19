<div class="admin-page admin-page--roles ag-list-page">
    <x-ag.page-header :heading="__('admin.roles.title')" :lede="auth()->user()?->can('roles.create') ? __('admin.roles.lede') : __('admin.roles.lede_viewer')">
        <x-slot:actions>
            @can('roles.create')
                <button type="button" class="ag-btn ag-btn--primary" wire:click="create">{{ __('admin.roles.add') }}</button>
            @endcan
        </x-slot:actions>
    </x-ag.page-header>

    <x-ag.flash />

    @if ($canShowForm)
        <form wire:submit="save" class="ag-section ag-form ag-form--constrained ag-catalog-form" novalidate>
            <header class="ag-section__header ag-catalog-form__header">
                <div class="ag-catalog-form__heading">
                    <x-ag.icon-tile name="shield" tone="violet" />
                    <h2 class="ag-section__title">{{ $editingId ? __('admin.roles.edit') : __('admin.roles.new') }}</h2>
                </div>
            </header>
            <div class="ag-section__body">

            <div class="ag-field">
                <label class="ag-field__label" for="role-name">{{ __('admin.roles.name') }}</label>
                <input
                    id="role-name"
                    class="ag-input"
                    type="text"
                    wire:model="name"
                    required
                    autocomplete="off"
                >
                <p class="ag-field__help">{{ __('admin.roles.name_help') }}</p>
                @error('name') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>

            <fieldset class="ag-fieldset ag-field">
                <legend class="ag-fieldset__legend">{{ __('admin.roles.permissions') }}</legend>
                <div class="ag-role-permissions">
                    @foreach ($allPermissions as $ability => $label)
                        <div class="ag-role-permissions__item" wire:key="perm-{{ $ability }}">
                            <x-ag.checkbox :id="'perm-'.str_replace('.', '-', $ability)" :label="__($label)" :value="$ability" wire:model="selectedPermissions" />
                        </div>
                    @endforeach
                </div>
                @error('selectedPermissions') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                @error('selectedPermissions.*') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </fieldset>

            <div class="ag-form__actions">
                <button type="submit" class="ag-btn ag-btn--primary">
                    {{ $editingId ? __('common.save') : __('admin.roles.add') }}
                </button>
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancel">{{ __('common.cancel') }}</button>
            </div>
            </div>
        </form>
    @endif

    @if ($roles->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ $roles->total() === 0 ? __('admin.roles.empty') : __('admin.roles.empty_page') }}</p>
            @if ($roles->total() > 0)
                <p class="ag-empty__text">{{ __('admin.roles.empty_page_text') }}</p>
                <button type="button" class="ag-btn ag-btn--ghost" wire:click="resetPage">{{ __('admin.roles.first_page') }}</button>
            @endif
        </div>
    @else
    <div class="ag-table-wrap ag-table-wrap--mobile-cards" role="region" aria-label="{{ __('admin.roles.title') }}" tabindex="0">
        <table class="ag-table ag-table--mobile-cards ag-table--roles">
            <thead>
                <tr>
                    <th scope="col">{{ __('admin.roles.name') }}</th>
                    <th scope="col">{{ __('admin.roles.permissions') }}</th>
                    <th scope="col">{{ __('admin.roles.users_count') }}</th>
                    @canany(['roles.update', 'roles.delete'])
                        <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                    @endcanany
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    <tr wire:key="role-{{ $role->id }}">
                        <td data-label="{{ __('admin.roles.name') }}">
                            <span class="ag-table__primary">
                                <code>{{ $role->name }}</code>
                                @if ($role->name === 'owner')
                                    <span class="ag-badge ag-badge--muted">{{ __('admin.roles.system') }}</span>
                                @endif
                            </span>
                        </td>
                        <td data-label="{{ __('admin.roles.permissions') }}">
                            @if ($role->name === 'owner')
                                {{ __('admin.roles.owner_locked') }}
                            @else
                                {{ $role->permissions->count() }}
                            @endif
                        </td>
                        <td data-label="{{ __('admin.roles.users_count') }}">{{ $role->users_count }}</td>
                        @canany(['roles.update', 'roles.delete'])
                            <td class="ag-table__actions {{ in_array($role->id, $manageableRoleIds, true) ? '' : 'ag-table__actions--unavailable' }}" data-label="{{ __('common.actions') }}">
                                @if (in_array($role->id, $manageableRoleIds, true))
                                    <span class="ag-row-actions">
                                        @can('roles.update')
                                            <button type="button" class="ag-icon-btn" wire:click="edit({{ $role->id }})" title="{{ __('common.edit') }}" aria-label="{{ __('common.edit') }}: {{ $role->name }}">
                                                <x-ag.icon name="pencil" :size="16" />
                                                <span class="ag-table__action-label">{{ __('common.edit') }}</span>
                                            </button>
                                        @endcan
                                        @if ($role->name !== 'owner')
                                            @can('roles.delete')
                                                <button
                                                    type="button"
                                                    class="ag-icon-btn ag-icon-btn--danger"
                                                    wire:click="deleteRole({{ $role->id }})"
                                                    wire:confirm="{{ __('admin.roles.delete_confirm') }}"
                                                    title="{{ __('common.delete') }}"
                                                    aria-label="{{ __('common.delete') }}: {{ $role->name }}"
                                                >
                                                    <x-ag.icon name="trash" :size="16" />
                                                    <span class="ag-table__action-label">{{ __('common.delete') }}</span>
                                                </button>
                                            @endcan
                                        @endif
                                    </span>
                                @endif
                            </td>
                        @endcanany
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
    {{ $roles->links() }}
    @include('livewire.admin.partials.confirm-password-modal')
</div>
