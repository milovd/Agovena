<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Agovena\Catalog\Capabilities\ProductCapabilityDefinition;
use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Catalog\Options\ConfigurableProductOptionContext;
use App\Agovena\Catalog\Options\ConfigurableProductOptionDefinition;
use App\Agovena\Catalog\Options\ProductOptionPricer;
use App\Agovena\Catalog\Options\ProductOptionValidator;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Provisioning\Contracts\ProvidesConfigurableProductOptions;
use App\Agovena\Provisioning\Contracts\Provisioner;
use App\Agovena\Provisioning\ProvisionerRegistry;
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

test('an optional slider starts without a paid stop and can return to no selection', function (): void {
    $product = makeSliderProduct(required: false);
    $product->purchaseOptions->first()->choices()->where('value', '2')->update(['price_adjustment_amount' => 300]);

    $component = Livewire::test(ProductConfigure::class, ['slug' => $product->slug])
        ->assertSet('optionSelections.ram', '')
        ->assertSeeHtml('aria-valuetext="'.e(__('common.none')).'"')
        ->assertSee('€10.00');

    $component->set('optionSelections.ram', '8')
        ->assertSee('€17.00')
        ->set('optionSelections.ram', '')
        ->assertSee('€10.00')
        ->call('continueConfiguration')
        ->assertHasNoErrors()
        ->assertRedirect(route('storefront.cart'));

    expect(app(CartService::class)->lines()[0]->selections)->toBe([]);
});

function addDependentSliderChoices(Product $product): void
{
    $region = $product->purchaseOptions()->create([
        'key' => 'region', 'label' => 'Region', 'type' => ProductOptionType::Select,
        'is_required' => true, 'is_active' => true, 'sort' => 0,
    ]);
    foreach (['eu', 'us', 'offline'] as $value) {
        $region->choices()->create(['value' => $value, 'label' => strtoupper($value), 'is_active' => true, 'sort' => 0]);
    }
    $product->capabilities()->create(['capability' => 'provisionable', 'config' => ['provider_key' => 'slider-test']]);
    app(ProductCapabilityRegistry::class)->register(new ProductCapabilityDefinition('provisionable', 'Provisionable'));
    $provider = Mockery::mock(
        Provisioner::class,
        ProvidesConfigurableProductOptions::class,
    );
    $provider->shouldReceive('id')->andReturn('slider-test');
    $provider->shouldReceive('configurableProductOptions')->andReturn([
        new ConfigurableProductOptionDefinition('ram', 'Memory', ProductOptionType::Slider, dynamicChoices: true),
    ]);
    $provider->shouldReceive('configurableProductOptionChoices')->andReturnUsing(
        static function (ConfigurableProductOptionDefinition $definition, ConfigurableProductOptionContext $context): array {
            return match ($context->selections['region'] ?? '') {
                'us' => [['value' => '8', 'label' => '8 GB', 'price_adjustment_amount' => 700]],
                'offline' => [],
                default => [
                    ['value' => '2', 'label' => '2 GB', 'price_adjustment_amount' => 0],
                    ['value' => '4', 'label' => '4 GB', 'price_adjustment_amount' => 300],
                ],
            };
        },
    );
    $provider->shouldReceive('validateConfigurableProductOptionValue')->andReturnNull();
    $provider->shouldReceive('configurableProductOptionHint')->andReturnNull();
    app(ProvisionerRegistry::class)->register($provider);
}

test('a required dynamic slider adopts a valid stop when a parent choice changes', function (): void {
    $product = makeSliderProduct();
    addDependentSliderChoices($product);

    $component = Livewire::test(ProductConfigure::class, ['slug' => $product->slug])
        ->assertSet('optionSelections.ram', '2')
        ->set('optionSelections.region', 'us')
        ->assertSet('optionSelections.ram', '8')
        ->assertSeeHtml('aria-valuetext="8 GB"')
        ->assertSeeHtml('max="0"')
        ->assertSee('€17.00')
        ->call('continueConfiguration')
        ->assertHasNoErrors()
        ->assertRedirect(route('storefront.cart'));

    expect(app(CartService::class)->lines()[0]->selections)->toBe(['ram' => '8', 'region' => 'us']);
});

test('a dynamic slider preserves valid values and recovers when choices become available again', function (): void {
    $product = makeSliderProduct();
    addDependentSliderChoices($product);

    Livewire::test(ProductConfigure::class, ['slug' => $product->slug])
        ->set('optionSelections.ram', '4')
        ->set('optionSelections.region', 'eu')
        ->assertSet('optionSelections.ram', '4')
        ->set('optionSelections.region', 'offline')
        ->assertSet('optionSelections.ram', '')
        ->call('continueConfiguration')
        ->assertHasErrors(['optionSelections.ram'])
        ->set('optionSelections.region', 'us')
        ->assertSet('optionSelections.ram', '8')
        ->assertHasNoErrors(['optionSelections.ram'])
        ->call('continueConfiguration')
        ->assertHasNoErrors();
});

test('an optional dynamic slider clears an unavailable paid stop without choosing a replacement', function (): void {
    $product = makeSliderProduct(required: false);
    addDependentSliderChoices($product);

    Livewire::test(ProductConfigure::class, ['slug' => $product->slug])
        ->assertSet('optionSelections.ram', '')
        ->set('optionSelections.ram', '4')
        ->set('optionSelections.region', 'us')
        ->assertSet('optionSelections.ram', '')
        ->assertSeeHtml('aria-valuetext="'.e(__('common.none')).'"')
        ->assertSee('€10.00')
        ->call('continueConfiguration')
        ->assertHasNoErrors();

    expect(app(CartService::class)->lines()[0]->selections)->toBe(['region' => 'us']);
});

test('a forged slider value is still rejected instead of silently normalized', function (): void {
    $product = makeSliderProduct();
    addDependentSliderChoices($product);

    Livewire::test(ProductConfigure::class, ['slug' => $product->slug])
        ->set('optionSelections.region', 'us')
        ->assertSet('optionSelections.ram', '8')
        ->set('optionSelections.ram', '2')
        ->assertSet('optionSelections.ram', '2')
        ->call('continueConfiguration')
        ->assertHasErrors(['optionSelections.ram']);
});
