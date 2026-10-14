@props(['icon' => 'puzzle'])

<div {{ $attributes->class(['ag-package-hero']) }}>
    <div class="ag-package-hero__copy">{{ $slot }}</div>
    <div class="ag-package-hero__art" aria-hidden="true">
        <span class="ag-package-hero__tile ag-package-hero__tile--subtle"></span>
        <span class="ag-package-hero__tile ag-package-hero__tile--settings"><x-ag.icon name="settings" :size="30" /></span>
        <span class="ag-package-hero__tile ag-package-hero__tile--center"><x-ag.logo alt="" /></span>
        <span class="ag-package-hero__tile ag-package-hero__tile--extension"><x-ag.icon :name="$icon" :size="30" /></span>
        <span class="ag-package-hero__tile ag-package-hero__tile--small"><x-ag.icon name="package" :size="22" /></span>
    </div>
</div>
