<?php

declare(strict_types=1);

/** Concatenated Admin CSS (Core bundle plus Theme admin partials). */
function shadcnAdminCss(): string
{
    $core = '';
    foreach (glob(resource_path('css/admin/{settings,generic,elements,components,screens,utilities}/*.css'), GLOB_BRACE) as $file) {
        $core .= file_get_contents($file)."\n";
    }
    foreach (glob(base_path('themes/default/resources/css/admin/*.css')) as $file) {
        $core .= file_get_contents($file)."\n";
    }

    return (string) preg_replace('/,\s+/', ', ', $core);
}

/** Body of the first rule whose selector list is exactly $selector. */
function shadcnRule(string $css, string $selector): string
{
    preg_match('/(?:^|\n)'.preg_quote($selector, '/').' \{(?<body>[^}]*)\}/', $css, $match);

    return $match['body'] ?? '';
}

test('tabs use the shadcn track with a raised active trigger, on the page and inside cards', function (): void {
    $css = shadcnAdminCss();
    $track = shadcnRule($css, '.ag-tabs, .ag-product-tabs');
    $active = shadcnRule($css, '.ag-tabs__tab:is(.ag-tabs__tab--active, [aria-selected="true"], [aria-pressed="true"]), .ag-product-tabs__tab:is(.is-active, [aria-selected="true"])');

    expect($track)->toContain('padding: 3px;')
        ->toContain('background: var(--ag-tabs-track);')
        ->toContain('width: fit-content;')
        ->and(shadcnRule($css, '.ag-product-tabs, .admin-page > .ag-tabs'))->toContain('--ag-tabs-track: var(--ag-color-tabs-track-page);')
        ->and($active)->toContain('background: var(--ag-color-tabs-active);')
        ->toContain('box-shadow: var(--ag-shadow-sm);')
        ->and($css)->not->toContain('.admin-page > .ag-tabs .ag-tabs__tab:hover');
});

test('the switch primitive is announced as a switch and uses the shadcn track and thumb sizes', function (): void {
    $html = (string) $this->blade('<x-ag.switch id="demo-switch" wire:model="enabled" label="Enabled" />');
    $css = shadcnAdminCss();

    expect($html)->toContain('role="switch"')
        ->toContain('id="demo-switch"')
        ->toContain('wire:model="enabled"')
        ->and(shadcnRule($css, '.ag-switch__track'))->toContain('width: 2rem;')->toContain('height: 1.15rem;')->toContain('background: var(--ag-color-input);')
        ->and(shadcnRule($css, '.ag-switch__thumb'))->toContain('width: 1rem;')
        ->and(shadcnRule($css, '.ag-switch__input:checked + .ag-switch__track .ag-switch__thumb'))->toContain('translateX(calc(100% - 2px))');
});

test('plain Admin checkboxes and radios share the checkbox primitive look without touching hidden primitive inputs', function (): void {
    $css = shadcnAdminCss();
    $plain = shadcnRule($css, '.admin-shell :is(input[type="checkbox"], input[type="radio"]):not([class*="__input"])');

    expect($plain)->toContain('appearance: none;')
        ->toContain('width: 1rem;')
        ->toContain('border: 1px solid var(--ag-color-input);')
        ->and(shadcnRule($css, '.ag-checkbox__box'))->toContain('border-radius: 4px;')
        ->and(shadcnRule($css, '.admin-shell input[type="radio"]:not([class*="__input"])'))->toContain('border-radius: 999px;');
});

test('form controls, tabs and badges read colors from tokens defined for light and dark', function (): void {
    $theme = (string) file_get_contents(base_path('themes/default/resources/css/admin/_tokens.css'));
    $core = (string) file_get_contents(resource_path('css/admin/settings/_tokens.css'));
    $split = (int) strpos($theme, '[data-theme="dark"]');

    foreach (['--ag-color-input', '--ag-color-control-bg', '--ag-color-primary-foreground', '--ag-color-switch-thumb', '--ag-color-switch-thumb-checked', '--ag-color-tabs-track', '--ag-color-tabs-track-page', '--ag-color-tabs-active', '--ag-shadow-xs'] as $token) {
        expect(substr($theme, 0, $split))->toContain($token.':')
            ->and(substr($theme, $split))->toContain($token.':')
            ->and($core)->toContain($token.':');
    }
});

test('badges and pagination follow the shadcn shapes', function (): void {
    $css = shadcnAdminCss();

    expect(shadcnRule($css, '.ag-badge'))->toContain('display: inline-flex;')->toContain('font-weight: 500;')->toContain('border-radius: 999px;')
        ->and(shadcnRule($css, '.ag-pagination__btn'))->toContain('background: transparent;')->toContain('border: 1px solid transparent;')
        ->and(shadcnRule($css, '.ag-pagination__btn--current'))->toContain('border-color: var(--ag-color-input);')->not->toContain('var(--ag-color-primary)');
});

test('list rows become skeletons only while a request runs and only after a short delay', function (): void {
    $css = shadcnAdminCss();
    $bar = shadcnRule($css, '.ag-table-wrap.is-loading tbody td::before, .ag-table-wrap.is-loading tbody td::after');

    expect($bar)->toContain('opacity: 0;')
        ->toContain('250ms forwards')
        ->and($css)->not->toMatch('/(?:^|\n)\.ag-table-wrap tbody td::(?:before|after) \{/')
        ->and(shadcnRule($css, '.ag-loading'))->toContain('clip-path: inset(50%);');
});

test('selected table rows stay highlighted like the shadcn data table', function (): void {
    expect(shadcnAdminCss())->toContain('.ag-table tbody tr:has(> td input[type="checkbox"]:checked)');
});
