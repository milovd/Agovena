<?php

declare(strict_types=1);

use App\Livewire\Admin\Dashboard;
use App\Models\Order;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

/**
 * Markup contract for the shadcn-style "Revenue & orders" dashboard chart:
 * interactive area/bar variant, HTML legend, skeletons and preserved controls.
 */
function dashboardChartXPath(string $html): DOMXPath
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8"?>'.$html);

    return new DOMXPath($document);
}

function dashboardChartConfig(DOMXPath $xpath): array
{
    $chart = $xpath->query('//*[@data-chart-config]')->item(0);

    return json_decode($chart->getAttribute('data-chart-config'), true, flags: JSON_THROW_ON_ERROR);
}

test('dashboard chart opts into the interactive variant with an HTML legend instead of a canvas legend', function () {
    Order::factory()->create(['created_at' => now()]);
    $component = Livewire::actingAs($this->createStaff())->test(Dashboard::class);

    foreach (['line', 'bar'] as $type) {
        $xpath = dashboardChartXPath($component->set('chartType', $type)->html());
        $config = dashboardChartConfig($xpath);

        expect($config['variant'])->toBe('interactive')
            ->and($config['showLegend'])->toBeFalse()
            ->and($config['type'])->toBe($type)
            ->and($config['dates'])->toHaveCount(count($config['labels']))
            ->and($config['dates'][0])->toMatch('/^\d{4}-\d{2}-\d{2}$/');

        $items = $xpath->query('//ul[contains(@class, "ag-chart-card__legend")]/li');
        expect($items)->toHaveCount(2)
            ->and(trim($items->item(0)->textContent))->toBe(__('admin.dashboard.charts.revenue'))
            ->and(trim($items->item(1)->textContent))->toBe(__('admin.dashboard.charts.orders'))
            ->and($xpath->query('.//span[contains(@class, "ag-chart-card__legend-dot--chart-1")][@aria-hidden="true"]', $items->item(0)))->toHaveCount(1)
            ->and($xpath->query('.//span[contains(@class, "ag-chart-card__legend-dot--chart-4")][@aria-hidden="true"]', $items->item(1)))->toHaveCount(1);
    }
});

test('dashboard chart renders a boot skeleton beside the canvas and a delayed request skeleton for range changes', function () {
    Order::factory()->create(['created_at' => now()]);
    $html = Livewire::actingAs($this->createStaff())->test(Dashboard::class)->html();
    $xpath = dashboardChartXPath($html);

    $plot = $xpath->query('//div[contains(@class, "ag-chart-card__plot")]')->item(0);
    expect($plot)->not->toBeNull()
        ->and($plot->hasAttribute('wire:ignore'))->toBeTrue()
        ->and($xpath->query('./canvas[@x-ref="canvas"]', $plot))->toHaveCount(1)
        ->and($xpath->query('./div[contains(@class, "ag-skeleton")][contains(@class, "ag-chart-card__skeleton--boot")][@aria-hidden="true"]', $plot))->toHaveCount(1);

    $loading = $xpath->query('//div[contains(@class, "ag-chart-card__skeleton--loading")]')->item(0);
    expect($loading)->not->toBeNull()
        ->and($loading->getAttribute('class'))->toContain('ag-skeleton')
        ->and($loading->hasAttribute('wire:loading.delay'))->toBeTrue()
        ->and($loading->getAttribute('wire:target'))->toBe('setChartRange')
        ->and(trim($loading->textContent))->toBe(__('common.loading'));

    expect($html)->not->toMatch('/<[^>]+\sstyle="/');
});

test('dashboard chart keeps every control, wire action and aria attribute of the card header', function () {
    $html = Livewire::actingAs($this->createStaff())->test(Dashboard::class)->html();
    $xpath = dashboardChartXPath($html);

    $group = $xpath->query('//div[@role="group"][@aria-labelledby="dashboard-chart-range-label"]')->item(0);
    expect($group)->not->toBeNull();

    $buttons = $xpath->query('./button', $group);
    expect($buttons)->toHaveCount(4);
    foreach (['7', '14', 'month', '90'] as $index => $range) {
        $button = $buttons->item($index);
        expect($button->getAttribute('wire:click'))->toBe("setChartRange('{$range}')")
            ->and($button->getAttribute('wire:target'))->toBe('setChartRange')
            ->and($button->hasAttribute('wire:loading.attr'))->toBeTrue()
            ->and($button->getAttribute('aria-pressed'))->toBe($range === '14' ? 'true' : 'false');
    }

    $select = $xpath->query('//select[@id="dashboard-chart-type"]')->item(0);
    expect($select->getAttribute('wire:model.live'))->toBe('chartType')
        ->and($xpath->query('//label[@for="dashboard-chart-type"]'))->toHaveCount(1)
        ->and($xpath->query('./option', $select))->toHaveCount(2)
        ->and($xpath->query('//h2[@id="dashboard-chart-title"]'))->toHaveCount(1)
        ->and($xpath->query('//section[@aria-labelledby="dashboard-chart-title"][@x-data="agChart"]'))->toHaveCount(1);
});

test('dashboard chart keeps the empty state and renders no skeleton or legend without data', function () {
    $html = Livewire::actingAs($this->createStaff())->test(Dashboard::class)->html();

    expect($html)->toContain(e(__('admin.dashboard.charts.empty_title')))
        ->and($html)->not->toContain('ag-skeleton')
        ->and($html)->not->toContain('ag-chart-card__legend');
});

test('chart styles define the skeleton, legend dots and shadcn tooltip with tokens only', function () {
    $skeleton = file_get_contents(resource_path('css/admin/components/_skeleton.css'));
    $patterns = file_get_contents(resource_path('css/admin/components/_patterns.css'));

    expect($skeleton)->toMatch('/\.ag-skeleton\s*\{[^}]*background:\s*var\(--ag-color-surface-muted\)/s')
        ->and($skeleton)->toMatch('/\.ag-skeleton\s*\{[^}]*border-radius:\s*var\(--ag-radius-md\)/s')
        ->and($skeleton)->toMatch('/\.ag-skeleton\s*\{[^}]*animation:[^;]*300ms/s')
        ->and($skeleton)->toContain('prefers-reduced-motion: reduce')
        ->and($patterns)->toMatch('/\.ag-chart-card__plot\[data-chart-state="ready"\] > \.ag-chart-card__skeleton--boot\s*\{\s*display:\s*none/s')
        ->and($patterns)->toMatch('/\.ag-chart-card__legend-dot--chart-4\s*\{\s*background:\s*var\(--ag-color-chart-4\)/')
        ->and($patterns)->toMatch('/\.ag-chart-card__tooltip\s*\{[^}]*border:\s*1px solid var\(--ag-color-border\)[^}]*border-radius:\s*var\(--ag-radius-md\)[^}]*background:\s*var\(--ag-color-surface\)/s')
        ->and($patterns)->toMatch('/\.ag-chart-card__tooltip-value\s*\{[^}]*font-variant-numeric:\s*tabular-nums[^}]*text-align:\s*right/s');
});

test('chart script draws a shadcn area and bar look and follows the theme attribute', function () {
    $script = file_get_contents(resource_path('js/admin/chart.js'));

    expect($script)->toContain("cubicInterpolationMode: 'monotone'")
        ->and($script)->toContain('createLinearGradient(0, area.top, 0, area.bottom)')
        ->and($script)->toContain("attributeFilter: ['data-theme']")
        ->and($script)->toContain('themeObserver?.disconnect()')
        ->and($script)->toContain('prefers-reduced-motion: reduce')
        ->and($script)->toContain("borderSkipped: 'start'")
        ->and($script)->not->toContain('innerHTML')
        ->and($script)->not->toContain("\u{2014}");
});
