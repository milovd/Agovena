<?php

declare(strict_types=1);

use App\Livewire\Admin\Invoices\Index as InvoicesIndex;
use App\Models\Invoice;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('shows shared success and error messages only once without hiding invoice actions', function (): void {
    $staff = $this->createStaff([], ['invoices.view']);
    $invoice = Invoice::query()->create([
        'number' => 'INV-VERIFY-01', 'status' => 'issued', 'customer_name' => 'Review Customer',
        'customer_email' => 'review@example.test', 'issued_at' => now()->toDateString(), 'subtotal_amount' => 1000, 'tax_amount' => 0,
        'total_amount' => 1000, 'currency' => 'EUR',
    ]);
    $this->actingAs($staff);
    session()->flash('status', 'Saved once');
    $response = $this->get(route('admin.invoices.index'));
    $response->assertOk()->assertSee(route('admin.invoices.show', $invoice), false)
        ->assertSee(route('admin.invoices.pdf', $invoice), false)
        ->assertDontSee(route('admin.invoices.edit', $invoice), false);
    expect(substr_count($response->getContent(), 'Saved once'))->toBe(1);
});

it('distinguishes filtered empty results and shows a loading state for existing invoice filters', function (): void {
    $staff = $this->createStaff([], ['invoices.view']);
    Livewire::actingAs($staff)->test(InvoicesIndex::class)
        ->assertSee(__('admin.invoices.empty.title'))
        ->assertSee('wire:target="search,status,gotoPage,previousPage,nextPage"', false)
        ->set('search', 'does-not-exist')
        ->assertSee(__('admin.invoices.empty.filtered_title'));
});
