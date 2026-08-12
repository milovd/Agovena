<div class="admin-guest__card">
    <h1 class="admin-guest__title">{{ config('app.name', 'Agovena') }}</h1>
    <p class="admin-guest__lede">{{ __('auth.sign_in_lede') }}</p>

    <form wire:submit="login" class="admin-guest__form" novalidate>
        <div class="ag-field">
            <label class="ag-field__label" for="email">{{ __('common.email') }}</label>
            <input
                id="email"
                class="ag-input"
                type="email"
                wire:model="email"
                autocomplete="username"
                required
                aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                @if($errors->has('email')) aria-describedby="email-error" @endif
            >
            @error('email')
                <p id="email-error" class="ag-field__error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="ag-field">
            <label class="ag-field__label" for="password">{{ __('common.password') }}</label>
            <input
                id="password"
                class="ag-input"
                type="password"
                wire:model="password"
                autocomplete="current-password"
                required
                aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                @if($errors->has('password')) aria-describedby="password-error" @endif
            >
            @error('password')
                <p id="password-error" class="ag-field__error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <x-ag.checkbox id="remember" wire:model="remember" :label="__('auth.remember_me')" />

        <button type="submit" class="ag-btn ag-btn--primary" wire:loading.attr="disabled">
            {{ __('auth.sign_in') }}
        </button>
    </form>
</div>
