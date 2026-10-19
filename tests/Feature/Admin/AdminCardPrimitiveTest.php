<?php

declare(strict_types=1);

/*
 * A card is a single-column grid. With an implicit "auto" column, content that cannot wrap
 * (a crontab line in a code block, a long URL) widens the column past the card, and the
 * card's overflow clip then cuts off its own heading and text on narrow screens.
 */
test('admin cards keep wide content inside the card instead of widening their column', function (): void {
    $css = file_get_contents(resource_path('css/admin/components/_card.css'));

    preg_match('/^\.ag-card \{(?<body>[^}]*)\}/m', (string) $css, $match);

    expect($match['body'] ?? '')->toContain('grid-template-columns: minmax(0, 1fr);')
        ->toContain('min-width: 0;');
});

test('the cron setup code block scrolls inside its own card', function (): void {
    $css = file_get_contents(resource_path('css/admin/components/_controls.css'));

    preg_match('/^\.ag-code \{(?<body>[^}]*)\}/m', (string) $css, $match);

    expect($match['body'] ?? '')->toContain('overflow-x: auto;')
        ->and(file_get_contents(resource_path('views/livewire/admin/system/cron-statistics.blade.php')))
        ->toContain('<pre class="ag-code" tabindex="0">');
});
