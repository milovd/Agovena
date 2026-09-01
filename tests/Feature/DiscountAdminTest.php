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
