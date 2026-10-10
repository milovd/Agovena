@props(['icon' => 'puzzle'])

<div {{ $attributes->class(['ag-package-hero']) }}>
    <div class="ag-package-hero__copy">{{ $slot }}</div>
    <div class="ag-package-hero__art" aria-hidden="true">
        <span class="ag-package-hero__piece ag-package-hero__piece--back"><x-ag.icon name="settings" :size="56" /></span>
        <span class="ag-package-hero__piece ag-package-hero__piece--front"><x-ag.icon :name="$icon" :size="90" /></span>
        <span class="ag-package-hero__piece ag-package-hero__piece--small"><x-ag.icon name="package" :size="34" /></span>
    </div>
</div>
