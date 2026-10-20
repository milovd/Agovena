<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

test('every Admin card header leads with an icon like the reference card anatomy', function (): void {
    $missing = [];
    foreach (Finder::create()->files()->in(resource_path('views/livewire/admin'))->name('*.blade.php') as $file) {
        $source = $file->getContents();
        preg_match_all('/<(?:x-ag\.card\.header|div class="ag-card__header|header class="ag-card__header)[^>]*>/', $source, $headers, PREG_OFFSET_CAPTURE);
        foreach ($headers[0] as [$tag, $offset]) {
            $opening = substr($source, $offset + strlen($tag), 600);
            if (! str_contains($opening, 'icon-tile') && ! str_contains($opening, 'x-ag.icon')) {
                $missing[] = $file->getRelativePathname().':'.(substr_count(substr($source, 0, $offset), "\n") + 1);
            }
        }
    }

    expect($missing)->toBe([]);
});

test('a card header with an icon tile puts the tile beside the title and description', function (): void {
    $css = (string) file_get_contents(resource_path('css/admin/components/_card.css'));

    expect($css)->toContain('.ag-card__header:has(> .ag-icon-tile:first-child) {')
        ->toContain('grid-template-columns: auto minmax(0, 1fr);');
});
