<?php

declare(strict_types=1);

use App\Agovena\Orders\OrderListStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function orderDetailHtml(object $test, User $staff, string $number = 'AG-DETAIL-1'): array
{
    $order = Order::factory()->create([
        'number' => $number,
        'customer_name' => 'Ada Detail',
        'customer_email' => 'ada@example.test',
        'subtotal_amount' => 2000,
        'shipping_amount' => 500,
        'shipping_method_label' => 'Courier',
        'total_amount' => 2500,
        'currency' => 'EUR',
    ]);
    OrderItem::factory()->for($order)->create(['label' => 'Detail Product', 'quantity' => 2, 'unit_amount' => 1000, 'line_total_amount' => 2000, 'currency' => 'EUR']);
    Payment::factory()->for($order)->create(['amount' => 2500, 'currency' => 'EUR']);

    $html = $test->actingAs($staff)->get(route('admin.orders.show', $order))->assertOk()->getContent();

    return [$order->fresh(), $html];
}

it('shows the order number as heading with one combined status in the header', function (): void {
    [$order, $html] = orderDetailHtml($this, $this->createStaff());
    $status = OrderListStatus::for($order);

    preg_match('#<div class="admin-order__summary">(.*?)</div>#s', $html, $summary);

    expect($html)->toContain('<h1 class="admin-page__heading">AG-DETAIL-1</h1>')
        ->and(substr_count($summary[1] ?? '', 'class="ag-badge'))->toBe(1)
        ->and($summary[1])->toContain('ag-badge--'.$status->badgeVariant().'">'.__('admin.orders.list_status.'.$status->value).'<')
        ->toContain($order->created_at->toDayDateTimeString());
});

it('turns edit into an icon button in the header and hides it without update permission', function (): void {
    [$order, $html] = orderDetailHtml($this, $this->createStaff());

    expect($html)->toMatch('#<a class="ag-icon-btn ag-icon-btn--header" href="'.preg_quote(route('admin.orders.edit', $order), '#').'" title="'.preg_quote(__('admin.orders.actions.edit'), '#').'" aria-label="'.preg_quote(e(__('admin.orders.actions.edit_aria', ['number' => $order->number])), '#').'">#')
        ->not->toContain('<a class="ag-btn ag-btn--secondary" href="'.route('admin.orders.edit', $order).'"');

    [$viewOnlyOrder, $viewOnly] = orderDetailHtml($this, $this->createStaff([], ['orders.view']), 'AG-DETAIL-2');
    expect($viewOnly)->not->toContain(route('admin.orders.edit', $viewOnlyOrder));
});

it('summarises the order in brand metric cards', function (): void {
    [, $html] = orderDetailHtml($this, $this->createStaff());

    expect(substr_count($html, 'class="ag-metric"'))->toBe(3)
        ->and($html)->toContain('class="ag-metrics admin-order__metrics admin-order__metrics--main"')
        ->toContain('€25.00')
        ->toContain(trans_choice('admin.orders.show.lines', 1, ['count' => 1]))
        ->toContain('Ada Detail')
        ->toContain('href="mailto:ada@example.test"');
});

it('uses icon cards for every Core order section and keeps all totals', function (): void {
    [, $html] = orderDetailHtml($this, $this->createStaff());

    foreach (['order-items-heading', 'order-addresses-heading', 'payment-heading', 'documents-heading', 'shipping-fulfillment-heading', 'shipping-returns-heading'] as $heading) {
        expect($html)->toMatch('#<header class="ag-card__header">\s*<span [^>]*class="ag-icon-tile"[^>]*>.*?id="'.$heading.'"#s');
    }

    expect($html)->not->toContain('id="order-summary-heading"')
        ->not->toContain('id="customer-heading"')
        ->toContain('class="admin-order__totals"')
        ->toContain('€20.00')
        ->toContain('€5.00')
        ->toContain('Courier')
        ->toContain('Detail Product');
});

it('aligns the order metrics with the item and payment columns', function (): void {
    [, $html] = orderDetailHtml($this, $this->createStaff());
    $css = (string) file_get_contents(resource_path('css/admin/screens/_orders.css'));

    // Total and Items share the main column, Customer sits above Payment in the side column.
    $layout = strpos($html, '<div class="ag-order-layout">');
    $main = strpos($html, 'admin-order__metrics--main" role="list"');
    $side = strpos($html, 'admin-order__metrics--side" role="list"');
    $cards = strpos($html, '<div class="ag-order-layout__main">');

    expect($layout)->toBeInt()
        ->and($layout < $main && $main < $side && $side < $cards)->toBeTrue()
        ->and(substr_count($html, 'class="ag-metric"'))->toBe(3)
        ->and($css)->toContain('.admin-page--order .admin-order__metrics {')
        ->toContain('.admin-order__metrics--side');
});

it('keeps button text readable on solid primary and danger buttons in both themes', function (): void {
    $buttons = (string) file_get_contents(resource_path('css/admin/components/_button.css'));
    $themeTokens = (string) file_get_contents(base_path('themes/default/resources/css/admin/_tokens.css'));
    $coreTokens = (string) file_get_contents(resource_path('css/admin/settings/_tokens.css'));

    expect($buttons)->not->toContain('color: #fff')
        ->toContain('color: var(--ag-color-primary-foreground)')
        ->toContain('color: var(--ag-color-danger-foreground)')
        ->and($coreTokens)->toContain('--ag-color-danger-foreground: #ffffff')
        ->and($themeTokens)->toContain('--ag-color-danger-foreground: #ffffff')
        ->toContain('--ag-color-danger-foreground: #0b1220');
});

it('makes cancelling an unpaid order a secondary destructive action', function (): void {
    $staff = $this->createStaff();
    $order = Order::factory()->create(['number' => 'AG-DETAIL-3']);
    Payment::factory()->for($order)->create();

    $html = $this->actingAs($staff)->get(route('admin.orders.show', $order))->assertOk()->getContent();

    expect($html)->toContain('class="ag-btn ag-btn--danger-outline" wire:click="startCancelUnpaid"')
        ->toContain('class="ag-btn ag-btn--primary" wire:click="startRecordPayment"');
});
