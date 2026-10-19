<?php

declare(strict_types=1);

use App\Enums\ProductOptionType;
use App\Livewire\Admin\Discounts\Index as DiscountsIndex;
use App\Livewire\Admin\PlanChanges\Index as PlanChangesIndex;
use App\Livewire\Admin\Products\OptionsEditor;
use App\Models\Product;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

/**
 * Every validated control must render its own message inside its field, otherwise a
 * rejected save looks like a silent no-op.
 *
 * @param  list<string>  $controlIds
 */
function expectFieldErrorsBelow(string $html, array $controlIds): void
{
    foreach ($controlIds as $id) {
        expect($html)->toMatch('/id="'.preg_quote($id, '/').'"(?:(?!<\/div>).)*class="ag-field__error" role="alert"/s');
    }
}

test('discount limits and minimum subtotal show their validation errors', function (): void {
    $page = Livewire::actingAs($this->createStaff())
        ->test(DiscountsIndex::class)
        ->call('create')
        ->set('code', 'SPRING')
        ->set('value', 10)
        ->set('min_subtotal_amount', -1)
        ->set('max_uses', 0)
        ->set('max_uses_per_customer', 0)
        ->set('type', 'bogus')
        ->call('save')
        ->assertHasErrors(['min_subtotal_amount', 'max_uses', 'max_uses_per_customer', 'type']);

    expectFieldErrorsBelow($page->html(), ['discount-type', 'discount-minimum', 'discount-max', 'discount-customer-max']);
});

test('product option price and length limits show their validation errors', function (): void {
    $product = Product::factory()->create();

    $text = Livewire::actingAs($this->createStaff())
        ->test(OptionsEditor::class, ['productId' => $product->id])
        ->call('create')
        ->set('key', 'engraving')
        ->set('label', 'Engraving')
        ->set('type', ProductOptionType::Text->value)
        ->set('price_adjustment_amount', -5)
        ->set('max_length', 6000)
        ->call('save')
        ->assertHasErrors(['price_adjustment_amount', 'max_length']);

    expectFieldErrorsBelow($text->html(), ['opt-price', 'opt-max-length']);
});

test('plan change type and timing show their validation errors', function (): void {
    $page = Livewire::actingAs($this->createStaff([], ['plan-changes.view', 'plan-changes.manage']))
        ->test(PlanChangesIndex::class)
        ->set('change_type', 'bogus')
        ->set('timing', 'bogus')
        ->call('save')
        ->assertHasErrors(['change_type', 'timing']);

    expectFieldErrorsBelow($page->html(), ['plan-type', 'plan-timing']);
});
