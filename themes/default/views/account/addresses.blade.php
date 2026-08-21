<div class="store-account">
    @include('theme::account.partials.nav', ['accountSection' => 'profile'])

    <section class="store-account__main store-account-panel">
        <header class="store-account-panel__header">
            <h1 class="store-account-panel__title">{{ __('customer.addresses.heading') }}</h1>
            <p class="store-account-panel__lede">{{ __('customer.addresses.lede') }}</p>
        </header>

        <x-ag.card id="addresses">
            <x-ag.card.content>
                @include('theme::account.partials.addresses-panel')
            </x-ag.card.content>
        </x-ag.card>
    </section>
</div>
