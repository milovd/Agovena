<?php

declare(strict_types=1);

use App\Agovena\Physical\Enums\ReturnRequestStatus;
use App\Agovena\Physical\Http\Livewire\Admin\ReturnShow;
use App\Agovena\Physical\Http\Livewire\Admin\ReturnsIndex;
use App\Agovena\Physical\Models\ReturnRequest;
use App\Agovena\Physical\ReturnRequestService;
use App\Models\Order;
use App\Models\OrderItem;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function appearanceReturn(ReturnRequestStatus $status = ReturnRequestStatus::Requested): ReturnRequest
{
    $order = Order::factory()->create(['number' => 'AGO-RETURNS-001']);
    $item = OrderItem::factory()->create(['order_id' => $order->id, 'label' => 'Concert Demo', 'quantity' => 2]);
    $request = ReturnRequest::query()->create([
        'order_id' => $order->id,
        'customer_email' => 'returns@example.com',
        'status' => $status,
        'reason' => 'Wrong size',
        'requested_at' => now(),
    ]);
    $request->items()->create(['order_item_id' => $item->id, 'quantity' => 2, 'restocked_quantity' => 0]);

    return $request;
}

test('populated returns list retains status filtering and exposes every row value and its detail link', function (): void {
    $staff = $this->createStaff([], ['returns.view']);
    $request = appearanceReturn();

    $page = Livewire::actingAs($staff)->test(ReturnsIndex::class)
        ->assertSee('wire:model.live="status"', false)
        ->assertSee('AGO-RETURNS-001')
        ->assertSee('returns@example.com')
        ->assertSee(route('admin.shipping.returns.show', $request), false);
    $html = $page->html();
    expect($html)->toContain('c-returns__table')
        ->toContain('data-label="'.__('shipping::returns.customer').'"')
        ->toContain('data-label="'.__('shipping::returns.status').'"')
        ->toContain('data-label="'.__('shipping::returns.requested_at').'"')
        ->toContain('ag-badge')
        ->toContain('c-returns__identity');

    $page->set('status', 'approved')->assertDontSee('AGO-RETURNS-001');
    $page->set('status', 'requested')->assertSee('AGO-RETURNS-001');
});

test('return detail keeps order context, item quantities and requested state management controls', function (): void {
    $staff = $this->createStaff();
    $request = appearanceReturn();

    $page = Livewire::actingAs($staff)->test(ReturnShow::class, ['returnRequest' => $request])
        ->assertSee('AGO-RETURNS-001')
        ->assertSee('returns@example.com')
        ->assertSee('Wrong size')
        ->assertSee('Concert Demo')
        ->assertSee(route('admin.orders.show', $request->order), false)
        ->assertSee('wire:click="approve"', false)
        ->assertSee('wire:submit="reject"', false)
        ->assertSee('wire:model="reject_reason"', false)
        ->assertSee('wire:model="staff_notes"', false)
        ->assertSee('wire:submit="saveNotes"', false)
        ->assertDontSee('wire:click="markReceived"', false);
    $html = $page->html();
    expect($html)->toContain('c-returns__summary')
        ->toContain('c-returns__items-table')
        ->toContain('data-label="'.__('shipping::returns.requested_quantity').'"')
        ->toContain('data-label="'.__('shipping::returns.restocked_quantity').'"')
        ->not->toContain('wire:model="restock_quantities.'.$request->items->firstOrFail()->id.'"');

    $page->call('approve')->assertSee('wire:click="markReceived"', false)->assertDontSee('wire:submit="reject"', false);
    $page->call('markReceived')
        ->assertSee('wire:click="complete"', false)
        ->assertSee('wire:click="restock"', false)
        ->assertSee('wire:model="restock_quantities.'.$request->items->firstOrFail()->id.'"', false);
});

test('view-only staff retain both pages and values without management controls', function (): void {
    $staff = $this->createStaff([], ['returns.view']);
    $request = appearanceReturn();
    app(ReturnRequestService::class)->markReceived(app(ReturnRequestService::class)->approve($request));

    Livewire::actingAs($staff)->test(ReturnsIndex::class)
        ->assertSee('AGO-RETURNS-001')
        ->assertSee(route('admin.shipping.returns.show', $request), false);
    Livewire::actingAs($staff)->test(ReturnShow::class, ['returnRequest' => $request->fresh()])
        ->assertSee('Concert Demo')
        ->assertSee('Wrong size')
        ->assertSee(__('shipping::returns.statuses.received'))
        ->assertDontSee('wire:click="complete"', false)
        ->assertDontSee('wire:click="restock"', false)
        ->assertDontSee('wire:model="restock_quantities.', false)
        ->assertDontSee('wire:submit="saveNotes"', false);
});

test('empty returns list keeps its filter and real empty guidance', function (): void {
    $staff = $this->createStaff([], ['returns.view']);

    Livewire::actingAs($staff)->test(ReturnsIndex::class)
        ->assertSee('wire:model.live="status"', false)
        ->assertSee(__('shipping::returns.empty'))
        ->assertDontSee('c-returns__table', false);
});
