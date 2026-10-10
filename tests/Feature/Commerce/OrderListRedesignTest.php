<?php

declare(strict_types=1);

use App\Livewire\Admin\Orders\Index as OrdersIndex;
use App\Models\Order;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('links an order number to its existing detail page and renders shared notices only once', function (): void {
    $staff = $this->createStaff([], ['orders.view']);
    $order = Order::factory()->create();
    $this->actingAs($staff);
    session()->flash('status', 'Saved once');
    $response = $this->get(route('admin.orders.index'));
    $response->assertOk()->assertSee('<a class="ag-table__name" href="'.route('admin.orders.show', $order).'">'.$order->number.'</a>', false);
    expect(substr_count($response->getContent(), 'Saved once'))->toBe(1);
});

it('uses the shared list structure while keeping filters, actions and mobile order context', function (): void {
    $staff = $this->createStaff();
    $order = Order::factory()->create(['number' => 'AG-TEST-ORDER-84']);

    Livewire::actingAs($staff)->test(OrdersIndex::class)
        ->assertSee('class="admin-page ag-list-page admin-page--orders"', false)
        ->assertSee('role="region" aria-label="'.__('admin.orders.title').'" tabindex="0"', false)
        ->assertSee('ag-table__mobile-meta', false)
        ->assertSee('wire:model.live="status"', false)
        ->assertSee('wire:model.live="paymentStatus"', false)
        ->assertSee('aria-haspopup="menu"', false)
        ->assertSee(route('admin.orders.edit', $order), false)
        ->assertSee('wire:click="confirmDelete('.$order->id.')"', false);
});

it('has loading and combined filters without losing the order detail or protected row actions', function (): void {
    $staff = $this->createStaff([], ['orders.view']);
    $order = Order::factory()->create(['number' => 'AG-TEST-ORDER-83']);
    Livewire::actingAs($staff)->test(OrdersIndex::class)
        ->assertSee('wire:target="search,status,paymentStatus,gotoPage,previousPage,nextPage"', false)
        ->assertDontSee(route('admin.orders.edit', $order), false)
        ->assertDontSee('wire:click="confirmDelete('.$order->id.')"', false)
        ->set('search', 'AG-TEST-ORDER-83')
        ->assertSee('AG-TEST-ORDER-83')
        ->set('search', 'no-result-anywhere')
        ->assertSee(__('admin.orders.empty.filtered_title'));
});
