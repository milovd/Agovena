@props([
    'name' => '',
    'size' => 'md',
])

@php
    $initials = collect(preg_split('/\s+/', trim($name)) ?: [])
        ->filter()
        ->take(2)
        ->map(static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    if ($initials === '') {
        $initials = 'A';
    }
@endphp

<span {{ $attributes->class(['ag-avatar', 'ag-avatar--'.$size]) }} aria-hidden="true">
    {{ $slot->isEmpty() ? $initials : $slot }}
</span>
