@props(['manifest', 'kind', 'onDisk' => true])
@php
    $imagePath = $onDisk
        ? app(\App\Agovena\Packages\PackageArtwork::class)->resolve($manifest->path, $manifest->logo)
        : null;
@endphp
<span {{ $attributes->class(['ag-package-mark']) }} aria-hidden="true">
    @if ($imagePath)
        <img src="{{ route('admin.packages.artwork', ['kind' => $kind, 'id' => $manifest->id]) }}" alt="" width="40" height="40" loading="eager" decoding="async">
    @else
        <x-ag.icon :name="$kind === 'extension' ? 'puzzle' : 'package'" :size="22" />
    @endif
</span>
