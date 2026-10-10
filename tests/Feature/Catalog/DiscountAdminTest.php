<?php

declare(strict_types=1);

use App\Livewire\Admin\Discounts\Index;
use App\Models\DiscountCode;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('discount code list uses icon actions', function (): void {
    $discount = DiscountCode::query()->create([
        'code' => 'SAVE10',
        'type' => 'percent',
        'value' => 10,
        'currency' => null,
        'min_subtotal_amount' => 0,
        'is_active' => true,
    ]);

    $html = Livewire::actingAs($this->createStaff())
        ->test(Index::class)
        ->assertSee('class="ag-table__actions"', false)
        ->assertSee('class="ag-row-actions"', false)
        ->assertSee(__('admin.discounts.actions.edit_aria', ['code' => $discount->code]), false)
        ->assertSee(__('admin.discounts.actions.delete_aria', ['code' => $discount->code]), false)
        ->html();

    expect($html)
        ->toContain('class="ag-icon-btn ag-icon-btn--danger"')
        ->and($html)->toContain('wire:click="edit('.$discount->id.')"')
        ->and($html)->toContain('wire:click="delete('.$discount->id.')"')
        ->and($html)->not->toContain('wire:click="edit('.$discount->id.')">Edit</button>')
        ->and($html)->not->toContain('wire:click="delete('.$discount->id.')">Delete</button>');
});

test('discount codes preserve the full responsive row and all inline fields for authorized staff', function (): void {
    $discount = DiscountCode::query()->create([
        'code' => 'WELCOME15', 'type' => 'percent', 'value' => 15,
        'currency' => null, 'min_subtotal_amount' => 0, 'is_active' => false,
    ]);

    Livewire::actingAs($this->createStaff())->test(Index::class)
        ->assertSee('class="admin-page ag-list-page admin-page--discounts"', false)
        ->assertSee('role="region" aria-label="'.__('admin.discounts.title').'" tabindex="0"', false)
        ->assertSee('WELCOME15')
        ->assertSee('15%')
        ->assertSee('class="ag-badge ag-badge--muted"', false)
        ->assertSee('wire:confirm="'.__('admin.discounts.delete_confirm').'"', false)
        ->call('edit', $discount->id)
        ->assertSee('class="ag-section ag-form ag-form--constrained ag-catalog-form"', false)
        ->assertSee('wire:model="code"', false)
        ->assertSee('wire:model.live="type"', false)
        ->assertSee('wire:model.number="value"', false)
        ->assertSee('wire:model.number="min_subtotal_amount"', false)
        ->assertSee('wire:model="starts_at"', false)
        ->assertSee('wire:model="ends_at"', false)
        ->assertSee('wire:model.number="max_uses"', false)
        ->assertSee('wire:model.number="max_uses_per_customer"', false)
        ->assertSee('wire:model="is_active"', false)
        ->set('type', 'fixed')
        ->assertSee('wire:model="currency"', false)
        ->call('cancel')
        ->assertDontSee('discount-code', false);
});

test('discount code validation remains visible without removing the inline form', function (): void {
    Livewire::actingAs($this->createStaff())->test(Index::class)
        ->call('create')
        ->set('value', 101)
        ->call('save')
        ->assertHasErrors(['value' => 'max'])
        ->assertSee('discount-value', false)
        ->assertSee('discount-starts', false)
        ->assertSee('discount-active', false);
});

test('view-only staff cannot see discount management controls', function (): void {
    $discount = DiscountCode::query()->create([
        'code' => 'VIEWONLY', 'type' => 'percent', 'value' => 10,
        'currency' => null, 'min_subtotal_amount' => 0, 'is_active' => true,
    ]);

    Livewire::actingAs($this->createStaff([], ['discounts.view']))->test(Index::class)
        ->assertSee('VIEWONLY')
        ->assertDontSee('wire:click="create"', false)
        ->assertDontSee('wire:click="edit('.$discount->id.')"', false)
        ->assertDontSee('wire:click="delete('.$discount->id.')"', false);
});
