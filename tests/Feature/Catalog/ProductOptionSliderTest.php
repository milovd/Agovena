<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Options\ProductOptionPricer;
use App\Agovena\Catalog\Options\ProductOptionValidator;
use App\Agovena\Checkout\PlaceOrder;
use App\Enums\ProductOptionType;
use App\Livewire\Admin\Products\OptionsEditor;
use App\Livewire\Storefront\ProductConfigure;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionChoice;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function makeSliderProduct(bool $required = true): Product
{
    $product = Product::factory()->active()->create([
        'name' => 'Slider VPS',
        'slug' => 'slider-vps',
        'price_amount' => 1000,
        'currency' => 'EUR',
    ]);

    $option = ProductOption::query()->create([
        'product_id' => $product->id,
        'key' => 'ram',
        'label' => 'Memory',
        'type' => ProductOptionType::Slider,
        'is_required' => $required,
        'is_active' => true,
        'sort' => 1,
        'price_adjustment_amount' => 0,
        'constraints' => [],
    ]);

    foreach ([['2', '2 GB', 0], ['4', '4 GB', 300], ['8', '8 GB', 700], ['16', '16 GB', 1500]] as $index => [$value, $label, $price]) {
        ProductOptionChoice::query()->create([
            'product_option_id' => $option->id,
            'value' => $value,
            'label' => $label,
            'price_adjustment_amount' => $price,
            'sort' => $index,
            'is_active' => $value !== '16',
        ]);
    }

    return $product->fresh(['purchaseOptions.choices']);
}

test('slider is a choice based option type', function (): void {
    expect(ProductOptionType::Slider->value)->toBe('slider')
        ->and(ProductOptionType::Slider->hasChoices())->toBeTrue()
        ->and(ProductOptionType::Slider->isBoolean())->toBeFalse()
        ->and(ProductOptionType::values())->toContain('slider');
});

test('slider validation accepts an active stop and rejects unknown or inactive stops', function (): void {
    $product = makeSliderProduct();
    $validator = app(ProductOptionValidator::class);

    expect($validator->validate($product, ['ram' => '4']))->toBe(['ram' => '4']);

    expect(fn () => $validator->validate($product, ['ram' => '32']))->toThrow(ValidationException::class);
    expect(fn () => $validator->validate($product, ['ram' => '16']))->toThrow(ValidationException::class);
    expect(fn () => $validator->validate($product, []))->toThrow(ValidationException::class);
});

test('slider pricing applies the chosen stop adjustment and snapshots its label', function (): void {
    $product = makeSliderProduct();
    $pricer = app(ProductOptionPricer::class);

    expect($pricer->unitPrice($product, ['ram' => '2'], 'EUR')->amount)->toBe(1000)
        ->and($pricer->unitPrice($product, ['ram' => '8'], 'EUR')->amount)->toBe(1700);

    $snapshot = $pricer->snapshot($product, ['ram' => '8']);
    expect($snapshot)->toHaveCount(1)
        ->and($snapshot[0]['type'])->toBe('slider')
        ->and($snapshot[0]['value'])->toBe('8')
        ->and($snapshot[0]['display'])->toBe('8 GB')
        ->and($snapshot[0]['price_adjustment_amount'])->toBe(700);

    $cart = app(CartService::class);
    $cart->add($product->id, 1, ['ram' => '4']);
    expect($cart->pricedLines()[0]->unitPrice->amount)->toBe(1300);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => 'Slider Buyer',
        'customer_email' => 'slider@example.test',
    ]);
    $item = $order->items->first();
    expect($item->unit_amount)->toBe(1300)
        ->and($item->options_snapshot[0]['display'])->toBe('4 GB');
});

test('staff can create a slider option whose choices are the ordered stops', function (): void {
    $staff = $this->createStaff();
    $product = Product::factory()->create();

    Livewire::actingAs($staff)
        ->test(OptionsEditor::class, ['productId' => $product->id])
        ->call('create')
        ->assertSee(__('admin.product_options.types.slider'))
        ->set('key', 'ram')
        ->set('label', 'Memory')
        ->set('type', ProductOptionType::Slider->value)
        ->assertSee(__('admin.product_options.slider_help'))
        ->assertSeeHtml('id="opt-choices"')
        ->set('choicesText', "2:2 GB\n4:4 GB=300\n8:8 GB=700")
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee(__('admin.product_options.types.slider'));

    $option = ProductOption::query()->where('product_id', $product->id)->where('key', 'ram')->firstOrFail();

    expect($option->type)->toBe(ProductOptionType::Slider)
        ->and($option->price_adjustment_amount)->toBe(0)
        ->and($option->choices()->orderBy('sort')->pluck('value')->all())->toBe(['2', '4', '8'])
        ->and($option->choices()->orderBy('sort')->pluck('price_adjustment_amount')->all())->toBe([0, 300, 700]);

    Livewire::actingAs($staff)
        ->test(OptionsEditor::class, ['productId' => $product->id])
        ->call('create')
        ->set('key', 'disk')
        ->set('label', 'Disk')
        ->set('type', ProductOptionType::Slider->value)
        ->set('choicesText', '')
        ->call('save')
        ->assertHasErrors(['choicesText']);
});

test('storefront renders the slider as an accessible range with stop labels', function (): void {
    $product = makeSliderProduct();

    $component = Livewire::test(ProductConfigure::class, ['slug' => $product->slug])
        ->assertSet('optionSelections.ram', '2')
        ->assertSeeHtml('type="range"')
        ->assertSeeHtml('min="0"')
        ->assertSeeHtml('max="2"')
        ->assertSeeHtml('step="1"')
        ->assertSeeHtml('value="0"')
        ->assertSeeHtml('aria-valuetext="2 GB"')
        ->assertSeeHtml('x-data="storefrontOptionSlider"')
        ->assertSeeHtml('for="option-slider-'.$product->purchaseOptions->first()->id.'"')
        ->assertSee('2 GB')
        ->assertSee('4 GB')
        ->assertSee('8 GB')
        ->assertDontSee('16 GB')
        ->assertSee('€10.00');

    $component->set('optionSelections.ram', '8')
        ->assertSeeHtml('value="2"')
        ->assertSeeHtml('data-index="2"')
        ->assertSeeHtml('aria-valuetext="8 GB"')
        ->assertSeeHtml('--fill: 100%')
        ->assertSee('€17.00')
        ->call('continueConfiguration')
        ->assertRedirect(route('storefront.cart'));

    $line = app(CartService::class)->lines()[0];
    expect($line->selections)->toBe(['ram' => '8']);
});
