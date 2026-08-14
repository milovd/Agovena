<div class="admin-page">
    <div class="admin-page__header">
        <div>
            <h2 class="admin-page__heading">{{ __('admin.roles.title') }}</h2>
            <p class="admin-page__lede">{{ __('admin.roles.lede') }}</p>
        </div>
        @can('roles.create')
            <button type="button" class="ag-btn ag-btn--primary" wire:click="create">{{ __('admin.roles.add') }}</button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="admin-panel ag-form" novalidate>
            <h3 class="admin-panel__title">{{ $editingId ? __('admin.roles.edit') : __('admin.roles.new') }}</h3>

            <div class="ag-field">
                <label class="ag-field__label" for="role-name">{{ __('admin.roles.name') }}</label>
                <input
                    id="role-name"
                    class="ag-input"
                    type="text"
                    wire:model="name"
                    required
                    @disabled($isOwnerEdit)
                    autocomplete="off"
                >
                <p class="ag-field__help">{{ __('admin.roles.name_help') }}</p>
                @error('name') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>

            @if ($isOwnerEdit)
                <p class="ag-field__help" role="status">{{ __('admin.roles.owner_locked') }}</p>
            @else
                <fieldset class="ag-field">
                    <legend class="ag-field__label">{{ __('admin.roles.permissions') }}</legend>
                    <div class="ag-check-grid" style="display:grid;gap:0.5rem;grid-template-columns:repeat(auto-fill,minmax(14rem,1fr));margin-top:0.5rem;">
                        @foreach ($allPermissions as $ability => $label)
                            <label class="ag-check" wire:key="perm-{{ $ability }}">
                                <input type="checkbox" value="{{ $ability }}" wire:model="selectedPermissions">
                                <span>{{ __($label) }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('selectedPermissions') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                    @error('selectedPermissions.*') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
                </fieldset>
            @endif

            <div class="ag-form__actions">
                @unless ($isOwnerEdit)
                    <button type="submit" class="ag-btn ag-btn--primary">
                        {{ $editingId ? __('common.save') : __('admin.roles.add') }}
                    </button>
                @endunless
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancel">{{ __('common.cancel') }}</button>
            </div>
        </form>
    @endif

    <div class="ag-table-wrap">
        <table class="ag-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('admin.roles.name') }}</th>
                    <th scope="col">{{ __('admin.roles.permissions') }}</th>
                    <th scope="col">{{ __('admin.roles.users_count') }}</th>
                    <th scope="col">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($roles as $role)
                    <tr wire:key="role-{{ $role->id }}">
                        <td>
                            <code>{{ $role->name }}</code>
                            @if ($role->name === 'owner')
                                <span class="ag-badge">system</span>
                            @endif
                        </td>
                        <td>
                            @if ($role->name === 'owner')
                                {{ __('admin.roles.owner_locked') }}
                            @else
                                {{ $role->permissions->count() }}
                            @endif
                        </td>
                        <td>{{ $role->users_count }}</td>
                        <td>
                            <div class="ag-table__actions">
                                @can('roles.update')
                                    <button type="button" class="ag-btn ag-btn--ghost" wire:click="edit({{ $role->id }})">{{ __('common.edit') }}</button>
                                @endcan
                                @if ($role->name !== 'owner')
                                    @can('roles.delete')
                                        <button
                                            type="button"
                                            class="ag-btn ag-btn--ghost"
                                            wire:click="delete({{ $role->id }})"
                                            wire:confirm="{{ __('admin.roles.delete_confirm') }}"
                                        >{{ __('common.delete') }}</button>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <div class="ag-empty" role="status">
                                <p class="ag-empty__title">{{ __('admin.roles.empty') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $roles->links() }}
    @include('livewire.admin.partials.confirm-password-modal')
</div>
