@props([
    'heading',
    'lede' => null,
])

<header {{ $attributes->class(['admin-page__header']) }}>
    <div class="admin-page__header-text">
        <h2 class="admin-page__heading">{{ $heading }}</h2>
        @if ($lede)
            <p class="admin-page__lede">{{ $lede }}</p>
        @endif
        {{ $slot }}
    </div>
    @isset($actions)
        <div class="admin-page__actions">
            {{ $actions }}
        </div>
    @endisset
</header>
