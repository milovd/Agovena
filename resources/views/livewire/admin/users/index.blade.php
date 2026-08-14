<div class="admin-page">
    <div class="admin-page__header">
        <div>
            <h2 class="admin-page__heading">{{ __('admin.users.title') }}</h2>
            <p class="admin-page__lede">{{ __('admin.users.lede') }}</p>
        </div>
        @can('users.create')
            <button type="button" class="ag-btn ag-btn--primary" wire:click="create">{{ __('admin.users.add') }}</button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="admin-panel ag-form" novalidate>
            <h3 class="admin-panel__title">{{ __('admin.users.new') }}</h3>
            <div class="ag-field">
                <label class="ag-field__label" for="user-name">{{ __('common.name') }}</label>
                <input id="user-name" class="ag-input" type="text" wire:model="name" required autocomplete="name">
                @error('name') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="user-email">{{ __('common.email') }}</label>
                <input id="user-email" class="ag-input" type="email" wire:model="email" required autocomplete="username">
                @error('email') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="user-password">{{ __('common.password') }}</label>
                <input id="user-password" class="ag-input" type="password" wire:model="password" required autocomplete="new-password">
                @error('password') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="user-role">{{ __('common.role') }}</label>
                <select id="user-role" class="ag-select" wire:model="role">
                    @foreach ($roles as $roleOption)
                        <option value="{{ $roleOption->name }}">{{ $roleOption->name }}</option>
                    @endforeach
                </select>
                <p class="ag-field__help">{{ __('admin.users.role_help') }}</p>
                @error('role') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-form__actions">
                <button type="submit" class="ag-btn ag-btn--primary">{{ __('admin.users.add') }}</button>
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancel">{{ __('common.cancel') }}</button>
            </div>
        </form>
    @endif

    <div class="ag-table-wrap">
        <table class="ag-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('common.name') }}</th>
                    <th scope="col">{{ __('common.email') }}</th>
                    <th scope="col">{{ __('common.roles') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->getRoleNames()->join(', ') ?: __('common.em_dash') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">
                            <div class="ag-empty" role="status">
                                <p class="ag-empty__title">{{ __('admin.users.empty') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
    @include('livewire.admin.partials.confirm-password-modal')
</div>
