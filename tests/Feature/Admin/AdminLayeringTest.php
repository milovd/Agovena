<?php

declare(strict_types=1);

/*
 * The mobile navigation drawer covers the page. Floating page controls (bulk actions,
 * comboboxes, menus) must stay underneath it, while modals still open above everything.
 */

function adminZIndex(string $file, string $selector, bool $mobile = false): int
{
    $css = (string) file_get_contents(resource_path('css/admin/components/'.$file));
    if ($mobile) {
        $css = substr($css, (int) strpos($css, '@media (max-width'));
    }

    preg_match('/'.preg_quote($selector, '/').'\s*\{[^}]*?z-index:\s*(\d+)/', $css, $match);

    return (int) ($match[1] ?? -1);
}

test('the mobile navigation drawer stays above floating page controls and below modals', function (): void {
    $drawer = adminZIndex('_admin-shell.css', "\n  .admin-sidebar", mobile: true);
    $backdrop = adminZIndex('_admin-shell.css', '.admin-shell__backdrop', mobile: true);
    $pageControls = [
        adminZIndex('_table.css', '.ag-bulk-selection__floating-actions'),
        adminZIndex('_combobox.css', '.ag-combobox__empty'),
        adminZIndex('_dropdown.css', '.ag-dropdown__menu'),
        adminZIndex('_table.css', '.ag-menu__panel'),
    ];
    $modal = adminZIndex('_modal.css', '.ag-modal');

    expect(min([$drawer, $backdrop, ...$pageControls, $modal]))->toBeGreaterThan(0)
        ->and($backdrop)->toBeGreaterThan(max($pageControls))
        ->and($drawer)->toBeGreaterThan($backdrop)
        ->and($modal)->toBeGreaterThan($drawer);
});
