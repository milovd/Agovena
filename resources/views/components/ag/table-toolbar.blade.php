@props(['filters' => false])
<div {{ $attributes->class(['ag-toolbar', 'ag-toolbar--filters' => $filters]) }}>
    {{ $slot }}
</div>
