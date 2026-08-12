<div class="admin-page">
    <div class="admin-page__header">
        <div>
            <h2 class="admin-page__heading">Staff</h2>
            <p class="admin-page__lede">Staff accounts for the Admin. Role management beyond Owner comes later.</p>
        </div>
        @can('staff.create')
            <button type="button" class="ag-btn ag-btn--primary" wire:click="create">Add staff</button>
        @endcan
    </div>

    @if ($showForm)
        <form wire:submit="save" class="admin-panel ag-form" novalidate>
            <h3 class="admin-panel__title">New staff user</h3>
            <div class="ag-field">
                <label class="ag-field__label" for="staff-name">Name</label>
                <input id="staff-name" class="ag-input" type="text" wire:model="name" required autocomplete="name">
                @error('name') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="staff-email">Email</label>
                <input id="staff-email" class="ag-input" type="email" wire:model="email" required autocomplete="username">
                @error('email') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="staff-password">Password</label>
                <input id="staff-password" class="ag-input" type="password" wire:model="password" required autocomplete="new-password">
                @error('password') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="staff-role">Role</label>
                <select id="staff-role" class="ag-input" wire:model="role">
                    <option value="owner">Owner</option>
                </select>
                <p class="ag-field__help">Additional roles and granular permission UI will arrive later.</p>
                @error('role') <p class="ag-field__error" role="alert">{{ $message }}</p> @enderror
            </div>
            <div class="ag-form__actions">
                <button type="submit" class="ag-btn ag-btn--primary">Create staff</button>
                <button type="button" class="ag-btn ag-btn--secondary" wire:click="cancel">Cancel</button>
            </div>
        </form>
    @endif

    <div class="ag-table-wrap">
        <table class="ag-table">
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Roles</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($staffUsers as $user)
                    <tr wire:key="staff-{{ $user->id }}">
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->getRoleNames()->join(', ') ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">
                            <div class="ag-empty" role="status">
                                <p class="ag-empty__title">No staff users</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $staffUsers->links() }}
</div>
